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

// 8. DB (driver chega na Tarefa 6c; config avisa se tentar montar antes)
if ($config->get('db_autostart')) {
    $dbClass = 'Reamur\\System\\Library\\Db\\' . ucfirst((string)$config->get('db_engine', 'mysqli'));

    if (!class_exists($dbClass)) {
        throw new \Exception("DB autostart ligado, mas o driver {$dbClass} ainda não existe (Tarefa 6c).");
    }
}

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

// 14. Pré-ações (startup/*): idioma, sessão, seo, login... (Arquitetura §4)
foreach ((array)$config->get('action_pre_action', []) as $preAction) {
    (new Action((string)$preAction))->execute($registry);
}

// 15. Rota pedida → dispatch (com fallback da rota de erro da app)
$route = (string)($request->get['route'] ?? '');

if ($route === '') {
    $route = (string)$config->get('action_default', 'common/home');
}

(new Router($registry))->dispatch(new Action($route), new Action((string)$config->get('action_error', 'error/not_found')));

// 16. Saída
$response->output();
