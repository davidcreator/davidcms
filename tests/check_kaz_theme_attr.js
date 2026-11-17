'use strict';
/*
 * KZ-23 (ADR 0116) — texto do tema dentro de atributo, no KAZEditor-Theme.
 *
 * Um atributo do mapa de elementos (KZ-05) cujo valor e so `{{ theme_text.chave }}`
 * (alt, aria-label, placeholder, data-du-*) passa a ser editavel no painel como
 * texto do tema: a escrita vai para o JSON dos textos (`strings_json`), no idioma
 * do canvas, e a fonte do tema nao muda.
 *
 * Este conferidor carrega dev/admin/view/js/kaz-element-attrs.js inteiro (ele
 * exporta para globalThis.KazElementAttrs) e roda os casos de
 * tests/fixtures/kaz_theme_attr.json sobre themeAttrName e themeAttr. Depois confere
 * o que nao se ve rodando: o bloco KZ-23 e a delegacao de input em kaz-canvas.js,
 * e as chaves novas nos tres idiomas. No fim estraga o arquivo de regras de duas
 * formas e exige que os casos acusem.
 *
 *   node tests/check_kaz_theme_attr.js
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const ROOT = path.join(__dirname, '..');
const read = p => fs.readFileSync(path.join(ROOT, p), 'utf8');
const FIX = JSON.parse(read('tests/fixtures/kaz_theme_attr.json'));
let checks = 0;
function check(ok, what) { checks++; if (!ok) throw new Error('FAIL: ' + what); }
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

function loadAttrs(source) {
  const box = { console, globalThis: {} };
  box.window = undefined;
  vm.createContext(box);
  vm.runInContext(source, box, { filename: 'kaz-element-attrs.js' });
  return box.globalThis.KazElementAttrs;
}

const attrsSrc = read('dev/admin/view/js/kaz-element-attrs.js');
const ATTRS = loadAttrs(attrsSrc);
check(!!ATTRS, 'kaz-element-attrs.js exportou KazElementAttrs');
check(typeof ATTRS.themeAttr === 'function' && typeof ATTRS.themeAttrName === 'function', 'themeAttr e themeAttrName exportadas');

FIX.names.forEach(c => {
  check(ATTRS.themeAttrName(c.name) === c.ok, 'themeAttrName(' + JSON.stringify(c.name) + ') = ' + c.ok);
});
FIX.attrs.forEach(c => {
  const got = ATTRS.themeAttr(c.a);
  check(same(got, c.r), 'themeAttr(' + JSON.stringify(c.a && c.a[0]) + ',' + JSON.stringify(c.a && c.a[6]) + ') = ' + JSON.stringify(c.r));
});

/* O que nao se ve rodando: bloco KZ-23 e delegacao no canvas, chaves nos idiomas. */
const canvas = read('dev/admin/view/js/kaz-canvas.js');
check(canvas.indexOf('KZ-23 (ADR 0116): texto do tema dentro de atributo') > 0, 'bloco KZ-23 presente em kaz-canvas.js');
check(canvas.indexOf("data-kaz-canvas-theme-attr") > 0, 'delegacao do campo theme-attr presente em kaz-canvas.js');
check(canvas.indexOf('paintThemeAttrField') > 0 && canvas.indexOf('onThemeAttrInput') > 0 && canvas.indexOf('setThemeAttr') > 0, 'funcoes do KZ-23 presentes');
['pt-br', 'en-us', 'es-eu'].forEach(lang => {
  const f = read('dev/admin/language/' + lang + '/design/theme.php');
  check(f.indexOf("text_canvas_theme_attr'") > 0, 'chave text_canvas_theme_attr em ' + lang);
  check(f.indexOf("text_canvas_theme_attr_help'") > 0, 'chave text_canvas_theme_attr_help em ' + lang);
});

/* Estragos: cada um deve ser acusado pelos casos. */
const broken1 = attrsSrc.replace('if (!themeAttrName(a[0])) return null;', '');
const A1 = loadAttrs(broken1);
const falhou1 = FIX.attrs.some(c => !same(A1.themeAttr(c.a), c.r));
check(falhou1, 'sem a checagem do nome, os casos de nome proibido passam a casar (estragado 1)');
const broken2 = attrsSrc.replace("a[1] !== 'variable'", "a[1] !== 'fixed'");
const A2 = loadAttrs(broken2);
const falhou2 = FIX.attrs.some(c => !same(A2.themeAttr(c.a), c.r));
check(falhou2, 'sem a checagem do tipo variable, os casos fixed passam a casar (estragado 2)');

console.log('check_kaz_theme_attr.js: ' + checks + ' conferências OK');
