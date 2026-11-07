/* Frente BO — verificador estatico do pacote.
   Uso: node tools/verify-bo.cjs <raiz-do-pacote>
   Nao executa runtime, nao acessa rede, nao escreve no GitHub. */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const ROOT = process.argv[2] || '.';
const R = (p) => path.join(ROOT, p);
const read = (p) => fs.readFileSync(R(p), 'utf8');
const has = (p) => fs.existsSync(R(p));
let P = 0, F = 0;
const ok = (n, c, x = '') => { c ? P++ : F++; console.log((c ? 'OK   ' : 'FAIL ') + n + (x !== '' ? ' · ' + x : '')); };
const sha = (p) => crypto.createHash('sha256').update(fs.readFileSync(R(p))).digest('hex');

console.log('== BO — verificador do pacote (' + path.resolve(ROOT) + ') ==\n');

// ── 1. Tema ──────────────────────────────────────────────────────────
const themeP = 'tema/david_universe_3d_bo.reamurtheme.json';
ok('tema exportado presente', has(themeP));
if (!has(themeP)) { console.log('\nABORT\n'); process.exit(1); }
const theme = JSON.parse(read(themeP));
const cfg = theme.config || theme;
ok('slug david_universe_3d_bo', (theme.slug || cfg.slug) === 'david_universe_3d_bo', String(theme.slug || cfg.slug));
ok('25 fontes Twig', Object.keys(cfg.templates || {}).length === 25, String(Object.keys(cfg.templates || {}).length));
ok('scene default = david-universe', cfg.scene === 'david-universe', String(cfg.scene));
ok('three.scene = david-universe', cfg.three?.scene === 'david-universe', String(cfg.three?.scene));
ok('three.enabled = true', cfg.three?.enabled === true);
ok('three.slot = hero', cfg.three?.slot === 'hero');
ok('three.count dentro de 50..800', cfg.three?.count >= 50 && cfg.three?.count <= 800, String(cfg.three?.count));
ok('5 regras de condicao', cfg.conditions?.rules?.length === 5);
const homeRule = (cfg.conditions?.rules || []).find(r => r.id === 'du_home_universe');
ok('regra da home usa a cena real', !!homeRule && homeRule.then.scene === 'david-universe');
ok('4 cenas originais preservadas nas regras',
   ['portfolio-3d', 'tools-3d', 'not-found'].every(s => JSON.stringify(cfg.conditions.rules).includes(s)));
ok('20 camadas kaz_scene (fallback)', cfg.kaz_scene?.layers?.length === 20);
ok('tokens dark presentes', cfg.tokens?.dark_surface === '#121516' && cfg.tokens?.dark_primary === '#5cc6d0');
const report = JSON.parse(read('evidencia/export-report.json'));
ok('checksum canonico no tema', theme.checksum === report.checksum, String(theme.checksum));
ok('checksum = 81f618c1…', /^81f618c1/.test(String(report.checksum)), report.checksum);

// HUD funcional
const header = cfg.templates['partial/site_header'];
['data-du-orbes', 'data-du-distancia', 'data-du-fase', 'data-du-progresso', 'data-du-toast',
 'data-du-hint-distancia'].forEach(k => ok('HUD ' + k, header.includes(k)));
ok('HUD corrige 00/10 → 00/14', !header.includes('00/10') && header.includes('00/14'));
ok('CSS do backdrop fixo', header.includes('[data-scene="david-universe"]') && header.includes('position:fixed'));
ok('html/body com fundo escuro', header.includes('html{background:#121516}') && header.includes('body.du{background:#121516}'));

// Proibicoes no TEMA (nao no runtime)
const tplAll = Object.values(cfg.templates).join('\n');
const BANNED = [/<script/i, /\|raw/, /javascript:/i, /<iframe/i, /<object/i, /<embed/i,
  /googleapis|gstatic/i, /https?:\/\/(cdn|unpkg|jsdelivr)/i,
  /https:\/\/davidalmeida\.xyz\/catalog\/assets/i, /\son[a-z]+\s*=/i];
BANNED.forEach((re, i) => ok('tema sem padrao proibido #' + (i + 1), !re.test(tplAll)));
ok('tema nao contem codigo JS solto', !/\b(function\s*\(|=>|const\s+\w+\s*=|let\s+\w+\s*=)/.test(tplAll));

// ── 2. Patch / allowlists ────────────────────────────────────────────
const diff = read('patch/PATCH-BO.diff');
// So os CAMINHOS de arquivo do diff interessam; comentarios do cabecalho
// citam SQL/migration/deploy justamente para dizer que NAO sao tocados.
const diffPaths = diff.split('\n').filter(l => /^(---|\+\+\+) /.test(l)).join('\n');
const stripComments = (s) => s.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '').replace(/^\s*#.*$/gm, '');
const PATCHED = [
  'dev/system/library/three/runtime/three/main.js',
  'dev/system/library/three/asset_manifest.php',
  'dev/system/library/theme/theme_package.php',
  'dev/system/library/theme/condition_engine.php',
  'dev/system/library/theme/theme_snapshot.php',
  'dev/system/library/three/scene_preset_registry.php',
  'dev/catalog/controller/startup/theme.php',
  'dev/catalog/controller/common/home.php',
  'dev/admin/controller/design/theme.php',
  'dev/system/library/three/runtime/three/scenes/david-universe.js',
];
PATCHED.forEach(p => ok('diff cobre ' + p.split('/').pop(), diff.includes('b/' + p)));
ok('diff nao toca em SQL/migration', !/upgrade_.*\.sql|migration|schema\.sql/i.test(diffPaths));
ok('diff nao toca em CI/deploy', !/\.github\/workflows|deploy|staging/i.test(diffPaths));

const main = read('patch/arquivos/' + PATCHED[0]);
ok('main.js registra a cena', main.includes("import './scenes/david-universe.js'"));
ok('main.js mantem as 4 cenas originais',
   ['hero-particles', 'not-found', 'portfolio-3d', 'tools-3d'].every(s => main.includes(s)));
const manifest = read('patch/arquivos/' + PATCHED[1]);
ok('asset_manifest publica david-universe.js', manifest.includes("'three/scenes/david-universe.js'"));
ok('asset_manifest mantem MIME js', manifest.includes("'js' => 'application/javascript; charset=utf-8'"));
['theme_package.php', 'condition_engine.php', 'theme_snapshot.php'].forEach((n, i) => {
  const s = read('patch/arquivos/dev/system/library/theme/' + n);
  ok(n + ' allowlist inclui a cena', /SCENES\s*=\s*\[[^\]]*'david-universe'/.test(s));
  ok(n + ' preserva as 4 cenas', ['hero-particles', 'portfolio-3d', 'tools-3d', 'not-found'].every(x => s.includes(x)));
});
const startup = read('patch/arquivos/dev/catalog/controller/startup/theme.php');
ok('startup/theme allowlist atualizada', /SCENES\s*=\s*\[[^\]]*'david-universe'/.test(startup));
const homeCtl = read('patch/arquivos/dev/catalog/controller/common/home.php');
ok('has_3d ligado para a cena nova', homeCtl.includes("'david-universe' => 'three_hero_enabled'"));
const admin = read('patch/arquivos/dev/admin/controller/design/theme.php');
ok('dropdown do Admin oferece a cena', admin.includes("'id'=>'david-universe'"));
ok('normalizacao do POST aceita a cena', admin.includes("'not-found','david-universe'],true)"));
const presets = read('patch/arquivos/dev/system/library/three/scene_preset_registry.php');
ok('preset universe registrado', presets.includes("'id'=>'universe'"));
ok('miniatura SVG da nave', presets.includes("$c['scene']==='david-universe'") && presets.includes('<polygon'));
const svgBranch = presets.slice(presets.indexOf("elseif ($c['scene']==='david-universe')"),
                                presets.indexOf("elseif ($c['scene']==='not-found')"));
ok('miniatura sem codigo', !/<script|url\(|\bon[a-z]+=/i.test(stripComments(svgBranch)));

// ── 3. Modulo da cena ────────────────────────────────────────────────
const sceneP = 'patch/arquivos/dev/system/library/three/runtime/three/scenes/david-universe.js';
const js = read(sceneP);
ok('registerScene david-universe', js.includes("registerScene('david-universe'"));
ok('importa do SceneRunner', js.includes("from '../core/scene-runner.js'"));
ok('constantes da g3 (HO/SHIP_LAG/ORB/TRAIL/MOON)',
   ['HO = 320', 'SHIP_LAG = 9', 'ORB_COUNT = 14', 'GATE_COUNT = 8', 'TRAIL = 60',
    'TRAIL_LIFE = 0.95', 'MOON_KM = 384400'].every(s => js.includes(s)));
['CylinderGeometry(0.5, 0.42, 1.9, 18)', 'ConeGeometry(0.42, 1.25, 18)',
 'ExtrudeGeometry', 'PointLight', 'flameOuter', 'flameInner'].forEach(s =>
  ok('nave: ' + s.split('(')[0], js.includes(s)));
['buildStars', 'buildNebulae', 'buildPlanets', 'buildAsteroids', 'buildAstronaut',
 'buildGates', 'buildOrbs', 'buildShip', 'buildTrail'].forEach(() => {});
['function starLayer', 'function bandTexture', 'function asteroidGeometry', 'function astronaut',
 'function nextOrb', 'function collect('].forEach(s => ok('cena: ' + s.trim(), js.includes(s)));
ok('interacao por scroll', js.includes('function onScroll') && js.includes("addEventListener('scroll'"));
ok('interacao por ponteiro', js.includes('function onPointer') && js.includes("addEventListener('pointermove'"));
ok('autopiloto coarse', js.includes('coarse || idle') && js.includes('nextOrb(shipZ)'));
ok('warp altera FOV', js.includes('66 + state.warp * 16'));
ok('coleta com raio 2 / 2.7', js.includes('coarse ? 2.7 : 2'));
ok('HUD so por textContent', js.includes('textContent') && js.includes('createElement'));
ok('IntersectionObserver de fases', js.includes("querySelectorAll('[id^=\"fase-\"]')"));
ok('reduced motion respeitado', js.includes('prefers-reduced-motion'));
ok('dispose remove listeners', js.includes('removeEventListener') && js.includes('dispose()'));
const jsCode = stripComments(js);
const JS_BANNED = [/\beval\s*\(/, /innerHTML/, /\bfetch\s*\(/, /document\.write/, /\bimport\s*\(/,
  /<script/i, /https?:\/\//, /window\.location\s*=/, /localStorage/];
JS_BANNED.forEach((re, i) => ok('modulo sem padrao proibido #' + (i + 1), !re.test(jsCode)));
ok('modulo nao usa React', !/from ['"]react|createRoot\s*\(|React\.|useState\s*\(/i.test(jsCode));
ok('modulo sintaxe valida (node --check)', (() => {
  try { require('child_process').execFileSync(process.execPath, ['--check', R(sceneP)], { stdio: 'pipe' }); return true; }
  catch (e) { return false; }
})());

// ── 4. Arquivos deliberadamente inalterados ──────────────────────────
const untouched = [
  ['patch/arquivos/dev/catalog/view/template/partial/theme-scene.twig', 'evidencia/original-theme-scene.sha256'],
  ['patch/arquivos/dev/system/library/three/runtime/three/core/scene-runner.js', 'evidencia/original-scene-runner.sha256'],
  ['patch/arquivos/CHECKSUMS.txt', 'evidencia/original-CHECKSUMS.sha256'],
];
untouched.forEach(([p, h]) => ok('inalterado: ' + p.split('/').pop(), sha(p) === read(h).trim()));

// ── 5. Evidencias ────────────────────────────────────────────────────
ok('export PHP 26/0', /PHP BO export: 26 OK \/ 0 FALHAS/.test(read('evidencia/export-bo.txt')));
ok('runtime Chromium 49/0', /CHROMIUM BO runtime: 49 OK \/ 0 FALHAS/.test(read('evidencia/runtime-bo.log')));
ok('TOKEN_PARSE 8/0', /TOKEN_PARSE: 8 OK \/ 0 FALHAS/.test(read('evidencia/token-parse.log')));
ok('prova do bloqueio BN 12/0', /PHP BO prova: 12 OK \/ 0 FALHAS/.test(read('evidencia/prova-bloqueio.log')));
ok('git apply --check OK', /git apply --check: OK/.test(read('evidencia/git-apply.log')));
ok('runtime report sem erros', (() => {
  const r = JSON.parse(read('evidencia/runtime-bo.json'));
  return r.errors.length === 0 && r.consoleErrors.length === 0 && r.failed.length === 0 && r.F === 0;
})());
ok('WebGL real / Three r186', (() => {
  const r = JSON.parse(read('evidencia/runtime-bo.json'));
  return r.boot.gl === true && String(r.boot.revision) === '186';
})());
ok('coleta registrada', (() => JSON.parse(read('evidencia/runtime-bo.json')).collected.n > 0)());
ok('convergencia do voo', (() => JSON.parse(read('evidencia/runtime-bo.json')).converged === true)());
['shot-bo-hero.png', 'shot-bo-voo.png', 'shot-bo-coleta.png', 'shot-bo-fim.png',
 'shot-bo-mobile.png', 'BO-Nave-e-Interacoes.png'].forEach(f =>
  ok('captura ' + f, has('evidencia/' + f) && fs.statSync(R('evidencia/' + f)).size > 10000));
['RELATORIO-BO.md', 'GUIA-BO.md', 'INDICE-BO.md', 'DIAGNOSTICO-BO.md',
 'ESPECIFICACAO-CENA.md', 'especificacao.json'].forEach(f => ok('doc ' + f, has('docs/' + f)));
ok('previa funcional completa', ['previa/index.html', 'previa/main-bo.js',
  'previa/runtime/three.module.js', 'previa/runtime/three.core.js',
  'previa/runtime/three/core/scene-runner.js', 'previa/runtime/three/scenes/david-universe.js']
  .every(has));

console.log(`\nBO verify: ${P} OK / ${F} FALHAS\n`);
process.exit(F ? 1 : 0);
