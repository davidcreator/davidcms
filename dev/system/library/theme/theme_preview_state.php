<?php declare(strict_types=1);
/**
 * Frente AG — UM UNICO tradutor do estado efetivo do tema para a previa.
 *
 * O Catalog (common/header.php) publica no atributo style do body, nesta ordem:
 *   token_css + component_css + schedule_css + media_css + site_css + scene_css
 * Ate a AF, a previa isolada (preview) e os paineis de comparacao consumiam
 * APENAS token_css e scene_css. Resultado: cor, tipografia, densidade, radius,
 * componentes, agenda e sites editados no KAZTheme-Editor nao tinham efeito
 * visivel antes de Publicar — a mesma classe de perda silenciosa da AF.
 *
 * Esta classe nao executa IO, request, banco, sessao, eval ou regex do usuario.
 * Tudo e fail-closed: parte suspeita e DESCARTADA, nunca relaxada. Os valores
 * ja chegam allowlistados dos registries (TokenRegistry, ComponentRegistry,
 * ThemeSchedule, ThemeMedia, ThemeSites, ThemeSceneCss); aqui ha defesa em
 * profundidade para o atributo style / bloco style da previa.
 */
namespace Reamur\System\Library\Theme;

final class ThemePreviewState {
    /** Mesma ordem de dev/catalog/controller/common/header.php. */
    private const CSS_PARTS = ['token_css','component_css','schedule_css','media_css','site_css','scene_css'];
    private const MAX_PART = 8192;
    private const MAX_CSS  = 65536;
    private const CLASS_RE = '/^[a-zA-Z0-9_-]{1,64}$/D';
    private const BAD_CSS  = '~[<>{}\\\\]|<\s*/?style|javascript\s*:|expression\s*\(|@import|behavior\s*:|-moz-binding~i';

    /** Declaracoes CSS allowlistadas, prontas para um atributo style escapado.
     *  Nunca devolve regra com seletor: use no style do body/painel. */
    public static function css(array $state): string {
        $out = [];
        foreach (self::CSS_PARTS as $key) {
            $value = $state[$key] ?? '';
            if (!is_string($value) || $value === '' || strlen($value) > self::MAX_PART) continue;
            if (preg_match(self::BAD_CSS, $value) === 1) continue;
            $out[] = rtrim(trim($value), ';') . ';';
        }
        $css = implode('', $out);
        return strlen($css) > self::MAX_CSS ? '' : $css;
    }

    /** Classes allowlistadas que header.php aplica no body. */
    public static function bodyClasses(array $state): string {
        $out = [];
        foreach ((array)($state['classes'] ?? []) as $class) {
            if (is_string($class) && preg_match(self::CLASS_RE, $class) === 1) $out[] = $class;
        }
        if ((string)($state['schedule_effective'] ?? 'published') === 'draft') $out[] = 'rms-theme-rollout';
        $site = (string)($state['site']['id'] ?? '');
        if ($site !== '' && preg_match('/^[a-z0-9_-]{1,24}$/D', $site) === 1) $out[] = 'rms-site-' . $site;
        return implode(' ', array_slice(array_values(array_unique($out)), 0, 16));
    }

    /** Globais PLANOS para Twig::setThemeGlobals() e renderSandboxPreview().
     *  Somente chaves `theme` ou com prefixo `theme_`: header/footer e o
     *  restante do contexto fixo do sandbox JAMAIS podem ser sobrescritos. */
    public static function globals(array $state): array {
        $primary = self::hex($state['primary'] ?? null, '#2563eb');
        $surface = self::hex($state['surface'] ?? null, '#ffffff');
        $out = [];
        $source = is_array($state['globals'] ?? null) ? $state['globals'] : [];
        foreach ($source as $name => $value) {
            if (!is_string($name) || ($name !== 'theme' && strncmp($name, 'theme_', 6) !== 0)) continue;
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name) !== 1) continue;
            if (is_scalar($value)) { $s = self::scalar($value); if ($s !== null) $out[$name] = $s; }
            elseif (is_array($value)) $out[$name] = self::flat($value);
        }
        /* Estado efetivo calculado por ThemeSnapshot::resolve() prevalece. */
        $out['theme'] = [
            'primary'=>$primary, 'surface'=>$surface,
            'vars'=>self::flat($state['vars'] ?? []),
            'flags'=>self::flat($state['flags'] ?? []),
            'classes'=>self::flat($state['classes'] ?? []),
            'matched'=>self::flat($state['matched'] ?? []),
            'tokens'=>self::flat($state['tokens'] ?? []),
            'sections'=>self::flat($state['sections'] ?? []),
        ];
        $out['theme_primary'] = $primary;
        $out['theme_surface'] = $surface;
        $out['theme_classes'] = self::bodyClasses($state);
        /* Nao expor theme_scene_view: renderSandboxPreview() ja define essa
           chave no contexto com previewSceneView() (AF), sanitizado. */
        return $out;
    }

    /** CSS REAL do Catalog (theme.css), que consome as variaveis --rms-*.
     *  Sem ele, injetar os tokens na previa isolada nao mudaria NADA: as
     *  declaracoes existiriam, mas nenhuma regra as usaria. Leitura somente,
     *  limitada e fail-closed; nenhum dado do request entra aqui. */
    public static function catalogCss(): string {
        $file = defined('DIR_SYSTEM') ? DIR_SYSTEM . '../catalog/view/css/theme.css' : '';
        if ($file === '' || !is_file($file)) return '';
        $css = (string)@file_get_contents($file);
        if ($css === '' || strlen($css) > 131072) return '';
        if (preg_match('~<\s*/?\s*(script|style)\b|@import|javascript\s*:|expression\s*\(|url\s*\(\s*[\'"]?\s*javascript~i', $css) === 1) return '';
        return $css;
    }

    /** Cores primaria/superficie allowlistadas para o atributo style. */
    public static function colorVars(array $state): string {
        return '--theme-primary:' . self::hex($state['primary'] ?? null, '#2563eb')
             . ';--theme-surface:' . self::hex($state['surface'] ?? null, '#ffffff') . ';';
    }

    private static function hex($value, string $fallback): string {
        $v = is_string($value) ? $value : '';
        return preg_match('/^#[0-9a-fA-F]{6}$/D', $v) === 1 ? $v : $fallback;
    }
    private static function scalar($value) {
        if (is_bool($value) || is_int($value) || (is_float($value) && is_finite($value))) return $value;
        if (!is_string($value) || strlen($value) > 256) return null;
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1 ? null : $value;
    }
    private static function flat($value): array {
        if (!is_array($value)) return [];
        $out = [];
        foreach ($value as $k => $v) {
            if (count($out) >= 64) break;
            $s = is_scalar($v) ? self::scalar($v) : null;
            if ($s !== null) $out[is_string($k) ? $k : (string)$k] = $s;
        }
        return $out;
    }
}
