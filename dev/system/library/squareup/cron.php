<?php

try {
    // Get current script directory
    $squareup_dir = dirname(__FILE__);
    
    // Load cron functions
    $cron_functions_path = $squareup_dir . DIRECTORY_SEPARATOR . 'cron_functions.php';
    if (!file_exists($cron_functions_path)) {
        throw new \RuntimeException("Cron functions file not found: $cron_functions_path");
    }
    require_once $cron_functions_path;
    
    // Initialize and get main application path
    $index = squareup_init($squareup_dir);
    
    if ($index) {
        if (!file_exists($index)) {
            throw new \RuntimeException("Main application file not found: $index");
        }
        require_once $index;
    } else {
        throw new \RuntimeException("Failed to initialize SquareUp cron");
    }
} catch (\Throwable $e) {
    // Log error to stderr for cron to catch
    file_put_contents('php://stderr', 'SquareUp Cron Error: ' . $e->getMessage() . PHP_EOL);
    exit(1); // Non-zero exit code indicates failure
}