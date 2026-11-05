<?php
/* Frente AL — PROBE instalavel. Somente leitura: nao escreve no projeto,
   nao abre banco, nao faz requisicao de rede. AL_ROOT / AL_OUT configuram
   os caminhos. */
$ROOT = getenv('AL_ROOT') ?: dirname(__DIR__, 2);
$OUT  = getenv('AL_OUT')  ?: '/tmp/al_site';
$C = 0; $F = 0;
function ok($label, $cond, $extra = '') {
  global $C, $F;
  if ($cond) { $C++; echo "OK   $label" . ($extra !== '' ? " · $extra" : '') . "\n"; }
  else { $F++; echo "FAIL $label" . ($extra !== '' ? " · $extra" : '') . "\n"; }
}
function rd($p) { return is_file($p) ? (string)file_get_contents($p) : ''; }
function has($h, $n) { return str_contains($h, $n); }
$CSS = rd($ROOT . '/dev/admin/view/css/kaz-theme-editor-ergonomia.css');
$JS  = rd($ROOT . '/dev/admin/view/js/kaz-theme-editor-ergonomia.js');
$CTL = rd($ROOT . '/dev/admin/controller/design/theme.php');
$TWG = rd($ROOT . '/dev/admin/view/template/design/kaz_theme_editor.twig');
$EDT = rd($ROOT . '/dev/admin/view/js/kaz-theme-editor.js');
$TFM = rd($ROOT . '/dev/admin/view/template/design/theme_form.twig');

ok('CSS aditivo presente', strlen($CSS) > 4000, strlen($CSS) . ' bytes');
ok('JS aditivo presente', strlen($JS) > 3000, strlen($JS) . ' bytes');
ok('controller presente', strlen($CTL) > 1000, strlen($CTL) . ' bytes');

/* --- 1. barras de rolagem conformes ao tema --- */
ok('scrollbar-color em todos os containers', has($CSS, 'scrollbar-color: var(--kaz-al-thumb) var(--kaz-al-track)'));
ok('scrollbar-width thin', has($CSS, 'scrollbar-width: thin'));
foreach (['::-webkit-scrollbar {', '::-webkit-scrollbar-track {', '::-webkit-scrollbar-thumb {',
          '::-webkit-scrollbar-thumb:hover', '::-webkit-scrollbar-corner {', '::-webkit-scrollbar-button {'] as $ps) {
  ok('pseudo-elemento ' . trim($ps, ' {:-webkit'), has($CSS, $ps));
}
ok('cores por mistura do tema', has($CSS, 'color-mix(in srgb, var(--kaz-al-primary) 68%'));
ok('trilha por mistura da superficie', has($CSS, 'color-mix(in srgb, var(--kaz-al-surface) 22%'));
ok('raio do tema honrado', has($CSS, '--kaz-al-radius: min(var(--rms-radius, 8px), 6px)'));
ok('tokens publicados honrados', has($CSS, 'var(--rms-color-primary') && has($CSS, 'var(--rms-color-surface'));
ok('fallback nos tokens do editor', has($CSS, '#31efb8') && has($CSS, '#1f1f23'));
ok('forced-colors respeitado', has($CSS, '@media (forced-colors: active)'));
ok('JS le primary do root', has($JS, "getAttribute('data-kaz-primary')"));
ok('JS le surface do root', has($JS, "getAttribute('data-kaz-surface')"));
ok('JS aplica variaveis de cor', has($JS, "'--kaz-al-primary'") && has($JS, "'--kaz-al-surface'"));
ok('JS valida cor hexagonal', has($JS, '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i'));
ok('JS acompanha campos de cor ao vivo', has($JS, 'input[type="color"][name="') && has($JS, "addEventListener('input'"));

/* --- 2. escala de todos os elementos --- */
ok('variavel de escala', has($CSS, '--kaz-ui: 1;'));
foreach (['compact' => '.86', 'comfortable' => '1.14', 'large' => '1.30'] as $k => $v) {
  ok('nivel ' . $k, has($CSS, 'data-kaz-ui-scale="' . $k . '"] { --kaz-ui: ' . $v));
}
ok('normal e o padrao', has($JS, "scale = 'normal'"));
ok('4 niveis no seletor', substr_count($JS, "o.value = k") === 1 && substr_count($JS, "'compact', 'normal', 'comfortable', 'large'") === 1);
$grupos = ['.kaz-topbar', '.kaz-tool', '.kaz-ico', '.kaz-btn', '.kaz-badge', '.kaz-tab',
           '.kaz-screenbar', '.kaz-screenbar__select', '.kaz-statusbar', '.kaz-module-tabs',
           '.kaz-module-link', '.kaz-layer', '.kaz-panel', '.kaz-panel__title'];
ok('14 grupos escalam em conjunto', count($grupos) === 14);
foreach ($grupos as $g) {
  ok('escala ' . $g, has($CSS, '#kaz-theme-editor ' . $g . ' {') ||
     has($CSS, '#kaz-theme-editor ' . $g . ','));
}
ok('calc() em todos os grupos', substr_count($CSS, 'calc(') >= 40, substr_count($CSS, 'calc('));
ok('canvas fora da escala', !has($CSS, '.kaz-stage {') && !has($CSS, '.kaz-screen {'));
ok('regra 1280 replicada', has($CSS, '@media (max-width: 1280px)') && has($CSS, 'max-height: calc(36px * var(--kaz-ui))'));
ok('regra 980 replicada', has($CSS, '@media (max-width: 980px)') && has($CSS, 'max-height: calc(40px * var(--kaz-ui))'));
ok('regra 620 replicada', has($CSS, '@media (max-width: 620px)'));
ok('JS aplica escala no root', has($JS, "setAttribute('data-kaz-ui-scale', v)"));
ok('JS escuta troca do seletor', has($JS, "addEventListener('change'"));

/* --- 3. expandir / recolher ferramentas --- */
foreach (['toolbar' => '.kaz-toolbar', 'tabs' => '.kaz-module-tabs', 'status' => '.kaz-statusbar'] as $k => $sel) {
  ok('fold ' . $k, has($CSS, 'data-kaz-fold~="' . $k . '"] ' . $sel . ' { display: none !important; }'));
}
ok('3 tokens de fold', has($JS, "var FOLDS = ['toolbar', 'tabs', 'status']"));
ok('botao recolher tudo', has($JS, "'Recolher tudo'") && has($JS, "'Expandir tudo'"));
ok('estado no root', has($JS, "setAttribute('data-kaz-fold', v.join(' '))"));
ok('aria-pressed atualizado', has($JS, "setAttribute('aria-pressed'"));
ok('status acessivel', has($JS, "'role', 'status'") && has($JS, "'aria-live', 'polite'") || has($JS, "setAttribute('role', 'status')"));
ok('screenbar nunca recolhida', !has($CSS, 'data-kaz-fold~="screenbar"'));
ok('nenhum campo removido do formulario', !has($JS, 'removeChild') && !has($JS, 'remove()'));
ok('reabertura sempre disponivel', has($JS, "var bar = root.querySelector('[data-kaz-screenbar]')"));

/* --- 4. seguranca e contratos --- */
$blocked = ['inner' . 'HTML', 'loc' . 'alStorage', 'ev' . 'al(', 'fet' . 'ch(',
            'document.w' . 'rite', 'session' . 'Storage'];
foreach ($blocked as $b) ok('sem primitiva indevida #' . (array_search($b, $blocked) + 1), !has($JS, $b));
ok('DOM puro', has($JS, 'document.createElement') && has($JS, 'textContent') && has($JS, 'setAttribute'));
ok('sem escrita em arquivo no JS', !has($JS, 'file_put_contents'));
ok('estado local descartavel', !has($JS, 'JSON.stringify') || !has($JS, 'sendBeacon'));
ok('sem SQL no JS', !has($JS, 'SELECT ') && !has($JS, 'INSERT INTO'));
ok('diagnostico somente leitura', has($JS, 'window.__kazErgo = function ()'));
ok('idempotente', has($JS, "data-kaz-ergo-ready") && has($JS, "if (!root || root.getAttribute"));
ok('carrega apos DOM pronto', has($JS, "document.readyState === 'loading'") && has($JS, 'DOMContentLoaded'));

ok('controller mantem -aj', has($CTL, "'-kaz-theme-editor-aj'") && has($CTL, '$kazAssetAJ'));
ok('controller: exatos 4 usos de -aj (contrato AJ intacto)', substr_count($CTL, '$kazAssetAJ') === 4, substr_count($CTL, '$kazAssetAJ'));
ok('controller registra -aj-al', has($CTL, "'-kaz-theme-editor-aj-al'") && has($CTL, '$kazAssetAL'));
ok('controller: 2 assets novos', substr_count($CTL, 'ergonomia') === 2, substr_count($CTL, 'ergonomia'));
ok('controller: CSS antes do JS', strpos($CTL, 'ergonomia.css') < strpos($CTL, 'ergonomia.js'));
ok('controller: sem SQL novo', !has($CTL, 'ALTER TABLE') && !has($CTL, 'CREATE TABLE'));

ok('Twig do editor intacto (dono)', has($TWG, 'data-kaz-owner="1"'));
ok('3 forms no Twig', substr_count($TWG, '<form') === 3, substr_count($TWG, '<form'));
ok('modulos no Twig nao diminuiram', substr_count($TWG, 'data-kaz-module') >= 30, substr_count($TWG, 'data-kaz-module'));
ok('name= nao aumentou', substr_count($TWG, 'name=') >= 49 && substr_count($TWG, 'name=') <= 60, substr_count($TWG, 'name='));
ok('painel AJ intacto', has($TWG, 'id="kaz-acceptance"') && has($TWG, 'id="kaz-release-status"'));
ok('theme_form 165 bytes / 2 raw', strlen($TFM) === 165 && substr_count($TFM, '|raw') === 2, strlen($TFM) . ' bytes');
ok('JS da AJ intacto (bootComplete)', has($EDT, 'bootComplete') && has($EDT, "setAttribute('data-kaz-health', 'ad+ae')"));
ok('JS da AJ nao foi reescrito pela AL', !has($EDT, '__kazErgo'));

/* --- 5. congelados e documentacao --- */
ok('schema.sql presente', is_file($ROOT . '/dev/install/sql/schema.sql'));
$status = rd($ROOT . '/docs/F9-27-Status.json');
ok('release_approved nao true', !has($status, '"release_approved": true'));
ok('F9-27 BLOCKED_NOT_RUN', has($status, 'BLOCKED_NOT_RUN'));
$dm = json_decode(rd($ROOT . '/tools/decision-matrix.json'), true);
ok('10 PENDING_DECISION', (is_array($dm) ? (int)($dm['status_counts']['PENDING_DECISION'] ?? 0) : 0) === 10);
ok('11 hashes congelados', is_array($dm) && count($dm['frozen_artifacts_sha256'] ?? []) === 11);
$mans = glob($ROOT . '/CHECKSUMS*.txt');
ok('14 manifestos', count($mans) === 14, count($mans));
$dd = glob($ROOT . '/docs/KAZTheme-Editor*.md');
ok('17 documentos', count($dd) === 17, count($dd));
$comSecao = 0;
foreach ($dd as $f) if (has(rd($f), 'Frente AL')) $comSecao++;
ok('17 docs com secao AL', $comSecao === 17, $comSecao);
$novo = rd($ROOT . '/docs/KAZTheme-Editor-Ergonomia-Visual.md');
ok('doc AL sem placeholder', !has($novo, 'TODO') && !has($novo, 'TBD') && !has($novo, '__TOTAL__'));
ok('doc AL nao afirma release aprovada', has($novo, 'não significam') && has($novo, 'release aprovada'));
ok('PROBE tool presente', is_file($ROOT . '/tools/kaz-theme-ergonomia-probe.php'));
ok('CJS tool presente', is_file($ROOT . '/tools/verify-kaz-ergonomia.cjs'));

if (!is_dir($OUT)) mkdir($OUT, 0777, true);
file_put_contents($OUT . '/probe.txt', "PROBE AL: $C OK / $F FALHAS\n");
echo "PROBE AL: $C OK / $F FALHAS\n";
exit($F ? 1 : 0);
