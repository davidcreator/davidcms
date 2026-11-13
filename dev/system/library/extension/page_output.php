<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * EX-01 (ADR 0095): o que as extensões pedem para sair numa página do site
 * público. A extensão NÃO escreve HTML: pede "uma meta tag", "um script deste
 * endereço" ou "este script embutido", e quem escreve a tag — com escape e
 * com o nonce da política de conteúdo (ADR 0093) — é o CMS, em render().
 *
 * Ter um ponto só por onde todo script de extensão passa é o que permite, na
 * etapa seguinte (aviso de cookies), segurar os scripts até haver consentimento.
 *
 * Um pedido recusado devolve false e fica em rejected(), com o motivo; o
 * cabeçalho do site grava essas linhas no log.
 */
namespace Reamur\System\Library\Extension;

use Reamur\System\Library\Security\Csp;

final class PageOutput {
    /** Dentro do <head>. */
    public const HEAD = 'head';

    /** Logo depois de <body>. O fim do <body> fica num trecho que o tema pode redefinir e não é oferecido. */
    public const BODY_START = 'body_start';

    public const MAX_ITEMS  = 60;
    public const MAX_INLINE = 20000;

    /** @var array<string, list<array<string, mixed>>> posição => itens */
    private array $items = [self::HEAD => [], self::BODY_START => []];

    /** @var list<string> */
    private array $rejected = [];

    private int $count = 0;

    private string $actor = '';

    /** @var list<string> origens de script que a extensão da vez declarou */
    private array $scriptSources = [];

    public function __construct(private string $route = '', private string $language = 'en-us') {}

    /** Rota da página que está sendo montada (ex.: common/home, blog/blog.info). */
    public function route(): string {
        return $this->route;
    }

    /** Idioma da página (ex.: pt-br). */
    public function language(): string {
        return $this->language;
    }

    /**
     * Uso do Manager: de quem são os próximos pedidos e quais origens de
     * script essa extensão declarou no manifesto.
     *
     * @param list<string> $scriptSources
     */
    public function actingAs(string $code, array $scriptSources = []): void {
        $this->actor         = $code;
        $this->scriptSources = array_values(array_filter($scriptSources, 'is_string'));
    }

    /** `<meta name="..." content="...">` no <head>. */
    public function meta(string $name, string $content): bool {
        return $this->addMeta('name', $name, $content);
    }

    /** `<meta property="..." content="...">` no <head> (Open Graph e afins). */
    public function property(string $property, string $content): bool {
        return $this->addMeta('property', $property, $content);
    }

    /**
     * `<script src="...">`. O endereço tem de ser https e estar coberto por
     * uma origem `script-src` do manifesto da extensão; senão o navegador o
     * bloquearia de qualquer modo, e o pedido é recusado aqui, com aviso no log.
     *
     * @param array{position?: string, async?: bool, defer?: bool, id?: string, integrity?: string, crossorigin?: string, data?: array<string, string>} $options
     */
    public function scriptSrc(string $url, array $options = []): bool {
        $position = (string)($options['position'] ?? self::HEAD);

        if (!$this->room($position, 'scriptSrc')) {
            return false;
        }

        if (array_diff(array_keys($options), ['position', 'async', 'defer', 'id', 'integrity', 'crossorigin', 'data']) !== []) {
            return $this->reject('scriptSrc: unknown option');
        }

        if (strlen($url) > 2000 || preg_match('~^https://[^\s"\'<>`\\\\]+$~D', $url) !== 1) {
            return $this->reject('scriptSrc: the address must be https://');
        }

        $covered = false;

        foreach ($this->scriptSources as $source) {
            if (Csp::covers($source, $url)) {
                $covered = true;

                break;
            }
        }

        if (!$covered) {
            return $this->reject('scriptSrc: ' . self::show($url) . ' is not covered by a script-src origin of the manifest');
        }

        $id = (string)($options['id'] ?? '');

        if ($id !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $id) !== 1) {
            return $this->reject('scriptSrc: invalid id');
        }

        $integrity = (string)($options['integrity'] ?? '');

        if ($integrity !== '' && preg_match('~^sha(?:256|384|512)-[A-Za-z0-9+/]+={0,2}(?: sha(?:256|384|512)-[A-Za-z0-9+/]+={0,2})*$~D', $integrity) !== 1) {
            return $this->reject('scriptSrc: invalid integrity');
        }

        $crossorigin = (string)($options['crossorigin'] ?? '');

        if (!in_array($crossorigin, ['', 'anonymous', 'use-credentials'], true)) {
            return $this->reject('scriptSrc: invalid crossorigin');
        }

        $data = [];

        foreach ((array)($options['data'] ?? []) as $name => $value) {
            if (!is_string($name) || preg_match('/^[a-z][a-z0-9-]{0,31}$/D', $name) !== 1 || !is_scalar($value) || strlen((string)$value) > 500) {
                return $this->reject('scriptSrc: invalid data attribute');
            }

            $data[$name] = (string)$value;
        }

        return $this->add($position, [
            'kind'        => 'src',
            'src'         => $url,
            'async'       => !empty($options['async']),
            'defer'       => !empty($options['defer']),
            'id'          => $id,
            'integrity'   => $integrity,
            'crossorigin' => $crossorigin,
            'data'        => $data,
        ]);
    }

    /**
     * `<script nonce="...">código</script>`. O código é o da extensão; um
     * valor que venha da configuração ou do visitante entra SEMPRE por js():
     *
     *     $page->script('window.exemplo = ' . PageOutput::js($id) . ';');
     */
    public function script(string $code, string $position = self::HEAD): bool {
        if (!$this->room($position, 'script')) {
            return false;
        }

        if (trim($code) === '' || strlen($code) > self::MAX_INLINE) {
            return $this->reject('script: empty or longer than ' . self::MAX_INLINE . ' bytes');
        }

        if (!mb_check_encoding($code, 'UTF-8')) {
            return $this->reject('script: not valid UTF-8');
        }

        // "</script" fecharia a tag no meio do código; "<!--" muda o modo de leitura do bloco.
        if (stripos($code, '</script') !== false || str_contains($code, '<!--')) {
            return $this->reject('script: the code may not contain "</script" or "<!--" (use PageOutput::js() for values)');
        }

        return $this->add($position, ['kind' => 'inline', 'code' => $code]);
    }

    /**
     * Um valor PHP como literal JavaScript, seguro dentro de `<script>`:
     * `<`, `>`, `&`, aspas e separadores de linha saem como `\uXXXX`.
     */
    public static function js(mixed $value): string {
        $json = json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return $json === false ? 'null' : $json;
    }

    /**
     * As tags de uma posição, prontas para o template (que as imprime sem
     * novo escape). `$nonce` é o da resposta (Csp::nonce()).
     */
    public function render(string $position, string $nonce): string {
        $e   = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $out = '';

        foreach ($this->items[$position] ?? [] as $item) {
            if ($item['kind'] === 'meta') {
                $out .= '<meta ' . $item['attr'] . '="' . $e($item['key']) . '" content="' . $e($item['content']) . '">' . "\n";
            } elseif ($item['kind'] === 'src') {
                $tag = '<script src="' . $e($item['src']) . '"';

                if ($item['id'] !== '') {
                    $tag .= ' id="' . $e($item['id']) . '"';
                }

                if ($item['integrity'] !== '') {
                    $tag .= ' integrity="' . $e($item['integrity']) . '"';
                }

                if ($item['crossorigin'] !== '') {
                    $tag .= ' crossorigin="' . $e($item['crossorigin']) . '"';
                }

                foreach ($item['data'] as $name => $value) {
                    $tag .= ' data-' . $name . '="' . $e($value) . '"';
                }

                $out .= $tag . ($item['async'] ? ' async' : '') . ($item['defer'] ? ' defer' : '') . '></script>' . "\n";
            } elseif ($item['kind'] === 'inline') {
                $out .= '<script nonce="' . $e($nonce) . '">' . $item['code'] . '</script>' . "\n";
            }
        }

        return $out;
    }

    /** Há algo a imprimir nesta posição? */
    public function has(string $position): bool {
        return !empty($this->items[$position]);
    }

    /** @return list<string> pedidos recusados, com a extensão e o motivo */
    public function rejected(): array {
        return $this->rejected;
    }

    private function addMeta(string $attr, string $key, string $content): bool {
        if (!$this->room(self::HEAD, 'meta')) {
            return false;
        }

        // Só name/property: http-equiv (que muda o comportamento da página) não é oferecido.
        if (preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,63}$/D', $key) !== 1) {
            return $this->reject('meta: invalid ' . $attr);
        }

        if ($content === '' || strlen($content) > 1024 || !mb_check_encoding($content, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/', $content) === 1) {
            return $this->reject('meta ' . self::show($key) . ': content empty, longer than 1024 bytes or with control characters');
        }

        return $this->add(self::HEAD, ['kind' => 'meta', 'attr' => $attr, 'key' => $key, 'content' => $content]);
    }

    private function room(string $position, string $what): bool {
        if (!isset($this->items[$position])) {
            return $this->reject($what . ': unknown position');
        }

        if ($this->count >= self::MAX_ITEMS) {
            return $this->reject($what . ': more than ' . self::MAX_ITEMS . ' items on one page');
        }

        return true;
    }

    /** @param array<string, mixed> $item */
    private function add(string $position, array $item): bool {
        $item['by']               = $this->actor;
        $this->items[$position][] = $item;
        $this->count++;

        return true;
    }

    private function reject(string $reason): bool {
        if (count($this->rejected) < 20) {
            $this->rejected[] = ($this->actor !== '' ? $this->actor : 'core') . ': ' . $reason;
        }

        return false;
    }

    private static function show(string $text): string {
        return substr((string)preg_replace('/[^A-Za-z0-9_.:\/-]/', '?', $text), 0, 120);
    }
}
