<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Motor declarativo de condicoes de tema (F12-01).
 *
 * OBJETIVO: permitir personalizar COMPLETAMENTE um tema publicado sem escrever
 * PHP, sem <script>, sem |raw e sem eval. O tema declara regras em JSON; cada
 * regra casa um contexto allowlisted e aplica overrides allowlisted (cores,
 * cena Three.js, variaveis Twig, flags e classes).
 *
 * GARANTIAS:
 *  - fail-closed: regra/operador/campo/valor invalido => regra IGNORADA, nunca
 *    excecao, nunca override parcial, nunca execucao de codigo;
 *  - nenhum eval, call_user_func, preg_match com padrao do usuario, arquivo,
 *    rede, cookie, sessao bruta ou dado sensivel;
 *  - limites de quantidade/profundidade/tamanho;
 *  - todo valor que chega ao Twig e escalar ou lista plana de escalares, e o
 *    autoescape html continua ligado;
 *  - Three.js so e ativado por cenas e slots allowlisted; o runtime continua
 *    sendo o unico self-hosted aprovado (scene-runner.js + data-scene).
 */
namespace Reamur\System\Library\Theme;

final class ConditionEngine {
    public const VERSION = 1;

    private const MAX_RULES  = 40;
    private const MAX_NODES  = 120;
    private const MAX_DEPTH  = 5;
    private const MAX_STRING = 256;
    private const MAX_ITEMS  = 20;
    private const MAX_VARS   = 40;

    /** Campos de contexto que uma regra pode consultar. */
    private const FIELDS = [
        'route', 'path', 'language', 'device', 'mobile', 'logged_in',
        'user_group', 'hour', 'weekday', 'date', 'month', 'variant',
        'https', 'ajax', 'query',
    ];

    /** Operadores permitidos; sem regex, sem expressoes, sem chamadas. */
    private const OPS = [
        'eq', 'neq', 'in', 'not_in', 'contains', 'starts_with', 'ends_with',
        'gt', 'gte', 'lt', 'lte', 'between', 'exists', 'empty', 'truthy', 'falsy',
    ];

    /** Cenas registradas em three/main.js — espelha a allowlist do runtime. */
    private const SCENES = ['', 'hero-particles', 'portfolio-3d', 'tools-3d', 'not-found'];

    /** Slots onde o shell confiavel pode emitir o container [data-scene]. */
    private const SLOTS = ['hero', 'home_top', 'footer_top'];

    /** Chaves aceitas em query.<chave>, sem cookies nem headers. */
    private const QUERY_KEYS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'ref', 'lang', 'theme', 'variant', 'view', 'page', 'debug',
    ];

    /** @return array<int,string> */
    public function fields(): array { return self::FIELDS; }
    /** @return array<int,string> */
    public function operators(): array { return self::OPS; }
    /** @return array<int,string> */
    public function scenes(): array { return self::SCENES; }
    /** @return array<int,string> */
    public function slots(): array { return self::SLOTS; }
    /** @return array<int,string> */
    public function queryKeys(): array { return self::QUERY_KEYS; }

    /** Contrato completo, usado pelo Admin e pela documentacao. */
    public function spec(): array {
        return [
            'version'    => self::VERSION,
            'max_rules'  => self::MAX_RULES,
            'max_nodes'  => self::MAX_NODES,
            'max_depth'  => self::MAX_DEPTH,
            'fields'     => self::FIELDS,
            'query_keys' => self::QUERY_KEYS,
            'operators'  => self::OPS,
            'groups'     => ['all', 'any', 'not'],
            'scenes'     => self::SCENES,
            'slots'      => self::SLOTS,
            'then'       => ['primary', 'surface', 'scene', 'three', 'vars', 'flags', 'classes', 'body_class', 'tokens'],
            'three'      => ['enabled', 'scene', 'count', 'color', 'speed', 'slot'],
            'limits'     => [
                'count' => [50, 800], 'speed' => [0.01, 0.20],
                'color' => '/^#[0-9a-fA-F]{6}$/', 'string' => self::MAX_STRING,
            ],
        ];
    }

    /** @param mixed $v */
    private function scalar($v): bool {
        return is_string($v) || is_int($v) || is_float($v) || is_bool($v);
    }

    /** @param mixed $v */
    private function safeScalar($v): bool {
        if (!$this->scalar($v)) return false;
        if (is_string($v)) {
            if (strlen($v) > self::MAX_STRING) return false;
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $v)) return false;
        }
        if (is_float($v) && !is_finite($v)) return false;
        return true;
    }

    private function safeName(string $v, string $pattern): bool {
        return $v !== '' && strlen($v) <= 48 && preg_match($pattern, $v) === 1;
    }

    /** @param mixed $v */
    private function boolish($v): bool {
        if (is_bool($v)) return $v;
        if (is_int($v) || is_float($v)) return (float)$v !== 0.0;
        if (is_string($v)) return in_array(strtolower(trim($v)), ['1', 'true', 'yes', 'on'], true);
        return false;
    }

    /** @param mixed $v @return array{0:bool,1:mixed} */
    private function sanitizeValue($v): array {
        if ($this->safeScalar($v)) return [true, $v];
        if (is_array($v)) {
            $out = [];
            $i = 0;
            foreach ($v as $item) {
                if ($i++ >= self::MAX_ITEMS || !$this->safeScalar($item)) return [false, null];
                $out[] = $item;
            }
            return [true, $out];
        }
        return [false, null];
    }

    /** Resolve um campo do contexto. Nunca acessa globais, arquivos ou objetos. */
    private function resolve(array $context, string $field): array {
        if (str_starts_with($field, 'query.')) {
            $key = substr($field, 6);
            if (!in_array($key, self::QUERY_KEYS, true)) return [false, null];
            $q = isset($context['query']) && is_array($context['query']) ? $context['query'] : [];
            if (!array_key_exists($key, $q)) return [false, null];
            $v = $q[$key];
            return $this->safeScalar($v) ? [true, $v] : [false, null];
        }
        if (!in_array($field, self::FIELDS, true)) return [false, null];
        if (!array_key_exists($field, $context)) return [false, null];
        $v = $context[$field];
        return $this->safeScalar($v) ? [true, $v] : [false, null];
    }

    /** @param mixed $left @param mixed $right */
    private function compare($left, string $op, $right): bool {
        $l = $this->scalar($left) ? (string)$left : '';
        switch ($op) {
            case 'exists':  return true; // tratado em evalNode antes
            case 'eq':      return $this->scalar($right) && $l === (string)$right;
            case 'neq':     return $this->scalar($right) && $l !== (string)$right;
            case 'in':      return is_array($right) && $this->listHas($right, $l);
            case 'not_in':  return is_array($right) && !$this->listHas($right, $l);
            case 'contains':
                if (is_array($right)) return $this->listHas($right, $l);
                return $this->scalar($right) && $right !== '' && str_contains($l, (string)$right);
            case 'starts_with': return $this->scalar($right) && str_starts_with($l, (string)$right);
            case 'ends_with':   return $this->scalar($right) && str_ends_with($l, (string)$right);
            case 'gt': case 'gte': case 'lt': case 'lte':
                if (!$this->scalar($right)) return false;
                $r = (string)$right;
                if (is_numeric($l) && is_numeric($r)) {
                    $a = (float)$l; $b = (float)$r;
                } else {
                    return $op === 'gt' ? strcmp($l, $r) > 0
                         : ($op === 'gte' ? strcmp($l, $r) >= 0
                         : ($op === 'lt' ? strcmp($l, $r) < 0 : strcmp($l, $r) <= 0));
                }
                return $op === 'gt' ? $a > $b : ($op === 'gte' ? $a >= $b : ($op === 'lt' ? $a < $b : $a <= $b));
            case 'between':
                if (!is_array($right) || count($right) !== 2) return false;
                $a = $right[0]; $b = $right[1];
                if (!$this->scalar($a) || !$this->scalar($b)) return false;
                if (!is_numeric((string)$a) || !is_numeric((string)$b)) return false;
                $lo = (float)min((float)$a, (float)$b); $hi = (float)max((float)$a, (float)$b);
                return is_numeric($l) && (float)$l >= $lo && (float)$l <= $hi;
            case 'truthy': return $this->boolish($left);
            case 'falsy':  return !$this->boolish($left);
            case 'empty':  return $l === '' || $l === '0' || strtolower($l) === 'false';
            default:       return false;
        }
    }

    private function listHas(array $list, string $needle): bool {
        foreach ($list as $item) {
            if ($this->scalar($item) && (string)$item === $needle) return true;
        }
        return false;
    }

    /** Valida um no (condicao ou grupo) e conta nos/profundidade. */
    private function checkNode($node, int $depth, int &$count): array {
        if ($depth > self::MAX_DEPTH || ++$count > self::MAX_NODES) return [['code' => 'node_limit', 'path' => 'when']];
        if (!is_array($node) || $node === []) return [['code' => 'node_invalid', 'path' => 'when']];

        $groups = array_intersect(['all', 'any', 'not'], array_keys($node));
        if (count($groups) === 1) {
            $kind = array_values($groups)[0];
            if (count($node) !== 1) return [['code' => 'group_extra_keys', 'path' => $kind]];
            $children = $node[$kind];
            if ($kind === 'not' && $this->isConditionShape($children)) $children = [$children];
            if (!is_array($children) || $children === []) return [['code' => 'group_empty', 'path' => $kind]];
            $errors = [];
            foreach ($children as $child) {
                foreach ($this->checkNode($child, $depth + 1, $count) as $e) $errors[] = $e;
            }
            return $errors;
        }
        if ($groups !== []) return [['code' => 'group_ambiguous', 'path' => 'when']];

        if (!isset($node['field']) || !is_string($node['field'])) return [['code' => 'field_missing', 'path' => 'when']];
        if (!isset($node['op']) || !is_string($node['op']) || !in_array($node['op'], self::OPS, true)) {
            return [['code' => 'op_invalid', 'path' => (string)$node['field']]];
        }
        $field = $node['field']; $op = $node['op'];
        if (str_starts_with($field, 'query.')) {
            if (!in_array(substr($field, 6), self::QUERY_KEYS, true)) return [['code' => 'field_invalid', 'path' => $field]];
        } elseif (!in_array($field, self::FIELDS, true)) {
            return [['code' => 'field_invalid', 'path' => $field]];
        }
        if (in_array($op, ['exists', 'truthy', 'falsy', 'empty'], true)) return [];
        if (!array_key_exists('value', $node)) return [['code' => 'value_missing', 'path' => $field]];
        [$ok, $value] = $this->sanitizeValue($node['value']);
        if (!$ok) return [['code' => 'value_invalid', 'path' => $field]];
        if (in_array($op, ['in', 'not_in'], true) && !is_array($value)) return [['code' => 'value_not_list', 'path' => $field]];
        if ($op === 'between' && (!is_array($value) || count($value) !== 2)) return [['code' => 'value_not_pair', 'path' => $field]];
        if (in_array($op, ['eq', 'neq', 'contains', 'starts_with', 'ends_with', 'gt', 'gte', 'lt', 'lte'], true) && is_array($value)) {
            return [['code' => 'value_not_scalar', 'path' => $field]];
        }
        return [];
    }

    private function isConditionShape($v): bool {
        return is_array($v) && isset($v['field']) && isset($v['op']);
    }

    /** Valida e saneia o bloco "then" de uma regra. */
    private function checkThen($then): array {
        if (!is_array($then) || $then === []) return [false, [], [['code' => 'then_invalid']]];
        $errors = []; $out = [];
        foreach ($then as $key => $value) {
            switch ($key) {
                case 'primary': case 'surface':
                    if (!is_string($value) || preg_match('/^#[0-9a-fA-F]{6}$/D', $value) !== 1) { $errors[] = ['code' => 'color_invalid', 'path' => $key]; break; }
                    $out[$key] = $value; break;
                case 'scene':
                    if (!is_string($value) || !in_array($value, self::SCENES, true)) { $errors[] = ['code' => 'scene_invalid', 'path' => $key]; break; }
                    $out[$key] = $value; break;
                case 'body_class':
                    if (!is_string($value) || !$this->safeName($value, '/^[a-zA-Z0-9_-]+$/')) { $errors[] = ['code' => 'class_invalid', 'path' => $key]; break; }
                    $out[$key] = $value; break;
                case 'vars':
                    if (!is_array($value)) { $errors[] = ['code' => 'vars_invalid', 'path' => $key]; break; }
                    $vars = []; $i = 0;
                    foreach ($value as $k => $v) {
                        $i++;
                        if ($i > self::MAX_VARS || !is_string($k) || !$this->safeName($k, '/^[a-z0-9_]+$/') || !$this->safeScalar($v)) {
                            $errors[] = ['code' => 'var_invalid', 'path' => (string)$k]; break;
                        }
                        $vars[$k] = $v;
                    }
                    $out['vars'] = $vars; break;
                case 'flags': case 'classes':
                    if (!is_array($value) || count($value) > self::MAX_ITEMS) { $errors[] = ['code' => 'list_invalid', 'path' => $key]; break; }
                    $list = [];
                    foreach ($value as $v) {
                        if (!is_string($v) || !$this->safeName($v, '/^[a-zA-Z0-9_-]+$/')) { $errors[] = ['code' => 'list_item_invalid', 'path' => $key]; break; }
                        $list[] = $v;
                    }
                    $out[$key] = array_values(array_unique($list)); break;
                case 'three':
                    if (!is_array($value)) { $errors[] = ['code' => 'three_invalid', 'path' => $key]; break; }
                    $three = [];
                    $three['enabled'] = $this->boolish($value['enabled'] ?? false);
                    $scene = (string)($value['scene'] ?? '');
                    if (!in_array($scene, self::SCENES, true)) { $errors[] = ['code' => 'three_scene_invalid', 'path' => 'three.scene']; break; }
                    $three['scene'] = $scene;
                    $slot = (string)($value['slot'] ?? 'hero');
                    if (!in_array($slot, self::SLOTS, true)) { $errors[] = ['code' => 'three_slot_invalid', 'path' => 'three.slot']; break; }
                    $three['slot'] = $slot;
                    $count = filter_var($value['count'] ?? 400, FILTER_VALIDATE_INT);
                    if ($count === false || $count < 50 || $count > 800) { $errors[] = ['code' => 'three_count_invalid', 'path' => 'three.count']; break; }
                    $three['count'] = (int)$count;
                    $color = (string)($value['color'] ?? '#8a4fff');
                    if (preg_match('/^#[0-9a-fA-F]{6}$/D', $color) !== 1) { $errors[] = ['code' => 'three_color_invalid', 'path' => 'three.color']; break; }
                    $three['color'] = $color;
                    $speed = $value['speed'] ?? 0.05;
                    if (!is_numeric($speed) || (float)$speed < 0.01 || (float)$speed > 0.20) { $errors[] = ['code' => 'three_speed_invalid', 'path' => 'three.speed']; break; }
                    $three['speed'] = (float)number_format((float)$speed, 2, '.', '');
                    $out['three'] = $three; break;
                case 'tokens':
                    if (!is_array($value) || $value === [] || !class_exists(TokenRegistry::class)) { $errors[] = ['code' => 'tokens_invalid', 'path' => 'tokens']; break; }
                    $patch = (new TokenRegistry())->normalize($value, true);
                    if ($patch === null || $patch === []) { $errors[] = ['code' => 'tokens_invalid', 'path' => 'tokens']; break; }
                    $out['tokens'] = $patch; break;
                default:
                    $errors[] = ['code' => 'then_key_invalid', 'path' => (string)$key]; break;
            }
        }
        return [$errors === [], $out, $errors];
    }

    /**
     * Valida o bloco "conditions" completo.
     * @return array<int,array{rule:int,code:string,path:string}>
     */
    public function validate(array $config): array {
        $version = $config['version'] ?? self::VERSION;
        if ((int)$version !== self::VERSION) return [['rule' => -1, 'code' => 'version_unsupported', 'path' => 'version']];
        $rules = $config['rules'] ?? [];
        if (!is_array($rules)) return [['rule' => -1, 'code' => 'rules_invalid', 'path' => 'rules']];
        if (count($rules) > self::MAX_RULES) return [['rule' => -1, 'code' => 'rules_limit', 'path' => 'rules']];

        $errors = []; $seen = []; $nodes = 0;
        foreach (array_values($rules) as $i => $rule) {
            if (!is_array($rule)) { $errors[] = ['rule' => $i, 'code' => 'rule_invalid', 'path' => 'rule']; continue; }
            $id = (string)($rule['id'] ?? '');
            if (!$this->safeName($id, '/^[a-z0-9_-]+$/')) { $errors[] = ['rule' => $i, 'code' => 'id_invalid', 'path' => 'id']; continue; }
            if (isset($seen[$id])) { $errors[] = ['rule' => $i, 'code' => 'id_duplicate', 'path' => $id]; continue; }
            $seen[$id] = true;
            foreach ($this->checkNode($rule['when'] ?? null, 0, $nodes) as $e) {
                $errors[] = ['rule' => $i, 'code' => $e['code'], 'path' => $id . ':' . $e['path']];
            }
            [$ok, , $thenErrors] = $this->checkThen($rule['then'] ?? null);
            if (!$ok) foreach ($thenErrors as $e) $errors[] = ['rule' => $i, 'code' => $e['code'], 'path' => $id . ':' . $e['path']];
        }
        if ($nodes > self::MAX_NODES) $errors[] = ['rule' => -1, 'code' => 'nodes_limit', 'path' => 'when'];
        return $errors;
    }

    /**
     * Valida/normaliza o bloco "three" de base do tema (fora de regras).
     * Compartilhado por Admin (POST) e Catalog (startup): uma unica fonte de
     * verdade para limites de cena, cor, contagem, velocidade e slot.
     * @return array|null null quando invalido
     */
    public function normalizeThree(array $raw): ?array {
        [, $then, $errors] = $this->checkThen(['three' => $raw]);
        return $errors === [] ? ($then['three'] ?? null) : null;
    }

    /** Mantem apenas regras integras; usado antes de persistir e de avaliar. */
    public function normalize(array $config): array {
        $errors = $this->validate($config);
        $bad = [];
        foreach ($errors as $e) if ($e['rule'] >= 0) $bad[$e['rule']] = true;
        $rules = [];
        foreach (array_values((array)($config['rules'] ?? [])) as $i => $rule) {
            if (isset($bad[$i]) || !is_array($rule)) continue;
            [, $then, ] = $this->checkThen($rule['then'] ?? null);
            $rules[] = ['id' => (string)$rule['id'], 'when' => $rule['when'], 'then' => $then];
        }
        return ['version' => self::VERSION, 'rules' => $rules, 'errors' => $errors];
    }

    /** Avaliacao recursiva; qualquer duvida resulta em false. */
    private function evalNode($node, array $context, int $depth): bool {
        if ($depth > self::MAX_DEPTH || !is_array($node) || $node === []) return false;
        foreach (['all', 'any', 'not'] as $kind) {
            if (!array_key_exists($kind, $node)) continue;
            $children = $node[$kind];
            if ($kind === 'not' && $this->isConditionShape($children)) $children = [$children];
            if (!is_array($children) || $children === []) return false;
            if ($kind === 'not') return !$this->evalNode(['all' => $children], $context, $depth + 1);
            foreach ($children as $child) {
                $r = $this->evalNode($child, $context, $depth + 1);
                if ($kind === 'all' && !$r) return false;
                if ($kind === 'any' && $r) return true;
            }
            return $kind === 'all';
        }
        $field = (string)($node['field'] ?? '');
        $op = (string)($node['op'] ?? '');
        if (!in_array($op, self::OPS, true)) return false;
        [$found, $value] = $this->resolve($context, $field);
        if ($op === 'exists') return $found;
        if (!$found) return $op === 'empty' || $op === 'falsy';
        [$ok, $right] = $this->sanitizeValue($node['value'] ?? null);
        if (!$ok && !in_array($op, ['truthy', 'falsy', 'empty'], true)) return false;
        return $this->compare($value, $op, $right);
    }

    /**
     * Avalia as regras e devolve APENAS overrides seguros.
     * @return array{primary:?string,surface:?string,scene:?string,three:?array,vars:array,flags:array,classes:array,matched:array,ignored:array}
     */
    public function evaluate(array $rules, array $context): array {
        $result = [
            'primary' => null, 'surface' => null, 'scene' => null, 'three' => null,
            'vars' => [], 'flags' => [], 'classes' => [], 'tokens' => [], 'matched' => [], 'ignored' => [],
        ];
        foreach (array_slice($rules, 0, self::MAX_RULES) as $rule) {
            if (!is_array($rule) || !isset($rule['when'], $rule['then'])) { $result['ignored'][] = 'rule_invalid'; continue; }
            [$ok, $then, ] = $this->checkThen($rule['then']);
            if (!$ok) { $result['ignored'][] = (string)($rule['id'] ?? 'then_invalid'); continue; }
            if (!$this->evalNode($rule['when'], $context, 0)) continue;
            $result['matched'][] = (string)($rule['id'] ?? '');
            foreach (['primary', 'surface', 'scene'] as $k) {
                if (array_key_exists($k, $then)) $result[$k] = $then[$k];
            }
            if (isset($then['three'])) $result['three'] = $then['three'];
            if (isset($then['tokens'])) $result['tokens'] = array_replace($result['tokens'], $then['tokens']);
            if (isset($then['vars'])) $result['vars'] = array_replace($result['vars'], $then['vars']);
            foreach (['flags', 'classes'] as $k) {
                if (isset($then[$k])) $result[$k] = array_values(array_unique(array_merge($result[$k], $then[$k])));
            }
            if (isset($then['body_class'])) $result['classes'][] = $then['body_class'];
            $result['classes'] = array_values(array_unique($result['classes']));
        }
        return $result;
    }
}
