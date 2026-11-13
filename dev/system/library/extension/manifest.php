<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * EX-01 (ADR 0095): a descrição de uma extensão — o arquivo extension.json na
 * pasta dela. É o ÚNICO arquivo da extensão que o CMS lê enquanto ela está
 * desligada: nenhum PHP da pasta roda para listar, mostrar ou configurar.
 *
 * A leitura é estrita de propósito: chave desconhecida, tipo errado ou valor
 * fora do formato recusam o arquivo inteiro, com a lista dos problemas. Um
 * manifesto aceito pela metade ligaria uma extensão diferente da que a tela
 * mostrou a quem a ligou.
 *
 * Guia para quem escreve extensão: docs/guias/Guia-Extensoes.md.
 */
namespace Reamur\System\Library\Extension;

use Reamur\System\Engine\Autoloader;
use Reamur\System\Library\Security\Csp;

final class Manifest {
    public const FILE      = 'extension.json';
    public const ENTRY     = 'extension.php';
    public const SCHEMA    = 1;
    public const MAX_BYTES = 65536;

    /** Aplicações em que uma extensão pode pedir para rodar. */
    public const APPS = ['catalog', 'admin'];

    /** Tipos de campo do formulário de configuração. */
    public const TYPES = ['text', 'textarea', 'checkbox', 'select', 'secret'];

    public const MAX_SETTINGS = 40;
    public const MAX_EVENTS   = 40;
    public const MAX_OPTIONS  = 50;
    public const MAX_SOURCES  = 20;

    private const KEYS          = ['schema', 'code', 'name', 'description', 'version', 'author', 'link', 'requires', 'apps', 'settings', 'events', 'csp'];
    private const SETTING_KEYS  = ['key', 'type', 'label', 'help', 'placeholder', 'default', 'required', 'max', 'pattern', 'options'];
    private const EVENT_KEYS    = ['trigger', 'handler', 'priority'];
    private const DEFAULT_MAX   = ['text' => 255, 'textarea' => 2000, 'secret' => 255];
    private const HARD_MAX      = 4000;

    /** @var array<string, mixed> */
    private array $data;

    /** @param array<string, mixed> $data já conferido por parse() */
    private function __construct(array $data) {
        $this->data = $data;
    }

    // ── nome da pasta, classe ───────────────────────────────────────────────

    /**
     * Código de extensão = nome da pasta. Minúsculas, dígitos e `_`, de 2 a 32
     * caracteres, e precisa voltar igual depois de passar pelo namespace
     * (`site_verification` → `SiteVerification` → `site_verification`); senão
     * o autoload não acharia as demais classes da extensão.
     */
    public static function isCode(string $code): bool {
        if (strlen($code) < 2 || strlen($code) > 32 || preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/D', $code) !== 1) {
            return false;
        }

        return Autoloader::toSnake(self::studly($code)) === $code;
    }

    /** Classe de entrada da extensão: `Reamur\Extension\<Codigo>\Extension`. */
    public static function className(string $code): string {
        return 'Reamur\\Extension\\' . self::studly($code) . '\\Extension';
    }

    private static function studly(string $code): string {
        return str_replace('_', '', ucwords($code, '_'));
    }

    // ── leitura ─────────────────────────────────────────────────────────────

    /**
     * Lê `<pasta das extensões>/<código>/extension.json`.
     *
     * @throws ManifestException
     */
    public static function read(string $directory, string $code): self {
        if (!self::isCode($code)) {
            throw new ManifestException(['folder name: must be 2-32 chars of a-z, 0-9 and _ (e.g. my_extension)']);
        }

        $file = rtrim($directory, '/\\') . '/' . $code . '/' . self::FILE;

        if (!is_file($file) || !is_readable($file)) {
            throw new ManifestException([self::FILE . ': file not found']);
        }

        $size = filesize($file);

        if ($size === false || $size > self::MAX_BYTES) {
            throw new ManifestException([self::FILE . ': larger than ' . self::MAX_BYTES . ' bytes']);
        }

        $json = file_get_contents($file);

        if ($json === false) {
            throw new ManifestException([self::FILE . ': could not be read']);
        }

        return self::parse($json, $code);
    }

    /**
     * Confere o texto de um extension.json. `$code` é o nome da pasta: o
     * campo "code" do arquivo tem de ser igual a ele.
     *
     * @throws ManifestException
     */
    public static function parse(string $json, string $code): self {
        if (strlen($json) > self::MAX_BYTES) {
            throw new ManifestException([self::FILE . ': larger than ' . self::MAX_BYTES . ' bytes']);
        }

        try {
            $raw = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ManifestException([self::FILE . ': not valid JSON (' . $e->getMessage() . ')']);
        }

        if (!is_array($raw) || ($raw !== [] && self::isList($raw))) {
            throw new ManifestException([self::FILE . ': must be a JSON object']);
        }

        $problems = [];
        $data     = [];

        foreach (array_keys($raw) as $key) {
            if (!in_array($key, self::KEYS, true)) {
                $problems[] = 'unknown key "' . self::show((string)$key) . '"';
            }
        }

        if (($raw['schema'] ?? null) !== self::SCHEMA) {
            $problems[] = 'schema: must be ' . self::SCHEMA;
        }

        if (!is_string($raw['code'] ?? null) || $raw['code'] !== $code) {
            $problems[] = 'code: must be the folder name ("' . self::show($code) . '")';
        }

        if (!self::isCode($code)) {
            $problems[] = 'code: must be 2-32 chars of a-z, 0-9 and _ (e.g. my_extension)';
        }

        $data['code'] = $code;

        $data['name'] = self::text($raw['name'] ?? null, 80);

        if ($data['name'] === null) {
            $problems[] = 'name: required; a text (max 80) or an object {"en-us": "...", "pt-br": "..."}';
        }

        $data['description'] = [];

        if (array_key_exists('description', $raw)) {
            $description = self::text($raw['description'], 400);

            if ($description === null) {
                $problems[] = 'description: a text (max 400) or an object by language';
            } else {
                $data['description'] = $description;
            }
        }

        $data['version'] = is_string($raw['version'] ?? null) ? $raw['version'] : '';

        if (preg_match('/^\d{1,5}\.\d{1,5}\.\d{1,5}(?:-[0-9A-Za-z.]{1,20})?$/D', $data['version']) !== 1) {
            $problems[] = 'version: required, like 1.0.0';
        }

        $data['author'] = '';

        if (array_key_exists('author', $raw)) {
            if (!is_string($raw['author']) || mb_strlen($raw['author']) > 80 || self::hasControl($raw['author'])) {
                $problems[] = 'author: a text of up to 80 chars';
            } else {
                $data['author'] = trim($raw['author']);
            }
        }

        $data['link'] = '';

        if (array_key_exists('link', $raw)) {
            if (!is_string($raw['link']) || strlen($raw['link']) > 200
                || preg_match('~^https://[A-Za-z0-9.-]+(?::[0-9]{1,5})?(?:/[A-Za-z0-9._\~!$&()*+,;=:@%/?#-]*)?$~D', $raw['link']) !== 1) {
                $problems[] = 'link: an https:// address of up to 200 chars';
            } else {
                $data['link'] = $raw['link'];
            }
        }

        $data['requires'] = self::requiresOf($raw['requires'] ?? [], $problems);
        $data['apps']     = self::appsOf($raw['apps'] ?? ['catalog'], $problems);
        $data['settings'] = self::settingsOf($raw['settings'] ?? [], $problems);
        $data['events']   = self::eventsOf($raw['events'] ?? [], $problems);
        $data['csp']      = self::cspOf($raw['csp'] ?? [], $problems);

        if ($problems !== []) {
            throw new ManifestException($problems);
        }

        return new self($data);
    }

    // ── o que o manifesto diz ───────────────────────────────────────────────

    public function code(): string {
        return (string)$this->data['code'];
    }

    public function name(string $language = 'en-us'): string {
        return self::pick($this->data['name'], $language);
    }

    public function description(string $language = 'en-us'): string {
        return self::pick($this->data['description'], $language);
    }

    public function version(): string {
        return (string)$this->data['version'];
    }

    public function author(): string {
        return (string)$this->data['author'];
    }

    public function link(): string {
        return (string)$this->data['link'];
    }

    /** @return list<string> subconjunto de APPS */
    public function apps(): array {
        return $this->data['apps'];
    }

    /** @return array<string, string> ex.: ['reamur' => '>=1.0.0', 'php' => '>=8.2'] */
    public function requires(): array {
        return $this->data['requires'];
    }

    /**
     * Campos de configuração, na ordem do arquivo.
     *
     * @return list<array{key:string, type:string, label:array<string,string>, help:array<string,string>, placeholder:string, default:string|bool, required:bool, max:int, pattern:string, options:list<array{value:string, label:array<string,string>}>}>
     */
    public function settings(): array {
        return $this->data['settings'];
    }

    /** @return list<string> chaves dos campos do tipo secret */
    public function secretKeys(): array {
        $keys = [];

        foreach ($this->settings() as $field) {
            if ($field['type'] === 'secret') {
                $keys[] = $field['key'];
            }
        }

        return $keys;
    }

    /** @return list<array{trigger:string, handler:string, priority:int}> */
    public function events(): array {
        return $this->data['events'];
    }

    /** @return array<string, list<string>> origens por diretiva, já conferidas por Csp::sources() */
    public function csp(): array {
        return $this->data['csp'];
    }

    /** Texto de um rótulo no idioma pedido; cai para en-us e depois para o primeiro que houver. */
    public static function pick(mixed $texts, string $language): string {
        if (!is_array($texts) || $texts === []) {
            return '';
        }

        foreach ([strtolower($language), '*', 'en-us'] as $key) {
            if (isset($texts[$key]) && is_string($texts[$key])) {
                return $texts[$key];
            }
        }

        $first = reset($texts);

        return is_string($first) ? $first : '';
    }

    /**
     * O que impede esta extensão de rodar nesta instalação. Lista vazia = pode.
     *
     * @return list<string> ex.: ['reamur >=2.0.0 (this site: 1.0.0)']
     */
    public function incompatibilities(string $cmsVersion, string $phpVersion = PHP_VERSION): array {
        $out = [];

        foreach (['reamur' => $cmsVersion, 'php' => $phpVersion] as $what => $actual) {
            $constraint = $this->data['requires'][$what] ?? '';

            if ($constraint !== '' && !self::satisfies($actual, $constraint)) {
                $out[] = $what . ' ' . $constraint . ' (this site: ' . $actual . ')';
            }
        }

        return $out;
    }

    /** `>=1.0.0 <2.0.0`: todas as comparações têm de valer. Sem operador = `>=`. */
    public static function satisfies(string $actual, string $constraint): bool {
        // Só o número conta: '8.2.32-1+deb' ou '1.0.0-rc1' comparam como 8.2.32 e 1.0.0.
        // As duas pontas viram X.Y.Z: para o version_compare do PHP, '8.3.0' é maior que '8.3'.
        $three  = static fn (string $v): string => implode('.', array_slice(array_merge(explode('.', $v), ['0', '0']), 0, 3));
        $actual = $three(preg_match('/^\d+(?:\.\d+){0,2}/', $actual, $m) === 1 ? $m[0] : '0');

        foreach (preg_split('/\s+/', trim($constraint), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (preg_match('/^(>=|<=|>|<|=)?(\d+(?:\.\d+){0,2})$/D', $part, $m) !== 1) {
                return false;
            }

            $operator = $m[1] !== '' ? $m[1] : '>=';

            if (!version_compare($actual, $three($m[2]), $operator === '=' ? '==' : $operator)) {
                return false;
            }
        }

        return true;
    }

    // ── valores de configuração ─────────────────────────────────────────────

    /**
     * Valores padrão dos campos que não são segredo.
     *
     * @return array<string, string|bool>
     */
    public function defaults(): array {
        $out = [];

        foreach ($this->settings() as $field) {
            if ($field['type'] !== 'secret') {
                $out[$field['key']] = $field['default'];
            }
        }

        return $out;
    }

    /**
     * Valores com que a extensão roda: o que está gravado, campo a campo, SE
     * ainda passa nas regras do manifesto; senão o padrão do campo. Chave que
     * o manifesto não declara é descartada. Assim o código da extensão pode
     * confiar que um valor com `pattern` obedece ao `pattern`, mesmo que o
     * manifesto tenha mudado depois de a configuração ser gravada.
     *
     * @param array<string, mixed> $stored
     * @return array<string, string|bool>
     */
    public function resolve(array $stored): array {
        $out = [];

        foreach ($this->settings() as $field) {
            if ($field['type'] === 'secret') {
                continue;
            }

            $key   = $field['key'];
            $value = $stored[$key] ?? null;

            if ($field['type'] === 'checkbox') {
                $out[$key] = is_bool($value) ? $value : $field['default'];

                continue;
            }

            $out[$key] = (is_string($value) && self::valueProblem($field, $value) === '') ? $value : $field['default'];
        }

        return $out;
    }

    /**
     * Confere o formulário de configuração enviado.
     *
     * Segredo: campo vazio mantém o que está gravado; `<chave>__clear` apaga.
     * O valor de um segredo nunca volta em `values`.
     *
     * @param array<string, mixed> $input      campos do POST
     * @param list<string>         $secretsSet chaves de segredo que já têm valor gravado
     * @return array{
     *   values: array<string, string|bool>,
     *   secrets: array<string, string|null>,
     *   errors: array<string, string>
     * } secrets: texto = gravar, null = apagar, ausente = manter. errors: chave => required|too_long|pattern|option
     */
    public function validate(array $input, array $secretsSet = []): array {
        $values  = [];
        $secrets = [];
        $errors  = [];

        foreach ($this->settings() as $field) {
            $key = $field['key'];
            $raw = $input[$key] ?? null;

            if ($field['type'] === 'checkbox') {
                $values[$key] = is_scalar($raw) && in_array((string)$raw, ['1', 'on', 'true'], true);

                continue;
            }

            $value = is_scalar($raw) ? (string)$raw : '';
            $value = str_replace(["\r\n", "\r"], "\n", $value);
            $value = $field['type'] === 'textarea' ? trim($value) : trim(str_replace("\n", ' ', $value));

            if ($field['type'] === 'secret') {
                $clear = is_scalar($input[$key . '__clear'] ?? null) && (string)$input[$key . '__clear'] === '1';

                if ($value !== '') {
                    $problem = self::valueProblem($field, $value);

                    if ($problem !== '') {
                        $errors[$key] = $problem;
                    } else {
                        $secrets[$key] = $value;
                    }
                } elseif ($clear) {
                    $secrets[$key] = null;

                    if ($field['required']) {
                        $errors[$key] = 'required';
                    }
                } elseif ($field['required'] && !in_array($key, $secretsSet, true)) {
                    $errors[$key] = 'required';
                }

                continue;
            }

            $values[$key] = $value;

            if ($value === '') {
                if ($field['required']) {
                    $errors[$key] = 'required';
                }

                continue;
            }

            $problem = self::valueProblem($field, $value);

            if ($problem !== '') {
                $errors[$key] = $problem;
            }
        }

        return ['values' => $values, 'secrets' => $secrets, 'errors' => $errors];
    }

    /**
     * Campos obrigatórios ainda sem valor, dado o que está gravado.
     *
     * @param array<string, mixed> $stored
     * @param list<string>         $secretsSet
     * @return list<string>
     */
    public function missing(array $stored, array $secretsSet = []): array {
        $resolved = $this->resolve($stored);
        $out      = [];

        foreach ($this->settings() as $field) {
            if (!$field['required']) {
                continue;
            }

            $empty = $field['type'] === 'secret'
                ? !in_array($field['key'], $secretsSet, true)
                : ($resolved[$field['key']] ?? '') === '';

            if ($empty) {
                $out[] = $field['key'];
            }
        }

        return $out;
    }

    /**
     * @param array{type:string, max:int, pattern:string, options:list<array{value:string, label:array<string,string>}>} $field
     * @return string '' = aceito; senão too_long | pattern | option
     */
    private static function valueProblem(array $field, string $value): string {
        if ($value === '') {
            return '';
        }

        if (self::hasControl($value, $field['type'] === 'textarea')) {
            return 'pattern';
        }

        if ($field['type'] === 'select') {
            foreach ($field['options'] as $option) {
                if ($option['value'] === $value) {
                    return '';
                }
            }

            return 'option';
        }

        if (mb_strlen($value) > $field['max']) {
            return 'too_long';
        }

        if ($field['pattern'] !== '' && preg_match(self::regex($field['pattern']), $value) !== 1) {
            return 'pattern';
        }

        return '';
    }

    private static function regex(string $pattern): string {
        return '~^(?:' . $pattern . ')$~Du';
    }

    // ── conferência das partes ──────────────────────────────────────────────

    /**
     * Texto simples ou objeto por idioma → mapa idioma => texto ('*' = sem idioma).
     *
     * @return array<string, string>|null
     */
    private static function text(mixed $value, int $max): ?array {
        if (is_string($value)) {
            $value = ['*' => $value];
        }

        if (!is_array($value) || $value === [] || count($value) > 20) {
            return null;
        }

        $out = [];

        foreach ($value as $language => $text) {
            if (!is_string($language) || ($language !== '*' && preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/D', $language) !== 1)) {
                return null;
            }

            if (!is_string($text) || trim($text) === '' || mb_strlen($text) > $max || self::hasControl($text)) {
                return null;
            }

            $out[$language] = trim($text);
        }

        return $out;
    }

    /** Lista JSON (chaves 0..n-1)? Escrito à mão: array_is_list() só existe do PHP 8.1 em diante. */
    private static function isList(array $value): bool {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    private static function hasControl(string $text, bool $allowNewline = false): bool {
        if (!mb_check_encoding($text, 'UTF-8')) {
            return true;
        }

        return preg_match($allowNewline ? '/[\x00-\x09\x0B-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/', $text) === 1;
    }

    /** Texto vindo do arquivo, seguro para aparecer numa mensagem de problema. */
    private static function show(string $text): string {
        return substr((string)preg_replace('/[^A-Za-z0-9_.:\/ -]/', '?', $text), 0, 60);
    }

    /**
     * @param list<string> $problems
     * @return array<string, string>
     */
    private static function requiresOf(mixed $raw, array &$problems): array {
        if (!is_array($raw) || ($raw !== [] && self::isList($raw))) {
            $problems[] = 'requires: must be an object like {"reamur": ">=1.0.0", "php": ">=8.2"}';

            return [];
        }

        $out = [];

        foreach ($raw as $what => $constraint) {
            if (!in_array($what, ['reamur', 'php'], true)) {
                $problems[] = 'requires: unknown key "' . self::show((string)$what) . '" (use reamur, php)';

                continue;
            }

            $valid = is_string($constraint) && trim($constraint) !== '' && strlen($constraint) <= 60;

            foreach ($valid ? (preg_split('/\s+/', trim((string)$constraint), -1, PREG_SPLIT_NO_EMPTY) ?: []) : [] as $part) {
                if (preg_match('/^(>=|<=|>|<|=)?\d+(?:\.\d+){0,2}$/D', $part) !== 1) {
                    $valid = false;
                }
            }

            if (!$valid) {
                $problems[] = 'requires.' . $what . ': use comparisons like ">=1.0.0" or ">=1.0.0 <2.0.0"';

                continue;
            }

            $out[$what] = trim((string)$constraint);
        }

        return $out;
    }

    /**
     * @param list<string> $problems
     * @return list<string>
     */
    private static function appsOf(mixed $raw, array &$problems): array {
        if (!is_array($raw) || $raw === [] || !self::isList($raw)) {
            $problems[] = 'apps: a non-empty list with "catalog" and/or "admin"';

            return ['catalog'];
        }

        $out = [];

        foreach ($raw as $app) {
            if (!is_string($app) || !in_array($app, self::APPS, true)) {
                $problems[] = 'apps: only "catalog" and "admin" are accepted';

                return ['catalog'];
            }

            if (!in_array($app, $out, true)) {
                $out[] = $app;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $problems
     * @return list<array<string, mixed>>
     */
    private static function settingsOf(mixed $raw, array &$problems): array {
        if (!is_array($raw) || ($raw !== [] && !self::isList($raw))) {
            $problems[] = 'settings: must be a list';

            return [];
        }

        if (count($raw) > self::MAX_SETTINGS) {
            $problems[] = 'settings: at most ' . self::MAX_SETTINGS . ' fields';

            return [];
        }

        $out  = [];
        $seen = [];

        foreach ($raw as $i => $field) {
            $at = 'settings[' . $i . ']';

            if (!is_array($field) || $field === [] || self::isList($field)) {
                $problems[] = $at . ': must be an object';

                continue;
            }

            $before = count($problems);

            foreach (array_keys($field) as $key) {
                if (!in_array($key, self::SETTING_KEYS, true)) {
                    $problems[] = $at . ': unknown key "' . self::show((string)$key) . '"';
                }
            }

            $key = $field['key'] ?? null;

            // `__` é reservado: "<chave>__clear" é o campo do formulário que apaga um segredo.
            if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,39}$/D', $key) !== 1 || str_contains($key, '__')) {
                $problems[] = $at . '.key: 1-40 chars of a-z, 0-9 and _, starting with a letter, without "__"';
                $key        = '';
            } elseif (isset($seen[$key])) {
                $problems[] = $at . '.key: "' . $key . '" is used twice';
            }

            $seen[$key] = true;

            $type = $field['type'] ?? null;

            if (!is_string($type) || !in_array($type, self::TYPES, true)) {
                $problems[] = $at . '.type: one of ' . implode(', ', self::TYPES);
                $type       = 'text';
            }

            $label = self::text($field['label'] ?? null, 120);

            if ($label === null) {
                $problems[] = $at . '.label: required; a text (max 120) or an object by language';
            }

            $help = [];

            if (array_key_exists('help', $field)) {
                $help = self::text($field['help'], 400);

                if ($help === null) {
                    $problems[] = $at . '.help: a text (max 400) or an object by language';
                }
            }

            $placeholder = '';

            if (array_key_exists('placeholder', $field)) {
                if (!is_string($field['placeholder']) || mb_strlen($field['placeholder']) > 120 || self::hasControl($field['placeholder'])
                    || !in_array($type, ['text', 'textarea'], true)) {
                    $problems[] = $at . '.placeholder: a text of up to 120 chars, for text and textarea only';
                } else {
                    $placeholder = $field['placeholder'];
                }
            }

            $required = false;

            if (array_key_exists('required', $field)) {
                if (!is_bool($field['required']) || $type === 'checkbox') {
                    $problems[] = $at . '.required: true or false; not used with checkbox';
                } else {
                    $required = $field['required'];
                }
            }

            $max = self::DEFAULT_MAX[$type] ?? 0;

            if (array_key_exists('max', $field)) {
                if (!is_int($field['max']) || $field['max'] < 1 || $field['max'] > self::HARD_MAX || !isset(self::DEFAULT_MAX[$type])) {
                    $problems[] = $at . '.max: a whole number from 1 to ' . self::HARD_MAX . ', for text, textarea and secret only';
                } else {
                    $max = $field['max'];
                }
            }

            $pattern = '';

            if (array_key_exists('pattern', $field)) {
                $p = $field['pattern'];

                if (!is_string($p) || $p === '' || strlen($p) > 200 || str_contains($p, '~') || self::hasControl($p)
                    || !isset(self::DEFAULT_MAX[$type]) || @preg_match(self::regex($p), '') === false) {
                    $problems[] = $at . '.pattern: a regular expression body of up to 200 chars without "~", for text, textarea and secret only';
                } else {
                    $pattern = $p;
                }
            }

            $options = [];

            if ($type === 'select') {
                $options = self::optionsOf($field['options'] ?? null, $at, $problems);
            } elseif (array_key_exists('options', $field)) {
                $problems[] = $at . '.options: only for type select';
            }

            $default = $type === 'checkbox' ? false : '';

            if (array_key_exists('default', $field)) {
                $d = $field['default'];

                if ($type === 'secret') {
                    $problems[] = $at . '.default: a secret has no default';
                } elseif ($type === 'checkbox') {
                    if (!is_bool($d)) {
                        $problems[] = $at . '.default: true or false';
                    } else {
                        $default = $d;
                    }
                } elseif (!is_string($d)) {
                    $problems[] = $at . '.default: a text';
                } else {
                    $default = $d;
                }
            }

            $entry = [
                'key'         => $key,
                'type'        => $type,
                'label'       => $label ?? [],
                'help'        => $help ?? [],
                'placeholder' => $placeholder,
                'default'     => $default,
                'required'    => $required,
                'max'         => $max,
                'pattern'     => $pattern,
                'options'     => $options,
            ];

            // O padrão passa pelas mesmas regras do valor digitado.
            if (count($problems) === $before && is_string($default) && $default !== '' && self::valueProblem($entry, $default) !== '') {
                $problems[] = $at . '.default: does not pass the rules of the field itself';
            }

            $out[] = $entry;
        }

        return $out;
    }

    /**
     * @param list<string> $problems
     * @return list<array{value:string, label:array<string,string>}>
     */
    private static function optionsOf(mixed $raw, string $at, array &$problems): array {
        if (!is_array($raw) || $raw === [] || !self::isList($raw) || count($raw) > self::MAX_OPTIONS) {
            $problems[] = $at . '.options: a list of 1 to ' . self::MAX_OPTIONS . ' {"value": "...", "label": "..."}';

            return [];
        }

        $out  = [];
        $seen = [];

        foreach ($raw as $j => $option) {
            $value = is_array($option) ? ($option['value'] ?? null) : null;
            $label = is_array($option) ? self::text($option['label'] ?? null, 120) : null;

            if (!is_array($option) || array_diff(array_keys($option), ['value', 'label']) !== []
                || !is_string($value) || $value === '' || strlen($value) > 64 || preg_match('/^[A-Za-z0-9_.-]+$/D', $value) !== 1
                || $label === null || isset($seen[$value])) {
                $problems[] = $at . '.options[' . $j . ']: {"value": 1-64 chars of A-Z a-z 0-9 _ . - (unique), "label": text}';

                continue;
            }

            $seen[$value] = true;
            $out[]        = ['value' => $value, 'label' => $label];
        }

        return $out;
    }

    /**
     * @param list<string> $problems
     * @return list<array{trigger:string, handler:string, priority:int}>
     */
    private static function eventsOf(mixed $raw, array &$problems): array {
        if (!is_array($raw) || ($raw !== [] && !self::isList($raw))) {
            $problems[] = 'events: must be a list';

            return [];
        }

        if (count($raw) > self::MAX_EVENTS) {
            $problems[] = 'events: at most ' . self::MAX_EVENTS . ' entries';

            return [];
        }

        $out  = [];
        $seen = [];

        foreach ($raw as $i => $event) {
            $at = 'events[' . $i . ']';

            if (!is_array($event) || $event === [] || self::isList($event) || array_diff(array_keys($event), self::EVENT_KEYS) !== []) {
                $problems[] = $at . ': an object with trigger, handler and (optional) priority';

                continue;
            }

            $trigger  = $event['trigger'] ?? null;
            $handler  = $event['handler'] ?? null;
            $priority = $event['priority'] ?? 0;

            if (!is_string($trigger) || strlen($trigger) > 128 || preg_match('~^[A-Za-z0-9_]+(?:/[A-Za-z0-9_]+)*$~D', $trigger) !== 1) {
                $problems[] = $at . '.trigger: an event name like page/output or order_add';

                continue;
            }

            // O prefixo "on" impede que o manifesto aponte para um método que
            // não foi escrito para ser ouvinte (construtor, métodos da classe base).
            if (!is_string($handler) || preg_match('/^on[A-Z][A-Za-z0-9]{0,60}$/D', $handler) !== 1) {
                $problems[] = $at . '.handler: a method name starting with "on", like onPageOutput';

                continue;
            }

            if (!is_int($priority) || $priority < -1000 || $priority > 1000) {
                $problems[] = $at . '.priority: a whole number from -1000 to 1000';

                continue;
            }

            if (isset($seen[$trigger . ' ' . $handler])) {
                $problems[] = $at . ': the same trigger and handler appear twice';

                continue;
            }

            $seen[$trigger . ' ' . $handler] = true;
            $out[]                           = ['trigger' => $trigger, 'handler' => $handler, 'priority' => $priority];
        }

        return $out;
    }

    /**
     * Origens de fora que a extensão pede. Quem decide o que é aceitável é a
     * mesma regra de REAMUR_CSP_SOURCES (Csp::sources): aqui, uma origem que
     * ela recusaria invalida o manifesto em vez de sumir em silêncio.
     *
     * @param list<string> $problems
     * @return array<string, list<string>>
     */
    private static function cspOf(mixed $raw, array &$problems): array {
        if (!is_array($raw) || ($raw !== [] && self::isList($raw))) {
            $problems[] = 'csp: must be an object like {"script-src": ["https://cdn.example.com"]}';

            return [];
        }

        $out = [];

        foreach ($raw as $directive => $sources) {
            if (!is_string($directive) || !in_array($directive, Csp::EXTRA_DIRECTIVES, true)) {
                $problems[] = 'csp: unknown directive "' . self::show((string)$directive) . '" (use ' . implode(', ', Csp::EXTRA_DIRECTIVES) . ')';

                continue;
            }

            if (!is_array($sources) || $sources === [] || !self::isList($sources) || count($sources) > self::MAX_SOURCES) {
                $problems[] = 'csp.' . $directive . ': a list of 1 to ' . self::MAX_SOURCES . ' https:// origins';

                continue;
            }

            foreach ($sources as $source) {
                $accepted = is_string($source) ? (Csp::sources([$directive => [$source]])[$directive] ?? []) : [];

                if ($accepted !== [$source]) {
                    $problems[] = 'csp.' . $directive . ': "' . self::show(is_string($source) ? $source : '?')
                        . '" is not accepted (only https://site or https://*.site, no keywords, no http)';

                    continue;
                }

                if (!in_array($source, $out[$directive] ?? [], true)) {
                    $out[$directive][] = $source;
                }
            }
        }

        return $out;
    }
}
