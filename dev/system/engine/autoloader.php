<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Autoloader das classes Reamur\* (Arquitetura-MVCL §2/§3).
 * Regra de mapeamento: cada segmento do namespace vira snake_case no caminho —
 * Reamur\Install\Controller\Install\Step_1 → {DIR_APPLICATION}controller/install/step_1.php
 * Reamur\System\Engine\Autoloader         → {DIR_SYSTEM}engine/autoloader.php
 */
namespace Reamur\System\Engine;

final class Autoloader {
    /** @var array<string, string> Par namespace-pai => diretório-base */
    private array $paths = [];

    private bool $registered = false;

    public function register(string $namespace, string $directory): void {
        $this->paths[trim($namespace, '\\')] = rtrim($directory, '/\\') . '/';

        if (!$this->registered) {
            spl_autoload_register([$this, 'load']);
            $this->registered = true;
        }
    }

    public function load(string $class): bool {
        foreach ($this->paths as $namespace => $directory) {
            $prefix = $namespace . '\\';

            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $segments = explode('\\', substr($class, strlen($prefix)));
            $file     = $directory . implode('/', array_map([self::class, 'toSnake'], $segments)) . '.php';

            if (is_file($file)) {
                require_once($file);

                return true;
            }

            return false;
        }

        return false;
    }

    /** PascalCase / CamelCase / Step_1 → snake_case */
    private static function toSnake(string $segment): string {
        return strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $segment));
    }
}
