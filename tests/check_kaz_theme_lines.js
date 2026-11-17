'use strict';
/*
 * KZ-24 (ADR 0117) — texto do tema com quebra de linha, no KAZEditor-Theme.
 *
 * O servidor (ThemeStrings) ja aceitava \n; o editor recusava (theme_lines) e
 * colava a quebra em espaco (themeClean herdava inlineClean). Agora o texto do
 * tema aceita quebra de linha no painel e na caixa da pagina (modo "box",
 * Enter quebra, Ctrl+Enter conclui), e o filtro nl2br passa a ser reconhecido.
 *
 * Este conferidor recorta de dev/admin/view/js/kaz-canvas.js as funcoes que
 * decidem e roda os casos de tests/fixtures/kaz_theme_lines.json:
 *   - themeClean  preserva \n, normaliza CRLF/CR, remove controles, teto de 2000;
 *   - THEME_REF   reconhece nl2br alem de upper/lower;
 *   - themeWhy    nao recusa mais texto com quebra de linha;
 *   - themeShown  nl2br devolve o texto cru (a quebra visual vem do pre-line na previa).
 * Depois confere o que nao se ve rodando (bloco KZ-24 no cabecalho, Enter no
 * inlineKey, pre-line no setElementText, chave de ajuda nos tres idiomas) e
 * estraga o recorte exigindo que os casos acusem.
 *
 *   node tests/check_kaz_theme_lines.js
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const ROOT = path.join(__dirname, '..');
const read = p => fs.readFileSync(path.join(ROOT, p), 'utf8');
const FIX = JSON.parse(read('tests/fixtures/kaz_theme_lines.json'));
let checks = 0;
function check(ok, what) { checks++; if (!ok) throw new Error('FAIL: ' + what); }
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

const source = read('dev/admin/view/js/kaz-canvas.js');
const START = source.indexOf('/* ---------------- KZ-21 (ADR 0114)');
const END = source.indexOf('/* ---------------- KZ-23 (ADR 0116)');
check(START > 0 && END > START, 'blocos KZ-21..KZ-23 encontrados em kaz-canvas.js');
const block = source.slice(START, END);

function cut(re) {
  const m = re.exec(block);
  check(m !== null, 'recorte encontrado: ' + re);
  return m[0];
}
const THEME_REF = cut(/  var THEME_REF = [^\n]+\n/);
const THEME_TEXT_MAX = cut(/  var THEME_KEY = [^\n]+\n/);
const consts = THEME_REF + THEME_TEXT_MAX + '  var THEME_TEXT_MAX = 2000;\n';
const fns = ['themeShown', 'themeWhy', 'themeClean'].map(n => cut(new RegExp('  function ' + n + '\\([^)]*\\) \\{[\\s\\S]*?\\n  \\}\\n'))).join('\n');
const box = { String, JSON, Object, Array, RegExp };
vm.createContext(box);
vm.runInContext(consts + fns + '\nthis.api = { themeShown: themeShown, themeWhy: themeWhy, themeClean: themeClean, THEME_REF: THEME_REF };', box);
const api = box.api;

FIX.clean.forEach(c => {
  let inp = c.in;
  if (c.times) inp = inp.repeat(c.times);
  if (c.with_nl) inp = inp + '\n';
  const got = api.themeClean(inp);
  if (c.out_ok === true) check(got.length <= 2000, 'themeClean aceitou ate o teto: ' + (c.in.slice(0, 8)));
  else if (c.out_ok === false) check(got.length > 2000 || got !== inp && got.length === 2000, 'themeClean cortou acima do teto');
  else check(got === c.out, 'themeClean(' + JSON.stringify(c.in.slice(0, 20)) + ') = ' + JSON.stringify(c.out && c.out.slice(0, 20)));
});
FIX.ref.forEach(c => {
  const m = api.THEME_REF.exec(c.expr);
  const got = m ? { key: m[1], filter: m[2] || '' } : { key: null, filter: '' };
  check(same(got, { key: c.key, filter: c.filter }), 'THEME_REF(' + c.expr + ') = ' + JSON.stringify(got));
});
FIX.why.forEach(c => {
  const got = api.themeWhy(true, true, true, { ok: true, lang: 'pt-br', shown: 'pt-br', text: c.text });
  check(got === c.why, 'themeWhy com texto ' + JSON.stringify(c.text.slice(0, 12)) + ' = ' + JSON.stringify(got));
});

/* O que nao se ve rodando. */
check(source.indexOf('KZ-24 (ADR 0117)') > 0, 'KZ-24 no cabecalho de kaz-canvas.js');
check(source.indexOf("inline.theme && !ev.ctrlKey && !ev.metaKey") > 0, 'Enter quebra linha em texto do tema (inlineKey)');
check(source.indexOf("value.indexOf('\\n') !== -1") > 0 && source.indexOf("'kaz-canvas__inline--box'") > 0, 'caixa vira "box" ao ganhar quebra (inlineInput/inlinePlace)');
check(source.indexOf("'white-space', 'pre-line'") > 0, 'previa com pre-line no setElementText');
check(source.indexOf('theme_lines') === -1 || source.indexOf("return 'theme_lines'") === -1, 'themeWhy nao recusa mais theme_lines');
['pt-br', 'en-us', 'es-eu'].forEach(lang => {
  const f = read('dev/admin/language/' + lang + '/design/theme.php');
  check(f.indexOf("text_canvas_theme_lines_help'") > 0, 'chave text_canvas_theme_lines_help em ' + lang);
});

/* Estragos: cada um deve ser acusado. */
const semNl = source.replace("replace(/\\r\\n?/g, '\\n')", "replace(/[\\r\\n]+/g, ' ')");
check(semNl !== source, 'estragado 1 aplicado (themeClean colando quebra)');
const box2 = { String, JSON, Object, Array, RegExp };
vm.createContext(box2);
const b2 = semNl.slice(semNl.indexOf('/* ---------------- KZ-21'), semNl.indexOf('/* ---------------- KZ-23'));
vm.runInContext(consts + ['themeShown', 'themeWhy', 'themeClean'].map(n => { const m = new RegExp('  function ' + n + '\\([^)]*\\) \\{[\\s\\S]*?\\n  \\}\\n').exec(b2); return m[0]; }).join('\n') + '\nthis.api = { themeClean: themeClean };', box2);
check(box2.api.themeClean('a\nb') === 'a b', 'sem preservar \\n, o caso de quebra acusa (estragado 1)');

console.log('check_kaz_theme_lines.js: ' + checks + ' conferências OK');
