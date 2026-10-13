<?php
declare(strict_types=1);

if (!function_exists('reamur_config_loader_is_absolute_path')) {
    function reamur_config_loader_is_absolute_path(string $path): bool {
        return (bool)preg_match('#^(?:[a-zA-Z]:[\\\\/]|\\\\\\\\|/)#', $path);
    }
}

if (!function_exists('reamur_config_loader_normalize_path')) {
    function reamur_config_loader_normalize_path(string $path): string {
        return str_replace('\\', '/', $path);
    }
}

if (!function_exists('reamur_config_loader_candidate_path')) {
    function reamur_config_loader_candidate_path(string $root, string $candidate): string {
        $candidate = trim($candidate);

        if ($candidate === '') {
            return '';
        }

        $candidate = reamur_config_loader_normalize_path($candidate);

        if (!reamur_config_loader_is_absolute_path($candidate)) {
            $candidate = rtrim(reamur_config_loader_normalize_path($root), '/') . '/' . ltrim($candidate, '/');
        }

        return $candidate;
    }
}

if (!function_exists('reamur_config_loader_resolve_file')) {
    function reamur_config_loader_resolve_file(string $root, string $default_relative, array $env_keys): string {
        foreach ($env_keys as $env_key) {
            $env_value = getenv($env_key);

            if (!is_string($env_value) || trim($env_value) === '') {
                continue;
            }

            $candidate = reamur_config_loader_candidate_path($root, $env_value);

            if ($candidate === '') {
                continue;
            }

            $resolved = realpath($candidate);

            if (is_string($resolved) && is_file($resolved)) {
                return reamur_config_loader_normalize_path($resolved);
            }

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return rtrim(reamur_config_loader_normalize_path($root), '/') . '/' . ltrim($default_relative, '/');
    }
}

if (!function_exists('reamur_load_catalog_config')) {
    function reamur_load_catalog_config(string $root): void {
        $config_file = reamur_config_loader_resolve_file($root, 'config.php', [
            'REAMUR_CATALOG_CONFIG_FILE',
            'REAMUR_CONFIG_FILE'
        ]);

        if (is_file($config_file)) {
            require_once $config_file;
        }
    }
}

if (!function_exists('reamur_load_admin_config')) {
    function reamur_load_admin_config(string $root): void {
        $config_file = reamur_config_loader_resolve_file($root, 'admin/config.php', [
            'REAMUR_ADMIN_CONFIG_FILE',
            'REAMUR_CONFIG_FILE'
        ]);

        if (is_file($config_file)) {
            require_once $config_file;
        }
    }
}
