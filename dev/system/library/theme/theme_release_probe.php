<?php declare(strict_types=1);
/**
 * Frente AH — estado da LIBERACAO do tema, calculado no servidor.
 *
 * Motivo: desde a frente A o usuario editava, salvava e nao tinha como saber
 * se o que via na previa ja era o que o catalogo publicado renderizaria.
 * `activate()` copia draft_config -> published_config/config e
 * getEffectiveTheme() le published_config, mas nenhum lugar da tela do Admin
 * mostrava "ha rascunho pendente" ou "publicado". E o instrumento da AE
 * (__kazHealth) exigia abrir o console e colar um JSON — nunca foi colado.
 *
 * Este resumo e PURO (sem IO, banco, sessao, request, eval) e devolve apenas
 * escalares allowlistados, prontos para autoescape no Twig. O HTML que o
 * exibe e server-side: aparece MESMO se o JS do editor nao inicializar —
 * exatamente o cenario silencioso das frentes AD/AE.
 *
 * Nenhuma migration, coluna, rota, traducao ou chave de config nova.
 */
namespace Reamur\System\Library\Theme;

if (!class_exists(ThemePreviewState::class, false)) {
    require_once __DIR__ . '/theme_preview_state.php';
}

final class ThemeReleaseProbe {
    public const ASSET_MARK = '-kaz-theme-editor-ah';
    private const CSS_PARTS = ['token_css','component_css','schedule_css','media_css','site_css','scene_css'];

    /** @param array<string,mixed> $draft      draft_config decodificado
     *  @param array<string,mixed> $published  published_config decodificado
     *  @param array<string,mixed> $state      ThemeSnapshot::resolve() do rascunho
     *  @param array<string,mixed> $sceneView  ThemeSceneCss::compile() do rascunho
     *  @return array<string,scalar> Somente escalares: seguro para |e / autoescape. */
    public static function summarize(array $draft, array $published, array $state, array $sceneView,
                                     bool $active, string $assetVersion): array {
        $a = self::canon($draft); $b = self::canon($published);
        $keys = [];
        foreach (array_values(array_unique(array_merge(array_keys($a), array_keys($b)))) as $key) {
            if (($a[$key] ?? null) !== ($b[$key] ?? null)) $keys[] = (string)$key;
            if (count($keys) >= 16) break;
        }
        sort($keys);
        $pending = $keys !== [];
        $parts = 0;
        foreach (self::CSS_PARTS as $part) {
            if (is_string($state[$part] ?? null) && trim((string)$state[$part]) !== '') $parts++;
        }
        $layers = is_array($sceneView['layers'] ?? null) ? count($sceneView['layers']) : 0;
        $css = ThemePreviewState::css($state);
        $verdict = !$active ? 'SEM_TEMA_ATIVO'
                 : ($pending ? 'RASCUNHO_PENDENTE' : 'PUBLICADO');
        return [
            'verdict'      => $verdict,
            'pending'      => $pending ? 1 : 0,
            'pending_count'=> count($keys),
            'pending_keys' => implode(', ', $keys),
            'css_parts'    => $parts,
            'css_bytes'    => strlen($css),
            'scene_layers' => min($layers, 48),
            'active'       => $active ? 1 : 0,
            'asset_ok'     => str_contains($assetVersion, self::ASSET_MARK) ? 1 : 0,
            'asset_version'=> substr($assetVersion, -32),
            'verdict_label'=> self::label($verdict),
            'hint'         => self::hint($verdict),
        ];
    }

    /** Comparacao deterministica de configs: primeiro nivel canonizado,
     *  mapas ordenados e serializados. Nao usa == em arrays brutos. */
    private static function canon(array $config): array {
        $out = [];
        foreach ($config as $key => $value) {
            if (!is_string($key)) continue;
            if (is_array($value)) {
                ksort($value);
                $value = (string)json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($value)) $value = $value ? '1' : '0';
            elseif (is_null($value)) $value = '';
            elseif (is_scalar($value)) $value = (string)$value;
            else continue;
            $out[$key] = $value;
        }
        ksort($out);
        return $out;
    }

    /** Rotulos fixos allowlistados; nenhuma chave de traducao nova. */
    private static function label(string $verdict): string {
        return match ($verdict) {
            'PUBLICADO'         => 'Publicado',
            'RASCUNHO_PENDENTE' => 'Rascunho pendente',
            default             => 'Nenhum tema ativo',
        };
    }
    private static function hint(string $verdict): string {
        return match ($verdict) {
            'PUBLICADO'         => 'Previa e catalogo usam o mesmo tema.',
            'RASCUNHO_PENDENTE' => 'Ha alteracoes nao publicadas. Use Ativar/Publicar para aplicar no catalogo.',
            default             => 'Ative um tema para publicar no catalogo.',
        };
    }
}
