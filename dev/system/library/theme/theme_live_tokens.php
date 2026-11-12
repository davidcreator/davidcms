<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @license   https://opensource.org/licenses/GPL-3.0
 *
 * KZ-03 (ADR 0085) — tokens ao vivo no canvas do KAZEditor-Theme.
 *
 * Duas perguntas que o painel "Design" precisa responder sem adivinhar:
 *
 *  1. Para cada design token, qual variavel CSS ele alimenta e qual valor CSS
 *     cada opcao produz? A resposta e DERIVADA de TokenRegistry::css(): esta
 *     classe nao repete tabelas de fontes, sombras ou densidade. Se o registro
 *     mudar, a previa imediata muda junto.
 *  2. Quais variaveis o PROPRIO tema declara (ex.: `--dj-bg: #1b1f24`) dentro
 *     de um <style> das suas fontes Twig, e quais delas tem valor simples o
 *     bastante para um controle visual (cor hex de 6 digitos ou medida em px)?
 *
 * Regras:
 *  - PURA: nenhum banco, sessao, request, rede, arquivo, cookie ou escrita.
 *  - Somente LEITURA das fontes: nenhuma fonte e alterada aqui. A gravacao de
 *    um novo valor e feita pelo editor no campo `templates[chave]` que ja
 *    existe, e passa pela validacao normal do tema.
 *  - Nomes e valores devolvidos obedecem a gramaticas fechadas (ver NAME,
 *    COLOR e LENGTH): nada livre chega ao painel.
 */
namespace Reamur\System\Library\Theme;

final class ThemeLiveTokens {
    /** Variaveis que as duas cores do modulo Geral alimentam (ThemePreviewState::colorVars). */
    public const GENERAL = ['primary' => '--theme-primary', 'surface' => '--theme-surface'];

    /** Teto de variaveis do tema devolvidas ao painel. */
    public const MAX_VARS = 96;

    private const NAME = '--[a-z][a-z0-9-]{0,47}';
    private const COLOR = '/^#[0-9a-fA-F]{6}$/D';
    private const LENGTH = '/^(\d{1,4}(?:\.\d{1,3})?)px$/D';

    /**
     * Mapa token -> variavel CSS e valores, derivado de TokenRegistry::css().
     *
     * @return array<string,array{var:string,kind:string,group:string,type:string,suffix?:string,map?:array<string,string>,min?:int|float,max?:int|float,step?:string,unit?:string,options?:list<string>}>
     */
    public static function spec(TokenRegistry $registry): array {
        $defaults = $registry->defaults();
        $base = self::declarations($registry->css($defaults));
        $out = [];
        foreach ($registry->fields() as $key => $meta) {
            $type = (string)$meta['type'];
            $probes = self::probes($meta, (string)$defaults[$key]);
            $var = '';
            $values = [];
            foreach ($probes as $probe) {
                $now = self::declarations($registry->css(array_replace($defaults, [$key => $probe])));
                foreach ($now as $name => $value) {
                    if (($base[$name] ?? null) === $value) continue;
                    // Um token alimenta exatamente uma variavel; outra coisa e erro de registro.
                    if ($var !== '' && $var !== $name) continue 3;
                    $var = $name;
                }
                if ($var !== '') $values[$probe] = $now[$var];
            }
            if ($var === '') continue;
            $values[(string)$defaults[$key]] = $base[$var];
            $entry = ['var' => $var, 'group' => (string)$meta['group'], 'type' => $type];
            if ($type === 'color') {
                $entry['kind'] = 'color';
            } elseif ($type === 'enum' || count($probes) > 2) {
                // Tabela fechada: cada opcao ja vem com o valor CSS pronto.
                ksort($values, SORT_NATURAL);
                $entry['kind'] = 'map';
                $entry['map'] = $values;
            } else {
                $suffix = self::suffix($values);
                if ($suffix === null) continue;
                $entry['kind'] = 'number';
                $entry['suffix'] = $suffix;
            }
            if ($type === 'enum') $entry['options'] = array_values(array_map('strval', (array)$meta['options']));
            if ($type === 'int' || $type === 'float') {
                $entry['min'] = $meta['min'];
                $entry['max'] = $meta['max'];
                $entry['step'] = $type === 'float' ? '0.01' : '1';
            }
            if (isset($meta['unit'])) $entry['unit'] = (string)$meta['unit'];
            $out[(string)$key] = $entry;
        }
        return $out;
    }

    /**
     * Variaveis que o tema declara em blocos <style> das suas fontes, na ordem
     * em que aparecem. `index` e a posicao (a partir de 0) daquela declaracao
     * entre as declaracoes do MESMO nome na MESMA fonte: e o que o editor usa
     * para reescrever exatamente aquele valor.
     *
     * @param array<string,mixed> $templates fontes do tema, por chave allowlistada
     * @return list<array{template:string,name:string,index:int,value:string,type:string,scope:string}>
     */
    public static function styleVars(array $templates): array {
        $out = [];
        foreach ($templates as $key => $source) {
            if (!is_string($key) || !in_array($key, ThemePackage::TEMPLATE_KEYS, true)) continue;
            if (!is_string($source) || $source === '' || strlen($source) > 131072) continue;
            if (preg_match_all('~<style\b[^>]*>(.*?)</style\s*>~is', $source, $blocks) < 1) continue;
            $seen = [];
            foreach ($blocks[1] as $css) {
                // Comentarios saem antes: uma declaracao comentada nao e uma declaracao.
                $css = (string)preg_replace_callback('~/\*.*?\*/~s', static fn(array $m): string => str_repeat(' ', strlen($m[0])), $css);
                if (preg_match_all('~(?<![\w-])(' . self::NAME . ')\s*:\s*([^;{}]*?)\s*(?=[;}])~', $css, $hits, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) continue;
                foreach ($hits as $hit) {
                    $name = $hit[1][0];
                    $index = $seen[$name] ?? 0;
                    $seen[$name] = $index + 1;
                    $value = $hit[2][0];
                    $type = preg_match(self::COLOR, $value) === 1 ? 'color' : (preg_match(self::LENGTH, $value) === 1 ? 'length' : '');
                    if ($type === '') continue;
                    $out[] = [
                        'template' => $key, 'name' => $name, 'index' => $index,
                        'value' => $type === 'color' ? strtolower($value) : $value,
                        'type' => $type, 'scope' => self::scope($css, (int)$hit[0][1]),
                    ];
                    if (count($out) >= self::MAX_VARS) return $out;
                }
            }
        }
        return $out;
    }

    /**
     * Cores de token vindas do formulario, limpas para medir contraste mesmo
     * quando o resto do formulario ainda esta invalido.
     *
     * @param array<string,mixed> $raw
     * @return array<string,string>
     */
    public static function colors(TokenRegistry $registry, array $raw): array {
        $out = [];
        foreach ($registry->fields() as $key => $meta) {
            if ($meta['type'] !== 'color') continue;
            $value = $raw[$key] ?? null;
            if (is_string($value) && preg_match(self::COLOR, $value) === 1) $out[(string)$key] = strtolower($value);
        }
        return $out;
    }

    /** @return array<string,string> nome da variavel => valor */
    private static function declarations(string $css): array {
        $out = [];
        foreach (explode(';', $css) as $part) {
            $at = strpos($part, ':');
            if ($at === false) continue;
            $out[trim(substr($part, 0, $at))] = trim(substr($part, $at + 1));
        }
        return $out;
    }

    /**
     * Valores de sondagem de um campo: todas as opcoes de um enum, todos os
     * inteiros de uma faixa curta (tabela), ou os dois extremos de uma faixa.
     *
     * @param array<string,mixed> $meta
     * @return list<string>
     */
    private static function probes(array $meta, string $default): array {
        switch ((string)$meta['type']) {
            case 'enum':
                return array_values(array_filter(array_map('strval', (array)$meta['options']), static fn(string $v): bool => $v !== $default));
            case 'color':
                return [$default === '#010203' ? '#030201' : '#010203'];
            case 'int':
                $min = (int)$meta['min'];
                $max = (int)$meta['max'];
                $all = $max - $min <= 8 && !isset($meta['unit']) ? range($min, $max) : [$min, $max];
                return array_values(array_filter(array_map('strval', $all), static fn(string $v): bool => $v !== $default));
            case 'float':
                $d = (int)($meta['decimals'] ?? 2);
                return array_values(array_filter(
                    [number_format((float)$meta['min'], $d, '.', ''), number_format((float)$meta['max'], $d, '.', '')],
                    static fn(string $v): bool => $v !== $default
                ));
        }
        return [];
    }

    /**
     * Sufixo comum (ex.: "px") quando todo valor CSS e "<entrada><sufixo>".
     *
     * @param array<string,string> $values entrada => valor CSS
     */
    private static function suffix(array $values): ?string {
        $suffix = null;
        foreach ($values as $input => $css) {
            $input = (string)$input;
            if (!str_starts_with($css, $input)) return null;
            $rest = substr($css, strlen($input));
            if ($suffix !== null && $suffix !== $rest) return null;
            $suffix = $rest;
        }
        return $suffix !== null && preg_match('/^[a-z%]{0,4}$/D', $suffix) === 1 ? $suffix : null;
    }

    /** Seletor (ou at-rule) que contem a declaracao, so para rotular no painel. */
    private static function scope(string $css, int $at): string {
        $open = strrpos(substr($css, 0, $at), '{');
        if ($open === false) return '';
        $head = substr($css, 0, $open);
        $from = max((int)strrpos($head, '}'), (int)strrpos($head, '{'), (int)strrpos($head, ';'));
        $scope = trim((string)preg_replace('/\s+/', ' ', substr($head, $from === 0 ? 0 : $from + 1)));
        if (preg_match('/^[\w\s.#:,>+~*()\[\]="\'@-]{1,80}$/D', $scope) !== 1) return '';
        return $scope;
    }
}
