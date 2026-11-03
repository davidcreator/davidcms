<?php declare(strict_types=1);
/**
 * F13-C — HTML isolado da comparacao "publicado x rascunho".
 *
 * Nao e uma fonte Twig editavel: e gerado no servidor, com todo dado dinamico
 * escapado. Sem <script>, sem <style> (so atributos style), sem |raw, sem
 * srcdoc, sem iframe, sem form, sem rede. A CSP endurece o preview legado com
 * base-uri 'none' e form-action 'none'. O unico HTML nao escapado e o fragmento
 * ja produzido por Tpl::renderSandboxPreview() (Twig sandbox + autoescape).
 */
namespace Reamur\System\Library\Theme;

final class ThemeCompareView {
    /** @param array<string,mixed> $p */
    public function render(array $p): string {
        $h = static function ($v): string {
            return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $lang = $h($p['lang'] ?? 'en-us');
        $labels = $p['labels'];
        $ctx = $p['context'];
        $diff = $p['diff'];
        $pub = $p['published'];
        $draft = $p['draft'];

        $o = '<!doctype html><html lang="' . $lang . '"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<meta name="robots" content="noindex,nofollow,noarchive">'
           . '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; style-src \'unsafe-inline\'; base-uri \'none\'; form-action \'none\'">'
           . '<title>' . $h($labels['title']) . '</title></head>'
           . '<body style="margin:0;padding:16px;font:14px/1.5 system-ui,sans-serif;background:#f1f5f9;color:#0f172a">'
           . '<div style="max-width:1400px;margin:0 auto">'
           . '<h1 style="margin:0 0 4px;font-size:18px">' . $h($labels['title']) . '</h1>'
           . '<p style="margin:0 0 12px;color:#475569;font-size:12px">'
           . $h($labels['context']) . ': <code>' . $h($ctx['route']) . '</code> · '
           . $h($ctx['language']) . ' · ' . $h($ctx['device'])
           . ($ctx['variant'] !== '' ? ' · ' . $h($labels['variant']) . ' ' . $h($ctx['variant']) : '')
           . ' · <code>' . $h($p['template_key']) . '.twig</code></p>';

        if ($p['error'] !== '') {
            $o .= '<div role="alert" style="margin:0 0 12px;padding:10px 12px;border:1px solid #fca5a5;border-radius:8px;background:#fef2f2;color:#991b1b">'
                . $h($labels['invalid']) . ': ' . $h($p['error']) . '. '
                . $h($labels['nothing_saved']) . '</div>';
        }

        // Resumo do diff (servidor, sem JS).
        $badge = $diff['total'] === 0
            ? '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#dcfce7;color:#166534">' . $h($labels['identical']) . '</span>'
            : '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#fef3c7;color:#92400e">' . $h($diff['total']) . ' ' . $h($labels['changes']) . '</span>';
        $o .= '<div style="margin:0 0 12px">' . $badge . ' <span style="color:#475569;font-size:12px">'
           . $h($labels['diff_help']) . '</span></div>';
        if ($diff['total'] > 0) {
            $o .= '<details style="margin:0 0 14px;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;background:#fff" open><summary style="cursor:pointer;font-weight:600">'
               . $h($labels['diff_title']) . '</summary><ul style="margin:8px 0 0;padding-left:20px;font-size:12px">';
            foreach (['changed'=>$labels['changed'], 'added'=>$labels['added'], 'removed'=>$labels['removed']] as $kind => $label) {
                foreach ($diff[$kind] as $row) {
                    $o .= '<li style="margin-bottom:3px"><strong>' . $h($label) . '</strong> <code>' . $h($row['key']) . '</code>'
                       . ($row['before'] !== '' ? ' <span style="color:#b91c1c">' . $h($row['before']) . '</span>' : '')
                       . ' → ' . ($row['after'] !== '' ? '<span style="color:#15803d">' . $h($row['after']) . '</span>' : '<em>' . $h($labels['empty']) . '</em>')
                       . '</li>';
                }
            }
            if ($diff['truncated']) $o .= '<li>' . $h($labels['truncated']) . '</li>';
            $o .= '</ul></details>';
        }

        $o .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start">';
        $o .= $this->panel($labels['published'], $pub, $p['published_html'], $labels, $h, '#e2e8f0', (string)($p['published_thumb']??''));
        $o .= $this->panel($labels['draft'], $draft, $p['draft_html'], $labels, $h, '#dbeafe', (string)($p['draft_thumb']??''));
        $o .= '</div></div></body></html>';
        return $o;
    }

    /** @param array<string,mixed> $s @param array<string,string> $labels */
    private function panel(string $title, array $s, string $fragment, array $labels, callable $h, string $barBg, string $thumb = ''): string {
        $css = $s['token_css'];
        $style = $css
            . ';--theme-primary:' . ($s['primary'] ?? '#2563eb')
            . ';--theme-surface:' . ($s['surface'] ?? '#ffffff')
            . ';font-family:var(--rms-font-family,system-ui,sans-serif)'
            . ';font-size:var(--rms-font-size,16px)'
            . ';line-height:var(--rms-line-height,1.55)'
            . ';color:var(--rms-color-text,#212529)'
            . ';background-color:var(--rms-color-surface,#ffffff)'
            . ';border:1px solid #cbd5e1;border-radius:8px;overflow:hidden';
        $o = '<section style="' . $h($style) . '">'
           . '<h2 style="margin:0;padding:9px 12px;font-size:13px;background:' . $barBg . ';color:#0f172a;font-weight:600">' . $h($title) . '</h2>'
           . '<div style="padding:12px">';

        // F13-D: miniatura da cena (data URI, sem |raw e sem <script>).
        if ($thumb !== '') {
            $o .= '<div style="margin:0 0 10px"><img src="' . $h($thumb) . '" alt="' . $h($labels['scene_thumb']??'Scene') . '" width="320" height="180" style="display:block;width:100%;max-width:320px;height:auto;border:1px solid #cbd5e1;border-radius:6px"></div>';
        }
        // F13-F: fita de secoes da home, gerada no servidor e toda escapada.
        $o .= $this->sectionStrip($s, $labels, $h);
        // F13-G: fita de componentes habilitados (servidor, tudo escapado).
        $o .= $this->componentStrip($s, $labels, $h);
        // F13-H: fita do agendamento/rollout (servidor, tudo escapado).
        $o .= $this->scheduleStrip($s, $labels, $h);
        // F13-I: fita das midias vetoriais habilitadas (servidor, escapado).
        $o .= $this->mediaStrip($s, $labels, $h);
        // F13-J: fita de sites/hosts (servidor, tudo escapado, sem host do request).
        $o .= $this->siteStrip($s, $labels, $h);
        // Amostra visual REAL: consome as mesmas variaveis que o theme.css.
        $o .= '<div style="padding:14px;border-radius:var(--rms-radius,12px);box-shadow:var(--rms-shadow,none);border:var(--rms-border-width,1px) solid #e2e8f0;background:var(--rms-color-surface,#ffffff)">'
           . '<h3 style="margin:0 0 6px;font-size:calc(var(--rms-font-size,16px) * var(--rms-font-scale,1.25));font-weight:var(--rms-heading-weight,700)">' . $h($labels['sample_title']) . '</h3>'
           . '<p style="margin:0 0 10px;color:var(--rms-color-muted,#5c6773)">' . $h($labels['sample_body']) . '</p>'
           . '<span style="display:inline-block;padding:7px 13px;border-radius:var(--rms-radius,12px);background:var(--rms-color-primary,#0a58ca);color:#fff">' . $h($labels['sample_button']) . '</span>'
           . '</div>';

        $matched = is_array($s['matched']) ? implode(', ', array_map('strval', $s['matched'])) : '';
        $flags = is_array($s['flags']) ? implode(', ', array_map('strval', $s['flags'])) : '';
        $o .= '<p style="margin:10px 0 0;font-size:11px;color:var(--rms-color-muted,#5c6773)">'
           . $h($labels['primary']) . ': <code>' . $h($s['primary'] ?? $labels['empty']) . '</code> · '
           . $h($labels['surface']) . ': <code>' . $h($s['surface'] ?? $labels['empty']) . '</code><br>'
           . $h($labels['matched']) . ': <code>' . ($matched !== '' ? $h($matched) : '—') . '</code> · '
           . $h($labels['flags']) . ': <code>' . ($flags !== '' ? $h($flags) : '—') . '</code><br>'
           . $h($labels['tokens']) . ': <code>' . $h($s['tokens']['font_size'] ?? '') . 'px</code> / '
           . '<code>' . $h($s['tokens']['radius'] ?? '') . 'px</code> / '
           . '<code>' . $h($s['tokens']['density'] ?? '') . '</code></p>';

        $o .= '<div style="margin-top:12px;padding-top:10px;border-top:1px solid #e2e8f0;font-size:13px">'
           . $fragment . '</div>';
        $o .= '</div></section>';
        return $o;
    }

    /** F13-F — mapa declarativo das secoes (nenhum dado do usuario e HTML). */
    private function sectionStrip(array $s, array $labels, callable $h): string {
        $sections = $s['sections'] ?? null;
        $out = '<div style="margin:0 0 10px"><div style="font-size:11px;font-weight:600;margin-bottom:5px;color:#475569">'
             . $h($labels['sections'] ?? 'Sections') . '</div>';
        if ($sections === null) {
            $out .= '<div style="font-size:11px;color:#64748b;font-style:italic">'
                 . $h($labels['section_legacy'] ?? 'CMS layout') . '</div>';
        } elseif ($sections === []) {
            $out .= '<div style="font-size:11px;color:#64748b;font-style:italic">'
                 . $h($labels['section_empty'] ?? 'No sections') . '</div>';
        } else {
            $out .= '<div style="display:flex;align-items:flex-end;gap:3px;min-height:52px;padding:6px;border:1px solid #cbd5e1;border-radius:6px;background:#f8fafc">';
            foreach ((array)($s['section_bars'] ?? []) as $bar) {
                $id = (string)($bar['id'] ?? '');
                $height = max(8, min(48, (int)($bar['height'] ?? 16)));
                $tone = preg_match('/^#[0-9a-fA-F]{6}$/D', (string)($bar['tone'] ?? '')) === 1 ? (string)$bar['tone'] : '#475569';
                $out .= '<div title="' . $h($id) . '" style="flex:1 1 0;min-width:0;height:' . $height
                     . 'px;background:' . $h($tone) . ';border-radius:2px;opacity:.85">'
                     . '<span style="display:block;font-size:8px;line-height:1.1;color:#fff;padding:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'
                     . $h($id) . '</span></div>';
            }
            $out .= '</div>';
        }
        return $out . '</div>';
    }

    /** F13-I — badges das midias efetivas (nenhum HTML do usuario). */
    private function mediaStrip(array $s, array $labels, callable $h): string {
        $bars = (array)($s['media_bars'] ?? []);
        $out = '<div style="margin:0 0 10px"><div style="font-size:11px;font-weight:600;margin-bottom:5px;color:#475569">'
             . $h($labels['media'] ?? 'Media') . '</div>';
        if ($bars === []) {
            $out .= '<div style="font-size:11px;color:#64748b;font-style:italic">'
                 . $h($labels['media_empty'] ?? 'None') . '</div>';
        } else {
            $out .= '<div style="display:flex;flex-wrap:wrap;gap:4px">';
            foreach ($bars as $bar) {
                $id = (string)($bar['id'] ?? '');
                $out .= '<span style="font-size:10px;padding:2px 6px;border:1px solid #cbd5e1;border-radius:999px;background:#fff">'
                     . '<code>' . $h($id) . '</code> ' . $h((string)($bar['variant'] ?? ''))
                     . ' · ' . $h((string)($bar['ratio'] ?? '')) . '</span>';
            }
            $out .= '</div>';
        }
        return $out . '</div>';
    }

    /** F13-J — sites habilitados e site ativo (hosts do CONFIG, escapados). */
    private function siteStrip(array $s, array $labels, callable $h): string {
        $bars = (array)($s['site_bars'] ?? []);
        $out = '<div style="margin:0 0 10px"><div style="font-size:11px;font-weight:600;margin-bottom:5px;color:#475569">'
             . $h($labels['site'] ?? 'Sites') . '</div>';
        if ($bars === []) {
            $out .= '<div style="font-size:11px;color:#64748b;font-style:italic">'
                 . $h($labels['site_empty'] ?? 'None') . '</div>';
        } else {
            $out .= '<div style="display:flex;flex-wrap:wrap;gap:4px">';
            foreach ($bars as $bar) {
                $active = (bool)($bar['active'] ?? false);
                $out .= '<span style="font-size:10px;padding:2px 6px;border:1px solid '
                     . ($active ? '#15803d' : '#cbd5e1') . ';border-radius:999px;background:'
                     . ($active ? '#dcfce7' : '#fff') . '">'
                     . '<code>' . $h((string)($bar['id'] ?? '')) . '</code> · '
                     . '<code>' . $h((string)($bar['slug'] ?? '')) . '</code> · '
                     . $h((string)($bar['language'] ?? ''))
                     . ($active ? ' · ' . $h($labels['site_active'] ?? 'Ativo') : '') . ' · '
                     . '<code>' . $h((string)($bar['host'] ?? '')) . '</code></span>';
            }
            $out .= '</div>';
        }
        return $out . '</div>';
    }

    /** F13-H — janela, publico e rollout efetivos (nenhum HTML do usuario). */
    private function scheduleStrip(array $s, array $labels, callable $h): string {
        $bars = (array)($s['schedule_bars'] ?? []);
        $effective = (string)($s['schedule_effective'] ?? 'published');
        $reason = (string)($s['schedule_reason'] ?? 'disabled');
        $window = (array)($bars['window'] ?? []);
        $rollout = (array)($bars['rollout'] ?? []);
        $out = '<div style="margin:0 0 10px"><div style="font-size:11px;font-weight:600;margin-bottom:5px;color:#475569">'
             . $h($labels['schedule'] ?? 'Schedule') . '</div>';
        $tone = $effective === 'draft' ? '#15803d' : '#64748b';
        $out .= '<div style="font-size:11px;margin-bottom:4px"><span style="display:inline-block;padding:2px 7px;border-radius:999px;background:'
             . ($effective === 'draft' ? '#dcfce7' : '#e2e8f0') . ';color:' . $tone . '">'
             . $h($effective === 'draft' ? ($labels['schedule_active'] ?? 'Draft rollout') : ($labels['schedule_inactive'] ?? 'Published'))
             . '</span> <code>' . $h($reason) . '</code></div>';
        $out .= '<div style="display:flex;flex-wrap:wrap;gap:4px;font-size:10px">';
        $out .= '<span style="padding:2px 6px;border:1px solid #cbd5e1;border-radius:4px;background:#fff">'
             . $h($labels['schedule_window'] ?? 'Window') . ': <code>' . $h((string)($window['from'] ?? '')) . '</code> → <code>'
             . $h((string)($window['until'] ?? '') !== '' ? (string)$window['until'] : ($labels['schedule_open'] ?? 'open')) . '</code></span>';
        $out .= '<span style="padding:2px 6px;border:1px solid #cbd5e1;border-radius:4px;background:#fff">'
             . $h($labels['schedule_rollout'] ?? 'Rollout') . ': <code>' . $h((string)($rollout['percent'] ?? 0)) . '%</code> · '
             . $h($labels['schedule_bucket'] ?? 'bucket') . ' <code>' . $h((string)($rollout['bucket'] ?? 0)) . '</code></span>';
        $out .= '<span style="padding:2px 6px;border:1px solid #cbd5e1;border-radius:4px;background:#fff">'
             . $h($labels['schedule_scope'] ?? 'Scope') . ': <code>' . $h((string)($bars['scope'] ?? 'all')) . '</code></span>';
        $out .= '</div>';
        if (!empty($bars['active'])) {
            $out .= '<div style="margin-top:5px;height:6px;border:1px solid #cbd5e1;border-radius:4px;background:#f8fafc;overflow:hidden">'
                 . '<div style="height:100%;width:' . max(0,min(100,(int)($rollout['percent'] ?? 0))) . '%;background:#2563eb"></div></div>';
        }
        $out .= '<div style="font-size:10px;color:#64748b;margin-top:3px">'
             . $h($labels['schedule_now'] ?? 'now') . ': <code>' . $h((string)($bars['now'] ?? '')) . '</code></div>';
        return $out . '</div>';
    }

    /** F13-G — badges dos componentes efetivos (nenhum HTML do usuario). */
    private function componentStrip(array $s, array $labels, callable $h): string {
        $ids = (array)($s['component_enabled'] ?? []);
        $cfg = (array)($s['components'] ?? []);
        $out = '<div style="margin:0 0 10px"><div style="font-size:11px;font-weight:600;margin-bottom:5px;color:#475569">'
             . $h($labels['components'] ?? 'Components') . '</div>';
        if ($ids === []) {
            $out .= '<div style="font-size:11px;color:#64748b;font-style:italic">'
                 . $h($labels['component_empty'] ?? 'None') . '</div>';
        } else {
            $out .= '<div style="display:flex;flex-wrap:wrap;gap:4px">';
            foreach ($ids as $id) {
                $id = (string)$id;
                $variant = (string)($cfg[$id]['variant'] ?? '');
                $tone = (string)($cfg[$id]['tone'] ?? '');
                $out .= '<span style="font-size:10px;padding:2px 6px;border:1px solid #cbd5e1;border-radius:999px;background:#fff">'
                     . '<code>' . $h($id) . '</code> ' . $h($variant) . ' / ' . $h($tone) . '</span>';
            }
            $out .= '</div>';
        }
        return $out . '</div>';
    }
}
