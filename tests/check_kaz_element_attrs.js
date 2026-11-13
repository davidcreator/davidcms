'use strict';
/*
 * KZ-05 (ADR 0098) — regras de atributo do elemento (dev/admin/view/js/kaz-element-attrs.js).
 * Roda sem navegador: `node tests/check_kaz_element_attrs.js`.
 * O mapa de cada caso e escrito a mao, no formato de ThemeSourceMap::describe().
 */
global.window = {};
require('../dev/admin/view/js/kaz-element-attrs.js');
const A = window.KazElementAttrs;
let checks = 0;
function check(ok, what) { checks++; if (!ok) throw new Error('FAIL: ' + what); }
function same(a, b, what) { check(JSON.stringify(a) === JSON.stringify(b), what + '\n   obtido:   ' + JSON.stringify(a) + '\n   esperado: ' + JSON.stringify(b)); }

/* Mapa de uma fonte, com um leitor de tags minimo so para os testes (aspas duplas ou simples, sem Twig). */
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
      if (!(NAMES[tag] || []).concat(['aria-label']).includes(name)) continue;
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
/* Cada posicao do mapa aponta para o que diz apontar. */
function mapHolds(src, map, what) {
  const fresh = mapOf(src);
  const norm = m => m.items.map(it => [it[0], it[2], it[3], it[4], it[5], (it[7] || []).map(a => a.slice(0, 6)).sort((p, q) => p[4] - q[4])]);
  same(norm(map), norm(fresh), what + ': o mapa acompanha a fonte');
}
function edit(src, n, name, value) { const map = mapOf(src); const r = A.setValue(src, map, n, name, value); return { r, map }; }

/* ---------- destino de link: o que entra e o que nao entra ---------- */
['/', '/contato', '/pt/servicos?x=1#topo', '#', '#secao', '?pagina=2', 'https://exemplo.com', 'HTTPS://Exemplo.com/a?b=1&c=2',
 'http://exemplo.com:8080/x', 'https://medium.com/@autor', 'https://wa.me/5511999999999', 'https://[::1]/x',
 'mailto:contato@exemplo.com', 'mailto:a@b.co?subject=Ol\u00e1 mundo', 'MAILTO:a@b.co', 'tel:+5511999999999', 'tel:+55 (11) 99999-9999', 'tel:0800-123'
].forEach(v => check(A.linkOk(v), 'aceita ' + v));
['', ' ', 'contato', 'contato.html', './contato', '../x', '//exemplo.com', '/\\exemplo.com', 'https:\\\\exemplo.com', 'https://', 'https:///x',
 'https://usuario@exemplo.com', 'https://exemplo.com@outro.com/x', 'https://.com', 'https://:80', 'http:exemplo.com', 'https:/exemplo.com',
 'javascript:alert(1)', 'JaVaScRiPt:alert(1)', ' javascript:alert(1)', 'java\tscript:alert(1)', 'data:text/html,x', 'vbscript:x', 'ftp://exemplo.com',
 'file:///etc/passwd', 'blob:https://x', 'https://exemplo.com/a b', '/a b', '#a b', '/a\nb', 'mailto:', 'mailto:semarroba', 'mailto:a@b.co\n', 'mailto:a@@b.co',
 'mailto:a b@c.co', 'tel:', 'tel:abc', 'tel:+', 'tel:12', 'tel:+55;ext=1', 'x'.repeat(10)
].forEach(v => check(!A.linkOk(v), 'recusa ' + JSON.stringify(v)));
check(A.linkOk('/' + 'a'.repeat(A.LINK_MAX - 1)), 'aceita destino no limite');
check(!A.linkOk('/' + 'a'.repeat(A.LINK_MAX)), 'recusa destino acima do limite');
check(A.LINK_MAX === 2000 && A.LABEL_MAX === 300 && A.SOURCE_MAX === 131072, 'limites');

/* ---------- escrita: nunca fecha o atributo, nunca vira Twig ---------- */
same(A.encode('a & b'), 'a &amp; b', 'e comercial');
same(A.encode('"x" \'y\''), '&quot;x&quot; &#39;y&#39;', 'as duas aspas');
same(A.encode('<b>{{ x }}{% y %}{# z #}'), '&lt;b&gt;&#123;&#123; x }}&#123;% y %}&#123;# z #}', 'tag e Twig');
same(A.encode('a\u0000b\u0007c\td\ne\u007f'), 'abcde', 'caracteres de controle saem');
same(A.encode('onclick=alert(1)'), 'onclick&#61;alert(1)', 'on...= e quebrado');
same(A.encode('ONLOAD = x'), 'ONLOAD &#61; x', 'on...= com espaco e maiusculas');
same(A.encode('srcdoc=x formaction=y'), 'srcdoc&#61;x formaction&#61;y', 'srcdoc e formaction');
same(A.encode('a=b'), 'a=b', 'igual comum fica');
same(A.encode('x|raw'), 'x&#124;raw', '|raw e quebrado');
same(A.encode('x | RAW'), 'x &#124; RAW', '| raw com espaco');
same(A.encode('a|b'), 'a|b', 'barra comum fica');
same(A.encode('javascript:x JAVASCRIPT :y'), 'javascript&#58;x JAVASCRIPT &#58;y', 'javascript: e quebrado');
same(A.encode('Ol\u00e1, \u4e16\u754c \ud83d\ude00'), 'Ol\u00e1, \u4e16\u754c \ud83d\ude00', 'acentos e emoji ficam');
/* O que a validacao do tema recusa (theme_sandbox.php, BLOCKED_HTML) nunca aparece depois de codificar. */
const BLOCKED = /<\s*(script|iframe|object|embed)\b|javascript\s*:|\bon[a-z]+\s*=|\|\s*raw\b|\{%\s*autoescape\b|\b(srcdoc|formaction)\s*=/i;
['<script>alert(1)</script>', 'javascript:alert(1)', 'x" onmouseover="alert(1)', "x' onfocus='alert(1)", '{{ 7*7 }}', '{% autoescape false %}', 'a|raw',
 '<iframe srcdoc=x>', 'formaction=x', '&amp;online=1', 'a&on=1', 'java script:x', '&#x6a;avascript:x', 'on\u0000click=x', '{# x #}', '{%- include "x" -%}'
].forEach(v => {
  const e = A.encode(v);
  check(!BLOCKED.test(e), 'codificado nao esbarra na validacao: ' + v + ' -> ' + e);
  check(!/["'<>]/.test(e) && !/\{[\{%#]/.test(e), 'codificado nao fecha o atributo nem abre Twig: ' + v + ' -> ' + e);
});

/* ---------- conferencia do que vem do servidor ---------- */
same(A.clean([['href', 'fixed', 9, 11, 3, 12, '']]), [['href', 'fixed', 9, 11, 3, 12, '']], 'lista valida passa');
same(A.clean([]), [], 'lista vazia e uma tag sem atributos conhecidos');
[null, undefined, 'x', {}, [['href']], [['style', 'fixed', 9, 11, 3, 12, '']], [['constructor', 'fixed', 9, 11, 3, 12, '']], [['href', 'outro', 9, 11, 3, 12, '']],
 [['href', 'fixed', '9', 11, 3, 12, '']], [['href', 'fixed', 9.5, 11, 3, 12, '']], [['href', 'fixed', -1, 11, 3, 12, '']], [['href', 'fixed', 9, 11, 3, 12, 5]],
 [['href', 'fixed', 9, 11, 3, 12, ''], ['href', 'fixed', 20, 21, 14, 22, '']], [['href', 'fixed', 9, 8, 3, 12, '']], [['href', 'fixed', 9, 11, 10, 12, '']],
 [['href', 'fixed', 9, 11, 3, 10, '']], [['href', 'fixed', 9, 11, 3, Infinity, '']], [['href', 'fixed', 9, 11, 3, NaN, '']], [[1, 'fixed', 9, 11, 3, 12, '']]
].forEach((bad, i) => check(A.clean(bad) === null, 'lista fora do formato fecha a tag (' + i + ')'));
check(A.find(['a', 'group', 0, 1, -1, -1, '', null], 'href') === null, 'tag fechada nao tem atributo');
check(A.find(null, 'href') === null, 'item ausente');

/* ---------- trocar um valor ---------- */
let S = '<p>Antes</p><a class="b" href="/velho" id="x">Ir</a><img src="a.png" alt="Foto"><button aria-label="Fechar">x</button><a href="#fim">Fim</a>';
let t = edit(S, 1, 'href', 'https://exemplo.com/?a=1&b=2');
same(t.r, { src: S.replace('/velho', 'https://exemplo.com/?a=1&amp;b=2') }, 'so o valor do href muda, com & codificado');
mapHolds(t.r.src, t.map, 'href mais longo');
t = edit(S, 1, 'href', '#');
same(t.r.src, S.replace('/velho', '#'), 'href mais curto');
mapHolds(t.r.src, t.map, 'href mais curto');
t = edit(S, 1, 'href', '  /novo  ');
same(t.r.src, S.replace('/velho', '/novo'), 'as pontas digitadas nao entram');
t = edit(S, 2, 'alt', 'Equipe "Reamur" & amigos');
same(t.r.src, S.replace('alt="Foto"', 'alt="Equipe &quot;Reamur&quot; &amp; amigos"'), 'alt com aspas e &');
mapHolds(t.r.src, t.map, 'alt');
t = edit(S, 2, 'alt', '');
same(t.r.src, S.replace('alt="Foto"', 'alt=""'), 'alt vazio e aceito (imagem decorativa)');
mapHolds(t.r.src, t.map, 'alt vazio');
let u = A.setValue(t.r.src, t.map, 2, 'alt', 'De volta');
same(u.src, S.replace('alt="Foto"', 'alt="De volta"'), 'valor vazio volta a ser preenchido');
mapHolds(u.src, t.map, 'alt preenchido de novo');
t = edit(S, 3, 'aria-label', 'Fechar o menu');
same(t.r.src, S.replace('aria-label="Fechar"', 'aria-label="Fechar o menu"'), 'aria-label');
mapHolds(t.r.src, t.map, 'aria-label');
t = edit(S, 3, 'aria-label', 'x'.repeat(A.LABEL_MAX + 50));
same(t.r.src, S.replace('aria-label="Fechar"', 'aria-label="' + 'x'.repeat(A.LABEL_MAX) + '"'), 'rotulo cortado no limite');
/* Escritas em sequencia, em elementos diferentes: o mapa local segue valendo. */
let map = mapOf(S), cur = S;
[[4, 'href', '/ultimo'], [1, 'href', 'mailto:a@b.co'], [3, 'aria-label', 'Sair'], [2, 'alt', 'Outra foto'], [1, 'href', '/de-novo'], [4, 'href', '#topo']].forEach(step => {
  const r = A.setValue(cur, map, step[0], step[1], step[2]);
  check(typeof r.src === 'string', 'sequencia: ' + step.join(' '));
  cur = r.src;
  mapHolds(cur, map, 'sequencia ' + step.join(' '));
});
same(cur, '<p>Antes</p><a class="b" href="/de-novo" id="x">Ir</a><img src="a.png" alt="Outra foto"><button aria-label="Sair">x</button><a href="#topo">Fim</a>', 'resultado da sequencia');
/* Aspas simples no codigo: o valor continua dentro delas. */
let Q = "<a href='/velho'>x</a>";
t = edit(Q, 0, 'href', "/it's?q=\"a\"");
same(t.r.src, "<a href='/it&#39;s?q=&quot;a&quot;'>x</a>", 'aspas simples no codigo');
mapHolds(t.r.src, t.map, 'aspas simples');
/* Unicode: posicoes em unidades UTF-16, como o navegador conta. */
let U = '<p>\ud83d\ude00 Ol\u00e1</p><a href="/a\u00e7\u00e3o">\u4e16\u754c</a>';
t = edit(U, 1, 'href', '/caf\u00e9-\ud83d\ude00');
same(t.r.src, '<p>\ud83d\ude00 Ol\u00e1</p><a href="/caf\u00e9-\ud83d\ude00">\u4e16\u754c</a>', 'unicode');
mapHolds(t.r.src, t.map, 'unicode');

/* ---------- recusas: nem a fonte nem o mapa mudam ---------- */
function refused(src, n, name, value, code, what, prepare) {
  const m = mapOf(src);
  if (prepare) prepare(m);
  const before = JSON.stringify(m);
  const r = A.setValue(src, m, n, name, value);
  same(r, { error: code }, what);
  check(JSON.stringify(m) === before, what + ': mapa intacto');
}
refused(S, 1, 'href', 'javascript:alert(1)', 'link', 'destino javascript: recusado');
refused(S, 1, 'href', '', 'link', 'destino vazio recusado');
refused(S, 1, 'href', 'contato', 'link', 'destino sem barra recusado');
refused(S, 1, 'href', '//outro.com', 'link', 'destino //site recusado');
refused(S, 0, 'href', '/x', 'locked', 'elemento sem href');
refused(S, 2, 'href', '/x', 'locked', 'href em tag que nao e link', m => m.items[2][7].push(['href', 'fixed', 30, 31, 25, 32, '']));
refused(S, 3, 'alt', 'x', 'locked', 'alt em tag que nao e imagem', m => m.items[3][7].push(['alt', 'fixed', 30, 31, 25, 32, '']));
refused(S, 1, 'target', '_blank', 'locked', 'target nao e campo de texto', m => m.items[1][7].push(['target', 'fixed', 30, 31, 25, 32, '']));
refused(S, 1, 'class', 'x', 'locked', 'atributo fora da lista');
refused(S, 1, 'constructor', 'x', 'locked', 'nome herdado de Object nao passa');
refused('<a href="{{ url }}">x</a>', 0, 'href', '/x', 'locked', 'href que vem de dado');
refused('<a href=/x>x</a>', 0, 'href', '/y', 'locked', 'href sem aspas');
refused(S, 1, 'href', '/x', 'locked', 'tag fechada pelo servidor', m => { m.items[1][7] = null; });
refused(S, 9, 'href', '/x', 'locked', 'elemento que nao existe');
refused(S.replace('href="/velho"', 'href="/outro-maior"'), 1, 'href', '/x', 'stale', 'mapa de outra fonte (valor de outro tamanho)', m => { m.items[1][7][0][3] -= 6; m.items[1][7][0][5] -= 6; });
refused(S, 1, 'href', '/x', 'stale', 'mapa deslocado', m => { const a = m.items[1][7][0]; a[2]++; a[3]++; a[4]++; a[5]++; });
refused(S, 1, 'href', '/x', 'stale', 'nome nao bate', m => { m.items[1][7][0][4] -= 3; });
refused(S, 1, 'href', '/x', 'stale', 'fim do atributo nao bate', m => { m.items[1][7][0][5] += 1; });
check(A.setValue(S, null, 1, 'href', '/x').error === 'locked', 'sem mapa');
let BIG = '<a href="/x">' + 'y'.repeat(A.SOURCE_MAX - 18) + '</a>';
check(BIG.length === A.SOURCE_MAX - 1, 'fonte a um caractere do teto');
refused(BIG, 0, 'href', '/xyz', 'long', 'fonte passaria do teto');
check(typeof edit(BIG, 0, 'href', '/xy').r.src === 'string', 'fonte exatamente no teto e aceita');

/* ---------- abrir em nova aba ---------- */
function tab(src, n, on) { const m = mapOf(src); const r = A.setNewTab(src, m, n, on); return { r, map: m }; }
let L = '<nav><a class="b" href="/x" id="k">Um</a> <a href="/y">Dois</a></nav>';
same(A.newTab(L, mapOf(L), 1), { can: true, on: false }, 'link simples: pode ligar');
t = tab(L, 1, true);
same(t.r.src, '<nav><a class="b" href="/x" target="_blank" rel="noopener" id="k">Um</a> <a href="/y">Dois</a></nav>', 'ligar escreve target e rel logo depois do href');
mapHolds(t.r.src, t.map, 'ligar');
same(A.newTab(t.r.src, t.map, 1), { can: true, on: true }, 'depois de ligar, esta ligada');
u = A.setNewTab(t.r.src, t.map, 1, false);
same(u.src, L, 'desligar devolve a fonte original, caractere por caractere');
mapHolds(u.src, t.map, 'desligar');
/* O mapa segue valendo para as escritas seguintes, no mesmo link e no proximo. */
map = mapOf(L); cur = L;
[() => A.setNewTab(cur, map, 1, true), () => A.setValue(cur, map, 1, 'href', 'https://exemplo.com/longo'), () => A.setValue(cur, map, 2, 'href', '#z'),
 () => A.setNewTab(cur, map, 2, true), () => A.setNewTab(cur, map, 1, false), () => A.setValue(cur, map, 1, 'href', '/c')].forEach((step, i) => {
  const r = step();
  check(typeof r.src === 'string', 'sequencia com nova aba, passo ' + i);
  cur = r.src;
  mapHolds(cur, map, 'sequencia com nova aba, passo ' + i);
});
same(cur, '<nav><a class="b" href="/c" id="k">Um</a> <a href="#z" target="_blank" rel="noopener">Dois</a></nav>', 'resultado da sequencia com nova aba');
/* target que ja existe com outro valor: troca o valor, nao duplica. */
t = tab('<a target="_self" href="/x">x</a>', 0, true);
same(t.r.src, '<a target="_blank" rel="noopener" href="/x">x</a>', 'target existente e trocado; rel entra depois do target');
mapHolds(t.r.src, t.map, 'target existente');
same(A.newTab('<a target="_self" href="/x">x</a>', mapOf('<a target="_self" href="/x">x</a>'), 0), { can: true, on: false }, '_self nao e nova aba');
same(A.newTab('<a href="/x" target=" _BLANK ">x</a>', mapOf('<a href="/x" target=" _BLANK ">x</a>'), 0), { can: true, on: true }, '_blank em maiusculas e com espaco conta');
/* rel que ja existe: so a palavra que falta entra, e nada se perde. */
t = tab('<a href="/x" rel="nofollow me">x</a>', 0, true);
same(t.r.src, '<a href="/x" target="_blank" rel="nofollow me noopener">x</a>', 'noopener acrescentado ao rel existente');
mapHolds(t.r.src, t.map, 'rel existente');
u = A.setNewTab(t.r.src, t.map, 0, false);
same(u.src, '<a href="/x" rel="nofollow me noopener">x</a>', 'desligar deixa o rel que tem outras palavras');
mapHolds(u.src, t.map, 'desligar com rel de outras palavras');
t = tab('<a href="/x" rel="NoOpener noreferrer">x</a>', 0, true);
same(t.r.src, '<a href="/x" target="_blank" rel="NoOpener noreferrer">x</a>', 'rel que ja tem noopener nao e mexido');
t = tab('<a href="/x" rel="">x</a>', 0, true);
same(t.r.src, '<a href="/x" target="_blank" rel="noopener">x</a>', 'rel vazio recebe so a palavra');
t = tab('<a href="/x" rel="me  ">x</a>', 0, true);
same(t.r.src, '<a href="/x" target="_blank" rel="me noopener">x</a>', 'espaco sobrando no fim do rel nao duplica');
t = tab('<a href="/x" rel="xnoopener">x</a>', 0, true);
same(t.r.src, '<a href="/x" target="_blank" rel="xnoopener noopener">x</a>', 'palavra parecida nao conta como noopener');
/* Ligar o que ja esta ligado nao muda nada. */
let ON = '<a href="/x" target="_blank" rel="noopener">x</a>';
same(tab(ON, 0, true).r, { src: ON }, 'ligar duas vezes nao muda a fonte');
/* Desligar sem target: o rel do autor fica. */
let AUTHOR = '<a href="/x" rel="noopener">x</a>';
same(tab(AUTHOR, 0, false).r, { src: AUTHOR }, 'desligar sem target nao retira o rel do autor');
/* Desligar retira o atributo com o espaco (ou a quebra de linha) que o separa. */
t = tab('<a href="/x"\n   target="_blank"\n   rel="noopener"\n   class="b">x</a>', 0, false);
same(t.r.src, '<a href="/x"\n   class="b">x</a>', 'atributos em linhas separadas');
mapHolds(t.r.src, t.map, 'atributos em linhas separadas');
t = tab('<a target="_blank" href="/x">x</a>', 0, false);
same(t.r.src, '<a href="/x">x</a>', 'target antes do href');
mapHolds(t.r.src, t.map, 'target antes do href');
t = tab('<a rel="noopener" href="/x" target="_blank">x</a>', 0, false);
same(t.r.src, '<a href="/x">x</a>', 'rel antes do href e target no fim');
mapHolds(t.r.src, t.map, 'rel antes do href');
/* Link cujo destino vem de dado tambem pode abrir em nova aba. */
t = tab('<a href="{{ social.url }}">x</a>', 0, true);
same(t.r.src, '<a href="{{ social.url }}" target="_blank" rel="noopener">x</a>', 'href de dado: nova aba entra depois dele');
/* Onde o editor nao mexe. */
function tabRefused(src, n, on, code, what, prepare) {
  const m = mapOf(src);
  if (prepare) prepare(m);
  const before = JSON.stringify(m);
  same(A.setNewTab(src, m, n, on), { error: code }, what);
  check(JSON.stringify(m) === before, what + ': mapa intacto');
  check(A.newTab(src, m, n).can === (code !== 'locked'), what + ': caixa ' + (code === 'locked' ? 'desativada' : 'ativa'));
}
tabRefused('<p>x</p>', 0, true, 'locked', 'nao e link');
tabRefused('<a id="x">x</a>', 0, true, 'locked', 'link sem href');
tabRefused('<a href=/x>x</a>', 0, true, 'locked', 'href sem aspas');
tabRefused('<a href="/x" target="{{ alvo }}">x</a>', 0, true, 'locked', 'target vem de dado');
tabRefused('<a href="/x" rel="{{ rel }}">x</a>', 0, true, 'locked', 'rel vem de dado');
tabRefused('<a href="/x" target=_blank>x</a>', 0, false, 'locked', 'target sem aspas');
tabRefused('<a href="/x">x</a>', 0, true, 'locked', 'tag fechada pelo servidor', m => { m.items[0][7] = null; });
tabRefused('<a href="/x">x</a>', 0, true, 'stale', 'mapa deslocado', m => { const a = m.items[0][7][0]; a[2]++; a[3]++; a[4]++; a[5]++; });
tabRefused('<a href="/x" target="_blank">x</a>', 0, false, 'stale', 'target fora do lugar', m => { m.items[0][7][1][4] += 2; });
tabRefused('<a href="/x" rel="me">x</a>', 0, true, 'stale', 'rel fora do lugar', m => { m.items[0][7][1][5] += 2; });
BIG = '<a href="/x">' + 'y'.repeat(A.SOURCE_MAX - 18 - 15) + '</a>';
tabRefused(BIG, 0, true, 'long', 'target cabe, rel nao: nada e escrito');
BIG = '<a href="/x">' + 'y'.repeat(A.SOURCE_MAX - 17 - 31) + '</a>';
check(tab(BIG, 0, true).r.src.length === A.SOURCE_MAX, 'target e rel cabendo exatamente no teto');
same(A.newTab(L, mapOf(L), 0), { can: false, on: false }, 'elemento que nao e link: caixa desativada');
same(A.newTab(L, null, 0), { can: false, on: false }, 'sem mapa: caixa desativada');
/* target ligado mas com rel de dado: mostra ligada, sem poder mudar. */
same(A.newTab('<a href="/x" target="_blank" rel="{{ r }}">x</a>', mapOf('<a href="/x" target="_blank" rel="{{ r }}">x</a>'), 0), { can: false, on: true }, 'ligada e travada');

/* Destino vindo do site: a caixa funciona quando o fim do valor e conferivel. */
(function () {
  const ok = '<a href="{{ home|default(\'/\') }}" class="b">x</a>';
  const mk = src => { const end = src.indexOf('}}') + 2; return { sig: '', items: [['a', 'fixed', 0, src.indexOf('>') + 1, src.indexOf('>') + 1, src.indexOf('</a>'), '', [['href', 'variable', 9, end, 3, end + 1, '']]]] }; };
  let m = mk(ok);
  same(A.newTab(ok, m, 0), { can: true, on: false }, 'href com Twig e aspa diferente: caixa ativa');
  const r = A.setNewTab(ok, m, 0, true);
  same(r.src, '<a href="{{ home|default(\'/\') }}" target="_blank" rel="noopener" class="b">x</a>', 'target e rel entram depois do href com Twig, que fica intacto');
  same(A.setNewTab(r.src, m, 0, false).src, ok, 'e saem sem deixar rastro');
  same(A.setValue(ok, mk(ok), 0, 'href', '/x'), { error: 'locked' }, 'href com Twig nunca e trocado pelo campo');
  const bad = '<a href="{{ link("a") }}" class="b">x</a>';
  m = mk(bad);
  same(A.newTab(bad, m, 0), { can: false, on: false }, 'href com Twig que usa a mesma aspa: caixa desativada');
  same(A.setNewTab(bad, m, 0, true), { error: 'locked' }, 'e nada e escrito');
})();

/* Mapas escritos a mao que o servidor nunca manda: as regras nao dependem dele estar certo. */
/* valor "deslocado" que comeca e termina depois de uma letra igual: a letra nao e aspa */
refused('<a href="x/y/x">t</a>', 0, 'href', '/novo', 'stale', 'valor apontando para o miolo do atributo', m => { const a = m.items[0][7][0]; a[2] += 1; a[3] -= 1; a[5] = a[3] + 1; });
/* um <p> com href no mapa nao ganha caixa de nova aba */
(function () {
  const src = '<p href="/x">t</p>', m = { sig: '', items: [['p', 'fixed', 0, 13, 13, 14, '', [['href', 'fixed', 9, 11, 3, 12, '']]]] };
  same(A.newTab(src, m, 0), { can: false, on: false }, 'href em tag que nao e link: caixa desativada');
  same(A.setNewTab(src, m, 0, true), { error: 'locked' }, 'e nada e escrito');
  same(A.setValue(src, m, 0, 'href', '/y'), { error: 'locked' }, 'nem o destino e trocado');
})();
/* atributo colado no anterior (o servidor fecha essa tag): desligar nao adivinha onde cortar */
(function () {
  const src = '<a href="/x"target="_blank">t</a>';
  const m = { sig: '', items: [['a', 'fixed', 0, 28, 28, 29, '', [['href', 'fixed', 9, 11, 3, 12, ''], ['target', 'fixed', 20, 26, 12, 27, '']]]] };
  const before = JSON.stringify(m);
  same(A.newTab(src, m, 0), { can: true, on: true }, 'target colado: lido como ligado');
  same(A.setNewTab(src, m, 0, false), { error: 'stale' }, 'target colado no href: nao retira');
  check(JSON.stringify(m) === before, 'target colado: mapa intacto');
})();

console.log('Kaz element attrs checker: ' + checks + ' PASS');
