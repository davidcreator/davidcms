<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Leitura/escrita dos arquivos de config ($_[]) de dev/system/config/.
 * default.php carrega sempre; depois config/{aplicacao}.php mescla por cima (Contrato §4).
 */
namespace Reamur\System\Engine;

final class Config {
    /** @var array<string, mixed> */
    private array $data = [];

    public function get(string $key, mixed $default = null): mixed {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool {
        return isset($this->data[$key]);
    }

    /**
     * Carrega DIR_CONFIG . {arquivo}.php e mescla o $_[] resultante.
     * Arquivo inexistente é ignorado (config por aplicação é opcional).
     */
    public function load(string $filename): void {
        $file = DIR_CONFIG . preg_replace('/[^a-z0-9_\-]/i', '', $filename) . '.php';

        $_ = [];

        if (is_file($file)) {
            require($file);
        }

        $this->data = array_replace($this->data, $_);
    }
}
