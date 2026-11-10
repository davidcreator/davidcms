<?php declare(strict_types=1);

$root = dirname(__DIR__) . '/';

$defs = [
    'REAMUR_VERSION'  => '1.0.0-test',
    'APPLICATION'     => 'Test',
    'HTTP_SERVER'     => 'https://example.test/',
    'HTTPS_SERVER'    => 'https://example.test/',
    'DIR_REAMUR'      => $root,
    'DIR_APPLICATION' => $root . 'dev/catalog/',
    'DIR_SYSTEM'      => $root . 'dev/system/',
    'DIR_LANGUAGE'    => $root . 'dev/catalog/language/',
    'DIR_TEMPLATE'    => $root . 'dev/catalog/view/template/',
    'DIR_CONFIG'      => $root . 'dev/system/config/',
    'DIR_STORAGE'     => $root . 'dev/system/storage/',
    'DIR_EXTENSION'   => $root . 'dev/extension/',
    'DB_PREFIX'       => 'rms_',
];

foreach ($defs as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}

$vendorAutoload = $root . 'dev/system/storage/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

require_once $root . 'dev/system/engine/autoloader.php';

$autoloader = new \Reamur\System\Engine\Autoloader();
$autoloader->register('Reamur\System', $root . 'dev/system/');
$autoloader->register('Reamur\Admin', $root . 'dev/admin/');
$autoloader->register('Reamur\Catalog', $root . 'dev/catalog/');
$autoloader->register('Reamur\Install', $root . 'dev/install/');
