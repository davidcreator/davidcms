'use strict';
/*
 * KZ-08 (ADR 0101) — alinhamento e espacamento de um elemento
 * (dev/admin/view/js/kaz-element-attrs.js: spaceOk, pxOf, layoutOf e a parte
 * de setStyle que escreve as nove propriedades).
 * Roda sem navegador: `node tests/check_kaz_element_space.js`.
 * O mapa de cada caso e escrito a mao ou por um leitor de tags minimo, no
 * formato de ThemeSourceMap::describe().
 */
global.window = {};
require('../dev/admin/view/js/kaz-element-attrs.js');
const A = window.KazElementAttrs;
let checks = 0;
function check(ok, what) { checks++; if (!ok) throw new Error('FAIL: ' + what); }
function same(a, b, what) { check(JSON.stringify(a) === JSON.stringify(b), what + '\n   obtido:   ' + JSON.stringify(a) + '\n   esperado: ' + JSON.stringify(b)); }

/* Leitor de tags so para os testes (aspas duplas ou simples; Twig so dentro de um valor). */
const NAMES = { a: ['href', 'target', 'rel'], img: ['alt'] };
function mapOf(src) {
  const items = [];
  const re = /<([a-z0-9]+)((?:\s+[a-zA-Z-]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>"']+))?)*)\s*\/?>/g;
  let m;
  while ((m = re.exec(src))) {
    const tag = m[1], open0 = m.index, open1 = m.index + m[0].length, attrs = [];
    const ar = /([a-zA-Z-]+)(?:(\s*=\s*)(?:"([^"]*)"|'([^']*)'|([^\s>"']+)))?/g;
    const base = open0 + 1 + tag.length;
    let x;
    while ((x = ar.exec(m[2]))) {
      const name = x[1].toLowerCase();
      if (!(NAMES[tag] || []).concat(['aria-label', 'style', 'aria-hidden', 'role']).includes(name)) continue;
      const a0 = base + x.index, a1 = a0 + x[0].length;
      if (x[3] !== undefined || x[4] !== undefined) {
        const val = x[3] !== undefined ? x[3] : x[4];
        const v0 = a0 + x[1].length + x[2].length + 1;
        attrs.push([name, /\{\{|\{%/.test(val) ? 'variable' : 'fixed', v0, v0 + val.length, a0, a1, '']);
      } else {
        attrs.push([name, 'bare', a1 - (x[5] || '').length, a1, a0, a1, '']);
      }
    }
    const close = src.indexOf('</' + tag + '>', open1);
    const leaf = close !== -1 && !/</.test(src.slice(open1, close));
    items.push([tag, leaf ? 'fixed' : 'group', open0, open1, leaf ? open1 : -1, leaf ? close : -1, '', attrs]);
  }
  return { sig: '', items: items };
}
/* Cada posicao do mapa aponta para o que diz apontar: igual ao mapa lido de novo da fonte escrita. */
function mapHolds(src, map, what) {
  const norm = m => m.items.map(it => [it[0], it[2], it[3], it[4], it[5], (it[7] || []).map(a => a.slice(0, 6)).sort((p, q) => p[4] - q[4])]);
  same(norm(map), norm(mapOf(src)), what + ': o mapa acompanha a fonte');
}
/* Escrita que da certo: confere a fonte nova e o mapa. */
function wrote(src, n, prop, value, expected, what) {
  const map = mapOf(src);
  same(A.setStyle(src, map, n, prop, value), { src: expected }, what);
  mapHolds(expected, map, what);
  return expected;
}
/* Escrita recusada: o erro certo, e nem a fonte nem o mapa mudam. */
function refused(src, n, prop, value, error, what) {
  const map = mapOf(src), before = JSON.stringify(map);
  same(A.setStyle(src, map, n, prop, value), { error: error }, what);
  check(JSON.stringify(map) === before, what + ': mapa intacto');
}
const NONE = { margin: [], padding: [] };
const lay = (src, n) => A.layoutOf(src, mapOf(src), n || 0);
const SIDES = ['top', 'right', 'bottom', 'left'];
const SPACE = ['margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left'];
const ALL = SPACE.concat(['text-align']);

/* ---------- o que o editor escreve ---------- */
same(A.SPACE_PROPS, SPACE, 'os quatro lados de margem e de espaco interno, nessa ordem');
same(A.ALIGN_VALUES, ['left', 'center', 'right', 'justify'], 'quatro alinhamentos');
check(A.SPACE_MIN === 0 && A.SPACE_MAX === 400, 'faixa do espacamento');
same(A.STYLE_PROPS, ['color', 'background-color', 'font-size'], 'a lista da KZ-06 nao mudou');
A.SPACE_PROPS.push('margin'); A.ALIGN_VALUES.push('start');
refused('<p>x</p>', 0, 'margin', '4px', 'locked', 'a lista exportada e copia: mexer nela nao abre propriedade nova');
refused('<p>x</p>', 0, 'text-align', 'start', 'value', 'idem para os alinhamentos');
A.SPACE_PROPS.pop(); A.ALIGN_VALUES.pop();

/* ---------- valores aceitos e recusados ---------- */
['0px', '1px', '16px', '400px', '12.5px', '12.25px', '016px', '0.5px', '0.00px', '399.99px'].forEach(v => check(A.spaceOk(v), 'espacamento aceito ' + v));
['', 'px', '0', '16', '401px', '400.01px', '1000px', '16 px', '16PX', '16pt', '1rem', '1.2em', '10%', 'auto', '-16px', '-0px', '+16px', '16.px', '.5px', '16.123px',
 '1e2px', '16px ', ' 16px', '16px;color:red', 'calc(16px)', '16px\n', '16px 8px', '0 auto', 'inherit', 'var(--gap)', '16px !important'
].forEach(v => check(!A.spaceOk(v), 'espacamento recusado ' + JSON.stringify(v)));
[['0', 0], ['0px', 0], ['18px', 18], ['18PX', 18], [' 18px ', 18], ['12.5px', 12.5], ['400px', 400], ['016px', 16]].forEach(c => same(A.pxOf(c[0]), c[1], 'campo mostra ' + JSON.stringify(c[0])));
['', '00', '0.0', '18', '-8px', 'auto', '1.5em', '10%', '401px', '18px !important', 'calc(1px)', 'var(--a)', '0 auto', 'px'].forEach(v => same(A.pxOf(v), null, 'campo nao mostra ' + JSON.stringify(v)));

/* ---------- o que o elemento tem hoje ---------- */
same(lay('<p>x</p>'), { state: 'none', values: {}, cover: NONE, blocked: [], align: true }, 'tag sem style');
same(lay('<p style="">x</p>'), { state: 'fixed', values: {}, cover: NONE, blocked: [], align: true }, 'style vazio');
same(lay('<p style="color:red;max-width:52ch">x</p>'), { state: 'fixed', values: {}, cover: NONE, blocked: [], align: true }, 'style sem nenhuma das nove');
same(lay('<p style="margin-top:18px;padding-left:12px;text-align:center;color:red">x</p>'),
  { state: 'fixed', values: { 'margin-top': '18px', 'padding-left': '12px', 'text-align': 'center' }, cover: NONE, blocked: [], align: true }, 'so as nove aparecem, como estao escritas');
same(lay('<p style="MARGIN-TOP: 12PX ; Text-Align : CENTER">x</p>').values, { 'margin-top': '12PX', 'text-align': 'CENTER' }, 'nome em minusculas, valor como esta');
same(lay('<p style="margin-left:auto;margin-top:-8px;padding-top:1.5em;text-align:start">x</p>').values,
  { 'margin-left': 'auto', 'margin-top': '-8px', 'padding-top': '1.5em', 'text-align': 'start' }, 'valor que o editor nao escreveria e lido mesmo assim');
/* forma curta: fica como esta, e e mostrada */
same(lay('<p style="color:var(--at-muted);margin:0 auto 26px;max-width:52ch">x</p>'),
  { state: 'fixed', values: {}, cover: { margin: ['margin: 0 auto 26px'], padding: [] }, blocked: [], align: true }, 'forma curta de margem');
same(lay('<p style="padding:4px 8px;margin-block:1em;PADDING-INLINE-START: 2px;margin-inline-end:0">x</p>').cover,
  { margin: ['margin-block: 1em', 'margin-inline-end: 0'], padding: ['padding: 4px 8px', 'padding-inline-start: 2px'] }, 'formas curtas e logicas dos dois grupos, na ordem do codigo');
same(lay('<p style="margin-topp:1px;margins:0;padding-box:1px;margin-block-middle:0;--margin:1px;scroll-margin:4px;scroll-padding-top:2px">x</p>'),
  { state: 'fixed', values: {}, cover: NONE, blocked: [], align: true }, 'nomes parecidos nao contam');
/* lado escrito antes da forma curta do mesmo grupo: anulado por ela */
same(lay('<p style="margin-top:5px;margin:0;padding-top:3px">x</p>'),
  { state: 'fixed', values: { 'margin-top': '5px', 'padding-top': '3px' }, cover: { margin: ['margin: 0'], padding: [] }, blocked: ['margin-top'], align: true }, 'so o lado do mesmo grupo fica anulado');
same(lay('<p style="margin:0;margin-top:5px">x</p>').blocked, [], 'lado escrito depois da forma curta vale');
same(lay('<p style="padding-left:1px;margin-left:2px;padding-block:0;padding-right:3px">x</p>').blocked, ['padding-left'], 'forma logica tambem anula o que vem antes; o que vem depois, nao');
same(lay('<p style="margin-top:1px;margin-left:2px;margin:0;margin-inline:4px;margin-bottom:3px">x</p>').blocked, ['margin-top', 'margin-left'], 'vale a ultima forma curta do grupo');
same(lay('<p style="margin:0;margin-top:5px;margin:1px;margin-left:2px">x</p>').blocked, ['margin-top'], 'lado entre duas formas curtas: a segunda o anula');
same(lay('<p style="padding:0;padding-top:5px;padding-inline:1px;padding-left:2px">x</p>').blocked, ['padding-top'], 'idem com forma logica por ultimo');
same(lay('<p style="text-align:left;margin:0;padding:0">x</p>').blocked, [], 'alinhamento nunca e anulado por forma curta');
/* estados em que o editor nao escreve */
[['<p style="margin-top:{{ m }}">x</p>', 'variable'], ['<p style="{{ s }}">x</p>', 'variable'], ['<p style=margin-top:1px>x</p>', 'locked'], ['<p style>x</p>', 'locked'],
 ['<p style="margin-top:1px;margin-top:2px">x</p>', 'locked'], ['<p style="text-align:left;color:red;TEXT-ALIGN:right">x</p>', 'locked'], ['<p style="padding-left:1px;padding-left:1px">x</p>', 'locked'],
 ['<p style="font-family:&quot;A&quot;;margin-top:1px">x</p>', 'locked'], ['<p style="margin-top:1px/*x*/">x</p>', 'locked'], ['<p style="margin-top">x</p>', 'locked'],
 ["<p style='margin-top:1px'>x</p>", 'fixed'], ['<p style="margin:0;margin:1px">x</p>', 'fixed'], ['<p style="color:red;color:blue;margin-top:1px">x</p>', 'fixed'],
 ['<br style="margin-top:1px">', 'skip'], ['<col style="padding-left:1px">', 'skip'], ['<wbr>', 'skip']
].forEach(c => same(lay(c[0]).state, c[1], 'estado de ' + c[0]));
/* cada familia so se trava com a repeticao das suas proprias propriedades */
same(A.styleOf('<p style="margin-top:1px;margin-top:2px;color:red">x</p>', mapOf('<p style="margin-top:1px;margin-top:2px;color:red">x</p>'), 0), { state: 'fixed', values: { color: 'red' } }, 'lado repetido nao trava a cor');
same(lay('<p style="color:red;color:blue;margin-top:1px">x</p>').values, { 'margin-top': '1px' }, 'cor repetida nao trava o espacamento');
/* em que tags */
['p', 'div', 'section', 'h2', 'span', 'a', 'li', 'button', 'td', 'label', 'input', 'textarea'].forEach(t => same([lay('<' + t + '>x</' + t + '>').state, lay('<' + t + '>x</' + t + '>').align], ['none', true], '<' + t + '>: espacamento e alinhamento'));
['img', 'picture', 'video', 'audio', 'canvas', 'svg', 'hr'].forEach(t => same([lay('<' + t + '>').state, lay('<' + t + '>').align], ['none', false], '<' + t + '>: espacamento, sem alinhamento'));
['br', 'wbr', 'source', 'track', 'param', 'area', 'map', 'col'].forEach(t => same([lay('<' + t + '>').state, lay('<' + t + '>').align], ['skip', false], '<' + t + '>: nenhum dos dois'));
same(lay('<img style="text-align:center;margin-top:4px">').values, { 'margin-top': '4px' }, 'alinhamento escrito em imagem nao aparece: nao ha campo para ele');
same(lay('<p>x</p>', 9).state, 'closed', 'item que nao existe');
same(A.layoutOf('<p>x</p>', null, 0), { state: 'closed', values: {}, cover: NONE, blocked: [], align: false }, 'mapa ausente');
same(A.layoutOf('<p>x</p>', { items: [['p', 'fixed', 0, 3, 3, 4, '', null]] }, 0).state, 'closed', 'tag que o servidor nao soube ler');
same(A.layoutOf('<br>', { items: [['br', 'void', 0, 4, -1, -1, '', null]] }, 0).state, 'closed', 'tag sem caixa que o servidor nao soube ler: fechada vem antes');
same(A.layoutOf('<p class="margin-top:1px">x</p>', { items: [['p', 'fixed', 0, 31, 31, 32, '', [['style', 'fixed', 10, 24, 3, 25, '']]]] }, 0).state, 'locked', 'mapa que nao e desta fonte: nada e lido');
/* ler nao muda o mapa nem devolve objetos compartilhados */
(function () {
  const src = '<p style="margin:0;margin-top:1px">x</p>', map = mapOf(src), before = JSON.stringify(map);
  const one = A.layoutOf(src, map, 0), two = A.layoutOf(src, map, 0);
  one.values['margin-top'] = 'x'; one.cover.margin.push('y'); one.blocked.push('z');
  same(two, { state: 'fixed', values: { 'margin-top': '1px' }, cover: { margin: ['margin: 0'], padding: [] }, blocked: [], align: true }, 'cada leitura devolve um objeto so dela');
  check(JSON.stringify(map) === before, 'ler nao muda o mapa');
  const empty = A.layoutOf('<p>x</p>', mapOf('<p>x</p>'), 0); empty.cover.margin.push('y');
  same(A.layoutOf('<p>x</p>', mapOf('<p>x</p>'), 0).cover, NONE, 'idem na tag sem style');
})();

/* ---------- tag sem style: o atributo e criado logo depois do nome da tag ---------- */
let T = '<section class="x"><h2>T\u00edtulo \ud83d\ude00</h2><p class="lead">Texto</p><img src="a.png" alt="Foto"><hr></section>';
SPACE.forEach(prop => wrote(T, 2, prop, '16px', T.replace('<p class', '<p style="' + prop + ':16px" class'), prop + ' em tag sem style'));
wrote(T, 2, 'text-align', 'center', T.replace('<p class', '<p style="text-align:center" class'), 'alinhamento em tag sem style');
wrote(T, 1, 'margin-top', '0px', T.replace('<h2>', '<h2 style="margin-top:0px">'), 'zero e um valor, nao um pedido para retirar');
wrote(T, 0, 'padding-left', '400px', T.replace('<section class', '<section style="padding-left:400px" class'), 'o teto da faixa, na tag de fora');
wrote(T, 3, 'margin-bottom', '24px', T.replace('<img src', '<img style="margin-bottom:24px" src'), 'margem em imagem');
wrote(T, 4, 'margin-top', '8px', T.replace('<hr>', '<hr style="margin-top:8px">'), 'margem em <hr>');
wrote('<p\n  class="a"\n>x</p>', 0, 'margin-top', '4px', '<p style="margin-top:4px"\n  class="a"\n>x</p>', 'tag quebrada em linhas');
/* formas de escrever o mesmo valor */
[['016.50px', '16.5px'], [' 20PX ', '20px'], ['0.00px', '0px'], ['12.50px', '12.5px'], ['7px', '7px']].forEach(c =>
  wrote('<p>x</p>', 0, 'margin-top', c[0], '<p style="margin-top:' + c[1] + '">x</p>', 'valor ' + JSON.stringify(c[0]) + ' sai como ' + c[1]));
[['CENTER', 'center'], [' Right ', 'right'], ['justify', 'justify'], ['left', 'left']].forEach(c =>
  wrote('<p>x</p>', 0, 'text-align', c[0], '<p style="text-align:' + c[1] + '">x</p>', 'alinhamento ' + JSON.stringify(c[0]) + ' sai como ' + c[1]));
/* as nove no mesmo style, na ordem em que sao pedidas; e a volta, ate a fonte original */
(function () {
  const map = mapOf(T);
  let r = { src: T }, body = [];
  ALL.forEach((prop, i) => {
    const v = prop === 'text-align' ? 'right' : (i + 1) + 'px';
    r = A.setStyle(r.src, map, 1, prop, v);
    body.push(prop + ':' + v);
    same(r.src, T.replace('<h2>', '<h2 style="' + body.join(';') + '">'), 'escrita ' + (i + 1) + ' de nove com o mesmo mapa');
    mapHolds(r.src, map, 'escrita ' + (i + 1) + ' de nove');
  });
  same(A.layoutOf(r.src, map, 1).values, Object.fromEntries(body.map(d => d.split(':'))), 'as nove lidas de volta');
  /* sai uma do meio, a primeira, a ultima... */
  [4, 0, 8, 2, 6, 1, 7, 3, 5].forEach((i, step) => {
    r = A.setStyle(r.src, map, 1, ALL[i], '');
    body = body.filter(d => d.split(':')[0] !== ALL[i]);
    same(r.src, T.replace('<h2>', body.length ? '<h2 style="' + body.join(';') + '">' : '<h2>'), 'retirada ' + (step + 1) + ' de nove');
    mapHolds(r.src, map, 'retirada ' + (step + 1) + ' de nove');
  });
  same(r.src, T, 'ida e volta: a fonte e a original, caractere por caractere');
  r = A.setStyle(r.src, map, 2, 'margin-top', '9px');
  same(r.src, T.replace('<p class', '<p style="margin-top:9px" class'), 'o mapa ainda vale para o elemento seguinte');
})();
/* retirar o que nao existe nao muda nada */
ALL.forEach(prop => wrote(T, 2, prop, '', T, 'retirar ' + prop + ' de tag sem style: fonte igual'));
wrote(T, 2, 'margin-top', '   ', T, 'so espacos conta como vazio');

/* ---------- style que ja existe ---------- */
let S = '<div><p style="color:var(--at-muted);margin:0 auto 26px;max-width:52ch">a</p><p style="margin-top:18px;padding-left:12px;text-align:center">b</p><p style="margin-left: auto ;">c</p><p style=" text-align:start">d</p></div>';
wrote(S, 2, 'margin-top', '32px', S.replace('margin-top:18px', 'margin-top:32px'), 'troca so o valor do lado; o resto do style fica igual');
wrote(S, 2, 'text-align', 'right', S.replace('text-align:center', 'text-align:right'), 'troca o alinhamento');
wrote(S, 2, 'margin-bottom', '8px', S.replace('text-align:center"', 'text-align:center;margin-bottom:8px"'), 'lado novo entra no fim, com ponto e virgula');
wrote(S, 2, 'margin-top', '', S.replace('margin-top:18px;', ''), 'retirar a primeira leva o ponto e virgula dela');
wrote(S, 2, 'padding-left', '', S.replace('padding-left:12px;', ''), 'retirar a do meio');
wrote(S, 2, 'text-align', '', S.replace(';text-align:center', ''), 'retirar a ultima leva o ponto e virgula de antes');
wrote(S, 3, 'margin-left', '10px', S.replace('margin-left: auto ;', 'margin-left: 10px ;'), 'valor que o editor nao escreveria e trocado; os espacos em volta ficam');
wrote(S, 3, 'margin-left', '', S.replace('<p style="margin-left: auto ;">', '<p>'), 'retirar a unica: o atributo sai inteiro');
wrote(S, 3, 'padding-top', '4px', S.replace('margin-left: auto ;"', 'margin-left: auto ;padding-top:4px"'), 'style que termina em ponto e virgula nao ganha outro');
wrote(S, 4, 'text-align', 'left', S.replace(' text-align:start', ' text-align:left'), 'espaco no comeco do valor fica');
wrote(S, 4, 'text-align', '', S.replace('<p style=" text-align:start">', '<p>'), 'retirar a unica com espaco no comeco');
wrote('<p style="MARGIN-TOP: 12PX ; Text-Align : CENTER">x</p>', 0, 'margin-top', '20px', '<p style="MARGIN-TOP: 20px ; Text-Align : CENTER">x</p>', 'nome em maiusculas e achado; so o valor muda');
wrote('<p style="MARGIN-TOP: 12PX ; Text-Align : CENTER">x</p>', 0, 'text-align', 'left', '<p style="MARGIN-TOP: 12PX ; Text-Align : left">x</p>', 'idem no alinhamento');
wrote('<p style="margin-top:8px !important">x</p>', 0, 'margin-top', '4px', '<p style="margin-top:4px">x</p>', 'o valor inteiro e trocado, com o !important junto');
wrote("<p style='margin-top:1px'>x</p>", 0, 'margin-top', '2px', "<p style='margin-top:2px'>x</p>", 'aspas simples ficam');
wrote('<p style="">x</p>', 0, 'margin-top', '2px', '<p style="margin-top:2px">x</p>', 'style vazio recebe a declaracao sem ponto e virgula');
wrote('<p style="margin-top:1px">x</p><p style="margin-top:1px">y</p>', 1, 'margin-top', '2px', '<p style="margin-top:1px">x</p><p style="margin-top:2px">y</p>', 'so o elemento pedido muda');

/* ---------- forma curta: nunca e reescrita; o lado pedido entra no fim, onde vale por cima ---------- */
wrote(S, 1, 'margin-top', '40px', S.replace('max-width:52ch"', 'max-width:52ch;margin-top:40px"'), 'lado novo depois da forma curta');
wrote(S, 1, 'padding-left', '4px', S.replace('max-width:52ch"', 'max-width:52ch;padding-left:4px"'), 'lado de outro grupo, tambem no fim');
(function () {
  const map = mapOf(S);
  let r = A.setStyle(S, map, 1, 'margin-bottom', '0px');
  r = A.setStyle(r.src, map, 1, 'margin-bottom', '12px');
  same(r.src, S.replace('max-width:52ch"', 'max-width:52ch;margin-bottom:12px"'), 'o lado ja acrescentado e trocado no lugar');
  same(A.layoutOf(r.src, map, 1), { state: 'fixed', values: { 'margin-bottom': '12px' }, cover: { margin: ['margin: 0 auto 26px'], padding: [] }, blocked: [], align: true }, 'a forma curta continua la, intacta');
  r = A.setStyle(r.src, map, 1, 'margin-bottom', '');
  same(r.src, S, 'retirar o lado devolve a fonte original: a forma curta volta a valer sozinha');
  mapHolds(S, map, 'forma curta, ida e volta');
})();
wrote('<p style="margin:0;margin-top:5px">x</p>', 0, 'margin-top', '9px', '<p style="margin:0;margin-top:9px">x</p>', 'lado escrito depois da forma curta: troca no lugar');
wrote('<p style="margin:0;margin-top:5px">x</p>', 0, 'margin-top', '', '<p style="margin:0">x</p>', 'e pode ser retirado');
/* lado anulado: o editor nao troca nem retira; os outros lados continuam abertos */
S = '<p style="margin-top:5px;margin:0;padding-left:2px;padding-inline:0">x</p>';
refused(S, 0, 'margin-top', '9px', 'locked', 'lado antes da forma curta: nao troca');
refused(S, 0, 'margin-top', '', 'locked', 'lado antes da forma curta: nem retira');
refused(S, 0, 'padding-left', '9px', 'locked', 'lado antes de uma forma logica: nao troca');
wrote(S, 0, 'margin-left', '9px', S.replace('padding-inline:0"', 'padding-inline:0;margin-left:9px"'), 'outro lado do mesmo grupo entra no fim e vale');
wrote(S, 0, 'padding-top', '9px', S.replace('padding-inline:0"', 'padding-inline:0;padding-top:9px"'), 'idem no outro grupo');
wrote(S, 0, 'text-align', 'center', S.replace('padding-inline:0"', 'padding-inline:0;text-align:center"'), 'e o alinhamento tambem');
wrote(S, 0, 'color', '#000', S.replace('padding-inline:0"', 'padding-inline:0;color:#000"'), 'a cor nao depende disso');

/* ---------- recusas: fonte e mapa intactos ---------- */
S = '<div><h2>T</h2><p style="margin-top:4px">x</p><img src="a.png" alt=""><p style="margin-top:{{ m }}">y</p><p style="font-family:&quot;A&quot;">z</p><p style="margin-top:1px;margin-top:2px">w</p><p style=margin-top:1px>v</p><br><svg></svg><hr></div>';
['-1px', '401px', '1rem', '16', '0', 'auto', '10%', '16px;color:red', 'calc(1px)', '#fff', 'center', '4px 8px', '4px !important', 'var(--gap)', '{{ x }}', '4px" onclick="alert(1)', 'inherit'
].forEach(v => SPACE.forEach(prop => refused(S, 2, prop, v, 'value', prop + ' fora da gramatica: ' + v)));
['start', 'end', 'inherit', 'initial', 'middle', 'centre', 'center;color:red', 'center !important', '4px', '#fff', 'left right', 'var(--a)', '{{ x }}', 'left" onclick="alert(1)', '-webkit-center'
].forEach(v => refused(S, 2, 'text-align', v, 'value', 'alinhamento fora da lista: ' + v));
['margin', 'padding', 'margin-block', 'margin-inline-start', 'padding-block-end', 'text-align-last', 'margin-Top', 'MARGIN-TOP', 'Text-Align', 'margintop', 'margin-', 'margin-middle', 'gap', 'width',
 'line-height', 'border', 'display', 'position', 'float', 'constructor', '__proto__', 'toString', ''
].forEach(p => { refused(S, 2, p, '4px', 'locked', 'propriedade fora da lista: ' + JSON.stringify(p)); refused(S, 2, p, '', 'locked', 'propriedade fora da lista nao e retirada: ' + JSON.stringify(p)); });
refused(S, 3, 'text-align', 'center', 'locked', 'imagem: sem alinhamento');
refused(S, 9, 'text-align', 'center', 'locked', '<svg>: sem alinhamento');
refused(S, 10, 'text-align', 'center', 'locked', '<hr>: sem alinhamento');
refused(S, 8, 'margin-top', '4px', 'locked', '<br>: sem espacamento');
refused(S, 8, 'text-align', 'center', 'locked', '<br>: sem alinhamento');
ALL.forEach(prop => {
  const v = prop === 'text-align' ? 'left' : '4px';
  refused(S, 4, prop, v, 'locked', 'style com Twig: nao escreve ' + prop);
  refused(S, 5, prop, v, 'locked', 'style com entidade: nao escreve ' + prop);
  refused(S, 6, prop, v, 'locked', 'lado repetido: nao escolhe qual trocar nem acrescenta outro (' + prop + ')');
  refused(S, 7, prop, v, 'locked', 'style sem aspas: nao escreve ' + prop);
  refused(S, 99, prop, v, 'locked', 'elemento que nao existe: ' + prop);
});
refused(S, 4, 'margin-top', '', 'locked', 'style com Twig: nem retira');
wrote(S, 6, 'color', '#000', S.replace('margin-top:1px;margin-top:2px"', 'margin-top:1px;margin-top:2px;color:#000"'), 'lado repetido nao impede a cor');
wrote('<p style="color:red;color:blue">x</p>', 0, 'margin-top', '4px', '<p style="color:red;color:blue;margin-top:4px">x</p>', 'cor repetida nao impede o espacamento');
refused('<p style="color:red;color:blue">x</p>', 0, 'color', '#000', 'locked', '... e a cor repetida continua fechada');
same(A.setStyle(S, null, 1, 'margin-top', '4px'), { error: 'locked' }, 'mapa ausente');
same(A.setStyle(S, { items: [['p', 'fixed', 0, 3, 3, 4, '', null]] }, 0, 'margin-top', '4px'), { error: 'locked' }, 'tag que o servidor nao soube ler');
/* a ordem das recusas: tag sem campo, depois valor, depois fonte */
refused('<br>', 0, 'margin-top', 'abc', 'locked', 'tag sem caixa vem antes do valor');
refused('<p style="margin-top:{{ m }}">x</p>', 0, 'margin-top', 'abc', 'value', 'valor vem antes do estado do style');

/* mapa de outra fonte */
(function () {
  const src = '<div><h2>T</h2><p style="margin-top:4px">x</p></div>', other = '<section><h2>T</h2><p style="margin-top:4px">x</p></section>';
  [[1, 'tag sem style'], [2, 'tag com style']].forEach(c => ALL.forEach(prop => {
    const map = mapOf(src), before = JSON.stringify(map);
    same(A.setStyle(other, map, c[0], prop, prop === 'text-align' ? 'left' : '8px'), { error: 'stale' }, 'mapa de outra fonte, ' + c[1] + ', ' + prop);
    check(JSON.stringify(map) === before, 'mapa de outra fonte: intacto');
  }));
  const glued = '<p class="a"style="margin-top:4px">x</p>', map = { items: [['p', 'fixed', 0, 35, 35, 36, '', [['style', 'fixed', 19, 33, 12, 34, '']]]] }, before = JSON.stringify(map);
  same(A.setStyle(glued, map, 0, 'margin-top', ''), { error: 'stale' }, 'style colado no atributo anterior: nao retira o atributo');
  check(JSON.stringify(map) === before, 'style colado: mapa intacto');
})();

/* fonte no teto (em bytes, como o servidor conta) */
(function () {
  const M = A.SOURCE_MAX, add = ' style="margin-top:4px"';
  const fits = '<p>' + 'x'.repeat(M - 7 - add.length) + '</p>';
  same(A.setStyle(fits, mapOf(fits), 0, 'margin-top', '4px').src.length, M, 'atributo novo cabe exatamente no teto');
  refused(fits, 0, 'margin-top', '40px', 'long', 'um byte acima do teto (atributo novo)');
  refused(fits, 0, 'text-align', 'center', 'long', 'alinhamento que nao cabe');
  const full = '<p style="margin-top:4px">' + 'x'.repeat(M - '<p style="margin-top:4px">'.length - 4) + '</p>';
  check(full.length === M, 'fonte cheia montada');
  refused(full, 0, 'margin-top', '40px', 'long', 'acima do teto (troca de valor)');
  refused(full, 0, 'padding-top', '1px', 'long', 'acima do teto (lado novo)');
  same(A.setStyle(full, mapOf(full), 0, 'margin-top', '9px').src.length, M, 'troca do mesmo tamanho passa');
  same(A.setStyle(full, mapOf(full), 0, 'margin-top', '').src, full.replace(' style="margin-top:4px"', ''), 'retirar sempre cabe');
  const acc = '<p>' + '\u00e9'.repeat((M - 7 - add.length) / 2 | 0) + 'x'.repeat((M - 7 - add.length) % 2) + '</p>';
  check(typeof A.setStyle(acc, mapOf(acc), 0, 'margin-top', '4px').src === 'string', 'fonte com acentos: o que cabe em bytes passa');
  same(A.setStyle(acc, mapOf(acc), 0, 'margin-top', '40px'), { error: 'long' }, 'fonte com acentos: passa do teto em bytes com metade dos caracteres');
})();

/* ---------- o que e escrito nunca esbarra na validacao do tema nem sai do atributo ---------- */
const BLOCKED = /<\s*(script|iframe|object|embed)\b|javascript\s*:|\bon[a-z]+\s*=|\|\s*raw\b|\{%\s*autoescape\b|\b(srcdoc|formaction)\s*=/i;
ALL.forEach(prop => (prop === 'text-align' ? A.ALIGN_VALUES : ['0px', '4px', '12.5px', '400px']).forEach(v => {
  const base = '<p class="a">x</p>', r = A.setStyle(base, mapOf(base), 0, prop, v);
  check(typeof r.src === 'string' && !BLOCKED.test(r.src), 'escrita aceita pela validacao: ' + prop + ' ' + v);
  same(r.src, '<p style="' + prop + ':' + v + '" class="a">x</p>', 'forma exata: ' + prop + ' ' + v);
  same(A.layoutOf(r.src, mapOf(r.src), 0).values, { [prop]: v }, 'o que foi escrito e lido de volta: ' + prop + ' ' + v);
  if (prop !== 'text-align') same(A.pxOf(v), Number(v.slice(0, -2)), 'e o campo mostra o mesmo numero: ' + v);
}));

/* ---------- convivio com as outras regras no mesmo mapa ---------- */
(function () {
  const src = '<a class="b" href="/velho" style="color:red;margin:0 0 8px">Ir</a><svg class="i"></svg><p>x</p>', map = mapOf(src);
  let r = A.setValue(src, map, 0, 'href', '/novo-destino');
  r = A.setStyle(r.src, map, 0, 'margin-top', '12px');
  r = A.setNewTab(r.src, map, 0, true);
  r = A.setStyle(r.src, map, 0, 'color', '#000');
  r = A.setStyle(r.src, map, 0, 'text-align', 'center');
  r = A.decorate(r.src, map, 1, true);
  r = A.setStyle(r.src, map, 1, 'margin-left', '6px');
  r = A.setStyle(r.src, map, 2, 'padding-top', '3px');
  r = A.setStyle(r.src, map, 0, 'font-size', '18px');
  same(r.src, '<a class="b" href="/novo-destino" target="_blank" rel="noopener" style="color:#000;margin:0 0 8px;margin-top:12px;text-align:center;font-size:18px">Ir</a>'
    + '<svg style="margin-left:6px" aria-hidden="true" class="i"></svg><p style="padding-top:3px">x</p>', 'link, nova aba, cor, enfeite e espacamento com o mesmo mapa');
  mapHolds(r.src, map, 'link, nova aba, cor, enfeite e espacamento');
  same(A.styleOf(r.src, map, 0).values, { color: '#000', 'font-size': '18px' }, 'styleOf continua mostrando so as tres da KZ-06');
  same(A.layoutOf(r.src, map, 0).values, { 'margin-top': '12px', 'text-align': 'center' }, 'e layoutOf so as nove da KZ-08');
})();

console.log('Kaz element space checker: ' + checks + ' PASS');
