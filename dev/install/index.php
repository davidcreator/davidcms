<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Front controller — aplicação INSTALL (wizard de instalação/upgrade).
 * Especificação: docs/arquitetura/Contrato-Bootstrap.md §2. A ÚNICA linha específica
 * da aplicação é o valor de APPLICATION (demais apps mudam apenas ela).
 */

// 1. Versão do núcleo (bump somente em release — ver Contrato-Bootstrap §5.3)
define('REAMUR_VERSION', '1.0.0');

// 2. Aplicação em execução
define('APPLICATION', 'Install');

// 3. Configuração da instalação (gerada pelo wizard install/)
if (is_file('config.php')) {
    require_once('config.php');
}

// 4. Install NUNCA redireciona (ela É o wizard). Para rodar ANTES do config.php
//    existir, autodefine o ambiente usando SOMENTE constantes do Contrato-Bootstrap §1.1
//    (Adendo F1-4 — nenhuma constante nova). Se config.php existir, o bloco é ignorado.
if (!defined('DIR_APPLICATION')) {
    $app_dir = str_replace('\\', '/', (string)realpath(__DIR__));

    // DIR (§1.1) — raízes derivadas da posição real de dev/install
    define('DIR_REAMUR', dirname(dirname($app_dir)) . '/');
    define('DIR_APPLICATION', $app_dir . '/');
    define('DIR_SYSTEM', DIR_REAMUR . 'dev/system/');
    define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
    define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');
    define('DIR_CONFIG', DIR_SYSTEM . 'config/');
    define('DIR_STORAGE', DIR_SYSTEM . 'storage/');
    define('DIR_EXTENSION', DIR_REAMUR . 'dev/extension/');

    // HTTP (§1.1) — URL base da própria aplicação install
    // ADR 0089 (S5): mesma regra do palpite de URL do passo 3. Atrás de CDN
    // ou proxy que termina o TLS o PHP não vê HTTPS, e os formulários do
    // instalador saíam apontando para http:// numa página aberta em https.
    // Aqui não há config.php (logo, não há lista de proxies confiáveis); o
    // cabeçalho só decide os links da própria resposta.
    $scheme = ((!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443
        || strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''), 2)[0])) === 'https') ? 'https' : 'http';
    $rawHost = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $rawHost = substr($rawHost, 0, strcspn($rawHost, "\r\n\0"));
    $host    = preg_replace('/[^a-zA-Z0-9.:\[\]-]/', '', $rawHost) ?: 'localhost';
    $base   = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/install/index.php')));

    define('HTTP_SERVER', $scheme . '://' . $host . rtrim($base, '/') . '/');
    define('HTTPS_SERVER', HTTP_SERVER);
}

// 5. Trava pós-instalação (F2-18, ADR 0018; ADR 0091): o instalador se recusa
//    (403) quando existe system/storage/install.lock OU um config.php do site.
//    A trava sozinha não bastava: ela faltava quando o assistente era
//    interrompido, quando a pasta storage não aceitava escrita e quando o
//    config.php era criado à mão. Decidido ANTES de subir o núcleo.
//    Para reinstalar: apague install.lock + os dois config.php.
require_once(dirname(__DIR__) . '/system/library/security/install_seal.php');

if (\Reamur\System\Library\Security\InstallSeal::isSealed(DIR_STORAGE, dirname(__DIR__))) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo \Reamur\System\Library\Security\InstallSeal::lockedPage();
    exit();
}

// 6. Bootstrap do núcleo — ordem inviolável, nada entre estes requires
require_once(DIR_SYSTEM . 'startup.php');
require_once(DIR_SYSTEM . 'framework.php');

// 6. A partir daqui o fluxo segue o ciclo MVCL (docs/arquitetura/Arquitetura-MVCL.md §4)
