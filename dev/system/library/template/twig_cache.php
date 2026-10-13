<?php
namespace Reamur\System\Library\Template;

/**
 * Twig filesystem cache with Windows-safe replacement handling.
 */
class TwigCache extends \Twig\Cache\FilesystemCache {
    private const WINDOWS_MOVE_ATTEMPTS = 5;
    private const WINDOWS_MOVE_DELAY_US = 50000;

    private int $options;

    public function __construct(string $directory, int $options = 0) {
        parent::__construct($directory, $options);

        $this->options = $options;
    }

    public function write(string $key, string $content): void {
        $dir = \dirname($key);

        if (!is_dir($dir)) {
            clearstatcache(true, $dir);

            $created = self::runSilentlyCallback(static fn() => @mkdir($dir, 0777, true));

            if ($created === false) {
                clearstatcache(true, $dir);

                if (!is_dir($dir)) {
                    throw new \RuntimeException(\sprintf('Unable to create the cache directory (%s).', $dir));
                }
            }
        } elseif (!is_writable($dir)) {
            throw new \RuntimeException(\sprintf('Unable to write in the cache directory (%s).', $dir));
        }

        $tmpFile = tempnam($dir, basename($key));

        if ($tmpFile === false) {
            throw new \RuntimeException(\sprintf('Failed to create a temporary cache file for "%s".', $key));
        }

        try {
            $written = self::runSilentlyCallback(static fn() => @file_put_contents($tmpFile, $content));

            if ($written !== false && $this->moveIntoPlace($tmpFile, $key)) {
                self::runSilentlyCallback(static fn() => @chmod($key, 0666 & ~umask()));
                $this->invalidateBytecode($key);

                return;
            }
        } finally {
            self::deletePath($tmpFile);
        }

        throw new \RuntimeException(\sprintf('Failed to write cache file "%s".', $key));
    }

    public static function clearDirectoryContents(string $directory): bool {
        $directory = rtrim($directory, '/\\');

        if ($directory === '' || !is_dir($directory)) {
            return true;
        }

        $entries = scandir($directory);

        if ($entries === false) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (!self::deletePath($directory . DIRECTORY_SEPARATOR . $entry)) {
                return false;
            }
        }

        return true;
    }

    public static function deletePath(string $path): bool {
        if ($path === '') {
            return true;
        }

        clearstatcache(true, $path);

        if (!file_exists($path) && !is_link($path)) {
            return true;
        }

        if (is_dir($path) && !is_link($path)) {
            $entries = scandir($path);

            if ($entries === false) {
                return false;
            }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                if (!self::deletePath($path . DIRECTORY_SEPARATOR . $entry)) {
                    return false;
                }
            }

            return self::retryOperation(static fn() => @rmdir($path), $path);
        }

        return self::retryOperation(static fn() => @unlink($path), $path);
    }

    private function moveIntoPlace(string $tmpFile, string $key): bool {
        if ($this->tryRename($tmpFile, $key)) {
            return true;
        }

        clearstatcache(true, $key);

        if (\PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        if (is_file($key) && $this->tryCopy($tmpFile, $key)) {
            return true;
        }

        if (is_file($key) && $this->tryDelete($key)) {
            return $this->tryRename($tmpFile, $key);
        }

        return !is_file($key) && $this->tryRename($tmpFile, $key);
    }

    private function invalidateBytecode(string $key): void {
        if (self::FORCE_BYTECODE_INVALIDATION !== ($this->options & self::FORCE_BYTECODE_INVALIDATION)) {
            return;
        }

        if (\function_exists('opcache_invalidate') && filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN)) {
            self::runSilentlyCallback(static fn() => @opcache_invalidate($key, true));
        } elseif (\function_exists('apc_compile_file')) {
            apc_compile_file($key);
        }
    }

    private static function runSilentlyCallback(callable $callback): mixed {
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    private function tryRename(string $source, string $target): bool {
        return self::retryOperation(static fn() => @rename($source, $target), $target);
    }

    private function tryCopy(string $source, string $target): bool {
        return self::retryOperation(static fn() => @copy($source, $target), $target);
    }

    private function tryDelete(string $path): bool {
        return self::deletePath($path);
    }

    private static function retryOperation(callable $callback, string $path = ''): bool {
        $attempts = \PHP_OS_FAMILY === 'Windows' ? self::WINDOWS_MOVE_ATTEMPTS : 1;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if (self::runSilentlyCallback($callback)) {
                return true;
            }

            if ($attempt + 1 < $attempts) {
                if ($path !== '') {
                    clearstatcache(true, $path);
                } else {
                    clearstatcache();
                }
                usleep(self::WINDOWS_MOVE_DELAY_US * ($attempt + 1));
            }
        }

        return false;
    }
}
