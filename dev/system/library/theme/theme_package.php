<?php declare(strict_types=1);
/**
 * F13-E — pacotes de tema versionaveis + child-theme.
 *
 * Nenhuma migration: `parent` e apenas uma chave allowlistada no JSON do
 * config (slug do tema pai). Nenhum arquivo e lido/escrito: export devolve
 * JSON na resposta e import recebe JSON no POST. Sem eval, call_user_func,
 * regex do usuario, IO, upload, codigo executavel ou HTML.
 *
 * A allowlist de templates passa a viver AQUI e o controller Admin a
 * referencia, para nao haver duas listas divergentes.
 */
namespace Reamur\System\Library\Theme;

final class ThemePackage {
    public const FORMAT = 'reamurcms-theme';
    public const VERSION = 1;
    public const MAX_BYTES = 524288;   // 512 KiB
    public const MAX_DEPTH = 5;
    public const TEMPLATE_KEYS = [
        'common/home', 'common/footer', 'common/contact',
        'partial/site_header', 'partial/hero', 'partial/hero-3d', 'partial/about',
        'partial/methodology', 'partial/skills', 'partial/services', 'partial/testimonials',
        'partial/portfolio', 'partial/tools', 'partial/news', 'partial/clients', 'partial/contact',
        'cms/page_info', 'catalog/project_list', 'catalog/project_info',
        'blog/blog_list', 'blog/blog_info', 'tool/tool_list', 'tool/tool_info',
        'checkout/quote', 'error/not_found',
    ];
    private const CONFIG_KEYS = ['primary','surface','scene','templates','conditions','three','tokens','parent','sections','components'];
    private const SCENES = ['', 'hero-particles', 'portfolio-3d', 'tools-3d', 'not-found'];
    private const MAP_KEYS = ['tokens','templates','three','components'];

    private ConditionEngine $engine;
    private TokenRegistry $tokens;
    private SectionRegistry $sections;
    private ComponentRegistry $components;

    public function __construct(ConditionEngine $engine, TokenRegistry $tokens, ?SectionRegistry $sections = null, ?ComponentRegistry $components = null) {
        $this->engine = $engine;
        $this->tokens = $tokens;
        $this->sections = $sections ?? new SectionRegistry();
        $this->components = $components ?? new ComponentRegistry();
    }

    /** Registro compartilhado com framework, controller Admin e snapshot. */
    public function sections(): SectionRegistry { return $this->sections; }
    public function components(): ComponentRegistry { return $this->components; }

    public function spec(): array {
        return ['format'=>self::FORMAT,'version'=>self::VERSION,'max_bytes'=>self::MAX_BYTES,
                'max_depth'=>self::MAX_DEPTH,'config_keys'=>self::CONFIG_KEYS,'template_keys'=>self::TEMPLATE_KEYS,
                'section_ids'=>$this->sections->ids(),'component_ids'=>$this->components->ids()];
    }

    /** @return array<string,mixed> */
    public function canonical(array $data): array {
        $out = $data;
        ksort($out);
        foreach ($out as $k => $v) if (is_array($v)) $out[$k] = $this->canonical($v);
        return $out;
    }

    public function checksum(array $config): string {
        return hash('sha256', (string)json_encode($this->canonical($config), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }

    /** Remove chaves fora da allowlist e normaliza tipos basicos. */
    public function sanitizeConfig(array $config): array {
        $out=[];
        foreach (self::CONFIG_KEYS as $key) {
            if (!array_key_exists($key,$config)) continue;
            $out[$key]=$config[$key];
        }
        if (isset($out['parent']) && !is_string($out['parent'])) unset($out['parent']);
        if (isset($out['templates']) && !is_array($out['templates'])) unset($out['templates']);
        if (isset($out['tokens']) && !is_array($out['tokens'])) unset($out['tokens']);
        if (isset($out['three']) && !is_array($out['three'])) unset($out['three']);
        if (isset($out['conditions']) && !is_array($out['conditions'])) unset($out['conditions']);
        if (isset($out['sections']) && !is_array($out['sections'])) unset($out['sections']);
        if (isset($out['components']) && !is_array($out['components'])) unset($out['components']);
        return $out;
    }

    /** @return list<string> Codigos de erro estaveis; [] = valido. */
    public function validateConfig(array $config): array {
        $e=[];
        foreach (array_keys($config) as $key) {
            if (!in_array((string)$key,self::CONFIG_KEYS,true)) { $e[]='config_key_invalid'; break; }
        }
        foreach (['primary','surface'] as $key) {
            $v=$config[$key]??null;
            if (!is_string($v) || preg_match('/^#[0-9a-fA-F]{6}$/D',$v)!==1) $e[]='color_invalid';
        }
        $scene=(string)($config['scene']??'');
        if (!in_array($scene,self::SCENES,true)) $e[]='scene_invalid';
        $parent=(string)($config['parent']??'');
        if ($parent!=='' && preg_match('/^[a-z0-9_-]{1,48}$/D',$parent)!==1) $e[]='parent_invalid';
        $templates=$config['templates']??[];
        if (!is_array($templates)) $e[]='templates_invalid';
        else foreach ($templates as $key=>$source) {
            if (!in_array((string)$key,self::TEMPLATE_KEYS,true) || !is_string($source) || strlen($source)>131072
                || preg_match('~<\s*(script|iframe|object|embed)\b|javascript\s*:|\bon[a-z]+\s*=|\|\s*raw\b|\{%\s*autoescape\b|\b(srcdoc|formaction)\s*=~i',$source)) {
                $e[]='template_source_invalid'; break;
            }
        }
        $conditions=$config['conditions']??['version'=>1,'rules'=>[]];
        if (!is_array($conditions) || $this->engine->validate($conditions)!==[]) $e[]='conditions_invalid';
        $three=$config['three']??[];
        if (!is_array($three) || $this->engine->normalizeThree($three)===null) $e[]='three_invalid';
        $tokens=$config['tokens']??[];
        if (!is_array($tokens) || $this->tokens->validate($tokens)!==[] || $this->tokens->normalize($tokens)===null) $e[]='tokens_invalid';
        // F13-F: lista opcional, ordenada e allowlistada ([] e override valido).
        if (array_key_exists('sections',$config)) {
            if (!is_array($config['sections']) || $this->sections->normalize($config['sections'])===null) $e[]='sections_invalid';
        }
        // F13-G: mapa opcional de componentes, enums allowlistados, fail-closed.
        if (array_key_exists('components',$config)) {
            if (!is_array($config['components']) || $this->components->normalize($config['components'])===null) $e[]='components_invalid';
        }
        return array_values(array_unique($e));
    }

    /** @return array<string,mixed> */
    public function export(string $name, string $slug, array $config, array $meta = []): array {
        $clean=$this->sanitizeConfig($config);
        return [
            'format'=>self::FORMAT,'version'=>self::VERSION,
            'name'=>substr($name,0,120),'slug'=>$slug,
            'parent'=>(string)($clean['parent']??''),
            'config'=>$clean,
            'meta'=>['generator'=>'ReamurCMS','schema'=>self::VERSION,'source'=>(string)($meta['source']??'draft')],
            'checksum'=>$this->checksum($clean),
        ];
    }

    public function encode(array $package): string {
        return (string)json_encode($package,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** Fail-closed: tamanho, formato, versao, checksum e config validado. */
    public function decode(string $json): array {
        $fail=static function(string $code): array {
            return ['ok'=>false,'errors'=>[$code],'name'=>'','slug'=>'','parent'=>'','config'=>[]];
        };
        if ($json==='' || strlen($json)>self::MAX_BYTES) return $fail('package_size_invalid');
        $pkg=json_decode($json,true);
        if (!is_array($pkg)) return $fail('json_invalid');
        if (($pkg['format']??'')!==self::FORMAT) return $fail('format_invalid');
        if (($pkg['version']??0)!==self::VERSION) return $fail('version_invalid');
        $name=(string)($pkg['name']??''); $slug=(string)($pkg['slug']??'');
        if ($name==='' || strlen($name)>120 || preg_match('/^[a-z0-9_-]{1,48}$/D',$slug)!==1) return $fail('identity_invalid');
        $config=$pkg['config']??null;
        if (!is_array($config)) return $fail('config_invalid');
        $sum=(string)($pkg['checksum']??'');
        if (preg_match('/^[0-9a-f]{64}$/D',$sum)!==1 || !hash_equals($this->checksum($config),$sum)) return $fail('checksum_invalid');
        $errors=$this->validateConfig($config);
        if ($errors!==[]) return ['ok'=>false,'errors'=>$errors,'name'=>$name,'slug'=>$slug,'parent'=>(string)($config['parent']??''),'config'=>[]];
        return ['ok'=>true,'errors'=>[],'name'=>$name,'slug'=>$slug,'parent'=>(string)($config['parent']??''),'config'=>$this->sanitizeConfig($config)];
    }

    /** Mescla pai -> filho. Mapas sao mesclados por chave; o resto substitui. */
    private function merge(array $base, array $override): array {
        $out=$base;
        foreach ($override as $key=>$value) {
            if (in_array((string)$key,self::MAP_KEYS,true) && is_array($value) && isset($out[$key]) && is_array($out[$key])) {
                $out[$key]=array_replace($out[$key],$value);
            } else $out[$key]=$value;
        }
        return $out;
    }

    /**
     * Resolve a cadeia de child-themes. `$available` e slug => config.
     * Ciclo, profundidade e pai ausente param a caminhada (fail-closed):
     * o filho simplesmente vira a base, sem loop e sem erro fatal.
     * @param array<string,array<string,mixed>> $available
     */
    public function resolveChain(array $config, array $available, string $selfSlug = ''): array {
        $seen=[]; if ($selfSlug!=='') $seen[$selfSlug]=true;
        $chain=[]; $cursor=$config;
        while (count($chain)<self::MAX_DEPTH) {
            $parent=(string)($cursor['parent']??'');
            if ($parent==='' || isset($seen[$parent]) || !isset($available[$parent]) || !is_array($available[$parent])) break;
            $seen[$parent]=true;
            $chain[]=$available[$parent];
            $cursor=$available[$parent];
        }
        $result=[];
        foreach (array_reverse($chain) as $ancestor) $result=$this->merge($result,$ancestor);
        $result=$this->merge($result,$config);
        return $this->sanitizeConfig($result);
    }
}
