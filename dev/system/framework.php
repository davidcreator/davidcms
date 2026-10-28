<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * framework.php — monta o Registry inteiro e executa o ciclo da requisição
 * (Contrato-Bootstrap §3 · Arquitetura-MVCL §4).
 * FAZ: serviços + pré-ações + dispatch + response.
 * NUNCA FAZ: saber de rotas concretas, renderizar view.
 */

use Reamur\System\Engine\{Autoloader, Registry, Config, Event, Loader, Action, Router};

// 1. Autoloader: namespaces do núcleo + da aplicação (Arquitetura §2)
require_once(DIR_SYSTEM . 'engine/autoloader.php');

$autoloader = new Autoloader();
$autoloader->register('Reamur\\' . APPLICATION, DIR_APPLICATION);
$autoloader->register('Reamur\\System', DIR_SYSTEM);

if (defined('DIR_EXTENSION')) {
    $autoloader->register('Reamur\\Extension', DIR_EXTENSION);
}

// 2. Composer (Twig e demais libs — Contrato §3: ponte única)
require_once(DIR_SYSTEM . 'vendor.php');

// 3. Registry
$registry = new Registry();
// Plano aprovado (CMS Page Structure): registrar contrato puro de layouts
// allowlisted; Admin valida e Catalog consome. Sem SQL, IO ou efeito no Blog.
// API compartilhada de configuração e manifesto allowlisted do Three.js.
$registry->set('three_scene_config', new \Reamur\System\Library\Three\SceneConfig());
$registry->set('three_asset_manifest', new \Reamur\System\Library\Three\AssetManifest());
$registry->set('cms_page_layout', new \Reamur\System\Library\Cms\PageLayout());

// 4. Config: default + aplicação (Contrato §4)
$config = new Config();
$config->load('default');
$config->load(strtolower(APPLICATION));
$registry->set('config', $config);

// 5. Log + handlers de erro/exceção
$log = new \Reamur\System\Library\Log($config->get('error_filename', 'error.log'));
$registry->set('log', $log);

ini_set('display_errors', $config->get('error_display', true) ? '1' : '0');
ini_set('log_errors', $config->get('error_log', true) ? '1' : '0');

set_error_handler(static function (int $code, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $code)) {
        return false; // respeita @-suppression
    }

    throw new \ErrorException($message, 0, $code, $file, $line);
});

set_exception_handler(static function (\Throwable $e) use ($registry, $config): void {
    $registry->get('log')->write(
        get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine()
    );

    http_response_code(500);

    if ($config->get('error_display', true)) {
        echo '<strong>ReamurCMS ' . REAMUR_VERSION . '</strong> — ' . get_class($e) . ': '
            . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine();
    } else {
        echo 'Ocorreu um erro. Detalhes no log do sistema.';
    }
});

// 6. Event + listeners do config (action_event)
$event = new Event();
$registry->set('event', $event);

// 7. Loader + Request + Response
$loader = new Loader($registry);
$registry->set('load', $loader);

$request = new \Reamur\System\Library\Request();
$registry->set('request', $request);

$response = new \Reamur\System\Library\Response();

foreach ((array)$config->get('response_header', []) as $header) {
    $response->addHeader((string)$header);
}

$response->setCompression((int)$config->get('response_compression', 0));
$registry->set('response', $response);

// 8. DB — somente quando a app pede via db_autostart (install fica off deliberadamente)
if ($config->get('db_autostart')) {
    $registry->set('db', new \Reamur\System\Library\Db(
        (string)$config->get('db_engine', 'mysqli'),
        (string)$config->get('db_hostname', 'localhost'),
        (string)$config->get('db_username', 'root'),
        (string)$config->get('db_password', ''),
        (string)$config->get('db_database', ''),
        (string)($config->get('db_port') ?? 3306)
    ));
}

// 8.1 Cache — sempre montado, ANTES das pré-ações (sessão/idiomas/settings
// dependem dele). F4-06 (ADR 0030): o driver efetivo vem de rms_setting — o
// painel (F4-01) edita cache_engine/cache_expire, mas a startup/setting que os
// injetaria no Config roda APÓS a montagem e ela mesma precisa do cache pronto
// para cachear settings (ovo e galinha). Solução: o boot consulta a tabela
// diretamente, com a mesma semântica da startup (valor não-vazio vence o
// default.php). Escolha ruim NUNCA derruba a app: fallback File + linha no log.
$cacheEngine = strtolower(trim((string)$config->get('cache_engine', 'file'))) ?: 'file';
$cacheExpire = (int)$config->get('cache_expire', 3600);

if ($config->get('db_autostart')) {
    try {
        $dbBoot = $registry->has('db') ? $registry->get('db') : null;

        if ($dbBoot !== null) {
            $bootPrefix = defined('DB_PREFIX') ? DB_PREFIX : 'rms_';

            $bootRows = $dbBoot->query(
                "SELECT `key`, `value` FROM `" . $bootPrefix . "setting`
                 WHERE `code` = 'config' AND `key` IN ('cache_engine', 'cache_expire')"
            );

            if ($bootRows instanceof \stdClass && !empty($bootRows->rows)) {
                foreach ((array)$bootRows->rows as $bootRow) {
                    $bootRow = (array)$bootRow;
                    $bootKey = (string)($bootRow['key'] ?? '');
                    $bootVal = (string)($bootRow['value'] ?? '');

                    if ($bootKey === 'cache_engine' && $bootVal !== '') {
                        $cacheEngine = strtolower(trim($bootVal)) ?: $cacheEngine;
                    } elseif ($bootKey === 'cache_expire' && (int)$bootVal > 0) {
                        $cacheExpire = (int)$bootVal;
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // tabela ausente (pré-install), DB fora do ar — seguem os defaults do arquivo
        $log->write('cache boot: rms_setting indisponível, usando defaults (' . $e->getMessage() . ')');
    }
}

// Verificador de usabilidade: alias do form, driver real e pré-requisito no servidor
$cacheDrivers = [
    'file'      => 'File',
    'apc'       => 'APCU', // alias histórico do form F4-01 (classe real: APCU)
    'apcu'      => 'APCU',
    'mem'       => 'Mem',
    'memcached' => 'Memcached',
    'redis'     => 'Redis',
];

$cachePrereq = [
    'File'      => static fn(): bool => true,
    'APCU'      => static fn(): bool => extension_loaded('apcu'),
    'Mem'       => static fn(): bool => extension_loaded('memcache'),
    'Memcached' => static fn(): bool => extension_loaded('memcached')
        && defined('CACHE_HOSTNAME') && defined('CACHE_PORT') && defined('CACHE_PREFIX'),
    'Redis'     => static fn(): bool => extension_loaded('redis'),
];

$cacheDriver = $cacheDrivers[$cacheEngine] ?? '';
$cacheClass  = $cacheDriver !== '' ? ('Reamur\\System\\Library\\Cache\\' . $cacheDriver) : '';

$cache = null;

if ($cacheDriver !== '' && $cacheDriver !== 'File'
    && class_exists($cacheClass)
    && ($cachePrereq[$cacheDriver] ?? static fn(): bool => false)()) {
    try {
        $cache = new $cacheClass($cacheExpire);
    } catch (\Throwable $e) {
        $log->write("cache: driver {$cacheEngine} recusou montagem ({$e->getMessage()}); fallback file");
    }
}

if ($cache === null && $cacheEngine !== 'file') {
    $log->write("cache: engine {$cacheEngine} indisponível neste servidor; fallback file");
}

$registry->set('cache', $cache ?? new \Reamur\System\Library\Cache\File($cacheExpire));

// 9. Session
if ($config->get('session_autostart')) {
    $sessionEngine = 'Reamur\\System\\Library\\Session\\' . ucfirst((string)$config->get('session_engine', 'file'));

    if (!class_exists($sessionEngine)) {
        throw new \Exception("Driver de sessão {$sessionEngine} não encontrado.");
    }

    $registry->set('session', new \Reamur\System\Library\Session(new $sessionEngine(), [
        'name'        => $config->get('session_name'),
        'path'        => $config->get('session_path'),
        'domain'      => $config->get('session_domain'),
        'samesite'    => $config->get('session_samesite'),
        'expire'      => $config->get('session_expire'),
        'probability' => $config->get('session_probability'),
        'divisor'     => $config->get('session_divisor'),
    ]));
}

// 10. Template (engine ativa vem do config — Contrato: twig é o padrão)
$templateClass = 'Reamur\\System\\Library\\Template\\' . ucfirst((string)$config->get('template_engine', 'twig'));

if (!class_exists($templateClass)) {
    throw new \Exception("Engine de template {$templateClass} não encontrada.");
}

$registry->set('template', new $templateClass(
    (string)$config->get('template_directory', ''),
    (bool)$config->get('template_cache', false)
));

// 11. Language (ADR 0001: diretório padrão en-us)
$registry->set('language', new \Reamur\System\Library\Language((string)$config->get('language_directory', 'en-us')));

// 12. Url (base = HTTP_SERVER do config/Adendo F1-4)
if ($config->get('url_autostart')) {
    $registry->set('url', new \Reamur\System\Library\Url(defined('HTTP_SERVER') ? HTTP_SERVER : '/'));
}

// 13. Document
$registry->set('document', new \Reamur\System\Library\Document());

// 14. Rota da requisição (ADR 0006): ?route= tem prioridade absoluta; senão o
// router da aplicação (config action_router = 'startup/router') resolve o
// caminho limpo; senão a rota padrão da app.
$route = (string)($request->get['route'] ?? '');

if ($route === '') {
    $actionRouter = (string)$config->get('action_router', '');

    if ($actionRouter !== '') {
        $result = (new Action($actionRouter))->execute($registry);
        $route  = is_string($result) ? trim($result, '/') : '';
    }

    if ($route === '') {
        $route = (string)$config->get('action_default', 'common/home');
    }
}

// Fonte única da rota para pré-ações e controllers (URL limpa não traz ?route=)
$config->set('_route', $route);

// 15. Pré-ações (startup/*): idioma, sessão, seo, login... (Arquitetura §4)
foreach ((array)$config->get('action_pre_action', []) as $preAction) {
    (new Action((string)$preAction))->execute($registry);
}

// 16. Rota pedida → dispatch (com fallback da rota de erro da app)
(new Router($registry))->dispatch(new Action($route), new Action((string)$config->get('action_error', 'error/not_found')));

// 16. Saída
$response->output();
