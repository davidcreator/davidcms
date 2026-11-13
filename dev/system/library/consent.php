<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * EX-02 (ADR 0096): a escolha do visitante sobre cookies que não são
 * necessários. Vive em um cookie próprio do site (`rms_consent`), gravado
 * pelo servidor, e é lida a cada página ANTES de qualquer extensão entrar em
 * funcionamento: a extensão que declara uma categoria só roda para o
 * visitante que aceitou essa categoria (Extension\Manager).
 *
 * Valor do cookie: `1.<instante unix>.<pares>`, em que cada par é a letra da
 * categoria seguida de 1 (aceitou) ou 0 (recusou). Ex.: `1.1763000000.a1m0`.
 * Categoria que não aparece não foi perguntada ainda. Qualquer valor fora
 * desse formato, vencido ou com data no futuro conta como "sem escolha".
 */
namespace Reamur\System\Library;

final class Consent {
    public const COOKIE = 'rms_consent';

    /** Categorias que precisam de aceitação. Os cookies necessários não entram: não dependem de escolha. */
    public const CATEGORIES = ['analytics', 'marketing'];

    public const DEFAULT_DAYS = 180;
    public const MIN_DAYS     = 30;
    public const MAX_DAYS     = 390;

    /** Linhas `Set-Cookie` de limpeza por resposta, no máximo. */
    public const MAX_EXPIRE_LINES = 40;

    private const LETTERS = ['analytics' => 'a', 'marketing' => 'm'];

    /**
     * @param array<string, bool> $choices categoria => aceitou
     */
    private function __construct(private array $choices, private int $time) {}

    /** Visitante que ainda não escolheu nada. */
    public static function none(): self {
        return new self([], 0);
    }

    /**
     * Uma escolha feita agora.
     *
     * @param array<string, mixed> $choices categoria => aceitou; categorias desconhecidas são ignoradas
     */
    public static function of(array $choices, int $now): self {
        $clean = [];

        foreach (self::CATEGORIES as $category) {
            if (array_key_exists($category, $choices)) {
                $clean[$category] = $choices[$category] === true;
            }
        }

        return new self($clean, $clean === [] ? 0 : $now);
    }

    /**
     * Lê o cookie do pedido.
     *
     * @param mixed $raw  valor recebido (qualquer coisa: vem do navegador)
     * @param int   $now  instante atual
     * @param mixed $days prazo de validade configurado
     */
    public static function fromCookie(mixed $raw, int $now, mixed $days = self::DEFAULT_DAYS): self {
        if (!is_string($raw) || preg_match('/^1\.([0-9]{10})\.((?:[am][01]){1,2})$/D', $raw, $m) !== 1) {
            return self::none();
        }

        $time = (int)$m[1];

        // Relógio do servidor pode variar alguns minutos; data bem no futuro é valor forjado ou corrompido.
        if ($time > $now + 300 || $time + self::days($days) * 86400 < $now) {
            return self::none();
        }

        $choices = [];

        foreach (str_split($m[2], 2) as $pair) {
            $category = (string)array_search($pair[0], self::LETTERS, true);

            if (isset($choices[$category])) {
                return self::none();
            }

            $choices[$category] = $pair[1] === '1';
        }

        return self::of($choices, $time);
    }

    /** Prazo em dias, dentro dos limites; valor inválido cai no padrão. */
    public static function days(mixed $value): int {
        if (is_string($value) && preg_match('/^[0-9]{1,4}$/D', $value) === 1) {
            $value = (int)$value;
        }

        if (!is_int($value) || $value < self::MIN_DAYS || $value > self::MAX_DAYS) {
            return self::DEFAULT_DAYS;
        }

        return $value;
    }

    /** O visitante aceitou esta categoria. */
    public function allows(string $category): bool {
        return ($this->choices[$category] ?? false) === true;
    }

    /**
     * Aceitou pelo menos uma das categorias.
     *
     * @param list<string> $categories
     */
    public function allowsAny(array $categories): bool {
        foreach ($categories as $category) {
            if ($this->allows((string)$category)) {
                return true;
            }
        }

        return false;
    }

    /** Já respondeu (sim ou não) sobre esta categoria. */
    public function decided(string $category): bool {
        return isset($this->choices[$category]);
    }

    /**
     * Das categorias em uso no site, as que ainda não foram perguntadas a este visitante.
     *
     * @param list<string> $categories
     * @return list<string>
     */
    public function pending(array $categories): array {
        return array_values(array_filter(
            $categories,
            fn ($category): bool => is_string($category) && in_array($category, self::CATEGORIES, true) && !$this->decided($category)
        ));
    }

    /** @return list<string> categorias aceitas */
    public function granted(): array {
        return array_keys(array_filter($this->choices));
    }

    /** Instante da escolha (0 = sem escolha). */
    public function time(): int {
        return $this->time;
    }

    /** Valor a gravar no cookie ('' = sem escolha, nada a gravar). */
    public function value(): string {
        if ($this->choices === []) {
            return '';
        }

        $pairs = '';

        foreach (self::CATEGORIES as $category) {
            if (isset($this->choices[$category])) {
                $pairs .= self::LETTERS[$category] . ($this->choices[$category] ? '1' : '0');
            }
        }

        return '1.' . sprintf('%010d', $this->time) . '.' . $pairs;
    }

    /**
     * Linha `Set-Cookie` que grava a escolha. HttpOnly: nenhum script precisa
     * ler este cookie; quem decide o que roda é o servidor.
     */
    public function cookieLine(mixed $days, bool $secure): string {
        $value = $this->value();

        if ($value === '') {
            return self::COOKIE . '=; Max-Age=0; Path=/; SameSite=Lax; HttpOnly' . ($secure ? '; Secure' : '');
        }

        return self::COOKIE . '=' . $value . '; Max-Age=' . (self::days($days) * 86400) . '; Path=/; SameSite=Lax; HttpOnly' . ($secure ? '; Secure' : '');
    }

    /**
     * Um nome de cookie atende a um padrão declarado por uma extensão?
     * Padrão = nome exato, ou prefixo terminado em `*` (`_ga_*`).
     */
    public static function nameMatches(string $name, string $pattern): bool {
        if (str_ends_with($pattern, '*')) {
            $prefix = substr($pattern, 0, -1);

            return $prefix !== '' && str_starts_with($name, $prefix);
        }

        return $pattern !== '' && $name === $pattern;
    }

    /**
     * Linhas `Set-Cookie` que apagam, no navegador, os cookies que as
     * etiquetas de uma categoria recusada deixaram. Só apaga o que o pedido
     * trouxe e que atende a um padrão declarado; nunca o cookie de sessão nem
     * o da própria escolha.
     *
     * Um cookie só é apagado com o mesmo domínio com que foi criado, e o
     * servidor não recebe essa informação. Por isso sai uma linha sem domínio
     * e uma para cada domínio pai do site.
     *
     * @param list<string|int> $present  nomes dos cookies recebidos no pedido
     * @param list<string>     $patterns padrões declarados pelas extensões
     * @param string           $host     domínio do site (de `site_url`, nunca do pedido)
     * @param list<string>     $keep     nomes que nunca são apagados
     * @return list<string>
     */
    public static function expireLines(array $present, array $patterns, string $host, array $keep = []): array {
        $keep[]  = self::COOKIE;
        $domains = [''];
        $host    = strtolower(trim($host, '.'));

        if (preg_match('/^(?=.{4,253}$)[a-z0-9-]{1,63}(?:\.[a-z0-9-]{1,63})+$/D', $host) === 1 && preg_match('/^[0-9.]+$/D', $host) !== 1) {
            $labels = explode('.', $host);

            // Do domínio completo até o de dois rótulos (o navegador recusa sozinho um sufixo público).
            for ($i = 0; $i <= count($labels) - 2 && $i < 4; $i++) {
                $domains[] = implode('.', array_slice($labels, $i));
            }
        }

        $lines = [];

        foreach ($present as $name) {
            $name = (string)$name;

            // O nome volta para um cabeçalho: só caracteres de nome de cookie comum.
            if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $name) !== 1 || in_array($name, $keep, true)) {
                continue;
            }

            $wanted = false;

            foreach ($patterns as $pattern) {
                if (is_string($pattern) && self::nameMatches($name, $pattern)) {
                    $wanted = true;

                    break;
                }
            }

            if (!$wanted) {
                continue;
            }

            foreach ($domains as $domain) {
                if (count($lines) >= self::MAX_EXPIRE_LINES) {
                    return $lines;
                }

                $lines[] = $name . '=; Max-Age=0; Path=/' . ($domain !== '' ? '; Domain=' . $domain : '');
            }
        }

        return $lines;
    }
}
