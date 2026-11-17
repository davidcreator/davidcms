'use strict';
/*
 * KZ-06 (ADR 0099) — cor e tamanho do texto de um elemento
 * (dev/admin/view/js/kaz-element-attrs.js: styleParse, styleOf, setStyle).
 * Roda sem navegador: `node tests/check_kaz_element_style.js`.
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
      if (!(NAMES[tag] || []).concat(['aria-label', 'style']).includes(name)) continue;
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
/* Uma escrita sobre uma fonte, com o mapa dela. */
function put(src, n, prop, value) { const map = mapOf(src); const r = A.setStyle(src, map, n, prop, value); return { r, map }; }
/* Escrita que da certo: confere a fonte nova e o mapa. */
function wrote(src, n, prop, value, expected, what) {
  const t = put(src, n, prop, value);
  same(t.r, { src: expected }, what);
  mapHolds(expected, t.map, what);
  return expected;
}
/* Escrita recusada: o erro certo, e nem a fonte nem o mapa mudam. */
function refused(src, n, prop, value, error, what) {
  const map = mapOf(src), before = JSON.stringify(map);
  same(A.setStyle(src, map, n, prop, value), { error: error }, what);
  check(JSON.stringify(map) === before, what + ': mapa intacto');
}

/* ---------- valores aceitos e recusados ---------- */
same(A.STYLE_PROPS, ['color', 'background-color', 'font-size'], 'so tres propriedades');
check(A.SIZE_MIN === 6 && A.SIZE_MAX === 400, 'faixa do tamanho');
['#fff', '#000000', '#4338ca', '#a1b', 'var(--at-primary)', 'var(--theme-primary)', 'var(--a)', 'var(--rms-color-text-2)', 'var(--a' + 'b'.repeat(47) + ')'
].forEach(v => check(A.colorOk(v), 'cor aceita ' + v));
['', ' ', 'red', 'transparent', 'inherit', 'currentColor', '#ff', '#ffff', '#fffff', '#fffffff', '#ffffffff', '#ggg', 'fff', '#FFF', ' #fff', '#fff ', '#fff\n',
 'rgb(0,0,0)', 'rgba(0,0,0,.5)', 'hsl(0 0% 0%)', 'var(--A)', 'var(--1a)', 'var(-a)', 'var(a)', 'var(--a,red)', 'var(--a) ', 'var( --a )', 'VAR(--a)', 'var(--a_b)',
 'var(--a' + 'b'.repeat(48) + ')', 'var(--a);position:fixed', '#fff;position:fixed', '#fff" onclick="x', "#fff' x='", 'url(javascript:x)', 'expression(alert(1))',
 '{{ cor }}', '#fff}', '#fff/*x*/', 'var(--a)var(--b)'
].forEach(v => check(!A.colorOk(v), 'cor recusada ' + JSON.stringify(v)));
['6px', '16px', '400px', '12.5px', '12.25px', '016px', '99.99px'].forEach(v => check(A.sizeOk(v), 'tamanho aceito ' + v));
['', 'px', '5px', '5.99px', '401px', '400.01px', '1000px', '16', '16 px', '16PX', '16pt', '1rem', '1.2em', '120%', '-16px', '+16px', '16.px', '.5px', '16.123px',
 '1e2px', '16px ', ' 16px', '16px;color:red', 'calc(16px)', '16px\n'
].forEach(v => check(!A.sizeOk(v), 'tamanho recusado ' + JSON.stringify(v)));

/* ---------- leitura do valor de um style ---------- */
same(A.styleParse(''), [], 'vazio');
same(A.styleParse(' ; ;; '), [], 'so separadores');
same(A.styleParse('color:red').map(d => [d.prop, d.value, d.from, d.to, d.v0, d.v1]), [['color', 'red', 0, 9, 6, 9]], 'uma declaracao');
same(A.styleParse('  Color :  RED  ; margin:0 auto').map(d => [d.prop, d.value, d.from, d.to, d.v0, d.v1]),
  [['color', 'RED', 0, 16, 11, 14], ['margin', '0 auto', 17, 31, 25, 31]], 'espacos e maiusculas: nome em minusculas, valor como esta');
same(A.styleParse('background:url(data:image/png;base64,AA==);color:#fff;').map(d => [d.prop, d.value]),
  [['background', 'url(data:image/png;base64,AA==)'], ['color', '#fff']], 'ponto e virgula dentro de parenteses nao separa');
same(A.styleParse('--at-x: 4px;grid-template-columns:minmax(0,1fr) 290px').map(d => d.prop), ['--at-x', 'grid-template-columns'], 'variavel e funcao');
same(A.styleParse('color:red !important').map(d => d.value), ['red !important'], '!important fica no valor');
same(A.styleParse('a:b:c').map(d => [d.prop, d.value]), [['a', 'b:c']], 'so o primeiro dois-pontos separa');
['color', 'color:', ':red', 'color red', '1a:b', 'a b:c', 'color:red;x', 'font-family:"Inter"', "font-family:'Inter'", 'font-family:&quot;Inter&quot;', 'color:red&#59;x:y',
 'content:\\3b', 'color:red/*x*/', 'a{color:red}', 'color:red}', 'color:<b>', 'color:a>b', 'width:calc((1px)', 'width:calc(1px))', 'a:)(', 'color:red\u0000', 'color:\u007fred',
 'color:{{ c }}', 'color:{% if x %}red{% endif %}'
].forEach(v => check(A.styleParse(v) === null, 'style que o leitor nao acompanha: ' + JSON.stringify(v)));

/* ---------- o que o elemento tem hoje ---------- */
let S = '<section class="x"><h2>T\u00edtulo</h2><p style="color:var(--at-muted);max-width:52ch;margin:0 auto 26px">Texto</p><img src="a.png" alt="Foto"><a href="/x" style="FONT-SIZE: 18px ; Background-Color:#fff">Ir</a></section>';
let M = mapOf(S);
same(M.items.map(it => it[0]), ['section', 'h2', 'p', 'img', 'a'], 'o leitor de teste acha as cinco tags');
same(A.styleOf(S, M, 0), { state: 'none', values: {} }, 'tag sem style');
same(A.styleOf(S, M, 2), { state: 'fixed', values: { color: 'var(--at-muted)' } }, 'style lido: so as tres propriedades aparecem');
same(A.styleOf(S, M, 3), { state: 'skip', values: {} }, 'img: os campos nao se aplicam');
same(A.styleOf(S, M, 4), { state: 'fixed', values: { 'font-size': '18px', 'background-color': '#fff' } }, 'nomes em maiusculas sao reconhecidos');
same(A.styleOf(S, M, 9), { state: 'closed', values: {} }, 'item que nao existe');
same(A.styleOf(S, null, 0), { state: 'closed', values: {} }, 'mapa ausente');
same(A.styleOf(S, { items: [['p', 'fixed', 0, 3, 3, 4, '', null]] }, 0), { state: 'closed', values: {} }, 'tag que o servidor nao soube ler');
[['<p style="color:{{ c }}">x</p>', 'variable'], ['<p style="{{ s }}">x</p>', 'variable'], ['<p style=color:red>x</p>', 'locked'], ['<p style>x</p>', 'locked'],
 ['<p style="color:red;color:blue">x</p>', 'locked'], ['<p style="font-size:1em;margin:0;FONT-SIZE:2em">x</p>', 'locked'], ['<p style="font-family:&quot;A&quot;">x</p>', 'locked'],
 ['<p style="color:red/*x*/">x</p>', 'locked'], ['<p style="color">x</p>', 'locked'], ["<p style='color:red'>x</p>", 'fixed'], ['<p style="">x</p>', 'fixed'],
 ['<p style="margin:0;margin:1px">x</p>', 'fixed'], ['<br style="color:red">', 'skip'], ['<video style="color:red">x</video>', 'skip']
].forEach(c => same(A.styleOf(c[0], mapOf(c[0]), 0).state, c[1], 'estado de ' + c[0]));
/* mapa que nao e desta fonte: nada e lido */
same(A.styleOf('<p class="color:red">x</p>', { items: [['p', 'fixed', 0, 26, 26, 27, '', [['style', 'fixed', 10, 19, 3, 20, '']]]] }, 0), { state: 'locked', values: {} }, 'nome do atributo nao confere');

/* ---------- tag sem style: o atributo e criado logo depois do nome da tag ---------- */
let T = '<section class="x"><h2>T\u00edtulo \ud83d\ude00</h2><p class="lead">Texto</p><a href="/x">Ir</a></section>';
let out = wrote(T, 1, 'color', '#4338CA', T.replace('<h2>', '<h2 style="color:#4338ca">'), 'cor em tag sem atributos (hex vira minusculas)');
wrote(T, 2, 'font-size', '18px', T.replace('<p class', '<p style="font-size:18px" class'), 'tamanho em tag com outros atributos');
wrote(T, 0, 'background-color', 'var(--at-soft)', T.replace('<section class', '<section style="background-color:var(--at-soft)" class'), 'fundo com cor do tema na tag de fora');
wrote(T, 3, 'color', '#fff', T.replace('<a href', '<a style="color:#fff" href'), 'ultima tag');
wrote('<p\n  class="a"\n>x</p>', 0, 'color', '#000', '<p style="color:#000"\n  class="a"\n>x</p>', 'tag quebrada em linhas');
wrote('<hr/>', 0, 'color', '#000', '<hr style="color:#000"/>', 'tag fechada com barra');
wrote('<input disabled>', 0, 'color', '#000', '<input style="color:#000" disabled>', 'tag com atributo sem valor');
/* o servidor manda o nome da tag em minusculas, mesmo quando o codigo o escreve em maiusculas */
same(A.setStyle('<P class="a">x</P>', { items: [['p', 'fixed', 0, 13, 13, 14, '', []]] }, 0, 'color', '#000'), { src: '<P style="color:#000" class="a">x</P>' }, 'nome da tag em maiuscula');
/* as outras duas entram no mesmo style, na ordem em que sao pedidas */
let m3 = mapOf(T), r3 = A.setStyle(T, m3, 1, 'color', '#111111');
r3 = A.setStyle(r3.src, m3, 1, 'background-color', '#ffffff');
r3 = A.setStyle(r3.src, m3, 1, 'font-size', '32px');
same(r3.src, T.replace('<h2>', '<h2 style="color:#111111;background-color:#ffffff;font-size:32px">'), 'tres escritas seguidas com o mesmo mapa');
mapHolds(r3.src, m3, 'tres escritas seguidas');
/* ... e saem em qualquer ordem, ate a fonte voltar ao que era */
r3 = A.setStyle(r3.src, m3, 1, 'background-color', '');
same(r3.src, T.replace('<h2>', '<h2 style="color:#111111;font-size:32px">'), 'retirar a do meio');
mapHolds(r3.src, m3, 'retirar a do meio');
r3 = A.setStyle(r3.src, m3, 1, 'font-size', '');
same(r3.src, T.replace('<h2>', '<h2 style="color:#111111">'), 'retirar a ultima');
r3 = A.setStyle(r3.src, m3, 1, 'color', '');
same(r3.src, T, 'retirar a que sobrou: o atributo sai e a fonte volta a ser a original');
mapHolds(T, m3, 'ida e volta');
/* outro elemento, com o mesmo mapa, depois de tudo isso */
r3 = A.setStyle(r3.src, m3, 3, 'color', '#abc');
same(r3.src, T.replace('<a href', '<a style="color:#abc" href'), 'o mapa ainda vale para o elemento seguinte');
/* retirar o que nao existe nao muda nada */
wrote(T, 1, 'color', '', T, 'retirar cor de tag sem style: fonte igual');
wrote(T, 1, 'color', '   ', T, 'so espacos conta como vazio');
same(out.length, T.length + ' style="color:#4338ca"'.length, 'so o atributo novo foi acrescentado');

/* ---------- style que ja existe ---------- */
S = '<div><p style="color:var(--at-muted);max-width:52ch;margin:0 auto 26px">Texto</p><span style="margin-top:18px">a</span><b style="color: red ;">b</b><i style=" font-size:12px">c</i></div>';
wrote(S, 1, 'color', '#101828', S.replace('color:var(--at-muted)', 'color:#101828'), 'troca so o valor da cor; o resto do style fica igual');
wrote(S, 1, 'color', 'var(--at-ink)', S.replace('var(--at-muted)', 'var(--at-ink)'), 'troca uma cor do tema por outra');
wrote(S, 1, 'font-size', '20px', S.replace('26px">', '26px;font-size:20px">'), 'propriedade nova entra no fim, com ponto e virgula');
wrote(S, 1, 'color', '', S.replace('color:var(--at-muted);', ''), 'retirar a primeira leva o ponto e virgula dela');
wrote(S, 2, 'color', '#000', S.replace('margin-top:18px"', 'margin-top:18px;color:#000"'), 'acrescentar em style de uma declaracao so');
refused(S, 1, 'max-width', '', 'locked', 'propriedade fora da lista do editor nao e retirada');
wrote(S, 3, 'color', '#00f', S.replace('color: red ;', 'color: #00f ;'), 'os espacos em volta do valor ficam');
wrote(S, 3, 'background-color', '#eee', S.replace('color: red ;"', 'color: red ;background-color:#eee"'), 'style que termina em ponto e virgula nao ganha outro');
wrote(S, 3, 'color', '', S.replace('<b style="color: red ;">', '<b>'), 'retirar a unica: o atributo sai inteiro');
wrote(S, 4, 'font-size', '14px', S.replace(' font-size:12px', ' font-size:14px'), 'espaco no comeco do valor fica');
wrote(S, 4, 'font-size', '', S.replace('<i style=" font-size:12px">', '<i>'), 'retirar a unica com espaco no comeco');
wrote('<p style="margin:0 ;  ">x</p>', 0, 'color', '#000', '<p style="margin:0 ;color:#000">x</p>', 'espacos no fim do style saem antes de acrescentar');
wrote('<p style="">x</p>', 0, 'color', '#000', '<p style="color:#000">x</p>', 'style vazio');
wrote('<p style="  ">x</p>', 0, 'color', '#000', '<p style="color:#000">x</p>', 'style so com espacos');
wrote("<p style='margin:0'>x</p>", 0, 'color', '#000', "<p style='margin:0;color:#000'>x</p>", 'aspas simples: escreve dentro delas');
wrote('<p style="margin:0; COLOR:red !important; padding:0">x</p>', 0, 'color', '#000', '<p style="margin:0; COLOR:#000; padding:0">x</p>', 'troca o valor inteiro, com o !important');
wrote('<p style="margin:0; color:red; padding:0">x</p>', 0, 'color', '', '<p style="margin:0; padding:0">x</p>', 'retirar a do meio');
wrote('<p style="margin:0; color:red">x</p>', 0, 'color', '', '<p style="margin:0">x</p>', 'retirar a ultima sem ponto e virgula leva o de antes');
wrote('<p style="margin:0; color:red;">x</p>', 0, 'color', '', '<p style="margin:0;">x</p>', 'retirar a ultima com ponto e virgula');
wrote('<p style="color:red; margin:0">x</p>', 0, 'color', '', '<p style="margin:0">x</p>', 'retirar a primeira: sem espaco sobrando no comeco');
wrote('<p style="color:red;;">x</p>', 0, 'color', '', '<p style=";">x</p>', 'ponto e virgula a mais do autor fica');
wrote('<p class="a" style="color:red" id="b">x</p>', 0, 'color', '', '<p class="a" id="b">x</p>', 'atributo no meio da tag sai com o espaco dele');
// tirar o que nao esta la nao toca no que esta
for (const prop of ['color', 'background-color', 'font-size']) {
  wrote('<p style="margin:0">x</p>', 0, prop, '', '<p style="margin:0">x</p>', 'retirar ' + prop + ' ausente: a unica declaracao fica');
  wrote('<p style="margin:0;padding:2px;border:0">x</p>', 0, prop, '', '<p style="margin:0;padding:2px;border:0">x</p>', 'retirar ' + prop + ' ausente: as tres declaracoes ficam');
}
wrote('<p style="color:red;margin:0">x</p>', 0, 'font-size', '', '<p style="color:red;margin:0">x</p>', 'retirar tamanho ausente nao leva a cor');
wrote('<p style="font-size:20px">x</p>', 0, 'color', '', '<p style="font-size:20px">x</p>', 'retirar cor ausente nao leva o tamanho');
wrote('<p style=" ;color:red">x</p>', 0, 'color', '', '<p>x</p>', 'sobra so um espaco: o atributo sai');
wrote('<p style="color:red; ">x</p>', 0, 'color', '', '<p>x</p>', 'sobra so um espaco no fim: o atributo sai');
wrote('<p style="background:url(data:image/png;base64,AA==);color:red">x</p>', 0, 'color', '#000', '<p style="background:url(data:image/png;base64,AA==);color:#000">x</p>', 'style com parenteses');
wrote('<p style="color:red">x</p><p style="color:red">y</p>', 1, 'color', '#000', '<p style="color:red">x</p><p style="color:#000">y</p>', 'so o elemento pedido muda');
wrote('<p style="font-size:1.2rem">x</p>', 0, 'font-size', '016.50px', '<p style="font-size:16.5px">x</p>', 'tamanho escrito sem zeros a mais');
wrote('<p style="font-size:1.2rem">x</p>', 0, 'font-size', ' 20PX ', '<p style="font-size:20px">x</p>', 'unidade em maiuscula e pontas');

/* ---------- recusas: fonte e mapa intactos ---------- */
S = '<div><h2>T</h2><p style="color:red">x</p><img src="a.png" alt=""><p style="color:{{ c }}">y</p><p style="font-family:&quot;A&quot;">z</p><p style="color:red;color:blue">w</p><p style=color:red>v</p></div>';
['red', 'rgb(0,0,0)', '#12', 'var(--A)', 'var(--a,#fff)', '#fff;position:fixed', '#fff" onclick="alert(1)', 'url(javascript:alert(1))', '{{ x }}', 'transparent'
].forEach(v => { refused(S, 1, 'color', v, 'value', 'cor fora da gramatica: ' + v); refused(S, 2, 'background-color', v, 'value', 'fundo fora da gramatica: ' + v); });
['5px', '401px', '1rem', '16', '16px;color:red', 'calc(1px)', '#fff'].forEach(v => refused(S, 1, 'font-size', v, 'value', 'tamanho fora da gramatica: ' + v));
refused(S, 1, 'color', '16px', 'value', 'tamanho no campo de cor');
['margin', 'background', 'font', 'position', 'style', 'constructor', '__proto__', '', 'Color', 'COLOR'].forEach(p => refused(S, 1, p, '#fff', 'locked', 'propriedade fora das tres: ' + JSON.stringify(p)));
refused(S, 3, 'color', '#fff', 'locked', 'img: nao escreve');
refused(S, 4, 'color', '#fff', 'locked', 'style com Twig: nao escreve');
refused(S, 4, 'color', '', 'locked', 'style com Twig: nem retira');
refused(S, 5, 'color', '#fff', 'locked', 'style com entidade: nao escreve');
refused(S, 6, 'color', '#fff', 'locked', 'propriedade repetida: nao escolhe qual trocar');
refused(S, 6, 'font-size', '16px', 'locked', 'propriedade repetida: nem acrescenta outra');
refused(S, 7, 'color', '#fff', 'locked', 'style sem aspas: nao escreve');
refused(S, 99, 'color', '#fff', 'locked', 'elemento que nao existe');
same(A.setStyle(S, null, 1, 'color', '#fff'), { error: 'locked' }, 'mapa ausente');
same(A.setStyle(S, { items: [['p', 'fixed', 0, 3, 3, 4, '', null]] }, 0, 'color', '#fff'), { error: 'locked' }, 'tag que o servidor nao soube ler');

/* mapa de outra fonte */
(function () {
  const src = '<div><h2>T</h2><p style="color:red">x</p></div>', other = '<section><h2>T</h2><p style="color:red">x</p></section>';
  [[1, 'tag sem style'], [2, 'tag com style']].forEach(c => {
    const map = mapOf(src), before = JSON.stringify(map);
    same(A.setStyle(other, map, c[0], 'color', '#fff'), { error: 'stale' }, 'mapa de outra fonte, ' + c[1]);
    check(JSON.stringify(map) === before, 'mapa de outra fonte: intacto');
  });
  /* a tag certa, mas com nome mais comprido no lugar (<h2> x <h20>) */
  let map = { items: [['h2', 'fixed', 0, 4, 4, 5, '', []]] };
  same(A.setStyle('<h20>T</h20>', map, 0, 'color', '#fff'), { error: 'stale' }, 'nome da tag continua depois do que o mapa diz');
  same(A.setStyle('<h2', { items: [['h2', 'group', 0, 3, -1, -1, '', []]] }, 0, 'color', '#fff'), { error: 'stale' }, 'fonte termina no nome da tag');
  same(A.setStyle('xh2>', { items: [['h2', 'group', 0, 4, -1, -1, '', []]] }, 0, 'color', '#fff'), { error: 'stale' }, 'nao comeca com <');
  /* o valor do style esta em outro lugar */
  map = { items: [['p', 'fixed', 0, 21, 21, 22, '', [['style', 'fixed', 11, 19, 3, 20, '']]]] };
  same(A.setStyle('<p style="color:red">x</p>', map, 0, 'color', '#fff'), { error: 'stale' }, 'posicao do valor nao confere');
  /* style colado no atributo anterior (o servidor fecha essa tag): retirar nao adivinha onde cortar */
  const glued = '<p class="a"style="color:red">x</p>';
  map = { items: [['p', 'fixed', 0, 30, 30, 31, '', [['style', 'fixed', 19, 28, 12, 29, '']]]] };
  const before = JSON.stringify(map);
  same(A.setStyle(glued, map, 0, 'color', ''), { error: 'stale' }, 'style colado: nao retira o atributo');
  check(JSON.stringify(map) === before, 'style colado: mapa intacto');
  same(A.setStyle(glued, map, 0, 'color', '#000'), { src: '<p class="a"style="color:#000">x</p>' }, 'style colado: trocar o valor nao depende do espaco');
})();

/* fonte no teto */
(function () {
  const pad = 'x'.repeat(A.SOURCE_MAX - '<p>'.length - '</p>'.length - ' style="color:#fff"'.length);
  const fits = '<p>' + pad + '</p>';
  same(A.setStyle(fits, mapOf(fits), 0, 'color', '#fff').src.length, A.SOURCE_MAX, 'cabe exatamente no teto');
  refused(fits, 0, 'color', '#ffffff', 'long', 'um caractere acima do teto (atributo novo)');
  // KZ-22: o teto e em bytes UTF-8 da fonte como o servidor a guarda; cada quebra de linha vale um byte
  const M = A.SOURCE_MAX, fill = (unit, bytes, total) => unit.repeat(Math.floor(total / bytes)) + 'x'.repeat(total % bytes);
  for (const [nome, unit, bytes] of [['ascii', 'x', 1], ['acento', '\u00e9', 2], ['tres bytes', '\u20ac', 3], ['emoji', '\ud83d\ude00', 4], ['quebra LF', '\n', 1], ['CR solto', '\r', 1], ['meio par', '\ud83d', 3], ['meio par baixo', '\ude00', 3]]) {
    check(A.tooLong(fill(unit, bytes, M)) === false, 'teto em bytes: ' + nome + ' cabendo exatamente');
    check(A.tooLong(fill(unit, bytes, M) + 'x') === true, 'teto em bytes: ' + nome + ' com um byte a mais');
  }
  check(A.tooLong('') === false && A.tooLong('x'.repeat(M + 1)) === true && A.tooLong('\u00e9'.repeat(M / 2 - 1) + '\u20ac') === true, 'teto em bytes: vazio, longo e mistura');
  check(Buffer.byteLength(fill('\u00e9', 2, M)) === M && Buffer.byteLength(fill('\ud83d\ude00', 4, M)) === M, 'a conta confere com a do Node');
  check(A.tooLong('\r\n'.repeat(M)) === false && A.tooLong('\r\n'.repeat(M) + 'x') === true && A.tooLong('\r\n'.repeat(M) + '\n') === true && A.tooLong('\r\n'.repeat(M) + '\r') === true, 'CR LF e uma quebra so: um byte');
  check(A.tooLong('\r\r\n'.repeat(M / 2)) === false && A.tooLong('\r\r\n'.repeat(M / 2) + 'x') === true && A.tooLong('\n\r'.repeat(M)) === true && A.tooLong('x\n\rx'.repeat(M / 4)) === false && A.tooLong('x\n\rx'.repeat(M / 4) + 'x') === true, 'CR CR LF e LF CR sao duas quebras cada');
  check(A.tooLong('x'.repeat(M - 1) + '\r\n') === false && A.tooLong('x'.repeat(M) + '\r\n') === true && A.tooLong('x'.repeat(M - 1) + '\r') === false, 'quebra no fim da fonte');
  {
    const acc = '<p>' + '\u00e9'.repeat((M - 7 - 19) / 2 | 0) + 'x'.repeat((M - 7 - 19) % 2) + '</p>';
    check(acc.length < M / 2 + 30 && A.setStyle(acc, mapOf(acc), 0, 'color', '#fff').src !== undefined, 'fonte com acentos: escrita que cabe em bytes passa');
    same(A.setStyle(acc, mapOf(acc), 0, 'color', '#ffff00'), { error: 'long' }, 'fonte com acentos: passa do teto em bytes mesmo com metade dos caracteres');
    same(A.setStyle(acc, mapOf(acc), 0, 'font-size', '12px'), { error: 'long' }, 'idem no tamanho');
    const st = '<p style="color:#fff">' + '\n'.repeat(M - 26) + '</p>';
    check(st.length === M && A.tooLong(st) === false, 'fonte com quebras montada no teto');
    same(A.setStyle(st, mapOf(st), 0, 'background-color', '#000'), { error: 'long' }, 'declaracao nova nao cabe');
    // KZ-22: metade do teto em quebras de linha enchia a fonte quando cada quebra valia dois bytes
    const half = '<p style="color:#fff">' + '\n'.repeat((M - 26) / 2) + '</p>';
    const wrote = A.setStyle(half, mapOf(half), 0, 'background-color', '#000');
    check(wrote.error === undefined && wrote.src.length === half.length + ';background-color:#000'.length && wrote.src.indexOf('background-color:#000') !== -1, 'fonte com muitas linhas e folga: a declaracao nova entra');
    same(A.setStyle(st, mapOf(st), 0, 'color', '#ffff00'), { error: 'long' }, 'valor maior nao cabe');
    check(A.setStyle(st, mapOf(st), 0, 'color', '#000').src.length === st.length, 'valor do mesmo tamanho cabe');
  }
  const full = '<p style="color:#fff">' + 'x'.repeat(A.SOURCE_MAX - '<p style="color:#fff">'.length - '</p>'.length) + '</p>';
  check(full.length === A.SOURCE_MAX, 'fonte cheia montada');
  refused(full, 0, 'color', '#ffffff', 'long', 'acima do teto (troca de valor)');
  refused(full, 0, 'font-size', '16px', 'long', 'acima do teto (propriedade nova)');
  same(A.setStyle(full, mapOf(full), 0, 'color', '#000').src.length, A.SOURCE_MAX, 'troca do mesmo tamanho passa');
  same(A.setStyle(full, mapOf(full), 0, 'color', '').src, full.replace(' style="color:#fff"', ''), 'retirar sempre cabe');
})();

/* ---------- o que e escrito nunca esbarra na validacao do tema nem sai do atributo ---------- */
const BLOCKED = /<\s*(script|iframe|object|embed)\b|javascript\s*:|\bon[a-z]+\s*=|\|\s*raw\b|\{%\s*autoescape\b|\b(srcdoc|formaction)\s*=/i;
['#fff', '#4338ca', 'var(--at-primary)', 'var(--onclick)', 'var(--on-load)', 'var(--raw)', 'var(--javascript)'].forEach(v => {
  ['color', 'background-color'].forEach(p => {
    const base = '<p class="a">x</p>', r = A.setStyle(base, mapOf(base), 0, p, v);
    check(typeof r.src === 'string' && !BLOCKED.test(r.src), 'escrita aceita pela validacao: ' + p + ' ' + v);
    same(r.src, '<p style="' + p + ':' + v + '" class="a">x</p>', 'forma exata: ' + p + ' ' + v);
    const again = mapOf(r.src);
    same(A.styleOf(r.src, again, 0), { state: 'fixed', values: { [p]: v } }, 'o que foi escrito e lido de volta: ' + p + ' ' + v);
  });
});

/* ---------- convivio com as regras da KZ-05 no mesmo mapa ---------- */
(function () {
  const src = '<a class="b" href="/velho" style="color:red">Ir</a><p>x</p>', map = mapOf(src);
  let r = A.setValue(src, map, 0, 'href', '/novo-destino');
  r = A.setStyle(r.src, map, 0, 'color', '#000');
  r = A.setNewTab(r.src, map, 0, true);
  r = A.setStyle(r.src, map, 0, 'font-size', '18px');
  r = A.setStyle(r.src, map, 1, 'color', '#333');
  same(r.src, '<a class="b" href="/novo-destino" target="_blank" rel="noopener" style="color:#000;font-size:18px">Ir</a><p style="color:#333">x</p>', 'link, nova aba e estilo com o mesmo mapa');
  mapHolds(r.src, map, 'link, nova aba e estilo');
  same(A.clean([['style', 'fixed', 9, 11, 3, 12, '']]), [['style', 'fixed', 9, 11, 3, 12, '']], 'style e um nome aceito na lista do servidor');
})();

/* KZ-22 (ADR 0115): a tabela de tamanhos que o servidor tambem confere (KazSourceLineEndingsTest) */
(function () {
  const fx = JSON.parse(require('fs').readFileSync(require('path').join(__dirname, 'fixtures', 'kaz_source_size.json'), 'utf8'));
  same(fx.max, A.SOURCE_MAX, 'o teto da tabela e o do script');
  check(fx.cases.length === 20, 'tabela de tamanhos inteira');
  const seen = { true: 0, false: 0, old: 0 };
  for (const c of fx.cases) {
    const typed = c.unit.repeat(c.times) + c.tail;
    const sent = typed.replace(/\r\n|\r|\n/g, '\r\n'), stored = typed.replace(/\r\n|\r/g, '\n');
    same(Buffer.byteLength(stored), c.bytes, c.name + ': bytes guardados');
    same(Buffer.byteLength(sent), c.bytes_sent, c.name + ': bytes como o formulario envia');
    same(c.bytes <= fx.max, c.ok, c.name + ': a tabela e coerente');
    // do jeito que foi montada, do jeito que o campo a guarda e do jeito que o formulario a envia: a mesma resposta
    for (const [how, s] of [['montada', typed], ['no campo', stored], ['enviada', sent]]) same(A.tooLong(s), !c.ok, c.name + ' (' + how + ')');
    seen[c.ok]++;
    if (c.ok && c.bytes_sent > fx.max) seen.old++;
  }
  same(seen, { true: 11, false: 9, old: 6 }, 'casos que cabem, que nao cabem e que a regra antiga recusava');
})();

console.log('Kaz element style checker: ' + checks + ' PASS');
