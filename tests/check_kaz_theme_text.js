'use strict';
/*
 * KZ-21 (ADR 0114) — texto do tema editado por idioma, no canvas do editor de tema.
 *
 * Um elemento que so imprime `{{ theme_text.chave }}` abre para edicao como um
 * texto fixo (campo do painel e dois cliques na pagina). O que e digitado vai
 * para o JSON dos textos do tema (`strings_json`), no idioma que o canvas
 * mostra; a fonte do tema nao muda.
 *
 * Este conferidor recorta de dev/admin/view/js/kaz-canvas.js as funcoes que
 * decidem (sem tocar na tela) e roda cada uma sobre os casos de
 * tests/fixtures/kaz_theme_text.json — os mesmos que KazThemeTextTest.php roda
 * no servidor (ThemeStrings), para que os dois lados digam a mesma coisa:
 *   - themeRef     que expressao e "um texto do tema" (e com que filtro de caixa);
 *   - themeIsText  que texto o servidor aceita;
 *   - themeRead    que texto o site mostra para a chave no idioma, e de que idioma ele vem;
 *   - themeWrite   o JSON novo (so aquela chave e aquele idioma mudam) ou por que nao;
 *   - themeBytes   o tamanho como o formulario envia;
 *   - themeWhy     quando o texto abre para edicao e, se nao, que aviso aparece;
 *   - themeShown, themeClean.
 * Depois le a fonte do script, do controller e dos tres idiomas e confere o que
 * nao se ve rodando: um so ponto de escrita no campo do formulario, a fonte do
 * tema intocada, o Esc, a troca de idioma, os rotulos. No fim estraga o recorte
 * de varias formas, uma por vez, e exige que os casos acusem cada uma.
 *
 *   node tests/check_kaz_theme_text.js
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const ROOT = path.join(__dirname, '..');
const read = p => fs.readFileSync(path.join(ROOT, p), 'utf8');
const source = read('dev/admin/view/js/kaz-canvas.js');
const controller = read('dev/admin/controller/design/theme.php');
const FIX = JSON.parse(read('tests/fixtures/kaz_theme_text.json'));
let checks = 0;
function check(ok, what) { checks++; if (!ok) throw new Error('FAIL: ' + what); }
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

/* ---------- recorte ---------- */
const START = '  /* ---------------- KZ-21 (ADR 0114): texto do tema editado por idioma ---------------- */';
const END = '  /* ---------------- KZ-18 (ADR 0111): texto editado direto na pagina ---------------- */';
const start = source.indexOf(START), end = source.indexOf(END);
check(start > 0 && end > start && source.split(START).length === 2, 'o bloco da KZ-21 foi encontrado em kaz-canvas.js, uma vez, antes do bloco da KZ-18');
const block = source.slice(start, end), flat = block.replace(/\/\*[\s\S]*?\*\//g, '');
const kz18 = source.slice(end, source.indexOf('  /* ---------------- KZ-05: atributos do elemento ---------------- */'));
function cut(name, from) {
  from = from || block;
  const m = new RegExp('  function ' + name + '\\([^)]*\\) \\{.*\\}\\n').exec(from) || new RegExp('  function ' + name + '\\([^)]*\\) \\{[\\s\\S]*?\\n  \\}\\n').exec(from);
  check(m !== null, 'a funcao ' + name + ' foi encontrada');
  return m[0];
}
const consts = /  var THEME_REF = [^\n]+\n  var THEME_KEY = [^\n]+\n  var THEME_KEYS_MAX = [^\n]+\n(?:  \/\*[^\n]*\*\/\n)?  var THEME_CTRL = [^\n]+\n/.exec(block);
check(consts !== null, 'as constantes da KZ-21 foram encontradas');
const NAMES = ['themeRef', 'themeParse', 'themeIsText', 'themeBytes', 'themeRead', 'themeWrite', 'themeShown', 'themeWhy', 'themeClean'];
const pieces = {};
NAMES.forEach(n => { pieces[n] = cut(n); });
const inlineClean = cut('inlineClean', kz18);
function build(replace) {
  const box = { TEXT_MAX: 2000, String, Number, Math, JSON, Object, Array, RegExp };
  let code = consts[0] + inlineClean + NAMES.map(n => pieces[n]).join('');
  if (replace) { check(code.split(replace[0]).length === 2, 'o trecho a estragar existe uma vez: ' + replace[0]); code = code.replace(replace[0], replace[1]); }
  vm.runInNewContext(code + '\n' + NAMES.map(n => 'this.' + n + ' = ' + n + ';').join(' ') + ' this.C = { keys: THEME_KEYS_MAX, langs: THEME_LANGS_MAX, text: THEME_TEXT_MAX, json: THEME_JSON_MAX, fallback: THEME_FALLBACK, key: THEME_KEY.source, lang: THEME_LANG.source, ref: THEME_REF.source };', box);
  return box;
}

/* ---------- casos ---------- */
const rep = (s, n) => new Array(n + 1).join(s);
function make(r) {
  const m = {};
  for (let i = 0; i < r.count; i++) { const by = {}; r.langs.forEach(c => { by[c] = rep(r.fill, r.times); }); m[r.prefix + ('00' + i).slice(-3)] = by; }
  Object.keys(r.extra || {}).forEach(k => { m[k] = Object.assign({}, r.extra[k]); });
  return m;
}
const utf8 = s => Buffer.byteLength(s.replace(/\n/g, '\r\n'), 'utf8');
function cases(K) {
  const out = [];
  const t = (ok, what) => out.push([!!ok, what]);
  const item = (kind, expr) => ['p', kind, 0, 3, 3, 20, expr, null];
  /* themeRef */
  FIX.ref.forEach(c => {
    const r = K.themeRef(item('variable', c.expr));
    t(c.key === null ? r === null : (r !== null && r.key === c.key && r.filter === c.filter), 'expressao ' + JSON.stringify(c.expr) + (c.key === null ? ': nao e texto do tema' : ': texto do tema ' + c.key));
  });
  ['fixed', 'empty', 'mixed', 'group', 'void'].forEach(kind => t(K.themeRef(item(kind, 'theme_text.a')) === null, 'elemento ' + kind + ' nunca e texto do tema, mesmo com a expressao certa'));
  t(K.themeRef(null) === null && K.themeRef(['p', 'variable', 0, 3, 3, 20]) === null, 'sem item, ou item sem expressao: nao e texto do tema');
  /* themeIsText */
  FIX.text.forEach(c => t(K.themeIsText(rep(c.fill, c.times)) === c.ok, 'texto ' + JSON.stringify(c.fill).slice(0, 12) + ' x ' + c.times + (c.ok ? ': aceito' : ': recusado')));
  t(K.themeIsText(7) === false && K.themeIsText(null) === false && K.themeIsText(['a']) === false, 'o que nao e texto e recusado');
  /* themeRead */
  FIX.read.forEach(c => {
    const r = K.themeRead(FIX.strings, c.key, c.language);
    t(r.text === c.text && r.from === c.from, 'leitura: ' + c.name);
  });
  t(same(K.themeRead(null, 'a', 'pt-br'), { text: '', from: '' }) && same(K.themeRead({}, 'toString', 'pt-br'), { text: '', from: '' }), 'leitura: sem mapa, ou chave que so existe no prototipo, nao da texto');
  /* themeWrite */
  FIX.write.forEach(c => {
    let r; try { r = K.themeWrite(c.raw, c.key, c.language, c.text); } catch (e) { r = { why: 'excecao: ' + e.message }; }
    if (c.json !== undefined) t(r.json === c.json && r.why === undefined, 'escrita: ' + c.name);
    else t(r.why === c.why && r.json === undefined, 'escrita recusada (' + c.why + '): ' + c.name);
  });
  FIX.limits.forEach(c => {
    const m = make(c.make), raw = JSON.stringify(m, null, 4), text = rep(c.fill, c.times), r = K.themeWrite(raw, c.key, c.language, text);
    if (!m[c.key]) m[c.key] = {};
    m[c.key][c.language] = text;
    const forced = JSON.stringify(m, null, 4);
    t(utf8(forced) === c.bytes, 'limite: a receita monta o mesmo JSON dos dois lados — ' + c.name);
    t(c.ok ? r.json === forced : (r.why === 'theme_full' && r.json === undefined), 'limite: ' + c.name);
  });
  const raw0 = JSON.stringify({ z_last: { 'pt-br': 'Z', 'en-us': 'z' }, a_first: { 'en-us': 'a' } }, null, 4);
  const w = K.themeWrite(raw0, 'z_last', 'en-us', 'novo');
  t(w.json === raw0.replace('"en-us": "z"', '"en-us": "novo"'), 'escrita: no JSON muda uma linha so; chaves e idiomas ficam na ordem em que estavam');
  t(K.themeWrite(raw0, 'Bad', 'en-us', 'x').why === 'el_stale' && K.themeWrite(raw0, 'a_first', 'en', 'x').why === 'el_stale' && K.themeWrite(raw0, 'a_first', 'EN-US', 'x').why === 'el_stale', 'escrita: chave ou idioma fora da gramatica nao escreve');
  t(K.themeWrite('{"__proto__": {"en-us": "x"}}', 'a_first', 'en-us', 'y').why === undefined && Object.prototype['en-us'] === undefined, 'escrita: uma chave __proto__ no JSON nao contamina nada');
  /* themeBytes */
  t(K.themeBytes('abc') === 3 && K.themeBytes('é') === 2 && K.themeBytes('—') === 3 && K.themeBytes('😀') === 4, 'tamanho: 1, 2, 3 e 4 bytes por caractere');
  t(K.themeBytes('a\nb') === 4, 'tamanho: a quebra de linha conta dois bytes (CR LF), como o formulario envia');
  [raw0, 'Olá — ação 😀 ñ\n{}'].forEach(s => t(K.themeBytes(s) === utf8(s), 'tamanho: igual ao do texto codificado em UTF-8 com CR LF'));
  /* themeShown */
  t(K.themeShown('Mouse', 'upper') === 'MOUSE' && K.themeShown('Mouse', 'lower') === 'mouse' && K.themeShown('Mouse', '') === 'Mouse', 'previa: o filtro de caixa da fonte e aplicado ao texto guardado');
  /* themeWhy */
  const st = (o) => Object.assign({ ok: true, lang: 'pt-br', shown: 'pt-br', text: 'Texto' }, o || {});
  t(K.themeWhy(true, true, true, st()) === '', 'abre: fonte do tema, mapa em dia, folha, JSON legivel, pagina no idioma do canvas');
  t(K.themeWhy(true, true, false, st()) === 'inline_variable', 'elemento com filhos na pagina: nao abre');
  t(K.themeWhy(false, true, true, st()) === 'inline_need_customize', 'padrao do sistema: manda personalizar');
  t(K.themeWhy(true, true, true, st({ ok: false })) === 'theme_bad_json' && K.themeWhy(true, true, true, null) === 'theme_bad_json', 'JSON dos textos ilegivel: avisa');
  t(K.themeWhy(true, false, true, st({ ok: false })) === 'theme_bad_json', 'JSON ilegivel vem antes de mapa defasado');
  t(K.themeWhy(true, false, true, st()) === 'el_stale', 'mapa defasado: espera');
  t(K.themeWhy(true, true, true, st({ shown: 'en-us' })) === 'el_stale' && K.themeWhy(true, true, true, st({ shown: '' })) === 'el_stale', 'pagina ainda em outro idioma (ou rascunho salvo): espera');
  t(K.themeWhy(true, true, true, st({ text: 'a\nb' })) === 'theme_lines', 'texto com quebra de linha: so no bloco do JSON');
  t(K.themeWhy(true, true, true, st({ text: '' })) === '', 'chave ainda sem texto: abre (digitar cria o texto)');
  /* themeClean */
  t(K.themeClean('a\r\nb\tc') === 'a b c' && K.themeClean('a\u0007b\u007fc\u0000') === 'abc', 'digitado: quebra de linha e tabulacao viram um espaco; caractere de controle sai');
  t(K.themeClean(rep('x', 2500)).length === 2000, 'digitado: corta em 2000');
  /* constantes */
  t(K.C.keys === 600 && K.C.langs === 12 && K.C.text === 2000 && K.C.json === 262144 && K.C.fallback === 'en-us', 'limites: 600 chaves, 12 idiomas, 2000 caracteres, 262144 bytes, recuo en-us');
  t(K.C.key === '^[a-z][a-z0-9_]{0,63}$' && K.C.lang === '^[a-z]{2}-[a-z]{2}$', 'gramaticas de chave e de idioma');
  return out;
}
const good = cases(build());
good.forEach(c => check(c[0], c[1]));
check(FIX.read.length >= 11 && FIX.write.length >= 18 && FIX.limits.length >= 14 && FIX.ref.length >= 20 && FIX.text.length >= 14, 'a tabela de casos esta inteira');

/* ---------- a fonte ---------- */
/* um so ponto escreve no campo do formulario, e so no JSON dos textos */
check(flat.split('field.value = ').length - 1 === 1 && /function themeCommit\(field, json\) \{\n    themeWriting = true;\n    field\.value = json;\n    try \{ field\.dispatchEvent\(new Event\('input', \{ bubbles: true \}\)\); \} finally \{ themeWriting = false; \}\n  \}/.test(block), 'um so ponto escreve no campo, e avisa o formulario com o evento input');
check(source.split('themeCommit(').length - 1 === 3 && flat.split('themeCommit(field, res.json);').length === 2 && flat.split('themeCommit(field, st.theme.raw);').length === 2, 'themeCommit e chamado em dois pontos: o texto digitado e a devolucao do Esc');
check(/function themeField\(\) \{\n    var el = form\.elements\['strings_json'\];\n    return el && el\.tagName === 'TEXTAREA' \? el : null;\n  \}/.test(block), 'o campo e o <textarea name="strings_json"> do formulario do tema');
check(!/writeSource\(|commitSource\(|encodeText\(|sourceField\(|templates\[/.test(flat), 'o bloco nao le nem escreve a fonte do tema');
check(!/innerHTML|insertAdjacent|outerHTML|document\.write|createElement\('(script|style|iframe)'|contenteditable|eval\(|new Function/.test(flat), 'nada de HTML montado a mao nem de codigo avaliado');
check(/function setThemeText\(doc, themeKey, text\) \{[\s\S]*?if \(ref && ref\.key === themeKey\) setElementText\(doc, key, n, themeShown\(text, ref\.filter\)\);/.test(block) && /list\[i\]\.textContent = text;/.test(source), 'a previa imediata troca so o texto (textContent) dos elementos daquela chave');
/* quem chama */
check(source.split('onThemeInput(').length - 1 === 3, 'onThemeInput: a definicao, o campo do painel e a caixa na pagina');
check(source.indexOf("if (t.getAttribute('data-kaz-canvas-theme-text') !== null) { if (!t.readOnly) onThemeInput(t); return; }") > 0, 'o campo do painel so escreve quando nao esta em modo leitura');
check(kz18.indexOf("var why = !sameElement(picked, st) ? 'el_stale' : (st.theme ? onThemeInput({ value: value }) : onTextInput({ value: value }));") > 0, 'a caixa na pagina escreve pelo caminho do campo do painel');
check(kz18.indexOf('if (ref) return themeWhy(own, mapFresh(sel.key), leaf, themeState(ref.key));') > 0, 'dois cliques: texto do tema e decidido por themeWhy');
check(kz18.indexOf("theme: ref ? { key: ref.key, filter: ref.filter, raw: tfield.value } : null };") > 0 && kz18.indexOf('if (ref) text = tstate.text;') > 0, 'ao abrir, a caixa guarda o JSON de antes e mostra o texto guardado');
check(kz18.indexOf('if (st.theme) themeInlineStop(st, keep);\n    else if (!keep && st.changed) {') > 0, 'ao fechar, a caixa de um texto do tema devolve o JSON, nao a fonte');
check(/function themeInlineStop\(st, keep\) \{\n    var field = themeField\(\);\n    if \(!keep && st\.changed && field && field\.value !== st\.theme\.raw\) \{/.test(block), 'Esc: so devolve quando algo mudou');
check(kz18.indexOf("st.mode = st.theme && st.theme.filter ? 'box' : inlineMode(m);") > 0, 'fonte com filtro de caixa: caixa propria');
check(source.indexOf("} else if (kind === 'variable' && themeRef(it)) {\n      /* KZ-21: texto do dicionario do tema, editado no idioma do canvas. */\n      paintThemeText(host, it, own, fresh);\n    } else if (kind === 'variable') {") > 0, 'painel: texto do tema ganha campo; as outras variaveis continuam so informadas');
/* o servidor alcanca o que foi digitado; idioma; JSON mudado por fora */
check(source.indexOf("if (liveTheme && mine > liveTheme.after) liveTheme = null;\n      shownLang = json.source === 'saved' ? '' : asked;") > 0, 'so uma resposta pedida depois da ultima tecla apaga a previa local; rascunho salvo nao conta como idioma mostrado');
check(source.indexOf("var asked = langSel ? String(langSel.value) : 'en-us';\n    body.set('canvas_language', asked);") > 0, 'o idioma pedido ao servidor e o guardado como idioma mostrado');
check(source.indexOf("if (inline && inline.theme) inlineStop(true);\n    liveTheme = null;\n    if (!picked || themeRef(elementItem(picked.key, picked.n))) paintElementBlock();\n    refresh();") > 0, 'trocar o idioma do canvas fecha a caixa aberta sobre um texto do tema');
check(source.indexOf("if (themeWriting || !ev.target || ev.target.name !== 'strings_json') return;\n    liveTheme = null;\n    if (inline && inline.theme) inlineStop(true);") > 0, 'JSON mudado no bloco proprio: a caixa fecha como esta');
check(source.indexOf('if (liveTheme) setThemeText(doc, liveTheme.key, liveTheme.text);') > 0, 'quadro redesenhado antes de o servidor alcancar: a previa local e reposta');
check(/box\.maxLength = THEME_TEXT_MAX;/.test(block) && /box\.readOnly = why !== '';/.test(block) && /warn\.setAttribute\('role', 'status'\);/.test(block) && /lab\.setAttribute\('for', id\);/.test(block), 'campo do painel: limite, modo leitura com motivo, aviso anunciado, rotulo ligado ao campo');

/* a lista de textos do tema do trecho */
check(source.indexOf("else { paintTextList(key, host); paintThemeList(key, host); paintLinkList(key, host); }") > 0, 'sem elemento escolhido, o painel lista os textos fixos, os textos do tema e os links do trecho');
const listFn = cut('paintThemeList');
check(listFn.indexOf("if (!map || !mapFresh(key) || !dict) return;") > 0 && listFn.indexOf("var ref = themeRef(it);\n      if (!ref) return;") > 0, 'lista: so com mapa em dia e JSON legivel; so elementos que sao texto do tema');
check(listFn.indexOf("rows.push([n, it[0], themeRead(dict, ref.key, lang).text || ref.key]);") > 0 && listFn.indexOf("b.setAttribute('data-kaz-canvas-el', String(r[0]));") > 0, 'lista: cada linha mostra o texto do idioma do canvas e escolhe o elemento pelo caminho de sempre');
check(listFn.indexOf("ul.setAttribute('data-kaz-canvas-theme-list', lang);") > 0 && listFn.indexOf("label('theme_texts', '').replace('%s', lang)") > 0, 'lista: o titulo diz o idioma');

/* ---------- rotulos ---------- */
const list = /\$canvasLabels=\[\];\s*foreach\(\[([\s\S]*?)\] as \$canvasKey\)/.exec(controller);
check(list !== null, 'a lista de rotulos do canvas foi encontrada no controller');
const NEW = ['theme_text', 'theme_text_help', 'theme_fallback', 'theme_bad_json', 'theme_empty', 'theme_lines', 'theme_full', 'theme_texts', 'theme_texts_help'];
const WITH_LANG = ['theme_text', 'theme_fallback', 'theme_texts'];
NEW.forEach(k => check(list[1].split("'" + k + "'").length === 2, 'rotulo ' + k + ' na lista do controller, uma vez'));
const used = {}; let m; const re = /\b(?:label|hint|themeWarn)\('([a-z0-9_]+)'/g;
while ((m = re.exec(block))) used[m[1]] = true;
const whys = {}; const rw = /(?:return|why:) '([a-z_]+)'/g;
while ((m = rw.exec(pieces.themeWhy + pieces.themeWrite))) whys[m[1]] = true;
check(Object.keys(used).length >= 6 && Object.keys(used).concat(Object.keys(whys)).every(k => list[1].indexOf("'" + k + "'") >= 0), 'todo rotulo e todo aviso do bloco esta na lista do controller');
check(same(Object.keys(whys).sort(), ['el_stale', 'inline_need_customize', 'inline_variable', 'theme_bad_json', 'theme_empty', 'theme_full', 'theme_lines']), 'os avisos que themeWhy e themeWrite devolvem');
const v = /'-kaz-canvas-kz(\d{2})'/.exec(controller);
check(v !== null && Number(v[1]) >= 21, 'a versao dos arquivos do canvas foi trocada nesta etapa');
const texts = {};
['pt-br', 'en-us', 'es-eu'].forEach(code => {
  const lang = read('dev/admin/language/' + code + '/design/theme.php'), t = {};
  const r = /^\$_\['text_(canvas|kaz)_([a-z0-9_]+)'\]\s*=\s*'((?:[^'\\]|\\.)*)';/gm; let x;
  while ((x = r.exec(lang))) t[(x[1] === 'kaz' ? 'kaz_' : '') + x[2]] = x[3].replace(/\\(['\\])/g, '$1');
  texts[code] = t;
  NEW.forEach(k => check(lang.split("$_['text_canvas_" + k + "']").length === 2, code + ': ' + k + ' escrito uma vez'));
  NEW.forEach(k => check(typeof t[k] === 'string' && t[k].length >= 16, code + ': ' + k + ' existe e e uma frase'));
  WITH_LANG.forEach(k => check(t[k].split('%s').length === 2, code + ': ' + k + ' tem um lugar para o idioma'));
  NEW.filter(k => WITH_LANG.indexOf(k) < 0).forEach(k => check(t[k].indexOf('%') < 0, code + ': ' + k + ' sem marcador'));
  ['theme_bad_json', 'theme_lines'].forEach(k => check(t[k].indexOf('"' + t.kaz_strings_summary + '"') > 0, code + ': ' + k + ' cita o bloco "' + t.kaz_strings_summary + '" pelo nome que ele tem'));
  check(!/[<>]/.test(NEW.map(k => t[k]).join('')), code + ': as frases novas nao trazem HTML');
  check(!/[.!?]$/.test(t.theme_text_help), code + ': a ajuda termina na chave, sem ponto (a chave vem em seguida)');
});
NEW.forEach(k => check(texts['pt-br'][k] !== texts['en-us'][k] && texts['pt-br'][k] !== texts['es-eu'][k] && texts['en-us'][k] !== texts['es-eu'][k], k + ': tres frases diferentes, uma por idioma'));

/* ---------- o conferidor acusa quando o codigo e estragado ---------- */
const BROKEN = [
  [["(?: ?\\| ?(upper|lower))?$/;", "(?: ?\\| ?(upper|lower))?/;"], 'expressao "theme_text.a.b": nao e texto do tema'],
  [["if (!it || it[1] !== 'variable') return null;", 'if (!it) return null;'], 'elemento mixed nunca e texto do tema, mesmo com a expressao certa'],
  [["var order = [lang, THEME_FALLBACK];", 'var order = [lang];'], 'leitura: sem o idioma: en-us vem antes do primeiro preenchido'],
  [["if (THEME_LANG.test(codes[i]) && themeIsText(by[codes[i]]))", 'if (themeIsText(by[codes[i]]))'], 'leitura: codigo de idioma invalido e pulado'],
  [["&& !THEME_CTRL.test(v)", ''], 'leitura: texto com caractere de controle nao conta'],
  [[".replace(/[\\uD800-\\uDBFF][\\uDC00-\\uDFFF]/g, '_').length", '.length'], 'limite: 2000 emojis cabem (um emoji conta um)'],
  [["if (Object.keys(map).length >= THEME_KEYS_MAX) return { why: 'theme_full' };", ''], 'limite: a chave de numero 601 nao cabe'],
  [["if (!has.call(map[key], lang) && Object.keys(map[key]).length >= THEME_LANGS_MAX) return { why: 'theme_full' };", ''], 'limite: o idioma de numero 13 nao cabe'],
  [["if (themeBytes(json) > THEME_JSON_MAX) return { why: 'theme_full' };", ''], 'limite: um byte acima do teto: nao cabe'],
  [["n += c === 0x0A ? 2 : (", 'n += ('], 'limite: sem contar a quebra de linha como dois bytes este caberia: nao cabe'],
  [["if (text.trim() === '') return { why: 'theme_empty' };", ''], 'escrita recusada (theme_empty): so espacos'],
  [["if (!by || typeof by !== 'object' || Array.isArray(by)) return null;", ''], 'escrita recusada (theme_bad_json): JSON invalido: chave com valor que nao e objeto'],
  [["var json = JSON.stringify(map, null, 4);", 'var json = JSON.stringify(map, null, 2);'], 'escrita: troca o texto de um idioma; a ordem das chaves fica'],
  [["if (!fresh || st.lang !== st.shown) return 'el_stale';", "if (!fresh) return 'el_stale';"], 'pagina ainda em outro idioma (ou rascunho salvo): espera'],
  [["if (String(st.text).indexOf('\\n') !== -1) return 'theme_lines';", ''], 'texto com quebra de linha: so no bloco do JSON'],
  [["if (!own) return 'inline_need_customize';", ''], 'padrao do sistema: manda personalizar'],
  [["if (filter === 'upper') return String(text).toUpperCase();", ''], 'previa: o filtro de caixa da fonte e aplicado ao texto guardado'],
];
BROKEN.forEach(b => {
  const bad = cases(build(b[0])).filter(c => !c[0]).map(c => c[1]);
  check(bad.indexOf(b[1]) >= 0, 'codigo estragado e acusado: ' + b[1]);
});

console.log('Kaz theme text checker: ' + checks + ' PASS');
