<?php
/**
 * config.php gerado pelo wizard de instalação (dev/install) — NUNCA versionar.
 * Constantes permitidas: docs/arquitetura/Contrato-Bootstrap.md §1.1 (nada além desta lista).
 * Placeholders {{...}} são substituídos por install/step_3.php via strtr().
 */

// HTTP
define('HTTP_SERVER', '{{HTTP_SERVER}}');
define('HTTPS_SERVER', '{{HTTPS_SERVER}}');

// F6-01 (ADR 0037 §1): exibição de erros na tela. Instalações NOVAS nascem
// em modo desenvolvimento (true). EM PRODUÇÃO: mude para false — ou REMOVA
// a constante: sem ela o núcleo assume false (display_errors desligado,
// erros SEMPRE em system/storage/logs/error.log).
define('REAMUR_ERROR_DISPLAY', true);

// Proxy / CDN (ADR 0089). Se o site fica atrás de CDN ou proxy reverso, o PHP
// recebe todos os visitantes com o IP do proxy: o limite de tentativas de
// login, contato e orçamento passa a valer para todos juntos. Declare em quem
// confiar (o painel inicial do admin mostra o que o servidor enxerga):
//   - endereços e faixas dos proxies (mais seguro):
//       define('REAMUR_TRUSTED_PROXIES', '203.0.113.0/24, 2001:db8::/32');
//   - ou quantos proxies ficam na frente do site. Use só se o servidor NÃO
//     puder ser acessado por fora da CDN:
//       define('REAMUR_TRUSTED_PROXY_HOPS', 1);
// Sem nenhuma das duas linhas, vale o IP de quem conectou. Use os mesmos
// valores em catalog/config.php e admin/config.php.

// DIR (raízes calculadas a partir de DIR_REAMUR — ver §1.1)
define('DIR_REAMUR', '{{DIR_REAMUR}}');
define('DIR_APPLICATION', DIR_REAMUR . 'dev/{{APP_DIR}}/');
define('DIR_SYSTEM', DIR_REAMUR . 'dev/system/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');
define('DIR_CONFIG', DIR_SYSTEM . 'config/');
define('DIR_STORAGE', DIR_SYSTEM . 'storage/');
define('DIR_EXTENSION', DIR_REAMUR . 'dev/extension/');

// DB
define('DB_DRIVER', '{{DB_DRIVER}}');
define('DB_HOSTNAME', '{{DB_HOSTNAME}}');
define('DB_USERNAME', '{{DB_USERNAME}}');
define('DB_PASSWORD', '{{DB_PASSWORD}}');
define('DB_DATABASE', '{{DB_DATABASE}}');
define('DB_PORT', '{{DB_PORT}}');
define('DB_PREFIX', '{{DB_PREFIX}}');
