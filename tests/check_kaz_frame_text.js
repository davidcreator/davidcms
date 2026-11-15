'use strict';
/*
 * KZ-11 (ADR 0104) — a moldura do editor de tema fala o idioma do admin.
 *
 * "Moldura" e o que fica sempre na tela: barra de cima, barra da tela, abas
 * dos paineis laterais, navegacao dos modulos e rodape. Ate a KZ-10 esses
 * textos estavam escritos em portugues dentro do Twig e do script; quem usava
 * o admin em ingles ou espanhol via a tela metade em cada idioma.
 *
 * Este conferidor nao deixa o texto fixo voltar:
 *   1. nas regioes da moldura do Twig, nenhum texto visivel nem dica
 *      (title, aria-label, placeholder, alt, data-kaz-tablabel) pode ter
 *      letras fora de `{{ ... }}`, salvo nomes proprios e teclas de atalho;
 *   2. toda chave `text_kaz_*` usada no Twig existe no arquivo de idioma, e
 *      toda chave do arquivo e usada (no Twig ou no script);
 *   3. o script so pede chaves que o controller entrega, e o texto de reserva
 *      escrito nele e igual ao do arquivo pt-br;
 *   4. a funcao KazEditorText se comporta como o combinado (reserva quando
 *      falta a frase, `%s`, leitura so quando o elemento ja existe).
 *
 *   node tests/check_kaz_frame_text.js          confere
 *   node tests/check_kaz_frame_text.js --list   imprime o que achou (JSON)
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const ROOT = path.join(__dirname, '..');
const TWIG = 'dev/admin/view/template/design/kaz_theme_editor.twig';
const SCRIPT = 'dev/admin/view/js/kaz-theme-editor.js';
const CONTROLLER = 'dev/admin/controller/design/theme.php';
const PT = 'dev/admin/language/pt-br/design/theme.php';
let checks = 0;
function check(ok, what) { checks++; if (!ok) throw new Error('FAIL: ' + what); }
function read(file) { return fs.readFileSync(path.join(ROOT, file), 'utf8'); }

/* Nomes proprios, formatos e teclas: iguais em qualquer idioma. */
const SAME_EVERYWHERE = [
  'KAZEditor-Theme', 'kaztheme-scene/v1', 'Three.js',
  'Ctrl+Shift+Z', 'Ctrl+Z', 'Ctrl+D', '(Delete)',
  '(V)', '(H)', '(R)', '(T)', '(B)', '(C)', '(S)', '(F)',
  'Desktop', 'Laptop', 'Tablet', 'Mobile', 'Widescreen',
  'X 0 · Y 0'
];
const HINTS = ['title', 'aria-label', 'placeholder', 'alt', 'data-kaz-tablabel'];

/* Regioes da moldura: [nome, onde comeca, onde termina (primeira ocorrencia depois do comeco)]. */
const REGIONS = [
  ['barra de cima', '<header class="kaz-topbar">', '</header>'],
  ['barra da tela', '<div class="kaz-screenbar kaz-screenbar--outer"', '<noscript>'],
  ['abas do painel esquerdo', '<aside class="kaz-sidebar kaz-sidebar--left"', '</div>'],
  ['navegacao dos modulos e palco', '<div class="kaz-modules-nav', '<div class="kaz-modules" data-kaz-modules'],
  ['abas do painel direito', '<aside class="kaz-sidebar kaz-sidebar--right"', '</div>'],
  ['rodape', '<footer class="kaz-statusbar">', '</footer>']
];

function region(source, name, from, to) {
  const a = source.indexOf(from);
  check(a !== -1, 'regiao "' + name + '": comeco nao encontrado (' + from + ')');
  check(source.indexOf(from, a + 1) === -1, 'regiao "' + name + '": comeco aparece mais de uma vez');
  const b = source.indexOf(to, a + from.length);
  check(b !== -1, 'regiao "' + name + '": fim nao encontrado (' + to + ')');
  return source.slice(a, b);
}

/* Tira do trecho o que nao e texto fixo: comentarios, saidas e comandos do Twig, desenho dos icones. */
function withoutTwig(piece) {
  return piece
    .replace(/\{#[\s\S]*?#\}/g, '')
    .replace(/\{\{[\s\S]*?\}\}/g, '')
    .replace(/\{%[\s\S]*?%\}/g, '')
    .replace(/(<svg\b[^>]*>)[\s\S]*?<\/svg>/g, '$1</svg>');
}
function withoutSame(text) {
  let out = text;
  SAME_EVERYWHERE.forEach(function (word) { out = out.split(word).join(' '); });
  return out;
}
/* Textos fixos com letras em um trecho ja sem Twig. */
function fixedTexts(piece) {
  const found = [];
  const clean = withoutTwig(piece);
  clean.replace(/<[a-zA-Z][^>]*>/g, function (tag) {
    HINTS.forEach(function (attr) {
      const m = new RegExp('\\s' + attr + '="([^"]*)"').exec(tag);
      if (m && /\p{L}/u.test(withoutSame(m[1]))) found.push(attr + '="' + m[1] + '"');
    });
    return tag;
  });
  clean.replace(/<[^>]*>/g, '\u0000').split('\u0000').forEach(function (text) {
    const t = text.replace(/\s+/g, ' ').trim();
    if (t !== '' && /\p{L}/u.test(withoutSame(t))) found.push(t);
  });
  return found;
}

/* Lista de chaves de uma constante do controller. */
function constantKeys(source, name) {
  const m = new RegExp('private const ' + name + ' = \\[([\\s\\S]*?)\\];').exec(source);
  check(m !== null, 'constante ' + name + ' nao encontrada no controller');
  return (m[1].match(/'([a-z0-9_]+)'/g) || []).map(function (s) { return s.slice(1, -1); });
}
/* Frases `text_kaz_*` de um arquivo de idioma. */
function languageTexts(source) {
  const out = {};
  const re = /^\$_\['text_kaz_([a-z0-9_]+)'\]\s*=\s*'((?:[^'\\]|\\.)*)';/gm;
  let m;
  while ((m = re.exec(source))) out[m[1]] = m[2].replace(/\\(['\\])/g, '$1');
  return out;
}
/* Texto de um literal de string com aspas simples. */
function literal(body) { return body.replace(/\\(.)/g, '$1'); }

const twig = read(TWIG);
const script = read(SCRIPT);
const controller = read(CONTROLLER);
const pt = languageTexts(read(PT));
const frameKeys = Object.keys(pt);
const scriptKeys = constantKeys(controller, 'KAZ_FRAME_SCRIPT');

// ---- 1. nenhuma frase fixa na moldura
const report = { regions: {}, twigKeys: [], scriptCalls: {} };
REGIONS.forEach(function (r) {
  const found = fixedTexts(region(twig, r[0], r[1], r[2]));
  report.regions[r[0]] = found;
  check(found.length === 0, 'texto fixo na regiao "' + r[0] + '": ' + JSON.stringify(found.slice(0, 5)));
});
const panels = twig.match(/<section class="kaz-module"[^>]*>/g) || [];
check(panels.length >= 14, 'esperava ao menos 14 paineis de modulo, achei ' + panels.length);
const fixedPanels = fixedTexts(panels.join('\n'));
report.regions['nome dos paineis de modulo'] = fixedPanels;
check(fixedPanels.length === 0, 'nome fixo em painel de modulo: ' + JSON.stringify(fixedPanels.slice(0, 5)));
const links = twig.match(/<button class="kaz-module-link[^>]* data-kaz-module="[^>]*>/g) || [];
check(links.length === 16, 'esperava 16 botoes de modulo, achei ' + links.length);
links.forEach(function (tag) {
  check(/data-kaz-tablabel="(\{\{ [a-z0-9_]+\|e\('html_attr'\) \}\}|Three\.js)"/.test(tag), 'botao de modulo sem nome vindo do idioma: ' + tag.slice(0, 90));
});
// o detector precisa mesmo enxergar texto fixo (senao as conferencias acima nao valem nada)
check(fixedTexts('<button title="Mover (V)" aria-label="{{ a }}">{{ b }}</button>').join('|') === 'title="Mover (V)"', 'detector: dica fixa');
check(fixedTexts('<span>Salvo</span><b>{{ x }} ↺</b><i>Desktop · 1440 × 900</i>').join('|') === 'Salvo', 'detector: texto fixo');
check(fixedTexts('<svg aria-label="Desenho"><title>Dentro</title></svg>').join('|') === 'aria-label="Desenho"', 'detector: so a etiqueta do svg conta');

// ---- 2. chaves do Twig e da lista
const twigKeys = Array.from(new Set((twig.match(/\btext_kaz_[a-z0-9_]+/g) || []).map(function (k) { return k.slice(9); }))).sort();
report.twigKeys = twigKeys;
check(frameKeys.length > 100, 'chaves text_kaz_* lidas do pt-br: ' + frameKeys.length);
check(new Set(scriptKeys).size === scriptKeys.length, 'KAZ_FRAME_SCRIPT tem chave repetida');
twigKeys.forEach(function (k) { check(frameKeys.indexOf(k) !== -1, 'o Twig usa text_kaz_' + k + ', que nao existe no arquivo de idioma'); });
scriptKeys.forEach(function (k) { check(frameKeys.indexOf(k) !== -1, 'KAZ_FRAME_SCRIPT tem "' + k + '", que nao existe no arquivo de idioma'); });
frameKeys.forEach(function (k) {
  check(twigKeys.indexOf(k) !== -1 || scriptKeys.indexOf(k) !== -1, 'chave "' + k + '" nao e usada no Twig nem vai para o script');
  check(typeof pt[k] === 'string' && pt[k] !== '', 'chave "' + k + '" sem frase em pt-br');
});
check(/data-kaz-labels="\{\{ kaz_labels_json\|e\('html_attr'\) \}\}"/.test(twig), 'o Twig entrega as frases do script em data-kaz-labels');
check(/\$data\['kaz_labels_json'\]=json_encode\(\$kazLabels,/.test(controller), 'o controller monta kaz_labels_json');
check(controller.indexOf('$data=array_replace($this->language->all(),$data);') !== -1, 'o controller entrega ao Twig todas as chaves do idioma');

// ---- 3. o que o script pede
function objectLiteral(name) {
  const m = new RegExp('var ' + name + ' = \\{([\\s\\S]*?)\\};').exec(script);
  check(m !== null, 'objeto ' + name + ' nao encontrado no script');
  const out = {};
  m[1].replace(/([a-z]+):\s*'((?:[^'\\]|\\.)*)'/g, function (all, k, v) { out[k] = literal(v); return all; });
  return out;
}
const calls = {};
script.replace(/(?:\bTXT|window\.KazEditorText)\('([a-z0-9_]+)',\s*'((?:[^'\\]|\\.)*)'/g, function (all, k, v) {
  const text = literal(v);
  check(!(k in calls) || calls[k] === text, 'o script tem dois textos de reserva para "' + k + '"');
  calls[k] = text;
  return all;
});
const scales = objectLiteral('LABELS'), folds = objectLiteral('FOLD_LABELS');
check(/TXT\('scale_' \+ k, LABELS\[k\] \|\| k\)/.test(script), 'nome do tamanho vem de scale_<tamanho>');
check(/TXT\('fold_' \+ f, FOLD_LABELS\[f\]\)/.test(script), 'nome do grupo vem de fold_<grupo>');
Object.keys(scales).forEach(function (k) { calls['scale_' + k] = scales[k]; });
Object.keys(folds).forEach(function (k) { calls['fold_' + k] = folds[k]; });
report.scriptCalls = calls;
Object.keys(calls).forEach(function (k) {
  check(scriptKeys.indexOf(k) !== -1, 'o script pede "' + k + '", que o controller nao manda');
  check(calls[k] === pt[k], 'texto de reserva de "' + k + '" no script ("' + calls[k] + '") difere do pt-br ("' + pt[k] + '")');
});
scriptKeys.forEach(function (k) { check(k in calls, 'o controller manda "' + k + '", que o script nao usa'); });
check((script.match(/(?:\bTXT|window\.KazEditorText)\(/g) || []).length >= Object.keys(calls).length - 5, 'chamadas de texto no script');
check(!/textContent = on \? 'N/.test(script) && !/'Expandir tudo' : 'Recolher tudo';/.test(script.replace(/TXT\([^)]*\)/g, '')), 'o script nao escreve mais a frase direto na tela');

// ---- 4. a funcao KazEditorText
const start = script.indexOf('window.KazEditorText = (function () {');
check(start !== -1, 'KazEditorText definida no script');
const end = script.indexOf('})();', start);
const source = script.slice(start, end + 5);
check(script.indexOf('(function (document, window) {') > end, 'KazEditorText vem antes do corpo do editor');
function sandbox(attr) {
  const state = { host: null, reads: 0 };
  const document = { getElementById: function (id) { state.reads++; return id === 'kaz-theme-editor' ? state.host : null; } };
  const window = {};
  vm.runInNewContext(source, { window: window, document: document, JSON: JSON, String: String });
  state.text = window.KazEditorText;
  state.mount = function (value) { state.host = { getAttribute: function (n) { return n === 'data-kaz-labels' ? value : null; } }; };
  if (attr !== undefined) state.mount(attr);
  return state;
}
let s = sandbox('{"saved":"Saved","folded_count":"%s group(s) collapsed","fold_one":"Collapse or expand %s","empty":"","n":7}');
check(s.text('saved', 'Salvo') === 'Saved', 'frase do idioma do admin');
check(s.text('other', 'Reserva') === 'Reserva', 'chave que falta: texto de reserva');
check(s.text('empty', 'Reserva') === 'Reserva', 'frase vazia: texto de reserva');
check(s.text('n', 'Reserva') === 'Reserva', 'valor que nao e texto: texto de reserva');
check(s.text('folded_count', '%s grupo(s)', 2) === '2 group(s) collapsed', '%s recebe o numero');
check(s.text('fold_one', 'x %s', 'Icons') === 'Collapse or expand Icons', '%s recebe o nome');
check(s.text('fold_one', 'x %s', '$& e $1') === 'Collapse or expand $& e $1', '%s: o valor entra como esta (sem $&)');
check(s.text('other', 'Recolher %s', 'Abas') === 'Recolher Abas', '%s tambem no texto de reserva');
check(s.text('fold_one', 'x %s') === 'Collapse or expand %s', 'sem terceiro argumento a frase volta inteira');
check(s.text('constructor', 'Reserva') === 'Reserva' && s.text('toString', 'Reserva') === 'Reserva', 'nomes herdados nao viram frase');
const readsOnce = s.reads;
s.text('saved', 'Salvo');
check(s.reads === readsOnce && readsOnce === 1, 'o atributo e lido uma vez so');
s = sandbox();
check(s.text('saved', 'Salvo') === 'Salvo', 'antes de o elemento existir: texto de reserva');
s.mount('{"saved":"Guardado"}');
check(s.text('saved', 'Salvo') === 'Guardado', 'depois que o elemento existe: frase do idioma');
['', 'nao e json', 'null', '[]', '"texto"', '5'].forEach(function (bad) {
  const b = sandbox(bad);
  check(b.text('saved', 'Salvo') === 'Salvo', 'atributo ' + JSON.stringify(bad) + ': texto de reserva, sem erro');
  b.text('saved', 'Salvo');
  check(b.reads === 1, 'atributo ' + JSON.stringify(bad) + ': lido uma vez so, mesmo sem servir');
});
s = sandbox();
s.host = { getAttribute: function () { return null; } };
check(s.text('saved', 'Salvo') === 'Salvo', 'sem o atributo: texto de reserva');

if (process.argv.indexOf('--list') !== -1) console.log(JSON.stringify(report, null, 1));
else console.log('Frame text checker: ' + checks + ' PASS (' + frameKeys.length + ' chaves, ' + scriptKeys.length + ' no script, ' + twigKeys.length + ' no Twig)');
