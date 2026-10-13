<?php
// Strict error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '0');

// Check PHP version with more detailed error message
if (version_compare(phpversion(), '8.0.0.0', '<')) {
    header('Content-Type: text/plain');
    exit('PHP 8.0 or higher is required. Current version: ' . phpversion());
}

// Timezone configuration with fallback
if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

// Enhanced Windows IIS Compatibility
if (!isset($_SERVER['DOCUMENT_ROOT'])) {
    if (isset($_SERVER['SCRIPT_FILENAME'])) {
        $_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', 
            substr($_SERVER['SCRIPT_FILENAME'], 0, 
            0 - strlen($_SERVER['PHP_SELF'])));
    } elseif (isset($_SERVER['PATH_TRANSLATED'])) {
        $_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', 
            substr(str_replace('\\\\', '\\', $_SERVER['PATH_TRANSLATED']), 
            0, 0 - strlen($_SERVER['PHP_SELF'])));
    }
}

// Request URI handling with security checks
if (!isset($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
    
    if (isset($_SERVER['QUERY_STRING'])) {
        $_SERVER['REQUEST_URI'] .= '?' . filter_var($_SERVER['QUERY_STRING'], 
            FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    }
}

// HTTP Host validation
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = filter_var(getenv('HTTP_HOST'), FILTER_SANITIZE_URL);
}

// Secure SSL detection
$_SERVER['HTTPS'] = false;
if ((isset($_SERVER['HTTPS']) && 
     (strtolower($_SERVER['HTTPS']) === 'on' || $_SERVER['HTTPS'] == '1')) ||
    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
    $_SERVER['HTTPS'] = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && 
           strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https' ||
           !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && 
           strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
    $_SERVER['HTTPS'] = true;
}

// IP address validation with security headers
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $_SERVER['REMOTE_ADDR'] = filter_var(
        $_SERVER['HTTP_X_FORWARDED_FOR'], 
        FILTER_VALIDATE_IP
    ) ?: $_SERVER['REMOTE_ADDR'];
} elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $_SERVER['REMOTE_ADDR'] = filter_var(
        $_SERVER['HTTP_CLIENT_IP'], 
        FILTER_VALIDATE_IP
    ) ?: $_SERVER['REMOTE_ADDR'];
}

// Core system files with existence checks
$systemFiles = [
    'autoloader' => DIR_SYSTEM . 'engine/autoloader.php',
    'config' => DIR_SYSTEM . 'engine/config.php',
    'helper' => DIR_SYSTEM . 'helper/general.php'
];

foreach ($systemFiles as $file) {
    if (!file_exists($file)) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Critical system file missing: ' . basename($file));
    }
    require_once $file;
}