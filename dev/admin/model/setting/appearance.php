<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 *
 * setting/appearance — F8-V (ADR 0047): resolução de tema do painel com
 * padrão global (rms_setting) + override por usuário (rms_user.theme_prefs),
 * catálogo de skins/acentos curados e validação WCAG AA (≥4.5:1) idêntica
 * à do validador CLI (dev/tools/validate-themes.php) — mesma fórmula.
 */
namespace Reamur\Admin\Model\Setting;

class Appearance extends \Reamur\System\Engine\Model {

    /** Skins disponíveis (arquivo admin/view/css/themes/<id>.css deve existir) */
    public const SKINS = ['light', 'ocean', 'forest', 'plum', 'slate', 'contrast'];

    public const SIDEBAR_VARIANTS = ['auto', 'light', 'dark'];
    public const DENSITIES        = ['comfortable', 'compact'];
    public const FONT_SCALES      = ['95', '100', '105'];
    public const MODES            = ['system', 'light', 'dark'];

    /** Superfícies de referência do Bootstrap para validação (iguais ao CLI) */
    private const BODY_BG = ['light' => [255, 255, 255], 'dark' => [33, 37, 41]];

    /**
     * Acentos curados (par por modo, todos WCAG AA ≥ 4.5 nos pares:
     * accent/contrast e accent/body-bg). '' = acento da própria skin.
     *
     * @var array<string, array{light: string, dark: string}>
     */
    public const CURATED_ACCENTS = [
        ''          => ['light' => '',        'dark' => ''],
        'blue'      => ['light' => '#0b5ed7', 'dark' => '#5c9dff'],
        'ocean'     => ['light' => '#106084', 'dark' => '#58b9e0'],
        'forest'    => ['light' => '#135c26', 'dark' => '#5cb871'],
        'plum'      => ['light' => '#66357d', 'dark' => '#c58ae0'],
        'slate'     => ['light' => '#37485a', 'dark' => '#9fb4c9'],
        'wine'      => ['light' => '#9f2f4e', 'dark' => '#e28aa2'],
        'gold'      => ['light' => '#8a6d00', 'dark' => '#e3b505'],
    ];

    /** Defaults do painel (quando nada configurado nem no usuário nem global) */
    public const DEFAULTS = [
        'skin'      => 'light',
        'accent'    => '',       // par hex light ou '' = da skin
        'accent_dark' => '',     // par hex dark (vazio = reusa accent)
        'sidebar'   => 'auto',
        'density'   => 'comfortable',
        'font_scale' => '100',
        'mode'      => 'system',
    ];

    /**
     * Tema efetivo: override do usuário (somente chaves presentes e válidas)
     * caindo para o padrão global desse site e, por fim, os DEFAULTS.
     *
     * @param array<string, mixed> $userPrefs JSON do usuário (pode vir vazio)
     * @return array{skin: string, accent: string, accent_dark: string, sidebar: string, density: string, font_scale: string, mode: string}
     */
    public function resolveTheme(array $userPrefs = []): array {
        $global = [
            'skin'        => (string)$this->config->get('config_admin_skin', self::DEFAULTS['skin']),
            'accent'      => (string)$this->config->get('config_admin_accent', ''),
            'accent_dark' => (string)$this->config->get('config_admin_accent_dark', ''),
            'sidebar'     => (string)$this->config->get('config_admin_sidebar_variant', self::DEFAULTS['sidebar']),
            'density'     => (string)$this->config->get('config_admin_density', self::DEFAULTS['density']),
            'font_scale'  => (string)$this->config->get('config_admin_font_scale', self::DEFAULTS['font_scale']),
            'mode'        => (string)$this->config->get('config_admin_theme_mode', self::DEFAULTS['mode']),
        ];

        $merged = [];
        foreach (self::DEFAULTS as $k => $d) {
            $g = (string)($global[$k] ?? $d);
            $u = (string)($userPrefs[$k] ?? '');
            $merged[$k] = $u !== '' ? $u : $g;
        }

        return $this->sanitize($merged);
    }

    /**
     * Normaliza/valida um mapa de tema; valores inválidos caem no default.
     *
     * @param array<string, string> $in
     * @return array<string, string>
     */
    public function sanitize(array $in): array {
        $out = self::DEFAULTS;

        $out['skin'] = in_array($in['skin'] ?? '', self::SKINS, true) ? (string)$in['skin'] : self::DEFAULTS['skin'];

        foreach (['accent', 'accent_dark'] as $k) {
            $v = trim((string)($in[$k] ?? ''));
            $out[$k] = ($v === '' || preg_match('/^#[0-9a-fA-F]{6}$/', $v)) ? strtolower($v) : '';
        }

        $out['sidebar']   = in_array($in['sidebar'] ?? '', self::SIDEBAR_VARIANTS, true) ? (string)$in['sidebar'] : self::DEFAULTS['sidebar'];
        $out['density']   = in_array($in['density'] ?? '', self::DENSITIES, true) ? (string)$in['density'] : self::DEFAULTS['density'];
        $out['font_scale'] = in_array((string)($in['font_scale'] ?? ''), self::FONT_SCALES, true) ? (string)$in['font_scale'] : self::DEFAULTS['font_scale'];
        $out['mode']      = in_array($in['mode'] ?? '', self::MODES, true) ? (string)$in['mode'] : self::DEFAULTS['mode'];

        return $out;
    }

    /* ─── WCAG (mesma fórmula do dev/tools/validate-themes.php) ─── */

    public static function relLum(array $c): float {
        $f = static function (float $v): float {
            $v /= 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * $f($c[0]) + 0.7152 * $f($c[1]) + 0.0722 * $f($c[2]);
    }

    public static function contrastRatioHex(string $hexA, string $hexB): float {
        $a = self::hexToRgb($hexA); $b = self::hexToRgb($hexB);
        if ($a === null || $b === null) { return 0.0; }
        $l1 = self::relLum($a); $l2 = self::relLum($b);
        if ($l2 > $l1) { [$l1, $l2] = [$l2, $l1]; }
        return ($l1 + 0.05) / ($l2 + 0.05);
    }

    public static function hexToRgb(string $hex): ?array {
        if (!preg_match('/^#([0-9a-f]{6})$/i', $hex, $m)) { return null; }
        $h = $m[1];
        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    public static function rgbToHex(array $c): string {
        return sprintf('#%02x%02x%02x', $c[0], $c[1], $c[2]);
    }

    /**
     * Valida uma cor de acento contra as duas superfícies do Bootstrap
     * (body light #fff e dark #212529). Retorna o mapa de vereditos.
     *
     * @return array{ok: bool, light: float, dark: float, fail_modes: array<int, string>}
     */
    public function checkAccent(string $hex): array {
        $out = ['ok' => true, 'light' => 0.0, 'dark' => 0.0, 'fail_modes' => []];
        if (self::hexToRgb($hex) === null) { $out['ok'] = false; $out['fail_modes'] = ['format']; return $out; }

        foreach (['light', 'dark'] as $mode) {
            $r = self::contrastRatioHex($hex, self::rgbToHex(self::BODY_BG[$mode]));
            $out[$mode] = $r;
            if ($r < 4.5) { $out['ok'] = false; $out['fail_modes'][] = $mode; }
        }
        return $out;
    }

    /**
     * Clareia (modo dark) ou escurece (modo light) uma cor até bater 4.5:1
     * contra a superfície do modo; usada para SUGERIR a variante do custom.
     */
    public function suggestVariant(string $hex, string $mode): string {
        $c = self::hexToRgb($hex);
        if ($c === null) { return $hex; }

        $target = self::rgbToHex(self::BODY_BG[$mode] ?? self::BODY_BG['light']);
        $step = $mode === 'dark' ? 12 : -12;
        for ($i = 0; $i < 24; $i++) {
            $r = self::contrastRatioHex(self::rgbToHex($c), $target);
            if ($r >= 4.5) { break; }
            foreach ($c as $j => $v) { $c[$j] = max(0, min(255, $v + $step)); }
        }
        return self::rgbToHex($c);
    }

    /**
     * Melhor cor de texto sobre o acento (menu ativo/botões): escolhe #fff/#000
     * com razão ≥ 4.5; se nenhum passar, devolve a de maior razão (a decisão de
     * BLOQUEAR o save fica no controller quando < 4.5).
     *
     * @return array{contrast: string, ratio: float}
     */
    public function bestContrastOn(string $accentHex): array {
        $rw = self::contrastRatioHex('#ffffff', $accentHex);
        $rb = self::contrastRatioHex('#000000', $accentHex);
        return $rw >= $rb ? ['contrast' => '#ffffff', 'ratio' => $rw] : ['contrast' => '#000000', 'ratio' => $rb];
    }

    /**
     * Valida o pacote completo de acento (par light/dark) para salvar.
     *
     * @return array<int, string> mensagens de erro (vazio = pode salvar)
     */
    public function validateAccentPair(string $light, string $dark): array {
        $errors = [];

        // accent vazio nos dois = usa o da skin → sempre válido
        if ($light === '' && $dark === '') { return []; }

        if ($light === '') { $errors[] = 'accent-custom-need-light'; return $errors; }

        $c = $this->checkAccent($light);
        if ($c['light'] < 4.5 || in_array('format', $c['fail_modes'], true)) { $errors[] = 'accent-light-fails'; }

        if ($dark !== '') {
            $cd = $this->checkAccent($dark);
            // a variante dark só precisa passar contra o bg DARK
            if ($cd['light'] < 1.0 || $cd['dark'] < 4.5) { $errors[] = 'accent-dark-fails'; }
        } else {
            // sem variante dark, a própria cor é aplicada no dark → precisa passar nos dois
            if (in_array('dark', $c['fail_modes'], true)) { $errors[] = 'accent-dark-missing'; }
        }

        // contraste do texto sobre o acento (menu ativo) nos dois pares
        if ($errors === []) {
            $bl = $this->bestContrastOn($light);
            if ($bl['ratio'] < 4.5) { $errors[] = 'accent-contrast-fails'; }
            $bd = $this->bestContrastOn($dark !== '' ? $dark : $light);
            if ($bd['ratio'] < 4.5) { $errors[] = 'accent-contrast-fails'; }
        }

        return array_values(array_unique($errors));
    }
}
