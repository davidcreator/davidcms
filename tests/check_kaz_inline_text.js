'use strict';
/*
 * KZ-18 (ADR 0111) — texto editado direto na pagina, no canvas do editor de tema.
 *
 * Dois cliques em um texto fixo abrem uma caixa de edicao sobre o proprio
 * texto. Este conferidor recorta de dev/admin/view/js/kaz-canvas.js as funcoes
 * que decidem (sem tocar na tela) e roda cada uma sobre casos escritos aqui:
 *   - inlineWhy      quais textos abrem, quais avisam e quais deixam o gesto antigo;
 *   - inlineClean    quebra de linha e tabulacao viram um espaco; limite de 2000;
 *   - inlineMode     quando a caixa fica sobre o texto e quando e caixa propria;
 *   - inlineGeometry onde a caixa fica;
 *   - inlineInk      a cor do texto na caixa;
 *   - inlineShift    quanto a camera anda para o texto ficar a vista.
 * Depois le a fonte do script, do CSS, do controller e dos tres idiomas e
 * confere o que nao se ve rodando: um so caminho de escrita, duas propriedades
 * no quadro, o gesto antigo preservado, as teclas que nao saem da caixa, os
 * rotulos. No fim estraga o recorte de varias formas, uma por vez, e exige que os casos acusem cada uma.
 *
 *   node tests/check_kaz_inline_text.js
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const ROOT = path.join(__dirname, '..');
const read = p => fs.readFileSync(path.join(ROOT, p), 'utf8');
const source = read('dev/admin/view/js/kaz-canvas.js');
const css = read('dev/admin/view/css/kaz-canvas.css');
const controller = read('dev/admin/controller/design/theme.php');
let checks = 0;
function check(ok, what) { checks++; if (!ok) throw new Error('FAIL: ' + what); }
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
const near = (a, b) => Math.abs(a - b) < 1e-9;

/* ---------- recorte ---------- */
const start = source.indexOf('  /* ---------------- KZ-18 (ADR 0111): texto editado direto na pagina ---------------- */');
const end = source.indexOf('  /* ---------------- KZ-05: atributos do elemento ---------------- */');
check(start > 0 && end > start, 'o bloco da KZ-18 foi encontrado em kaz-canvas.js');
const block = source.slice(start, end);
function cut(name) {
  /* Primeiro a forma de uma linha so; senao, ate a primeira chave de fechamento no recuo da funcao. */
  const m = new RegExp('  function ' + name + '\\([^)]*\\) \\{.*\\}\\n').exec(block) || new RegExp('  function ' + name + '\\([^)]*\\) \\{[\\s\\S]*?\\n  \\}\\n').exec(block);
  check(m !== null, 'a funcao ' + name + ' foi encontrada');
  return m[0];
}
const consts = /  var INLINE_MIN_PX = [^\n]+\n/.exec(block), blockList = /  var INLINE_BLOCK = \[[^\]]+\];\n/.exec(block), hideList = /  var INLINE_HIDE = \[\[[^\n]+\]\];\n/.exec(block);
check(consts !== null && blockList !== null && hideList !== null, 'as constantes da KZ-18 foram encontradas');
const NAMES = ['inlineWhy', 'inlineClean', 'inlineMode', 'inlineGeometry', 'inlineInk', 'inlineShift'];
const pieces = {};
NAMES.forEach(n => { pieces[n] = cut(n); });
function build(replace) {
  const box = { TEXT_MAX: 2000, clamp: (v, lo, hi) => Math.min(hi, Math.max(lo, v)), String, Number, Math, isFinite, JSON };
  let code = consts[0] + blockList[0] + hideList[0] + NAMES.map(n => pieces[n]).join('');
  if (replace) { check(code.split(replace[0]).length === 2, 'o trecho a estragar existe uma vez: ' + replace[0]); code = code.replace(replace[0], replace[1]); }
  vm.runInNewContext(code + '\n' + NAMES.map(n => 'this.' + n + ' = ' + n + ';').join(' ') + ' this.INLINE_HIDE = INLINE_HIDE; this.INLINE_MIN_PX = INLINE_MIN_PX;', box);
  return box;
}

/* ---------- casos ---------- */
function item(kind, from) { return ['p', kind, 0, 10, from === undefined ? 3 : from, 8, '', null]; }
function cases(K) {
  const out = [];
  const t = (ok, what) => out.push([!!ok, what]);
  /* inlineWhy(it, own, fresh, leaf) */
  t(K.inlineWhy(item('fixed'), true, true, true) === '', 'texto fixo, fonte propria, mapa em dia, sem filhos: abre');
  t(K.inlineWhy(item('empty'), true, true, true) === '', 'elemento sem texto ainda: abre');
  t(K.inlineWhy(item('variable'), true, true, true) === 'inline_variable', 'texto que vem de dado: avisa');
  t(K.inlineWhy(item('variable'), false, false, false) === 'inline_variable', 'texto que vem de dado avisa mesmo sem fonte propria');
  t(K.inlineWhy(item('mixed'), true, true, true) === 'el_mixed', 'texto misto: avisa com a frase do painel');
  t(K.inlineWhy(item('group'), true, true, true) === null, 'grupo: nao e texto, vale o gesto antigo');
  t(K.inlineWhy(item('void'), true, true, true) === null, 'imagem ou campo: nao e texto');
  t(K.inlineWhy(null, true, true, true) === null, 'sem elemento: vale o gesto antigo');
  t(K.inlineWhy(item('fixed', -1), true, true, true) === null, 'texto fixo sem intervalo de texto: nao abre');
  t(K.inlineWhy(item('fixed'), true, true, false) === null, 'texto fixo com elementos dentro: nao abre');
  t(K.inlineWhy(item('fixed'), false, true, true) === 'inline_need_customize', 'padrao do sistema: manda personalizar');
  t(K.inlineWhy(item('fixed'), true, false, true) === 'el_stale', 'mapa defasado: avisa');
  t(K.inlineWhy(item('fixed'), false, false, true) === 'inline_need_customize', 'padrao do sistema vem antes de mapa defasado');
  /* inlineClean */
  t(K.inlineClean('a\nb') === 'a b' && K.inlineClean('a\r\nb') === 'a b' && K.inlineClean('a\tb') === 'a b' && K.inlineClean('a\n\n\t\r\nb') === 'a b', 'quebra de linha e tabulacao viram um espaco so');
  t(K.inlineClean('Olá & <b> {x} é ç 😀  dois') === 'Olá & <b> {x} é ç 😀  dois', 'o resto do texto passa como foi digitado');
  t(K.inlineClean('a'.repeat(2100)).length === 2000 && K.inlineClean('a'.repeat(2000)).length === 2000 && K.inlineClean('') === '', 'limite de 2000 caracteres');
  t(K.inlineClean(12) === '12', 'valor que nao e texto vira texto');
  /* inlineMode */
  const base = { display: 'block', lines: 1, webfont: false, transformed: false, vertical: false, rtl: false, hollow: false, pseudo: false };
  const with_ = o => Object.assign({}, base, o);
  t(K.inlineMode(base) === 'place', 'bloco comum: caixa sobre o texto');
  t(K.inlineMode(with_({ lines: 7 })) === 'place', 'bloco de varias linhas: sobre o texto');
  t(K.inlineMode(with_({ display: 'inline' })) === 'place', 'link de uma linha: sobre o texto');
  t(K.inlineMode(with_({ display: 'inline', lines: 2 })) === 'box', 'link que quebra de linha: caixa propria');
  t(K.inlineMode(with_({ display: 'flex', lines: 2 })) === 'place', 'botao flex de duas linhas: sobre o texto');
  t(K.inlineMode(with_({ pseudo: true })) === 'place', 'conteudo do CSS antes do texto, uma linha: sobre o texto');
  t(K.inlineMode(with_({ pseudo: true, lines: 3 })) === 'box', 'conteudo do CSS antes do texto, varias linhas: caixa propria');
  ['webfont', 'transformed', 'vertical', 'rtl', 'hollow'].forEach(flag => { const o = {}; o[flag] = true; t(K.inlineMode(with_(o)) === 'box', flag + ': caixa propria'); });
  /* inlineGeometry */
  const g0 = { display: 'block', fontSize: 16, frameWidth: 1440, lines: 2, lineH: 27.2, lineOff: 4, contentLeft: 158, contentTop: 676, contentWidth: 489, textLeft: 158, textTop: 680, textRight: 595, textBottom: 726 };
  const geo = o => K.inlineGeometry(Object.assign({}, g0, o));
  let g = geo({});
  t(g.left === 158 && g.width === 489 && near(g.top, 676) && near(g.height, 54.4) && g.nowrap === false, 'bloco: a caixa ocupa a largura do elemento, do topo da primeira linha, com a altura das linhas');
  t(g.padTop === 0 && g.padBottom === 0, 'linha mais alta que a letra: sem margem interna');
  g = geo({ lineOff: -2, lineH: 12.8, lineArea: 18, lines: 1 });
  t(near(g.padTop, 2) && near(g.padBottom, 3.2) && near(g.top, 680) && near(g.height, 12.8), 'linha mais baixa que a letra: a sobra da letra vira margem interna e o texto fica no mesmo lugar');
  g = geo({ display: 'flex', lines: 1, textLeft: 300, textRight: 380 });
  t(g.left === 300 && near(g.width, 96) && g.nowrap === true && near(g.top, 676), 'linha unica fora de bloco: comeca onde o texto comeca, com uma letra de folga, sem quebrar');
  g = geo({ display: 'flex', lines: 2 });
  t(g.left === 158 && g.width === 489 && g.nowrap === false, 'fora de bloco, com quebra de linha: largura do elemento');
  g = geo({ lines: 1, pseudo: true, textLeft: 190, textRight: 260 });
  t(g.left === 190 && near(g.width, 86) && g.nowrap === true, 'bloco com conteudo do CSS antes do texto: a caixa comeca onde o texto comeca');
  g = geo({ lines: 1, nowrap: true, textLeft: 158, textRight: 900 });
  t(g.left === 158 && near(g.width, 758) && g.nowrap === true, 'bloco que nao quebra linha: a caixa tambem nao quebra');
  g = geo({ display: 'list-item' });
  t(g.left === 158 && g.width === 489 && g.nowrap === false, 'item de lista conta como bloco');
  g = geo({ lines: 0, contentWidth: 11, textTop: 0 });
  t(g.left === 158 && near(g.top, 676) && g.width === 32 && near(g.height, 27.2) && g.nowrap === true, 'sem texto: lugar do elemento, largura minima de duas letras, uma linha');
  g = geo({ lineH: 0, lines: 1 });
  t(near(g.height, 19.2), 'sem medida de linha: 1,2 vez a letra');
  g = geo({ contentLeft: 1500, contentWidth: 400 });
  t(g.left === 1432 && g.width === 8, 'a caixa nunca sai do quadro (esquerda e largura limitadas)');
  g = geo({ contentLeft: -30, contentWidth: 2000 });
  t(g.left === 0 && g.width === 1440, 'elemento mais largo que o quadro: a caixa para na borda');
  /* inlineInk(fill, color, opacity) */
  t(K.inlineInk('rgb(1, 2, 3)', 'rgb(9, 9, 9)', 1) === 'rgb(1, 2, 3)', 'a cor de preenchimento vale mais que `color`');
  t(K.inlineInk('currentcolor', 'rgb(9, 9, 9)', 1) === 'rgb(9, 9, 9)' && K.inlineInk('', 'rgb(9, 9, 9)', 1) === 'rgb(9, 9, 9)', 'sem preenchimento proprio: `color`');
  t(K.inlineInk('rgba(0, 0, 0, 0)', 'rgb(9, 9, 9)', 1) === '' && K.inlineInk('transparent', 'rgb(9, 9, 9)', 1) === '', 'preenchimento transparente (degrade recortado no texto): letra vazada');
  t(K.inlineInk('', 'rgba(5, 5, 5, 0)', 1) === '' && K.inlineInk('', '', 1) === '', 'cor transparente ou ausente: letra vazada');
  t(K.inlineInk('', 'rgb(92, 198, 208)', 0.5) === 'rgba(92, 198, 208, 0.5)', 'a transparencia do elemento entra na cor');
  t(K.inlineInk('', 'rgba(92, 198, 208, 0.22)', 0.5) === 'rgba(92, 198, 208, 0.11)', 'transparencia da cor e do elemento se multiplicam');
  t(K.inlineInk('', 'rgba(92, 198, 208, 0.22)', 1) === 'rgba(92, 198, 208, 0.22)', 'elemento opaco: a cor passa igual');
  t(K.inlineInk('', 'color(srgb 0.1 0.2 0.3)', 0.5) === 'color(srgb 0.1 0.2 0.3)', 'cor em formato que nao e rgb: passa igual');
  /* inlineShift(a, b, lo, hi, edge) */
  t(K.inlineShift(100, 200, 0, 600, 24) === 0, 'trecho a vista: a camera fica');
  t(K.inlineShift(-50, 50, 0, 600, 24) === 74, 'trecho cortado no comeco: entra com a folga');
  t(K.inlineShift(560, 660, 0, 600, 24) === -84, 'trecho cortado no fim: entra com a folga');
  t(K.inlineShift(300, 1200, 0, 600, 24) === -276, 'trecho maior que a area e com o comeco longe da borda: o comeco vai para a borda');
  t(K.inlineShift(10, 900, 0, 600, 24) === 0, 'trecho maior que a area com o comeco ja na borda: a camera fica');
  t(K.inlineShift(-900, 20, 0, 600, 24) === 924, 'trecho maior que a area que ficou para tras: volta ao comeco');
  /* constantes */
  t(same(K.INLINE_HIDE, [['-webkit-text-fill-color', 'transparent'], ['text-shadow', 'none']]), 'so duas propriedades de pintura sao postas no elemento do quadro');
  t(K.INLINE_MIN_PX === 12, 'letra de pelo menos 12 px na tela para digitar');
  return out;
}
const good = cases(build());
good.forEach(c => check(c[0], c[1]));

/* ---------- a fonte ---------- */
const flat = block.replace(/\/\*[\s\S]*?\*\//g, '');
check(source.split("document.createElement('textarea')").length - 1 >= 1 && /var box = document\.createElement\('textarea'\), text = decodeText\(field\.value\.slice\(it\[4\], it\[5\]\)\);/.test(block), 'a caixa e um <textarea> que abre com o texto decodificado da fonte');
check(!/contenteditable|contentEditable|designMode/.test(source), 'nada de contenteditable no canvas');
check(flat.split('onTextInput(').length - 1 === 1 && /var why = sameElement\(picked, st\) \? onTextInput\(\{ value: value \}\) : 'el_stale';/.test(block), 'a caixa escreve pelo mesmo caminho do campo "Texto" do painel, em um ponto so');
check(!/writeSource\(|encodeText\(|sourceField\([^)]*\)\.value\s*=[^=]/.test(flat), 'a caixa nao escreve na fonte por conta propria nem codifica texto');
check(flat.split('commitSource(').length - 1 === 1 && /commitSource\(st\.key, st\.map, st\.src\);/.test(block), 'a unica escrita direta e a devolucao da fonte guardada (Esc)');
check(/src: field\.value, map: JSON\.parse\(JSON\.stringify\(elements\[sel\.key\]\)\)/.test(block), 'ao abrir, a fonte e uma copia do mapa sao guardadas para o Esc');
/* o que a caixa poe no quadro */
check(flat.split('.style.setProperty(').length - 1 === 2 && flat.split('.style.removeProperty(').length - 1 === 1, 'tres pontos tocam no estilo do elemento do quadro: por, devolver e retirar');
check(/INLINE_HIDE\.forEach\(function \(pair\) \{ node\.style\.setProperty\(pair\[0\], pair\[1\], 'important'\); \}\);/.test(block), 'o que e posto no elemento vem da lista INLINE_HIDE');
check(/if \(was\[1\] === ''\) st\.node\.style\.removeProperty\(was\[0\]\); else st\.node\.style\.setProperty\(was\[0\], was\[1\], was\[2\]\);/.test(block), 'ao fechar, cada propriedade volta ao valor que tinha');
check(!/setAttribute\('style'|removeAttribute\('style'|\.cssText|innerHTML|insertAdjacent|\.textContent = [^';]*node|createElement\('(script|style|iframe)'/.test(flat), 'a caixa nao reescreve atributo, HTML nem cria script ou estilo');
check(flat.split('appendChild(').length - 1 === 2 && /f\.body\.appendChild\(box\);/.test(block) && /document\.body\.appendChild\(probe\);/.test(block), 'a caixa e a regua entram neste documento; nada entra no documento do quadro');
/* teclas e gestos */
const key = cut('inlineKey');
check(/^  function inlineKey\(ev\) \{\n    ev\.stopPropagation\(\);/.test(key), 'toda tecla dentro da caixa para na caixa');
check(/if \(ev\.isComposing \|\| ev\.keyCode === 229\) return;/.test(key), 'tecla de composicao (acento, IME) nao conclui');
check(/if \(ev\.key === 'Enter'\) \{ ev\.preventDefault\(\); inlineStop\(true, true\); \}\n    else if \(ev\.key === 'Escape'\) \{ ev\.preventDefault\(\); inlineStop\(false, true\); \}/.test(key), 'Enter conclui e Esc desfaz');
check(/\['pointerdown', 'pointerup', 'mousedown', 'click', 'dblclick'\]\.forEach\(function \(name\) \{\n      box\.addEventListener\(name, function \(ev\) \{ ev\.stopPropagation\(\); \}\);/.test(block), 'os gestos dentro da caixa nao chegam ao canvas');
check(/box\.addEventListener\('blur', function \(\) \{ if \(inline && inline\.box === box\) inlineStop\(true\); \}\);/.test(block), 'sair da caixa conclui com o que foi digitado');
check(/if \(byKey && st\.back && st\.back\.to === view\.k\)/.test(block), 'a camera so volta quando a caixa foi fechada pelo teclado e o zoom nao foi mexido');
check(/if \(f\.busy\) return;/.test(block) && /frames\[id\]\.busy = true; frames\[id\]\.iframe\.srcdoc = doc;/.test(source) && /f\.busy = false;\n    if \(inline && inline\.frame === f\) inlinePlace\(\);/.test(source), 'enquanto o quadro recarrega a caixa espera; quando a pagina nova chega, ela se reposiciona');
/* o duplo clique */
const dbl = /viewport\.addEventListener\('dblclick', function \(ev\) \{[\s\S]*?\n  \}\);/.exec(source);
check(dbl !== null, 'o duplo clique do canvas foi encontrado');
const order = ['var onEl = hit && wy >= 0 ? elementAt(wx, wy) : null, why = onEl ? inlineReason(onEl, hit) : null;', "if (why === '' && inlineStart(onEl, hit, false)) return;", 'if (why) { hint(why); return; }', 'if (hit) focusFrame(hit); else fitAll();'].map(x => dbl[0].indexOf(x));
check(order.every(x => x > 0) && same(order, order.slice().sort((a, b) => a - b)), 'duplo clique: texto que abre, depois texto que avisa, depois o gesto antigo (focar o quadro ou mostrar tudo)');
const keys = /viewport\.addEventListener\('keydown', function \(ev\) \{[\s\S]*?\n  \}\);/.exec(source);
check(keys !== null && /else if \(\(ev\.key === 'Enter' \|\| ev\.key === 'F2'\) && !ctrl && picked\) \{/.test(keys[0]) && /if \(reason === ''\) inlineStart\(picked, inFrame, true\); else if \(reason\) hint\(reason\); else used = false;/.test(keys[0]), 'teclado: Enter ou F2 com um elemento escolhido abre a caixa');
check(keys[0].indexOf("ev.key === 'Enter' || ev.key === 'F2'") < keys[0].indexOf("ev.key === 'Escape' && picked"), 'teclado: a edicao vem antes de "Esc limpa a selecao"');
/* o aviso */
check(/hintEl = make\('div', 'kaz-canvas__toast', ''\);\n  hintEl\.setAttribute\('role', 'status'\);/.test(source) && /hintEl\.hidden = true;\n  viewport\.appendChild\(hintEl\);/.test(source), 'o aviso existe desde a abertura, escondido, com role="status"');
check(/box\.setAttribute\('aria-label', label\('inline_label', ''\)\);/.test(block) && /box\.maxLength = TEXT_MAX;/.test(block), 'a caixa tem nome e o limite do painel');

/* ---------- CSS ---------- */
const rule = sel => { const i = css.indexOf('\n' + sel + ' {'); check(i >= 0, 'regra CSS ' + sel); return css.slice(i, css.indexOf('}', i)); };
const boxRule = rule('.kaz-canvas .kaz-canvas__inline');
['position: absolute', 'padding: 0', 'border: 0', 'background: transparent', 'resize: none', 'overflow: hidden', 'user-select: text', 'box-sizing: content-box'].forEach(d => check(boxRule.indexOf(d) > 0, 'a caixa sobre o texto: ' + d));
check(/\.kaz-canvas \.kaz-canvas__inline:focus, \.kaz-canvas \.kaz-canvas__inline:focus-visible \{[^}]*outline: calc\(2px \/ var\(--kaz-canvas-k, 1\)\) solid #a78bfa/.test(css), 'o contorno da caixa em foco e o do canvas, nao o geral do admin');
check(rule('.kaz-canvas__toast').indexOf('pointer-events: none') > 0 && /\.kaz-canvas__toast\[hidden\] \{ display: none !important; \}/.test(css), 'o aviso nao recebe clique e some quando escondido');
check(/\[data-kaz-canvas-inline="1"\] \.kaz-canvas__mark--el, \[data-kaz-canvas-inline="1"\] \.kaz-canvas__mark--elhover \{ display: none; \}/.test(css), 'com a caixa aberta, o contorno do elemento sai');
check(rule('.kaz-canvas__inlineprobe').indexOf('visibility: hidden') > 0 && rule('.kaz-canvas__inlineprobe').indexOf('position: fixed') > 0, 'a regua fica fora da tela e invisivel');
check(css.split('.kaz-canvas__hint {').length - 1 === 2, 'a linha de ajuda do canvas (.kaz-canvas__hint) continua com as duas regras de antes');

/* ---------- rotulos ---------- */
const list = /\$canvasLabels=\[\];\s*foreach\(\[([\s\S]*?)\] as \$canvasKey\)/.exec(controller);
check(list !== null, 'a lista de rotulos do canvas foi encontrada no controller');
const NEW = ['inline_help', 'inline_label', 'inline_variable', 'inline_need_customize'];
NEW.forEach(k => check(list[1].split("'" + k + "'").length === 2, 'rotulo ' + k + ' na lista do controller, uma vez'));
const used = {}; let m; const re = /\b(?:label|hint)\('([a-z0-9_]+)'/g;
while ((m = re.exec(block))) used[m[1]] = true;
check(same(Object.keys(used).sort(), ['inline_help', 'inline_label'].sort()) || Object.keys(used).every(k => list[1].indexOf("'" + k + "'") >= 0), 'todo rotulo pedido pelo bloco esta na lista do controller');
["'inline_variable'", "'el_mixed'", "'inline_need_customize'", "'el_stale'"].forEach(k => check(pieces.inlineWhy.indexOf(k) > 0 && list[1].indexOf(k) >= 0, 'aviso ' + k + ' sai de inlineWhy e esta na lista'));
check(/'-kaz-canvas-kz\d{2}'/.test(controller) && /'-kaz-canvas-kz(1[89]|[2-9]\d)'/.test(controller), 'a versao dos arquivos do canvas foi trocada nesta etapa');
const WORDS = { 'pt-br': ['Enter', 'Esc', /p[áa]gina/i, /Duplo clique|Dois cliques/], 'en-us': ['Enter', 'Esc', /page/i, /Double-click/], 'es-eu': ['Intro', 'Esc', /p[áa]gina/i, /doble clic/i] };
const texts = {};
Object.keys(WORDS).forEach(code => {
  const lang = read('dev/admin/language/' + code + '/design/theme.php'), t = {};
  const r = /^\$_\['text_canvas_([a-z0-9_]+)'\]\s*=\s*'((?:[^'\\]|\\.)*)';/gm; let x;
  while ((x = r.exec(lang))) t[x[1]] = x[2].replace(/\\(['\\])/g, '$1');
  NEW.forEach(k => check(lang.split("$_['text_canvas_" + k + "']").length === 2, code + ': ' + k + ' escrito uma vez'));
  texts[code] = t;
  const w = WORDS[code];
  NEW.forEach(k => check(typeof t[k] === 'string' && t[k].length >= 30, code + ': ' + k + ' existe e e uma frase'));
  ['inline_help', 'inline_label'].forEach(k => check(t[k].indexOf(w[0]) >= 0 && t[k].indexOf(w[1]) >= 0, code + ': ' + k + ' diz as duas teclas (' + w[0] + ', ' + w[1] + ')'));
  check(w[2].test(t.inline_help) && w[2].test(t.inline_label) && w[3].test(t.inline_help), code + ': a ajuda fala em dois cliques na pagina');
  check(t.inline_need_customize.indexOf('"' + t.customize + '"') > 0, code + ': o aviso cita o botao "' + t.customize + '" pelo nome que ele tem');
  check(/text/i.test(t.frame_hint) && /text/i.test(t.hint), code + ': o rotulo do quadro e a linha de ajuda falam da edicao de texto');
  check(!/[<>]/.test(NEW.map(k => t[k]).join('')), code + ': as frases novas nao trazem HTML');
});
NEW.concat(['frame_hint', 'hint']).forEach(k => {
  check(texts['pt-br'][k] !== texts['en-us'][k] && texts['pt-br'][k] !== texts['es-eu'][k] && texts['en-us'][k] !== texts['es-eu'][k], k + ': tres frases diferentes, uma por idioma');
});

/* ---------- o conferidor acusa quando o codigo e estragado ---------- */
const BROKEN = [
  [["if (it[1] === 'variable') return 'inline_variable';", "if (it[1] === 'variable') return '';"], 'texto que vem de dado: avisa'],
  [["if (!own) return 'inline_need_customize';", ''], 'padrao do sistema: manda personalizar'],
  [["replace(/[\\r\\n\\t\\f\\v]+/g, ' ')", "replace(/[\\t\\f\\v]+/g, ' ')"], 'quebra de linha e tabulacao viram um espaco so'],
  [['var top = m.lines ? m.textTop - off : m.contentTop', 'var top = m.lines ? m.textTop : m.contentTop'], 'bloco: a caixa ocupa a largura do elemento, do topo da primeira linha, com a altura das linhas'],
  [['g.padTop = Math.max(0, -off);', 'g.padTop = 0;'], 'linha mais baixa que a letra: a sobra da letra vira margem interna e o texto fica no mesmo lugar'],
  [["if ((m.display === 'inline' || m.pseudo) && m.lines > 1) return 'box';", ''], 'link que quebra de linha: caixa propria'],
  [[' && !m.pseudo && !m.nowrap)', ')'], 'bloco que nao quebra linha: a caixa tambem nao quebra'],
  [['if (!(op >= 0 && op < 1)) return ink;', 'return ink;'], 'a transparencia do elemento entra na cor'],
];
BROKEN.forEach(b => {
  const bad = cases(build(b[0])).filter(c => !c[0]).map(c => c[1]);
  check(bad.indexOf(b[1]) >= 0, 'codigo estragado e acusado: ' + b[1]);
});

console.log('Kaz inline text checker: ' + checks + ' PASS');
