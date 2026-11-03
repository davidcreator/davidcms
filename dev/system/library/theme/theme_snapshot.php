<?php declare(strict_types=1);
/**
 * F13-C — estado EFETIVO de um tema.
 *
 * Uma unica fonte de verdade para "o que o visitante ve": Catalog (startup) e
 * Admin (previa rascunho x publicado) resolvem o mesmo config com o mesmo
 * contexto e chegam ao mesmo resultado. Nenhuma escrita, IO, eval, regex de
 * usuario, HTTP ou sessao. Tudo passa por allowlists existentes.
 */
namespace Reamur\System\Library\Theme;

final class ThemeSnapshot {
    private const SCENES = ['', 'hero-particles', 'portfolio-3d', 'tools-3d', 'not-found'];
    private ConditionEngine $engine;
    private TokenRegistry $tokens;
    private ThemePackage $package;
    private SectionRegistry $sections;
    private ComponentRegistry $components;
    private ThemeSchedule $schedule;

    public function __construct(ConditionEngine $engine, TokenRegistry $tokens, ?ThemePackage $package = null, ?SectionRegistry $sections = null, ?ComponentRegistry $components = null, ?ThemeSchedule $schedule = null) {
        $this->engine = $engine;
        $this->tokens = $tokens;
        $this->package = $package ?? new ThemePackage($engine, $tokens);
        $this->sections = $sections ?? $this->package->sections();
        $this->components = $components ?? $this->package->components();
        $this->schedule = $schedule ?? $this->package->schedule();
    }

    /**
     * @param array<string,mixed> $config   Config publicada OU rascunho (ja validado).
     * @param array<string,mixed> $context  Contexto allowlisted do ConditionEngine.
     * @return array<string,mixed>
     */
    public function resolve(array $config, array $context, array $themeMap = [], string $selfSlug = '', array $scheduleState = []): array {
        // F13-E: child-theme. Com mapa, resolve a cadeia de pais antes de
        // calcular o estado efetivo; sem mapa, comportamento identico ao de C.
        // Ciclo/profundidade/pai ausente param a caminhada (fail-closed).
        if ($themeMap !== []) $config = $this->package->resolveChain($config, $themeMap, $selfSlug);
        $rules = [];
        if (isset($config['conditions']) && is_array($config['conditions'])) {
            $rules = $this->engine->normalize($config['conditions'])['rules'];
        }
        $r = $this->engine->evaluate($rules, $context);

        // Cores: regra casada ou base; hex invalido vira null (nao publicado).
        $primary = $r['primary'] ?? (is_string($config['primary'] ?? null) ? $config['primary'] : null);
        $surface = $r['surface'] ?? (is_string($config['surface'] ?? null) ? $config['surface'] : null);
        $primary = ($primary !== null && preg_match('/^#[0-9a-fA-F]{6}$/D', $primary) === 1) ? $primary : null;
        $surface = ($surface !== null && preg_match('/^#[0-9a-fA-F]{6}$/D', $surface) === 1) ? $surface : null;

        // Cena: allowlist fixa (legado F10-03) + override casado.
        $scene = $r['scene'] ?? (is_string($config['scene'] ?? null) ? $config['scene'] : '');
        if (!in_array($scene, self::SCENES, true)) $scene = '';

        // Three.js: base + ultimo override, sempre allowlisted.
        $base = isset($config['three']) && is_array($config['three']) ? $config['three'] : [];
        $three = $this->engine->normalizeThree($base) ?? [];
        if (is_array($r['three'])) $three = array_replace($three, $r['three']);
        if ($three === []) $three = ['enabled'=>false,'scene'=>'','slot'=>'hero','count'=>400,'color'=>'#8a4fff','speed'=>0.05];
        if (($three['scene'] ?? '') === '' && $scene !== '') $three['scene'] = $scene;

        // Design tokens (F13-B): defaults, base e patches, com contraste AA
        // revalidado no merge final. Patch ilegivel e ignorado (fail-closed).
        $tokenBase = isset($config['tokens']) && is_array($config['tokens']) ? $config['tokens'] : [];
        $tokens = $this->tokens->normalize($tokenBase) ?? $this->tokens->defaults();
        if (is_array($r['tokens']) && $r['tokens'] !== []) {
            $merged = array_replace($tokens, $r['tokens']);
            $readable = true;
            foreach ($this->tokens->contrastPairs($merged) as $pair) {
                if (!$pair['ok']) { $readable = false; break; }
            }
            if ($readable) $tokens = $merged;
        }

        $templates = isset($config['templates']) && is_array($config['templates']) ? $config['templates'] : [];

        // F13-F: composicao declarativa da home. Ausente => null (layout legado
        // do CMS); presente => lista validada, inclusive [] (nenhuma secao).
        $sections = (array_key_exists('sections',$config) && is_array($config['sections']))
            ? ($this->sections->normalize($config['sections']) ?? []) : null;
        $sectionBars = $this->sections->bars($sections);

        // F13-G: biblioteca de componentes. Ausente/invalido => nenhum.
        $componentConfig = (array_key_exists('components',$config) && is_array($config['components']))
            ? ($this->components->normalize($config['components']) ?? []) : [];
        $componentCss = $this->components->css($componentConfig);
        $componentGlobals = $this->components->globals($componentConfig);

        // F13-H: a DECISAO vem de fora (Catalog ou previa Admin); o snapshot
        // apenas a publica. Sem scheduleState => publicado, sem alterar A-G.
        $scheduleBars = $this->schedule->bars($scheduleState);
        $scheduleCss = $this->schedule->css($scheduleState);

        return [
            'primary'    => $primary,
            'surface'    => $surface,
            'scene'      => $scene,
            'three'      => $three,
            'tokens'     => $tokens,
            'token_css'  => $this->tokens->css($tokens),
            'vars'       => $r['vars'],
            'flags'      => $r['flags'],
            'classes'    => $r['classes'],
            'matched'    => $r['matched'],
            'ignored'    => $r['ignored'],
            'templates'  => $templates,
            'sections'   => $sections,
            'sections_defined' => ($sections !== null),
            'section_bars' => $sectionBars,
            'components' => $componentConfig,
            'component_enabled' => $this->components->enabled($componentConfig),
            'component_css' => $componentCss,
            'schedule' => $scheduleState,
            'schedule_effective' => (string)($scheduleState['effective'] ?? 'published'),
            'schedule_reason' => (string)($scheduleState['reason'] ?? 'disabled'),
            'schedule_percent' => (int)($scheduleState['percent'] ?? 0),
            'schedule_bucket' => (int)($scheduleState['bucket'] ?? 0),
            'schedule_bars' => $scheduleBars,
            'schedule_css' => $scheduleCss,
            'globals'    => array_replace([
                'theme' => [
                    'vars' => $r['vars'], 'flags' => $r['flags'],
                    'classes' => $r['classes'], 'matched' => $r['matched'],
                    'tokens' => $tokens, 'sections' => ($sections ?? []),
                ],
                'theme_scene' => $three,
            ], $componentGlobals, $this->schedule->globals($scheduleState)),
        ];
    }

    /**
     * Diff estruturado e limitado entre dois configs. Nao devolve HTML: todos
     * os valores sao strings truncadas, prontas para escapar na view.
     * @return array{added:list<array>,removed:list<array>,changed:list<array>,total:int,truncated:bool}
     */
    public function diff(array $published, array $draft): array {
        $a = self::flatten($published);
        $b = self::flatten($draft);
        $out = ['added'=>[], 'removed'=>[], 'changed'=>[], 'total'=>0, 'truncated'=>false];
        foreach ($b as $k => $v) {
            if (!array_key_exists($k, $a)) $out['added'][] = ['key'=>$k, 'before'=>'', 'after'=>$v];
            elseif ($a[$k] !== $v) $out['changed'][] = ['key'=>$k, 'before'=>$a[$k], 'after'=>$v];
        }
        foreach ($a as $k => $v) {
            if (!array_key_exists($k, $b)) $out['removed'][] = ['key'=>$k, 'before'=>$v, 'after'=>''];
        }
        $count = count($out['added']) + count($out['removed']) + count($out['changed']);
        $out['total'] = $count;
        if ($count > 60) {
            $out['added'] = array_slice($out['added'], 0, 20);
            $out['removed'] = array_slice($out['removed'], 0, 20);
            $out['changed'] = array_slice($out['changed'], 0, 20);
            $out['truncated'] = true;
        }
        return $out;
    }

    /** @return array<string,string> */
    public static function flatten(array $data, string $prefix = '', int $depth = 0): array {
        $out = [];
        foreach ($data as $k => $v) {
            if (count($out) >= 200) { $out[$prefix . '_truncated'] = 'true'; break; }
            $key = $prefix === '' ? (string)$k : $prefix . '.' . (string)$k;
            if (is_array($v) && $depth < 4) {
                foreach (self::flatten($v, $key, $depth + 1) as $sk => $sv) $out[$sk] = $sv;
            } elseif (is_array($v)) {
                $out[$key] = '[array]';
            } elseif (is_bool($v)) {
                $out[$key] = $v ? 'true' : 'false';
            } elseif ($v === null) {
                $out[$key] = 'null';
            } elseif (is_scalar($v)) {
                $out[$key] = substr((string)$v, 0, 120);
            } else {
                $out[$key] = '[value]';
            }
        }
        return $out;
    }
}
