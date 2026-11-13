<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @license   https://opensource.org/licenses/GPL-3.0
 *
 * KZ-04 (ADR 0086) — mapa de elementos de uma fonte Twig do tema.
 *
 * O canvas do KAZEditor-Theme precisa responder: "este titulo que eu cliquei
 * esta em que ponto do codigo, e o texto dele e fixo ou vem de uma variavel?".
 * Esta classe le UMA fonte Twig e devolve cada tag de abertura escrita nela,
 * com a posicao no codigo e a natureza do conteudo.
 *
 * Regras:
 *  - PURA: nenhum banco, sessao, request, rede, arquivo, cookie ou escrita.
 *  - NAO interpreta Twig nem HTML por completo: reconhece os delimitadores do
 *    Twig ({{ }}, {% %}, {# #}, verbatim) para nunca tocar dentro deles, e as
 *    tags de abertura no texto entre eles. Quem usa annotate() deve conferir
 *    que o Twig da fonte anotada e o mesmo da original (Twig::renderThemePage
 *    faz isso comparando os tokens) e descartar a anotacao se nao for.
 *  - O atributo inserido tem formato fechado: ` data-kz-el="CHAVE:N"`, com a
 *    chave allowlistada e N inteiro. Nenhum texto do tema entra nele.
 *  - O atributo e informativo, nao uma barreira: o autor do tema pode escrever
 *    um igual no proprio codigo. O editor confere chave, indice e tag.
 *
 * KZ-05 (ADR 0098) — atributos do elemento. Para cada tag mapeada, a classe
 * diz onde estao os VALORES de alguns atributos (href, target e rel de <a>;
 * alt de <img>; aria-label de qualquer tag) e se cada valor e fixo ou vem de
 * Twig. Quando a tag nao pode ser lida com seguranca (Twig solto entre os
 * atributos, atributo repetido, aspa fora de lugar), `attrs` e null e o editor
 * nao oferece nenhum campo de atributo para ela.
 */
namespace Reamur\System\Library\Theme;

final class ThemeSourceMap {
    /** Atributo que liga um elemento da pagina ao ponto do codigo que o gerou. */
    public const ATTR = 'data-kz-el';

    /** Teto de elementos anotados por fonte. */
    public const MAX_ELEMENTS = 4000;

    /** Tamanho maximo da expressao devolvida para um texto que vem de variavel. */
    public const MAX_EXPR = 120;

    /** Tags que nao viram elemento selecionavel (documento, metadados, codigo). */
    private const SKIP = ['html', 'head', 'body', 'meta', 'link', 'title', 'base', 'style', 'script', 'template', 'noscript'];

    /** Tags sem conteudo. */
    private const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];

    /**
     * Atributos cujo valor o editor conhece. `*` vale para qualquer tag.
     * Lista fechada: nenhum outro atributo viaja para o navegador.
     */
    private const ATTRS = [
        'a'   => ['href', 'target', 'rel'],
        'img' => ['alt'],
        '*'   => ['aria-label'],
    ];

    private const SPACE = " \t\n\r\f";

    /** Tags cujo conteudo e texto cru: nada dentro delas e tag. */
    private const RAW = ['style', 'script', 'textarea', 'title'];

    private const KEY = '/^[a-z0-9_\/-]{1,64}$/D';

    /** Quebras de linha como o <textarea> do editor as entrega (`\n`). */
    public static function normalize(string $source): string {
        return str_replace(["\r\n", "\r"], "\n", $source);
    }

    /**
     * Elementos da fonte, na ordem em que as tags de abertura aparecem.
     *
     * Posicoes em BYTES: `open` cobre a tag de abertura inteira, `at` e onde o
     * atributo entra (logo apos o nome da tag) e `text` cobre o conteudo quando
     * o elemento nao tem outro elemento dentro.
     *
     * `kind`: fixed (texto escrito no codigo), variable (so um `{{ ... }}`),
     * mixed (texto com Twig), empty (sem conteudo), void (tag sem conteudo
     * possivel) ou group (tem outros elementos dentro).
     *
     * `attrs` (KZ-05): atributos conhecidos escritos na tag, ou null quando a
     * tag nao pode ser lida com seguranca. Ver attributes().
     *
     * @return list<array{n:int,tag:string,at:int,open:array{0:int,1:int},text:?array{0:int,1:int},kind:string,expr:string,attrs:?list<array{name:string,kind:string,value:array{0:int,1:int},span:array{0:int,1:int},expr:string}>}>
     */
    public static function scan(string $source): array {
        $len = strlen($source);
        $out = [];
        $state = 'data';   // data | tag | comment | raw
        $quote = '';       // aspa aberta dentro de uma tag
        $rawName = '';     // tag de texto cru em andamento
        $svg = 0;          // profundidade dentro de <svg>
        $current = -1;     // elemento cuja tag de abertura esta sendo lida
        $closing = '';     // nome da tag de fechamento sendo lida
        $last = '';        // ultimo caractere util da tag em leitura (para `/>`)
        $pending = -1;     // elemento a espera de saber se e uma folha
        $twigIn = [];      // trechos Twig dentro do conteudo do elemento pendente
        $i = 0;

        while ($i < $len) {
            $c = $source[$i];

            // 1. Twig vale em qualquer estado: nada e inserido dentro dele.
            if ($c === '{' && $i + 1 < $len && ($source[$i + 1] === '{' || $source[$i + 1] === '%' || $source[$i + 1] === '#')) {
                $end = self::twigEnd($source, $i, $len);
                if ($pending >= 0 && $state === 'data') $twigIn[] = [$i, $end];
                $last = '}';
                $i = $end;
                continue;
            }

            if ($state === 'comment') {
                if ($c === '-' && substr_compare($source, '-->', $i, 3) === 0) { $state = 'data'; $i += 3; continue; }
                $i++;
                continue;
            }

            if ($state === 'raw') {
                if ($c === '<' && $i + 1 < $len && $source[$i + 1] === '/' && strncasecmp(substr($source, $i + 2, strlen($rawName)), $rawName, strlen($rawName)) === 0) {
                    $state = 'tag'; $quote = ''; $last = ''; $current = -1; $closing = $rawName; $rawName = '';
                    $i += 2 + strlen($closing);
                    continue;
                }
                $i++;
                continue;
            }

            if ($state === 'tag') {
                if ($quote !== '') {
                    if ($c === $quote) $quote = '';
                    $last = '"';
                } elseif ($c === '"' || $c === "'") {
                    $quote = $c;
                    $last = '"';
                } elseif ($c === '>') {
                    $state = 'data';
                    if ($current >= 0) {
                        $tag = $out[$current]['tag'];
                        $out[$current]['open'][1] = $i + 1;
                        $out[$current]['attrs'] = self::attributes($source, $out[$current]['at'], $i, $tag);
                        if ($last === '/' || in_array($tag, self::VOID, true)) {
                            $out[$current]['kind'] = 'void';
                        } elseif (in_array($tag, self::RAW, true)) {
                            $state = 'raw'; $rawName = $tag;
                        } else {
                            if ($tag === 'svg') $svg++;
                            $pending = $current; $twigIn = [];
                        }
                    } elseif ($closing !== '') {
                        if ($closing === 'svg' && $svg > 0) $svg--;
                    }
                    $current = -1; $closing = '';
                } elseif ($c !== ' ' && $c !== "\n" && $c !== "\t") {
                    $last = $c;
                }
                $i++;
                continue;
            }

            // 2. Texto: so interessa o inicio de uma tag.
            if ($c !== '<' || $i + 1 >= $len) { $i++; continue; }
            $next = $source[$i + 1];

            if ($next === '!' && substr_compare($source, '<!--', $i, 4) === 0) {
                $pending = -1;
                $state = 'comment'; $i += 4;
                continue;
            }
            if ($next === '!' || $next === '?') {
                $pending = -1;
                $state = 'tag'; $quote = ''; $last = ''; $current = -1; $closing = ''; $i += 2;
                continue;
            }
            if ($next === '/') {
                $nameLen = strspn($source, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789:-', $i + 2);
                if ($nameLen === 0) { $i++; continue; }
                $name = strtolower(substr($source, $i + 2, $nameLen));
                if ($pending >= 0) {
                    if ($out[$pending]['tag'] === $name) self::closeLeaf($out[$pending], $source, $i, $twigIn);
                    $pending = -1;
                }
                $state = 'tag'; $quote = ''; $last = ''; $current = -1; $closing = $name;
                $i += 2 + $nameLen;
                continue;
            }
            if (!ctype_alpha($next)) { $i++; continue; }

            $nameLen = strspn($source, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789:-', $i + 1);
            $name = strtolower(substr($source, $i + 1, $nameLen));
            $after = $i + 1 + $nameLen;
            $pending = -1;
            $state = 'tag'; $quote = ''; $last = ''; $closing = ''; $current = -1;
            // Nome de tag montado com Twig (<h{{ n }}>): nao da para apontar com seguranca.
            $dynamic = $after + 1 < $len && $source[$after] === '{' && ($source[$after + 1] === '{' || $source[$after + 1] === '%' || $source[$after + 1] === '#');
            if (!$dynamic && $svg === 0 && !in_array($name, self::SKIP, true) && count($out) < self::MAX_ELEMENTS) {
                $out[] = ['n' => count($out), 'tag' => $name, 'at' => $after, 'open' => [$i, $after], 'text' => null, 'kind' => 'group', 'expr' => '', 'attrs' => null];
                $current = count($out) - 1;
            } elseif (!$dynamic && in_array($name, self::RAW, true)) {
                // <style>/<script>/<title> nao viram elemento, mas o conteudo deles e pulado.
                $rawName = $name;
                $end = self::tagEnd($source, $after, $len);
                $state = 'raw';
                $i = $end;
                continue;
            } elseif (!$dynamic && $name === 'svg') {
                $svg++;
            }
            $i = $after;
        }
        return $out;
    }

    /**
     * Fonte com ` data-kz-el="CHAVE:N"` em cada tag de abertura mapeada.
     * Chave fora da gramatica devolve a fonte intacta.
     *
     * @param list<array{n:int,at:int}> $elements resultado de scan() sobre a MESMA fonte
     */
    public static function annotate(string $key, string $source, array $elements): string {
        if (preg_match(self::KEY, $key) !== 1 || $elements === []) return $source;
        $out = '';
        $from = 0;
        foreach ($elements as $el) {
            $at = (int)$el['at'];
            if ($at < $from || $at > strlen($source)) return $source;
            $out .= substr($source, $from, $at - $from) . ' ' . self::ATTR . '="' . $key . ':' . (int)$el['n'] . '"';
            $from = $at;
        }
        return $out . substr($source, $from);
    }

    /**
     * Mapa para o navegador. Posicoes em unidades UTF-16 (as de String em
     * JavaScript) sobre a fonte com quebras `\n`. `sig` identifica a fonte
     * medida: o editor so escreve quando o campo tem exatamente este conteudo.
     *
     * Cada item: [tag, kind, abre_ini, abre_fim, texto_ini, texto_fim, expr, atributos]
     * (texto_ini/texto_fim = -1 quando o elemento nao e folha).
     *
     * `atributos` (KZ-05) e null quando a tag nao pode ser lida com seguranca,
     * ou uma lista de [nome, kind, valor_ini, valor_fim, atributo_ini, atributo_fim, expr].
     *
     * @param list<array<string,mixed>> $elements resultado de scan() sobre a MESMA fonte
     * @return array{sig:string,items:list<array{0:string,1:string,2:int,3:int,4:int,5:int,6:string,7:?list<array{0:string,1:string,2:int,3:int,4:int,5:int,6:string}>}>}
     */
    public static function describe(string $source, array $elements): array {
        $wanted = [];
        foreach ($elements as $el) {
            $wanted[$el['open'][0]] = 0; $wanted[$el['open'][1]] = 0;
            if ($el['text'] !== null) { $wanted[$el['text'][0]] = 0; $wanted[$el['text'][1]] = 0; }
            foreach ($el['attrs'] ?? [] as $attr) {
                $wanted[$attr['value'][0]] = 0; $wanted[$attr['value'][1]] = 0;
                $wanted[$attr['span'][0]] = 0; $wanted[$attr['span'][1]] = 0;
            }
        }
        ksort($wanted);
        $ascii = preg_match('/[\x80-\xff]/', $source) !== 1;
        $prev = 0; $units = 0;
        foreach ($wanted as $byte => $_) {
            $units += $ascii ? $byte - $prev : self::utf16Length(substr($source, $prev, $byte - $prev));
            $wanted[$byte] = $units;
            $prev = $byte;
        }
        $items = [];
        foreach ($elements as $el) {
            $attrs = null;
            if (is_array($el['attrs'] ?? null)) {
                $attrs = [];
                foreach ($el['attrs'] as $attr) {
                    $attrs[] = [
                        $attr['name'], $attr['kind'],
                        $wanted[$attr['value'][0]], $wanted[$attr['value'][1]],
                        $wanted[$attr['span'][0]], $wanted[$attr['span'][1]],
                        $attr['expr'],
                    ];
                }
            }
            $items[] = [
                $el['tag'], $el['kind'], $wanted[$el['open'][0]], $wanted[$el['open'][1]],
                $el['text'] !== null ? $wanted[$el['text'][0]] : -1,
                $el['text'] !== null ? $wanted[$el['text'][1]] : -1,
                $el['expr'],
                $attrs,
            ];
        }
        return ['sig' => self::signature($source), 'items' => $items];
    }

    /** Assinatura de uma fonte: CRC-32 dos bytes UTF-8 + tamanho em unidades UTF-16. */
    public static function signature(string $source): string {
        return sprintf('%08x:%d', crc32($source), self::utf16Length($source));
    }

    /** Quantas unidades UTF-16 um texto UTF-8 ocupa (sem depender de mbstring). */
    private static function utf16Length(string $chunk): int {
        if ($chunk === '') return 0;
        return strlen($chunk) - (int)preg_match_all('/[\x80-\xBF]/', $chunk) + (int)preg_match_all('/[\xF0-\xF7]/', $chunk);
    }

    /**
     * Fecha um elemento folha: guarda o intervalo do conteudo e o classifica.
     *
     * @param array{n:int,tag:string,at:int,open:array{0:int,1:int},text:?array{0:int,1:int},kind:string,expr:string} $el
     * @param list<array{0:int,1:int}> $twigIn
     */
    private static function closeLeaf(array &$el, string $source, int $end, array $twigIn): void {
        $start = $el['open'][1];
        $el['text'] = [$start, $end];
        $raw = substr($source, $start, $end - $start);
        if ($twigIn === []) {
            $el['kind'] = trim($raw) === '' ? 'empty' : 'fixed';
            return;
        }
        $el['kind'] = 'mixed';
        if (count($twigIn) !== 1) return;
        [$a, $b] = $twigIn[0];
        if (trim(substr($source, $start, $a - $start) . substr($source, $b, $end - $b)) !== '') return;
        $expr = self::printed(substr($source, $a, $b - $a));
        if ($expr === null) return;
        $el['kind'] = 'variable';
        $el['expr'] = $expr;
    }

    /** Expressao de um `{{ ... }}` sozinho, pronta para mostrar; null se o trecho nao e isso. */
    private static function printed(string $piece): ?string {
        if (preg_match('/^\{\{-?~?\s*(.*?)\s*~?-?\}\}$/Ds', $piece, $m) !== 1) return null;
        $expr = (string)preg_replace('/\s+/', ' ', $m[1]);
        if (strlen($expr) > self::MAX_EXPR) $expr = substr($expr, 0, self::MAX_EXPR);
        // Corte no meio de um caractere multibyte nao pode virar JSON invalido.
        return preg_match('//u', $expr) === 1 ? $expr : '';
    }

    /**
     * Atributos conhecidos de UMA tag de abertura, entre o nome da tag ($i) e o
     * `>` que a fecha ($end, exclusivo). Posicoes em bytes na fonte.
     *
     * Cada item: `value` e o intervalo do valor (sem as aspas) e `span` vai do
     * nome do atributo ate depois da aspa final. `kind`:
     *  - fixed:    valor entre aspas, sem Twig;
     *  - variable: valor entre aspas com Twig (`expr` quando e um `{{ ... }}` so);
     *  - bare:     atributo sem valor ou com valor sem aspas (nao editavel).
     *
     * Devolve null (tag fechada para o editor) quando ha Twig solto entre os
     * atributos, um nome repetido, aspa fora do inicio de um valor, atributo
     * colado no anterior ou valor sem fim: em qualquer desses casos o que esta
     * escrito nao diz com certeza qual atributo a pagina vai ter.
     *
     * @return ?list<array{name:string,kind:string,value:array{0:int,1:int},span:array{0:int,1:int},expr:string}>
     */
    private static function attributes(string $source, int $i, int $end, string $tag): ?array {
        $wanted = array_merge(self::ATTRS[$tag] ?? [], self::ATTRS['*']);
        $found = [];
        $seen = [];
        while (true) {
            $gap = strspn($source, self::SPACE, $i, $end - $i);
            $i += $gap;
            if ($i >= $end) break;
            $c = $source[$i];
            if ($c === '/') { $i++; continue; }
            if ($gap === 0) return null;
            $nameLen = strcspn($source, self::SPACE . "=/>\"'<{", $i, $end - $i);
            if ($nameLen === 0) return null;
            $name = strtolower(substr($source, $i, $nameLen));
            if (isset($seen[$name])) return null;
            $seen[$name] = true;
            $start = $i;
            $i += $nameLen;

            $kind = 'bare';
            $value = [$i, $i];
            $expr = '';
            $j = $i + strspn($source, self::SPACE, $i, $end - $i);
            if ($j < $end && $source[$j] === '=') {
                $j++;
                $j += strspn($source, self::SPACE, $j, $end - $j);
                if ($j >= $end) return null;
                $q = $source[$j];
                if ($q === '"' || $q === "'") {
                    $k = $j + 1;
                    $twig = [];
                    while ($k < $end && $source[$k] !== $q) {
                        if (self::twigAt($source, $k, $end)) {
                            $stop = self::twigEnd($source, $k, $end);
                            $twig[] = [$k, $stop];
                            $k = $stop;
                            continue;
                        }
                        $k++;
                    }
                    if ($k >= $end) return null;
                    $value = [$j + 1, $k];
                    $kind = 'fixed';
                    if ($twig !== []) {
                        $kind = 'variable';
                        if (count($twig) === 1 && trim(substr($source, $j + 1, $twig[0][0] - $j - 1) . substr($source, $twig[0][1], $k - $twig[0][1])) === '') {
                            $expr = (string)self::printed(substr($source, $twig[0][0], $twig[0][1] - $twig[0][0]));
                        }
                    }
                    $i = $k + 1;
                } else {
                    $k = $j;
                    while ($k < $end && strpos(self::SPACE, $source[$k]) === false) {
                        if ($source[$k] === '"' || $source[$k] === "'") return null;
                        if (self::twigAt($source, $k, $end)) { $k = self::twigEnd($source, $k, $end); continue; }
                        $k++;
                    }
                    if ($k > $end) return null;
                    $value = [$j, $k];
                    $i = $k;
                }
            }
            if (in_array($name, $wanted, true)) {
                $found[] = ['name' => $name, 'kind' => $kind, 'value' => $value, 'span' => [$start, $i], 'expr' => $expr];
            }
        }
        return $found;
    }

    /** Ha um delimitador de abertura do Twig em $i? */
    private static function twigAt(string $source, int $i, int $len): bool {
        return $source[$i] === '{' && $i + 1 < $len && ($source[$i + 1] === '{' || $source[$i + 1] === '%' || $source[$i + 1] === '#');
    }

    /** Fim (exclusivo) do trecho Twig que comeca em $i. Sem fechamento: vai ate o fim da fonte. */
    private static function twigEnd(string $source, int $i, int $len): int {
        $kind = $source[$i + 1];
        if ($kind === '#') {
            $end = strpos($source, '#}', $i + 2);
            return $end === false ? $len : $end + 2;
        }
        $close = $kind === '{' ? '}' : '%';
        $j = $i + 2;
        $depth = 0; // como no lexer do Twig, o fechamento so vale fora de ( [ {
        while ($j < $len) {
            $c = $source[$j];
            if ($c === '"' || $c === "'") {
                $j++;
                while ($j < $len && $source[$j] !== $c) $j += ($source[$j] === '\\') ? 2 : 1;
                $j++;
                continue;
            }
            if ($depth === 0 && $c === $close && $j + 1 < $len && $source[$j + 1] === '}') { $j += 2; break; }
            if ($c === '(' || $c === '[' || $c === '{') $depth++;
            elseif (($c === ')' || $c === ']' || $c === '}') && $depth > 0) $depth--;
            $j++;
        }
        if ($j > $len) $j = $len;
        // {% verbatim %} ... {% endverbatim %}: o miolo e texto para o Twig e nao e mapeado.
        if ($kind === '%' && preg_match('/^\{%-?~?\s*(verbatim|raw)\s*~?-?%\}$/D', substr($source, $i, $j - $i), $m) === 1) {
            if (preg_match('/\{%-?~?\s*end' . $m[1] . '\s*~?-?%\}/', $source, $e, PREG_OFFSET_CAPTURE, $j) !== 1) return $len;
            return (int)$e[0][1] + strlen((string)$e[0][0]);
        }
        return $j;
    }

    /** Posicao logo apos o `>` que fecha a tag iniciada antes de $i (respeita aspas e Twig). */
    private static function tagEnd(string $source, int $i, int $len): int {
        $quote = '';
        while ($i < $len) {
            $c = $source[$i];
            if ($c === '{' && $i + 1 < $len && ($source[$i + 1] === '{' || $source[$i + 1] === '%' || $source[$i + 1] === '#')) { $i = self::twigEnd($source, $i, $len); continue; }
            if ($quote !== '') { if ($c === $quote) $quote = ''; }
            elseif ($c === '"' || $c === "'") $quote = $c;
            elseif ($c === '>') return $i + 1;
            $i++;
        }
        return $len;
    }
}
