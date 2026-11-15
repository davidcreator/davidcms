'use strict';
/*
 * KZ-10 (ADR 0103) — o atributo `pattern` dos campos de formulario.
 *
 * O navegador compila o valor de `pattern` como /^(?:valor)$/v. A flag `v` e
 * mais exigente que a `u`: hifen solto dentro de colchetes ("[a-z0-9_-]") e
 * erro. Quando o valor nao compila o navegador escreve um aviso no console e
 * IGNORA o atributo: o campo passa a aceitar qualquer coisa, sem ninguem ver.
 *
 * Este conferidor acha todo `pattern` escrito no produto (atributo nos .twig
 * e `setAttribute('pattern', '...')` nos .js), compila cada um do jeito do
 * navegador e confere, nos dois campos que repetem uma regra do servidor, que
 * aceitam e recusam os mesmos exemplos que ela.
 *
 *   node tests/check_form_patterns.js          confere
 *   node tests/check_form_patterns.js --list   so imprime o que achou (JSON)
 */
const fs = require('fs');
const path = require('path');
const ROOT = path.join(__dirname, '..');
let checks = 0;
function check(ok, what) { checks++; if (!ok) throw new Error('FAIL: ' + what); }

/** O valor compila como o navegador compila? Devolve '' ou a mensagem do erro. */
function browserError(value) {
  try { new RegExp('^(?:' + value + ')$', 'v'); return ''; } catch (e) { return String(e.message); }
}
function accepts(value, text) { return new RegExp('^(?:' + value + ')$', 'v').test(text); }

/** Texto de um literal de string do JavaScript (sem as aspas), com os escapes resolvidos. */
function unescapeJs(body) {
  const named = { n: '\n', t: '\t', r: '\r', b: '\b', f: '\f', v: '\v', 0: '\0' };
  return body.replace(/\\(.)/g, function (all, c) { return Object.prototype.hasOwnProperty.call(named, c) ? named[c] : c; });
}

/** Todo `pattern` do produto: [arquivo, linha, valor]. */
function collect() {
  const out = [];
  const skip = ['node_modules', 'vendor', 'storage', '.git'];
  (function walk(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const full = path.join(dir, entry.name);
      if (entry.isDirectory()) { if (skip.indexOf(entry.name) === -1) walk(full); continue; }
      const twig = /\.twig$/.test(entry.name), js = /\.js$/.test(entry.name) && !/\.min\.js$/.test(entry.name);
      if (!twig && !js) continue;
      const source = fs.readFileSync(full, 'utf8');
      const rel = path.relative(ROOT, full).split(path.sep).join('/');
      const line = function (at) { return source.slice(0, at).split('\n').length; };
      let m;
      if (twig) {
        const attr = /\spattern=(?:"([^"]*)"|'([^']*)')/g;
        while ((m = attr.exec(source))) out.push([rel, line(m.index), m[1] !== undefined ? m[1] : m[2]]);
      } else {
        const call = /setAttribute\(\s*(['"])pattern\1\s*,\s*(['"])((?:\\.|(?!\2)[^\\\n])*)\2\s*\)/g;
        while ((m = call.exec(source))) out.push([rel, line(m.index), unescapeJs(m[3])]);
      }
    }
  })(path.join(ROOT, 'dev'));
  return out.sort(function (a, b) { return a[0] < b[0] ? -1 : a[0] > b[0] ? 1 : a[1] - b[1]; });
}

const found = collect();
if (process.argv[2] === '--list') { console.log(JSON.stringify(found)); process.exit(0); }

// Node sem a flag `v` (anterior ao 20) nao consegue repetir o que o navegador faz.
try { new RegExp('a', 'v'); } catch (e) { console.log('Form pattern checker: SKIP (este node nao tem a flag v)'); process.exit(0); }

// 1. O conferidor enxerga o defeito que motivou a etapa, e aceita as formas certas.
check(browserError('[a-z0-9_-]{1,48}') !== '', 'hifen solto dentro de colchetes e erro para o navegador');
check(browserError('[a-z0-9_\\-]{1,48}') === '', 'hifen com barra compila');
check(browserError('[a-z0-9]+(-[a-z0-9]+)*') === '', 'hifen fora de colchetes compila');
check(new RegExp('^(?:[a-z0-9_-]{1,48})$', 'u').test('a-b'), 'a forma antiga compilava com a flag u: so a v pega o defeito');
check(unescapeJs('[a-z0-9_\\\\-]{1,48}') === '[a-z0-9_\\-]{1,48}', 'escape de literal JS resolvido');

// 2. Nada do que esta no produto e ignorado pelo navegador.
check(found.length >= 12, 'achou os campos com pattern (' + found.length + ')');
for (const [file, line, value] of found) {
  check(!/\{\{|\{%/.test(value), file + ':' + line + ' pattern montado pelo Twig nao pode ser conferido aqui: ' + value);
  const error = browserError(value);
  check(error === '', file + ':' + line + ' o navegador ignora este pattern: ' + value + '\n   ' + error);
  check(value !== '' && !/^\^|\$$/.test(value), file + ':' + line + ' pattern nao leva ^ nem $ (o navegador ja ancora): ' + value);
}

// 3. Os dois campos que repetem a regra do servidor /^[a-z0-9_-]{1,48}$/ (ID de regra de condicao; slug do tema).
const SERVER = /^[a-z0-9_-]{1,48}$/;
const SAMPLES = ['a', '0', 'a-b', 'a_b', '-', '_', 'regra-1_a', 'x'.repeat(48), 'x'.repeat(49), '', 'A', 'Regra', 'a b', ' a', 'a ', 'a.b', 'a/b',
  'a\\b', 'ação', 'a\nb', 'a--b', '[a]', 'a]', 'a^', 'a–b'];
const twins = found.filter(function (it) {
  return (it[0] === 'dev/admin/view/js/theme-condition-builder.js') ||
    (it[0] === 'dev/admin/view/template/design/kaz_theme_editor.twig' && /\{1,48\}/.test(it[2]));
});
check(twins.length === 2, 'os dois campos gemeos da regra do servidor foram achados (' + twins.length + ')');
for (const [file, , value] of twins) {
  check(value === '[a-z0-9_\\-]{1,48}', file + ' usa a forma com barra: ' + value);
  for (const sample of SAMPLES) {
    // Campo vazio nao passa pelo pattern no navegador (quem cobra e `required` ou o servidor).
    if (sample === '') continue;
    check(accepts(value, sample) === SERVER.test(sample), file + ' e o servidor concordam sobre ' + JSON.stringify(sample));
  }
}

console.log('Form pattern checker: ' + checks + ' PASS');
