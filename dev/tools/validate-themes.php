#!/usr/bin/env php
<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @license   GPL-3.0
 *
 * validate-themes.php — F8-V (ADR 0047): validação WCAG AA das skins do admin.
 *
 * Uso:  php dev/tools/validate-themes.php
 * Sai com código 0 quando TODOS os pares texto/fundo críticos de todas as
 * skins (nos 2 modos light/dark e nas variantes de sidebar) têm razão >= 4.5:1
 * (texto) ou >= 3:1 (componentes/gráficos). Caso contrário lista os pares e
 * sai com código 1. Roda em CLI, nunca via web.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

/** Diretório de skins (inclui os defaults embutidos no admin.css) */
$themesDir = dirname(__DIR__) . '/admin/view/css/themes';
$adminCss  = dirname(__DIR__) . '/admin/view/css/admin.css';

/* ─── luminância relativa + razão de contraste (WCAG 2.x) ─── */
function relLum(array $c): float {
    $f = static function (float $v): float {
        $v /= 255;
        return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * $f($c[0]) + 0.7152 * $f($c[1]) + 0.0722 * $f($c[2]);
}
function contrastRatio(array $fg, array $bg): float {
    $l1 = relLum($fg); $l2 = relLum($bg);
    if ($l2 > $l1) { [$l1, $l2] = [$l2, $l1]; }
    return ($l1 + 0.05) / ($l2 + 0.05);
}

/** hex '#rgb'/'#rrggbb' → [r,g,b]; aceita também rgba() com alpha sobre bg */
function parseColor(string $raw, ?array $over = null): ?array {
    $raw = trim($raw);
    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $raw, $m)) {
        $h = $m[1];
        if (strlen($h) === 3) { $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2]; }
        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }
    if (preg_match('/^rgba?\(\s*(\d+)\s+(\d+)\s+(\d+)\s*(?:\/\s*([\d.]+%?)\s*)?\)$/i', $raw, $m)) {
        $c = [(int)$m[1], (int)$m[2], (int)$m[3]];
        $a = isset($m[4]) && $m[4] !== '' ? (str_ends_with($m[4], '%') ? (float)$m[4] / 100 : (float)$m[4]) : 1.0;
        if ($over !== null && $a < 1) {
            return [(int)round($c[0] * $a + $over[0] * (1 - $a)), (int)round($c[1] * $a + $over[1] * (1 - $a)), (int)round($c[2] * $a + $over[2] * (1 - $a))];
        }
        return $c;
    }
    return null;
}

/** parser bem simples: extrai --var: valor; dentro de blocos seletor { } na ordem */
function parseCssVars(string $css): array {
    // [seletor, [var => valor]]
    $blocks = [];
    preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $m, PREG_SET_ORDER);
    foreach ($m as $b) {
        $sel = trim(preg_replace('/\/\*.*?\*\//s', '', $b[1]));
        if ($sel === '' || str_starts_with($sel, '@')) { continue; }
        $vars = [];
        preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $b[2], $vm, PREG_SET_ORDER);
        foreach ($vm as $v) { $vars[trim($v[1])] = trim($v[2]); }
        if ($vars) { $blocks[] = [$sel, $vars]; }
    }
    return $blocks;
}

/** um bloco se aplica ao contexto se TODAS as restrições nele presentes forem satisfeitas */
function selApplies(string $sel, string $mode, string $variant): bool {
    if (preg_match('/data-bs-theme\s*=\s*"(light|dark)"/', $sel, $m) && $m[1] !== $mode) { return false; }
    if (preg_match('/data-rms-sidebar\s*=\s*"(auto|light|dark)"/', $sel, $m) && $m[1] !== $variant) { return false; }
    return true;
}

/** resolve um token: percorre na ordem; bloco relevante e posterior sobrescreve */
function resolve(array $blocks, string $var, string $mode, string $variant): ?string {
    $val = null;
    foreach ($blocks as [$sel, $vars]) {
        if (!isset($vars[$var])) { continue; }
        if (selApplies($sel, $mode, $variant)) { $val = $vars[$var]; }
    }
    return $val;
}

/* ─── superfícies de referência do Bootstrap (5.3 data-bs-theme) ─── */
$BODY = ['light' => [255, 255, 255], 'dark' => [33, 37, 41]];      // body-bg
$TEXT = ['light' => [33, 37, 41],   'dark' => [222, 226, 230]];   // body-color

/* pares que o validador mede por skin·modo·variante */
$fails = 0;
$files = glob($themesDir . '/*.css') ?: [];
sort($files);

/* corpora: cada skin arquivo + o fallback embutido no admin.css */
$corpora = [];
foreach ($files as $f) { $corpora[basename($f, '.css')] = file_get_contents($f); }
$corpora['admin-defaults(embedded)'] = file_get_contents($adminCss);

foreach ($corpora as $skin => $css) {
    $blocks = parseCssVars($css);

    foreach (['light', 'dark'] as $mode) {
        /* tokens efetivos nesta skin·modo para cada variante de sidebar */
        foreach (['auto', 'light', 'dark'] as $variant) {
            $hints = [$mode, $variant];

            $accent = parseColor((string)resolve($blocks, '--rms-accent', $mode, $variant) ?: '');
            $accentC = parseColor((string)resolve($blocks, '--rms-accent-contrast', $mode, $variant) ?: '');
            $sideBg = parseColor((string)resolve($blocks, '--rms-sidebar-bg', $mode, $variant) ?: '');
            $sideFg = parseColor((string)resolve($blocks, '--rms-sidebar-fg', $mode, $variant) ?: '');
            $sideMut = parseColor((string)resolve($blocks, '--rms-sidebar-fg-muted', $mode, $variant) ?: '');
            $sideTit = parseColor((string)resolve($blocks, '--rms-sidebar-fg-title', $mode, $variant) ?: '');
            $bodyC = parseColor((string)resolve($blocks, '--bs-body-color', $mode, $variant) ?: '') ?: $TEXT[$mode];
            $draftFg = parseColor((string)resolve($blocks, '--rms-badge-draft-fg', $mode, $variant) ?: '');

            /* sidebar dark-variante implícita: quando bloco dark-mode define os tokens
               e a hint casa, os valores vêm certos; senão caem no default do admin.css */
            $pairs = [
                ['sidebar fg/bg', $sideFg, $sideBg, 4.5],
                ['sidebar muted/bg', $sideMut, $sideBg, 4.5],
                ['sidebar title/bg', $sideTit, $sideBg, 4.5],
                ['accent-contrast/accent (ativo no menu)', $accentC, $accent, 4.5],
                ['accent/body-bg (links/ícones no conteúdo)', $accent, $BODY[$mode], 4.5],
                ['body/body-bg', $bodyC, $BODY[$mode], 4.5],
            ];
            if ($draftFg) { $pairs[] = ['badge draft fg/superfície', $draftFg, $BODY[$mode], 3.0]; }

            foreach ($pairs as [$label, $fg, $bg, $min]) {
                if (!$fg || !$bg) { continue; }
                $r = contrastRatio($fg, $bg);
                if ($r < $min - 1e-9) {
                    printf("FALHOU  %-28s %-5s sidebar=%-6s  %-44s %5.2f:1 (< %g)\n", $skin, $mode, $variant, $label, $r, $min);
                    $fails++;
                }
            }
        }
    }
}

echo $fails === 0
    ? "OK — todas as skins passam WCAG AA nos pares críticos (" . count($corpora) . " skins).\n"
    : "Total de falhas: $fails\n";

exit($fails === 0 ? 0 : 1);
