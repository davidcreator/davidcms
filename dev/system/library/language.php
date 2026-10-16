<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Serviço de idiomas da aplicação (Arquitetura-MVCL §9).
 * Arquivo: {app}/language/{diretório}/{rota}.php define $_[ ].
 * en-us é o padrão (ADR 0001); códigos alternativos via load($route, $code).
 */
namespace Reamur\System\Library;

final class Language {
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private string $directory = 'en-us') {}

    public function get(string $key): string {
        return (string)($this->data[$key] ?? $key);
    }

    public function set(string $key, mixed $value): void {
        $this->data[$key] = $value;
    }

    /** @return array<string, mixed> */
    public function all(): array {
        return $this->data;
    }

    /**
     * Mescla as chaves de um arquivo de language.
     * Arquivo inexistente é ignorado (defaults da app seguem valendo).
     *
     * @return array<string, mixed> Todas as chaves acumuladas
     */
    public function load(string $route, string $code = ''): array {
        $_ = [];

        $file = DIR_APPLICATION . 'language/' . ($code ?: $this->directory) . '/' . $route . '.php';

        if (is_file($file)) {
            require($file);
        }

        $this->data = array_replace($this->data, $_);

        return $this->data;
    }
}
