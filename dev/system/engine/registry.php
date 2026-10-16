<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Container central de serviços (Arquitetura-MVCL §5).
 * Controller e Model recebem o Registry no construtor e o expõem via __get.
 */
namespace Reamur\System\Engine;

final class Registry {
    /** @var array<string, mixed> */
    private array $data = [];

    public function get(string $key): mixed {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool {
        return isset($this->data[$key]);
    }
}
