<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * EX-01 (ADR 0095): classe-base de toda extensão. O arquivo
 * `dev/extension/<codigo>/extension.php` declara
 * `Reamur\Extension\<Codigo>\Extension extends` esta classe e escreve os
 * ouvintes que o extension.json aponta (métodos públicos `on...`).
 *
 * O construtor é fechado: a extensão não roda código próprio ao ser criada,
 * só quando um evento que ela declarou acontece.
 *
 * Guia: docs/guias/Guia-Extensoes.md.
 */
namespace Reamur\System\Library\Extension;

use Reamur\System\Engine\Registry;

abstract class Extension {
    /**
     * @param array<string, string|bool> $settings valores já conferidos pelo manifesto; segredos abertos
     * @param list<string>               $locked   segredos gravados que a chave do config.php não abre
     */
    final public function __construct(
        protected Registry $registry,
        private Manifest $manifest,
        #[\SensitiveParameter] private array $settings,
        private string $app,
        private array $locked = []
    ) {}

    final public function code(): string {
        return $this->manifest->code();
    }

    final public function manifest(): Manifest {
        return $this->manifest;
    }

    /** 'catalog' (site público) ou 'admin'. */
    final protected function app(): string {
        return $this->app;
    }

    /**
     * Valor de um campo de configuração. Texto para text, textarea, select e
     * secret; true/false para checkbox. Um segredo que não pôde ser aberto
     * chega como '' (veja secretLocked()).
     */
    final protected function setting(string $key, string|bool $default = ''): string|bool {
        return $this->settings[$key] ?? $default;
    }

    /** O segredo está gravado, mas a chave do config.php não o abre (ADR 0094). */
    final protected function secretLocked(string $key): bool {
        return in_array($key, $this->locked, true);
    }

    /** Escreve uma linha no log do sistema, identificada com o código da extensão. */
    final protected function log(string $message): void {
        Manager::log($this->registry, 'extension ' . $this->code() . ': ' . $message);
    }

    /** Nunca despeja a configuração (pode conter segredos) num var_dump ou num rastro de erro. */
    public function __debugInfo(): array {
        return ['code' => $this->code(), 'app' => $this->app];
    }
}
