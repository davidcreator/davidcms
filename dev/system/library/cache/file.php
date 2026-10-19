<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Cache em arquivo (driver padrão do config): DIR_STORAGE/cache/cache.{chave}.
 * Expiração por carimbo embutido no próprio payload.
 */
namespace Reamur\System\Library\Cache;

final class File {
    private string $directory;

    public function __construct(private int $expire = 3600) {
        $this->directory = DIR_STORAGE . 'cache/';

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    public function get(string $key): mixed {
        $file = $this->directory . 'cache.' . preg_replace('/[^a-zA-Z0-9_.\-]/', '', $key);

        if (!is_file($file)) {
            return null;
        }

        $payload = json_decode((string)file_get_contents($file), true);

        if (!is_array($payload) || ($payload['expire'] ?? 0) < time()) {
            $this->delete($key);

            return null;
        }

        return $payload['data'] ?? null;
    }

    public function set(string $key, mixed $value): void {
        $file = $this->directory . 'cache.' . preg_replace('/[^a-zA-Z0-9_.\-]/', '', $key);

        file_put_contents($file, json_encode([
            'expire' => time() + $this->expire,
            'data'   => $value,
        ]), LOCK_EX);
    }

    public function delete(string $key): void {
        $file = $this->directory . 'cache.' . preg_replace('/[^a-zA-Z0-9_.\-]/', '', $key);

        if (is_file($file)) {
            unlink($file);
        }
    }

    /**
     * Limpa TODOS os arquivos cache.* (botão "Limpar cache" do painel — F4-01).
     * O framework registra o adapter cru no Registry (sd no wrapper Cache),
     * o que tornava o method_exists do wrapper cache/Cache::clear() a apenas
     * proteção — o adapter em si precisa existir com o mesmo contrato.
     */
    public function clear(): void {
        foreach (glob($this->directory . 'cache.*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
