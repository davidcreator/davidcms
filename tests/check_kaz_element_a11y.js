'use strict';
/*
 * KZ-07 (ADR 0100) — imagem sem texto alternativo e desenho sem descricao
 * (dev/admin/view/js/kaz-element-attrs.js: a11y, canLabel, putAttr, decorate, dropLabel).
 * Roda sem navegador: `node tests/check_kaz_element_a11y.js`.
 * O mapa de cada caso vem de um leitor de tags minimo, no formato de
 * ThemeSourceMap::describe() (o que esta dentro de <svg> nao vira item; um
 * <title> la dentro marca o nono campo do <svg>).
 * O leitor daqui e o do KZ-07: nao monta o resumo do que ha dentro de <a> e
 * <button> (KZ-09). Com um mapa assim o link fica "por conferir", nunca
 * pendente; as regras do KZ-09 estao em tests/check_kaz_element_name.js.
 */
global.window = {};
require('../dev/admin/view/js/kaz-element-attrs.js');
const A = window.KazElementAttrs;
let checks = 0;
function check(ok, what) { checks++; if (!ok) throw new Error('FAIL: ' + what); }
function same(a, b, what) { check(JSON.stringify(a) === JSON.stringify(b), what + '\n   obtido:   ' + JSON.stringify(a) + '\n   esperado: ' + JSON.stringify(b)); }

const NAMES = { a: ['href', 'target', 'rel'], img: ['alt'] };
const ANY = ['aria-label', 'style', 'aria-hidden', 'aria-labelledby', 'role'];
function mapOf(src) {
  const items = [];
  const re = /<(\/?)([a-z0-9]+)((?:\s+[a-zA-Z-]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>"']+))?)*)\s*(\/?)>/g;
  let m, depth = 0, rootSvg = null;
  while ((m = re.exec(src))) {
    const tag = m[2];
    if (m[1] === '/') { if (tag === 'svg' && depth > 0 && --depth === 0) rootSvg = null; continue; }
    if (depth > 0) {
      if (tag === 'svg' && m[4] !== '/') depth++;
      if (tag === 'title' && rootSvg) rootSvg[8] = 1;
      continue;
    }
    const open0 = m.index, open1 = m.index + m[0].length, attrs = [];
    const ar = /([a-zA-Z-]+)(?:(\s*=\s*)(?:"([^"]*)"|'([^']*)'|([^\s>"']+)))?/g;
    const base = open0 + 1 + tag.length;
    let x;
    while ((x = ar.exec(m[3]))) {
      const name = x[1].toLowerCase();
      if (!(NAMES[tag] || []).concat(ANY).includes(name)) continue;
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
    const item = [tag, leaf ? 'fixed' : 'group', open0, open1, leaf ? open1 : -1, leaf ? close : -1, '', attrs];
    if (tag === 'svg') { item.push(0); if (m[4] !== '/') { depth = 1; rootSvg = item; } }
    items.push(item);
  }
  return { sig: '', items: items };
}
/* Cada posicao do mapa aponta para o que diz apontar: igual ao mapa lido de novo da fonte escrita. */
function mapHolds(src, map, what) {
  const norm = m => m.items.map(it => [it[0], it[2], it[3], it[4], it[5], (it[7] || []).map(a => a.slice(0, 6)).sort((p, q) => p[4] - q[4])]);
  same(norm(map), norm(mapOf(src)), what + ': o mapa acompanha a fonte');
}
/* Escrita que da certo: fonte nova conferida e mapa em dia. Devolve [fonte, mapa] para encadear. */
function wrote(fn, src, n, args, expected, what, map) {
  map = map || mapOf(src);
  same(A[fn].apply(null, [src, map, n].concat(args)), { src: expected }, what);
  mapHolds(expected, map, what);
  return [expected, map];
}
/* Escrita recusada: o erro certo, e nem a fonte nem o mapa mudam. */
function refused(fn, src, n, args, error, what, map) {
  map = map || mapOf(src);
  const before = JSON.stringify(map);
  same(A[fn].apply(null, [src, map, n].concat(args)), { error: error }, what);
  check(JSON.stringify(map) === before, what + ': mapa intacto');
}
function state(src, n) { const r = A.a11y(src, mapOf(src), n || 0); return r.kind + '/' + r.state + '/' + r.via; }

// --- 1. o que esta pendente ---------------------------------------------------
same(A.LABEL_TAGS, ['a', 'button', 'svg', 'nav', 'section', 'aside', 'form', 'header', 'footer', 'main'], 'tags que aceitam rotulo novo');
check(A.LABEL_ROLES.indexOf('img') !== -1 && A.LABEL_ROLES.indexOf('presentation') === -1 && A.LABEL_ROLES.indexOf('none') === -1, 'papeis que aceitam rotulo');
for (const [src, want] of [
  ['<img src="a.png">', 'img/missing/'],
  ['<img src="a.png" />', 'img/missing/'],
  ['<img src="{{ foto }}" class="x">', 'img/missing/'],
  ['<img src="a.png" title="dica">', 'img/missing/'],
  ['<img src="a.png" alt="">', 'img/decorative/alt'],
  ['<img src="a.png" alt="  ">', 'img/decorative/alt'],
  ['<img src="a.png" alt>', 'img/decorative/alt'],
  ['<img src="a.png" alt=foto>', 'img/named/alt'],
  ['<img src="a.png" alt="Foto da equipe">', 'img/named/alt'],
  ["<img src='a.png' alt='Foto'>", 'img/named/alt'],
  ['<img src="a.png" alt="&nbsp;">', 'img/named/alt'],
  ['<img src="a.png" alt="{{ titulo }}">', 'img/data/alt'],
  ['<img src="a.png" alt="Foto de {{ nome }}">', 'img/data/alt'],
  ['<img src="a.png" aria-hidden="true">', 'img/hidden/aria-hidden'],
  ['<img src="a.png" aria-hidden="TRUE">', 'img/hidden/aria-hidden'],
  ['<img src="a.png" aria-hidden=" true ">', 'img/hidden/aria-hidden'],
  ['<img src="a.png" aria-hidden="false">', 'img/missing/'],
  ['<img src="a.png" aria-hidden>', 'img/missing/'],
  ['<img src="a.png" aria-hidden="">', 'img/missing/'],
  ['<img src="a.png" aria-hidden="{{ oculto }}">', 'img/data/aria-hidden'],
  ['<img src="a.png" role="presentation">', 'img/hidden/role'],
  ['<img src="a.png" role="none">', 'img/hidden/role'],
  ['<img src="a.png" role="img none">', 'img/hidden/role'],
  ['<img src="a.png" role="img">', 'img/missing/'],
  ['<img src="a.png" role="nonexistent">', 'img/missing/'],
  ['<img src="a.png" role="{{ papel }}">', 'img/data/role'],
  ['<img src="a.png" aria-label="Logo">', 'img/named/aria-label'],
  ['<img src="a.png" aria-label="">', 'img/missing/'],
  ['<img src="a.png" aria-label=" ">', 'img/missing/'],
  ['<img src="a.png" aria-label="{{ nome }}">', 'img/data/aria-label'],
  ['<img src="a.png" aria-labelledby="titulo">', 'img/named/aria-labelledby'],
  ['<img src="a.png" aria-labelledby="">', 'img/missing/'],
  ['<img src="a.png" alt="" aria-label="Logo">', 'img/named/aria-label'],
  ['<img src="a.png" alt="Foto" aria-hidden="true">', 'img/hidden/aria-hidden'],
  ['<img src="a.png" alt="" aria-hidden="false">', 'img/decorative/alt'],
  ['<svg viewBox="0 0 1 1"><path d="M0 0"/></svg>', 'svg/missing/'],
  ['<svg/>', 'svg/missing/'],
  ['<svg class="i" width="24"><g><path/></g></svg>', 'svg/missing/'],
  ['<svg><title>Seta</title><path/></svg>', 'svg/named/title'],
  ['<svg><g><title>Seta</title></g></svg>', 'svg/named/title'],
  ['<svg><desc>so descricao longa</desc><path/></svg>', 'svg/missing/'],
  ['<svg aria-hidden="true"><path/></svg>', 'svg/hidden/aria-hidden'],
  ['<svg aria-hidden="true" role="img" aria-label="Selo"><path/></svg>', 'svg/hidden/aria-hidden'],
  ['<svg aria-hidden="false"><path/></svg>', 'svg/missing/'],
  ['<svg role="presentation"><path/></svg>', 'svg/hidden/role'],
  ['<svg role="img"><path/></svg>', 'svg/missing/'],
  ['<svg role="img" aria-label="Selo"><path/></svg>', 'svg/named/aria-label'],
  ['<svg aria-label="Selo"><path/></svg>', 'svg/named/aria-label'],
  ['<svg aria-label=""><path/></svg>', 'svg/missing/'],
  ['<svg aria-labelledby="t"><title id="t">x</title></svg>', 'svg/named/aria-labelledby'],
  ['<svg aria-label="{{ nome }}"><path/></svg>', 'svg/data/aria-label'],
  ['<svg aria-labelledby="{{ id }}"><path/></svg>', 'svg/data/aria-labelledby'],
  ['<svg role="{{ papel }}"><path/></svg>', 'svg/data/role'],
  ['<svg aria-hidden="{{ oculto }}" aria-label="Selo"><path/></svg>', 'svg/data/aria-hidden'],
  // sem aspas, o valor e lido como o navegador le; sem valor nenhum, vale vazio
  ['<svg aria-hidden=true><path/></svg>', 'svg/hidden/aria-hidden'],
  ['<svg aria-hidden=TRUE role=img aria-label=Selo><path/></svg>', 'svg/hidden/aria-hidden'],
  ['<svg aria-hidden=false><path/></svg>', 'svg/missing/'],
  ['<svg aria-hidden><path/></svg>', 'svg/missing/'],
  ['<svg role=presentation><path/></svg>', 'svg/hidden/role'],
  ['<svg role=img><path/></svg>', 'svg/missing/'],
  ['<svg aria-label=Selo><path/></svg>', 'svg/named/aria-label'],
  ['<svg aria-label><path/></svg>', 'svg/missing/'],
  ['<svg aria-labelledby=t><path/></svg>', 'svg/named/aria-labelledby'],
  ['<svg aria-labelledby><path/></svg>', 'svg/missing/'],
  ['<svg aria-hidden={{x}}><path/></svg>', 'svg/data/aria-hidden'],
  ['<svg role={{x}}><path/></svg>', 'svg/data/role'],
  ['<svg aria-label={{x}}><path/></svg>', 'svg/data/aria-label'],
  ['<svg aria-labelledby={%if%}><path/></svg>', 'svg/data/aria-labelledby'],
  ['<img src="a.png" alt>', 'img/decorative/alt'],
  ['<img src="a.png" alt=Foto>', 'img/named/alt'],
  ['<img src="a.png" alt={{t}}>', 'img/data/alt'],
  ['<img src="a.png" alt={#t#}>', 'img/data/alt'],
  ['<p>texto</p>', '/none/'],
  ['<a href="/x">x</a>', 'link/unknown/'],
  ['<a name="x">x</a>', '/none/'],
  ['<button>x</button>', 'button/unknown/'],
  ['<picture></picture>', '/none/'],
]) same(state(src), want, 'situacao de ' + src);
same(state('<p>a</p><img src="b"><svg><title>t</title></svg><svg></svg>', 1), 'img/missing/', 'indice 1');
same(state('<p>a</p><img src="b"><svg><title>t</title></svg><svg></svg>', 2), 'svg/named/title', 'indice 2: o <title> e do proprio desenho');
same(state('<p>a</p><img src="b"><svg><title>t</title></svg><svg></svg>', 3), 'svg/missing/', 'indice 3: o <title> do vizinho nao vale');
same(state('<p>a</p>', 5), '/none/', 'indice que nao existe');
{
  // tag que o servidor nao soube ler: nem pendente nem resolvida
  const src = '<img src="a" {% if x %}alt="b"{% endif %}>', map = { sig: '', items: [['img', 'void', 0, src.length, -1, -1, '', null]] };
  same(A.a11y(src, map, 0), { kind: 'img', state: 'unknown', via: '' }, 'tag fechada para o editor: nao conferida');
  same(A.a11y('<svg {{ a }}></svg>', { items: [['svg', 'group', 0, 13, -1, -1, '', null, 0]] }, 0).state, 'unknown', 'idem no desenho');
  // mapa de outra versao da fonte
  const m2 = mapOf('<img src="a" alt="x">');
  same(A.a11y('<p><img src="a" alt="x"></p>', m2, 0).state, 'unknown', 'mapa defasado (tag em outro lugar): nao conferida');
  same(A.a11y('<img src="a" alt=\'x">', m2, 0).state, 'unknown', 'mapa defasado (aspa trocada): nao conferida');
  same(A.a11y('<img src="a">', null, 0), { kind: '', state: 'none', via: '' }, 'sem mapa');
  same(A.a11y('<svg role=\'none"></svg>', mapOf('<svg role="none"></svg>'), 0).state, 'unknown', 'mapa defasado (aspa do role trocada): nao conferida');
  same(A.a11y('<svg class=aaaa aria-hidden=true></svg>', mapOf('<svg aria-hidden=true class=aaaa></svg>'), 0).state, 'unknown', 'mapa defasado (atributo sem aspas em outro lugar): nao conferida');
  same(A.a11y('<svg aria-hidden="tru"></svg>', mapOf('<svg aria-hidden=true ></svg>'), 0).state, 'unknown', 'mapa defasado (o atributo ganhou aspas): nao conferida');
  same(A.a11y('<svg aria-hidden=truest></svg>', mapOf('<svg aria-hidden=true ></svg>'), 0).state, 'unknown', 'mapa defasado (o valor sem aspas cresceu): nao conferida');
  same(A.a11y('<svg aria-hiddex=true class=a></svg>', mapOf('<svg aria-hidden=true class=a></svg>'), 0).state, 'unknown', 'mapa defasado (outro nome no lugar do atributo sem aspas): nao conferida');
  same(A.a11y('<svg aria-hidden=\'rue x=\'></svg>', mapOf('<svg aria-hidden=true x=1></svg>'), 0).state, 'unknown', 'mapa defasado (aspa onde nao havia): nao conferida');
  // nono campo forjado nao vira nome
  same(A.a11y('<svg></svg>', { items: [['svg', 'group', 0, 5, -1, -1, '', [], 'sim']] }, 0).state, 'missing', 'nono campo so vale quando e 1');
  same(A.a11y('<svg></svg>', { items: [['svg', 'group', 0, 5, -1, -1, '', [], 1]] }, 0).state, 'named', 'nono campo 1: ha <title>');
}

// --- 1b. pendencias de um trecho -------------------------------------------------
{
  const src = '<header><a href="/"><svg class="logo"><path/></svg></a><img src="a.png"><img src="b.png" alt="Foto"><img src="c.png" alt="">' +
    '<svg aria-hidden="true"><path/></svg><svg><title>Seta</title></svg><p>texto</p><img src="{{ f }}" alt="{{ t }}"><svg viewBox="0 0 1 1"/></header>';
  const map = mapOf(src);
  same(map.items.map(it => it[0]), ['header', 'a', 'svg', 'img', 'img', 'img', 'svg', 'svg', 'p', 'img', 'svg'], 'itens do caso');
  /* O <a> entra em "por conferir": este mapa nao traz o resumo do KZ-09. */
  same(A.pending(src, map), { missing: [2, 3, 10], unknown: 1 }, 'pendentes na ordem do codigo');
  map.items[4][7] = null; map.items[6][7] = null; map.items[8][7] = null;
  same(A.pending(src, map), { missing: [2, 3, 10], unknown: 3 }, 'tags fechadas para o editor sao contadas a parte (o <p> nao conta)');
  same(A.pending('<p>' + src, map), { missing: [], unknown: 9 }, 'mapa defasado: nada e dado como pendente, as nove ficam por conferir');
  same(A.pending('<p>x</p>', mapOf('<p>x</p>')), { missing: [], unknown: 0 }, 'trecho sem imagem nem desenho');
  same(A.pending('', null), { missing: [], unknown: 0 }, 'sem mapa');
  same(A.pending('', { items: 'x' }), { missing: [], unknown: 0 }, 'mapa fora do formato');
  // resolver um a um esvazia a lista
  let s2 = src; const m2 = mapOf(src);
  s2 = A.decorate(s2, m2, 2, true).src; same(A.pending(s2, m2).missing, [3, 10], 'depois de marcar o logo como enfeite');
  s2 = A.putAttr(s2, m2, 3, 'alt', 'Foto').src; same(A.pending(s2, m2).missing, [10], 'depois de descrever a imagem');
  s2 = A.putAttr(s2, m2, 10, 'aria-label', 'Selo').src; same(A.pending(s2, m2), { missing: [], unknown: 1 }, 'depois de descrever o desenho');
  s2 = A.dropLabel(s2, m2, 10).src; same(A.pending(s2, m2).missing, [10], 'retirar a descricao devolve a pendencia');
  s2 = A.decorate(s2, m2, 2, false).src; s2 = A.putAttr(s2, m2, 3, 'alt', '').src; s2 = A.decorate(s2, m2, 3, false).src;
  same(s2, src, 'tudo desfeito: fonte original');
}

// --- 2. onde o rotulo pode ser criado ------------------------------------------
for (const tag of A.LABEL_TAGS) check(A.canLabel('<' + tag + ' class="x"></' + tag + '>', mapOf('<' + tag + ' class="x"></' + tag + '>'), 0) === true, 'rotulo novo em <' + tag + '>');
for (const tag of ['div', 'p', 'span', 'h1', 'h2', 'li', 'ul', 'img', 'article', 'label', 'input', 'td', 'strong', 'figure']) {
  const src = tag === 'img' ? '<img src="a">' : '<' + tag + '>x</' + tag + '>';
  check(A.canLabel(src, mapOf(src), 0) === false, 'sem rotulo novo em <' + tag + '>');
}
for (const role of A.LABEL_ROLES) check(A.canLabel('<div role="' + role + '">x</div>', mapOf('<div role="' + role + '">x</div>'), 0) === true, 'rotulo novo em role=' + role);
for (const role of ['presentation', 'none', 'text', 'paragraph', 'heading', '', 'imgx', '{{ r }}']) {
  check(A.canLabel('<div role="' + role + '">x</div>', mapOf('<div role="' + role + '">x</div>'), 0) === false, 'sem rotulo novo em role=' + role);
}
check(A.canLabel('<div role="x BUTTON">x</div>', mapOf('<div role="x BUTTON">x</div>'), 0) === true, 'papel entre outros, em maiusculas');
check(A.canLabel('<div role=button>x</div>', mapOf('<div role=button>x</div>'), 0) === false, 'papel sem aspas nao e lido');
check(A.canLabel('<img src="a" role="img">', mapOf('<img src="a" role="img">'), 0) === false, 'imagem usa alt, nunca rotulo novo');
check(A.canLabel('<a>x</a>', { items: [['a', 'fixed', 0, 3, 3, 4, '', null]] }, 0) === false, 'tag fechada para o editor');
check(A.canLabel('<p><div role="button">x</div>', mapOf('<div role="button">x</div>'), 0) === false, 'mapa defasado: papel nao confere');
check(A.canLabel('<div role=\'button">x</div>', mapOf('<div role="button">x</div>'), 0) === false, 'mapa defasado (aspa trocada): papel nao e lido');

// --- 3. criar texto alternativo e rotulo ---------------------------------------
wrote('putAttr', '<img src="a.png">', 0, ['alt', 'Foto da equipe'], '<img alt="Foto da equipe" src="a.png">', 'alt criado logo depois do nome da tag');
wrote('putAttr', '<img src="a.png"/>', 0, ['alt', 'x'], '<img alt="x" src="a.png"/>', 'tag fechada com />');
wrote('putAttr', '<img>', 0, ['alt', 'x'], '<img alt="x">', 'tag sem atributo nenhum');
wrote('putAttr', '<img/>', 0, ['alt', 'x'], '<img alt="x"/>', 'tag sem atributo, com /');
wrote('putAttr', '<img\n  src="{{ foto }}"\n  class="x">', 0, ['alt', 'x'], '<img alt="x"\n  src="{{ foto }}"\n  class="x">', 'tag em varias linhas, com Twig no src');
wrote('putAttr', '<p>a</p><img src="a.png"><p>b</p>', 1, ['alt', 'x'], '<p>a</p><img alt="x" src="a.png"><p>b</p>', 'vizinhos intactos');
wrote('putAttr', '<img src="a.png">', 0, ['alt', '  com espacos  '], '<img alt="com espacos" src="a.png">', 'pontas aparadas');
wrote('putAttr', '<img src="a.png">', 0, ['alt', 'a "b" <c> & {{ d }} \'e\''], '<img alt="a &quot;b&quot; &lt;c&gt; &amp; &#123;&#123; d }} &#39;e&#39;" src="a.png">', 'o que e digitado vira texto');
wrote('putAttr', '<img src="a.png">', 0, ['alt', 'x onerror=alert(1)'], '<img alt="x onerror&#61;alert(1)" src="a.png">', 'padrao recusado pela validacao do tema e quebrado');
wrote('putAttr', '<img src="a.png">', 0, ['alt', 'y'.repeat(400)], '<img alt="' + 'y'.repeat(300) + '" src="a.png">', 'no maximo 300 caracteres');
wrote('putAttr', '<img src="a.png">', 0, ['alt', ''], '<img src="a.png">', 'campo vazio em atributo que nao existe: nada muda');
wrote('putAttr', '<img src="a.png">', 0, ['alt', '   '], '<img src="a.png">', 'so espacos: nada muda');
wrote('putAttr', '<img src="a.png" alt="antes">', 0, ['alt', 'depois'], '<img src="a.png" alt="depois">', 'alt que ja existe: troca so o valor, no lugar dele');
wrote('putAttr', '<img src="a.png" alt="antes">', 0, ['alt', ''], '<img src="a.png" alt="">', 'alt que ja existe pode ficar vazio (enfeite)');
refused('putAttr', '<img src="a.png" alt="{{ t }}">', 0, ['alt', 'x'], 'locked', 'alt que vem de dado nao e trocado');
refused('putAttr', '<img src="a.png" alt>', 0, ['alt', 'x'], 'locked', 'alt sem aspas nao e trocado');
refused('putAttr', '<p>x</p>', 0, ['alt', 'x'], 'locked', 'alt so em imagem');
refused('putAttr', '<svg></svg>', 0, ['alt', 'x'], 'locked', 'alt nao vai em desenho');
refused('putAttr', '<img src="a">', 0, ['title', 'x'], 'locked', 'outro atributo nao e criado');
refused('putAttr', '<img src="a">', 0, ['onerror', 'x'], 'locked', 'nem atributo de evento');
refused('putAttr', '<a href="/x">x</a>', 0, ['href', '/y'], 'locked', 'href continua em setValue');
refused('putAttr', '<img src="a">', 0, ['aria-label', 'x'], 'locked', 'rotulo novo nao vai em imagem');
refused('putAttr', '<div>x</div>', 0, ['aria-label', 'x'], 'locked', 'rotulo novo nao vai em bloco comum');
refused('putAttr', '<p>x</p>', 0, ['aria-label', 'x'], 'locked', 'nem em paragrafo');
{
  const src = '<img src="a" {% if x %}alt="b"{% endif %}>', map = { sig: '', items: [['img', 'void', 0, src.length, -1, -1, '', null]] };
  same(A.putAttr(src, map, 0, 'alt', 'x'), { error: 'locked' }, 'tag fechada para o editor: nada e criado');
  same(A.putAttr('<p><img src="a"></p>', mapOf('<img src="a">'), 0, 'alt', 'x'), { error: 'stale' }, 'mapa defasado: nada e criado');
  same(A.putAttr('<imgx src="a">', mapOf('<img src="a">'), 0, 'alt', 'x'), { error: 'stale' }, 'outra tag no lugar: nada e criado');
  same(A.putAttr('<img src="a">', null, 0, 'alt', 'x'), { error: 'locked' }, 'sem mapa');
  same(A.putAttr('<img src="a">', mapOf('<img src="a">'), 3, 'alt', 'x'), { error: 'locked' }, 'indice que nao existe');
}
wrote('putAttr', '<a href="/x"><i class="ico"></i></a>', 0, ['aria-label', 'Abrir o menu'], '<a aria-label="Abrir o menu" href="/x"><i class="ico"></i></a>', 'rotulo criado em link');
wrote('putAttr', '<button type="button"></button>', 0, ['aria-label', 'Fechar'], '<button aria-label="Fechar" type="button"></button>', 'rotulo criado em botao');
wrote('putAttr', '<nav class="menu"></nav>', 0, ['aria-label', 'Principal'], '<nav aria-label="Principal" class="menu"></nav>', 'rotulo criado em navegacao');
wrote('putAttr', '<section></section>', 0, ['aria-label', 'Depoimentos'], '<section aria-label="Depoimentos"></section>', 'rotulo criado em secao');
wrote('putAttr', '<div role="button" class="b">x</div>', 0, ['aria-label', 'Ok'], '<div aria-label="Ok" role="button" class="b">x</div>', 'rotulo criado onde o role e da lista');
wrote('putAttr', '<svg viewBox="0 0 1 1"><path/></svg>', 0, ['aria-label', 'Selo de qualidade'], '<svg role="img" aria-label="Selo de qualidade" viewBox="0 0 1 1"><path/></svg>', 'desenho: rotulo vem com role="img"');
wrote('putAttr', '<svg role="graphics-document"><path/></svg>', 0, ['aria-label', 'Mapa'], '<svg aria-label="Mapa" role="graphics-document"><path/></svg>', 'desenho que ja tem role: o role fica como esta');
wrote('putAttr', '<svg role="{{ r }}"><path/></svg>', 0, ['aria-label', 'Mapa'], '<svg aria-label="Mapa" role="{{ r }}"><path/></svg>', 'role que vem de dado: nao e tocado');
wrote('putAttr', '<svg role><path/></svg>', 0, ['aria-label', 'Mapa'], '<svg aria-label="Mapa" role><path/></svg>', 'role sem valor: nao e duplicado');
wrote('putAttr', '<a href="/x">x</a>', 0, ['aria-label', ''], '<a href="/x">x</a>', 'rotulo vazio em atributo que nao existe: nada muda');
wrote('putAttr', '<div aria-label="antes">x</div>', 0, ['aria-label', 'depois'], '<div aria-label="depois">x</div>', 'rotulo que ja existe e trocado em qualquer tag (KZ-05)');
wrote('putAttr', '<div aria-label="antes">x</div>', 0, ['aria-label', ''], '<div aria-label="">x</div>', 'rotulo que ja existe pode ficar vazio; quem retira e dropLabel');
refused('putAttr', '<a href="/x" aria-label="{{ t }}">x</a>', 0, ['aria-label', 'y'], 'locked', 'rotulo que vem de dado nao e trocado');
{
  // digitar letra por letra: a primeira cria, as outras trocam o valor, com o MESMO mapa
  let src = '<p>a</p><img src="a.png" class="x"><p>b</p>'; const map = mapOf(src);
  for (const typed of ['F', 'Fo', 'Fot', 'Foto', 'Foto ', 'Foto d', 'Fot', '']) {
    const want = '<p>a</p><img alt="' + typed.trim() + '" src="a.png" class="x"><p>b</p>';
    [src] = wrote('putAttr', src, 1, ['alt', typed], want, 'digitando "' + typed + '"', map);
  }
  same(A.a11y(src, map, 1), { kind: 'img', state: 'decorative', via: 'alt' }, 'apagar tudo deixa alt vazio: enfeite');
  let s2 = '<svg class="i"><path/></svg>'; const m2 = mapOf(s2);
  for (const typed of ['S', 'Se', 'Selo']) [s2] = wrote('putAttr', s2, 0, ['aria-label', typed], '<svg role="img" aria-label="' + typed + '" class="i"><path/></svg>', 'desenho, digitando "' + typed + '"', m2);
  same(A.a11y(s2, m2, 0), { kind: 'svg', state: 'named', via: 'aria-label' }, 'desenho passa a ter nome');
}
{
  // teto de tamanho: o que nao cabe nao e criado
  const pad = 'x'.repeat(A.SOURCE_MAX - '<img src="a"><p></p>'.length - ' alt="abc"'.length);
  const fits = '<img src="a"><p>' + pad + '</p>';
  same(A.putAttr(fits, mapOf(fits), 0, 'alt', 'abc').src.length, A.SOURCE_MAX, 'cabe exatamente no teto');
  same(A.putAttr(fits, mapOf(fits), 0, 'alt', 'abcd'), { error: 'long' }, 'um caractere a mais nao cabe');
  same(A.putAttr(fits, mapOf(fits), 0, 'alt', 'ab\u00e9'), { error: 'long' }, 'acento vale dois bytes');
  const full = '<img src="a"><p>' + pad + 'x'.repeat(' alt="abc"'.length) + '</p>';
  same(A.decorate(full, mapOf(full), 0, true), { error: 'long' }, 'marca de enfeite que nao cabe');
  const svg = '<svg></svg><p>' + 'x'.repeat(A.SOURCE_MAX - '<svg></svg><p></p>'.length - 5) + '</p>';
  same(A.putAttr(svg, mapOf(svg), 0, 'aria-label', 'a'), { error: 'long' }, 'role + rotulo que nao cabem: nenhum dos dois e escrito');
  same(A.decorate(svg, mapOf(svg), 0, true), { error: 'long' }, 'aria-hidden que nao cabe');
  // fonte que ja passou do teto: trocar "false" por "true" encurta, mas continua sem caber
  const over = '<svg aria-hidden="false"></svg><p>' + 'x'.repeat(A.SOURCE_MAX) + '</p>';
  refused('decorate', over, 0, [true], 'long', 'fonte acima do teto: a marca nao e trocada');
  const bare = n => '<svg aria-hidden></svg><p>' + 'x'.repeat(A.SOURCE_MAX - '<svg aria-hidden></svg><p></p>'.length - n) + '</p>';
  refused('decorate', bare(6), 0, [true], 'long', 'aria-hidden sem valor: ="true" que nao cabe');
  same(A.decorate(bare(7), mapOf(bare(7)), 0, true).src.length, A.SOURCE_MAX, 'aria-hidden sem valor: ="true" cabe exatamente no teto');
}

// --- 4. e so enfeite -----------------------------------------------------------
wrote('decorate', '<img src="a.png">', 0, [true], '<img alt="" src="a.png">', 'imagem: alt vazio');
wrote('decorate', '<img src="a.png" class="x" />', 0, [true], '<img alt="" src="a.png" class="x" />', 'imagem com /');
wrote('decorate', '<svg viewBox="0 0 1 1"><path/></svg>', 0, [true], '<svg aria-hidden="true" viewBox="0 0 1 1"><path/></svg>', 'desenho: aria-hidden="true"');
wrote('decorate', '<svg/>', 0, [true], '<svg aria-hidden="true"/>', 'desenho sem atributo, com /');
wrote('decorate', '<svg aria-hidden="false" class="i"><path/></svg>', 0, [true], '<svg aria-hidden="true" class="i"><path/></svg>', 'aria-hidden que nao escondia: troca o valor');
wrote('decorate', '<svg class="i" aria-hidden=""><path/></svg>', 0, [true], '<svg class="i" aria-hidden="true"><path/></svg>', 'aria-hidden vazio: troca o valor');
wrote('decorate', '<svg role="img"><path/></svg>', 0, [true], '<svg aria-hidden="true" role="img"><path/></svg>', 'desenho com role="img" e sem nome');
wrote('decorate', '<svg aria-label=""><path/></svg>', 0, [true], '<svg aria-hidden="true" aria-label=""><path/></svg>', 'rotulo vazio nao e nome: pode virar enfeite');
for (const src of ['<img src="a" alt="Foto">', '<img src="a" alt="">', '<img src="a" alt="{{ t }}">', '<img src="a" aria-hidden="true">', '<img src="a" role="presentation">',
  '<img src="a" aria-label="Logo">', '<svg aria-label="Selo"><path/></svg>', '<svg><title>Seta</title></svg>', '<svg aria-hidden="true"></svg>', '<svg role="none"></svg>',
  '<svg aria-labelledby="t"></svg>', '<svg aria-label="{{ n }}"></svg>']) {
  refused('decorate', src, 0, [true], 'locked', 'so o que esta pendente vira enfeite por aqui: ' + src);
}
for (const src of ['<p>x</p>', '<a href="/x">x</a>', '<div role="img">x</div>', '<picture></picture>']) {
  refused('decorate', src, 0, [true], 'locked', 'so imagem e desenho: ' + src);
  refused('decorate', src, 0, [false], 'locked', 'idem ao desmarcar: ' + src);
}
wrote('decorate', '<img alt="" src="a.png">', 0, [false], '<img src="a.png">', 'desmarcar imagem: o alt vazio sai');
wrote('decorate', '<img src="a.png" alt="" class="x">', 0, [false], '<img src="a.png" class="x">', 'alt vazio no meio da tag sai com o espaco dele');
wrote('decorate', '<img src="a.png"\n     alt=" ">', 0, [false], '<img src="a.png">', 'alt so com espaco, em outra linha');
wrote('decorate', '<svg aria-hidden="true" viewBox="0 0 1 1"><path/></svg>', 0, [false], '<svg viewBox="0 0 1 1"><path/></svg>', 'desmarcar desenho: aria-hidden sai');
wrote('decorate', '<svg class="i" aria-hidden="TRUE"><path/></svg>', 0, [false], '<svg class="i"><path/></svg>', 'aria-hidden em maiusculas');
for (const src of ['<img src="a" alt="Foto">', '<img src="a">', '<img src="a" alt="{{ t }}">', '<img src="a" alt=Foto>', '<img src="a" alt="" aria-hidden="true">', '<img src="a" alt="" role="presentation">',
  '<img src="a" alt="" aria-label="Logo">', '<svg><path/></svg>', '<svg aria-hidden="false"></svg>', '<svg role="presentation"></svg>', '<svg aria-label="Selo"></svg>',
  '<svg aria-hidden="{{ h }}"></svg>', '<svg aria-hidden=false></svg>', '<svg aria-hidden></svg>']) {
  wrote('decorate', src, 0, [false], src, 'desmarcar so retira a marca de enfeite que esta valendo: ' + src);
}
{
  const src = '<img src="a" {% if x %}alt=""{% endif %}>', map = { sig: '', items: [['img', 'void', 0, src.length, -1, -1, '', null]] };
  same(A.decorate(src, map, 0, true), { error: 'locked' }, 'tag fechada para o editor');
  same(A.decorate('<p><img src="a"></p>', mapOf('<img src="a">'), 0, true), { error: 'stale' }, 'mapa defasado: nada e marcado');
  same(A.decorate('<p><svg aria-hidden="true"></svg></p>', mapOf('<svg aria-hidden="true"></svg>'), 0, false), { error: 'stale' }, 'mapa defasado: nada e desmarcado');
  same(A.decorate('<img src="a">', null, 0, true), { error: 'locked' }, 'sem mapa');
  same(A.decorate('<svg aria-hidden=\'false"></svg>', mapOf('<svg aria-hidden="false"></svg>'), 0, true), { error: 'stale' }, 'mapa defasado (aspa trocada): nada e marcado');
  same(A.dropLabel('<a aria-label=\'x" href="/x">x</a>', mapOf('<a aria-label="x" href="/x">x</a>'), 0), { error: 'stale' }, 'mapa defasado (aspa trocada): nada e retirado');
  same(A.dropLabel('<svg role=\'img" aria-label="x"></svg>', mapOf('<svg role="img" aria-label="x"></svg>'), 0), { error: 'stale' }, 'mapa defasado (aspa do role trocada): nada e retirado');
}
for (const src of ['<img src="a.png">', '<p>a</p><img\n src="{{ f }}" class="x"/><p>b</p>', '<svg viewBox="0 0 1 1"><path/></svg>', '<a href="/x"><svg class="i"><use href="#s"/></svg> Texto</a>']) {
  const n = mapOf(src).items.findIndex(it => it[0] === 'img' || it[0] === 'svg'), map = mapOf(src);
  const on = A.decorate(src, map, n, true);
  check(typeof on.src === 'string' && on.src !== src, 'ida: ' + src);
  check(['decorative', 'hidden'].indexOf(A.a11y(on.src, map, n).state) !== -1, 'depois de marcar, deixa de estar pendente: ' + src);
  same(A.decorate(on.src, map, n, false), { src: src }, 'marcar e desmarcar devolve a fonte original: ' + src);
  mapHolds(src, map, 'ida e volta: ' + src);
  same(A.a11y(src, map, n).state, 'missing', 'e volta a estar pendente: ' + src);
}
// atributo sem aspas ou sem valor
wrote('decorate', '<svg aria-hidden=false class="i"><path/></svg>', 0, [true], '<svg aria-hidden="true" class="i"><path/></svg>', 'aria-hidden=false sem aspas vira aria-hidden="true"');
wrote('decorate', '<svg class="i" aria-hidden><path/></svg>', 0, [true], '<svg class="i" aria-hidden="true"><path/></svg>', 'aria-hidden sem valor ganha ="true"');
wrote('decorate', '<svg ARIA-HIDDEN role=img class="i"><path/></svg><img src="a">', 0, [true], '<svg aria-hidden="true" role=img class="i"><path/></svg><img src="a">', 'e o que vem depois na fonte acompanha');
wrote('decorate', '<svg aria-hidden=true class="i"><path/></svg>', 0, [false], '<svg class="i"><path/></svg>', 'aria-hidden=true sem aspas tambem e retirado');
wrote('decorate', '<img src="a.png" alt>', 0, [false], '<img src="a.png">', 'alt sem valor tambem e retirado');
wrote('decorate', '<img src="a.png" alt class="x">', 0, [false], '<img src="a.png" class="x">', 'alt sem valor no meio da tag');
{
  const [s1, m1] = wrote('decorate', '<svg aria-hidden=false><path/></svg>', 0, [true], '<svg aria-hidden="true"><path/></svg>', 'ida sem aspas');
  same(A.a11y(s1, m1, 0), { kind: 'svg', state: 'hidden', via: 'aria-hidden' }, 'depois da troca, o desenho esta escondido');
  wrote('decorate', s1, 0, [false], '<svg><path/></svg>', 'e a marca sai inteira na volta', m1);
  refused('decorate', '<svg aria-hidden={{x}}><path/></svg>', 0, [true], 'locked', 'aria-hidden sem aspas que vem de dado: nao e trocado');
  same(A.decorate('<svg aria-hidden={{x}}><path/></svg>', mapOf('<svg aria-hidden={{x}}><path/></svg>'), 0, false), { src: '<svg aria-hidden={{x}}><path/></svg>' }, 'nem retirado');
}
{
  // atributos colados um no outro: tirar um juntaria o vizinho ao que vem antes
  const glued = '<a aria-label="x"href="/x">y</a>';
  const gmap = () => ({ sig: '', items: [['a', 'fixed', 0, 27, 27, 28, '', [['aria-label', 'fixed', 15, 16, 3, 17, ''], ['href', 'fixed', 23, 25, 17, 26, '']]]] });
  same([glued.slice(15, 16), glued.slice(3, 17), glued.slice(23, 25), glued.slice(17, 26)], ['x', 'aria-label="x"', '/x', 'href="/x"'], 'o mapa feito a mao aponta para os atributos');
  refused('dropLabel', glued, 0, [], 'stale', 'rotulo colado no atributo seguinte: nao e retirado', gmap());
  // segundo passo que falha: o primeiro nao pode ficar no mapa
  const half = '<svg class="a"role="img" aria-label="x"></svg>';
  const hmap = () => ({ sig: '', items: [['svg', 'group', 0, 40, -1, -1, '', [['role', 'fixed', 20, 23, 14, 24, ''], ['aria-label', 'fixed', 37, 38, 25, 39, '']], 0]] });
  same([half.slice(20, 23), half.slice(14, 24), half.slice(37, 38), half.slice(25, 39)], ['img', 'role="img"', 'x', 'aria-label="x"'], 'idem no desenho');
  refused('dropLabel', half, 0, [], 'stale', 'role="img" colado no atributo anterior: nada e retirado, nem o rotulo', hmap());
}

// --- 5. retirar o rotulo -------------------------------------------------------
wrote('dropLabel', '<a aria-label="Abrir o menu" href="/x">x</a>', 0, [], '<a href="/x">x</a>', 'rotulo sai, com o espaco dele');
wrote('dropLabel', '<a href="/x" aria-label="Abrir">x</a>', 0, [], '<a href="/x">x</a>', 'rotulo no fim da tag');
wrote('dropLabel', '<div class="a" aria-label="">x</div>', 0, [], '<div class="a">x</div>', 'rotulo vazio de qualquer tag');
wrote('dropLabel', '<svg role="img" aria-label="Selo" viewBox="0 0 1 1"><path/></svg>', 0, [], '<svg viewBox="0 0 1 1"><path/></svg>', 'desenho: role="img" sai junto');
wrote('dropLabel', '<svg aria-label="Selo" class="i" role="IMG"><path/></svg>', 0, [], '<svg class="i"><path/></svg>', 'role depois do rotulo, em maiusculas');
wrote('dropLabel', '<svg role="graphics-document" aria-label="Mapa"><path/></svg>', 0, [], '<svg role="graphics-document"><path/></svg>', 'outro role fica');
wrote('dropLabel', '<svg role="img presentation" aria-label="Mapa"><path/></svg>', 0, [], '<svg role="img presentation"><path/></svg>', 'role com mais de uma palavra fica');
wrote('dropLabel', '<svg role="{{ r }}" aria-label="Mapa"><path/></svg>', 0, [], '<svg role="{{ r }}"><path/></svg>', 'role que vem de dado fica');
wrote('dropLabel', '<div role="img" aria-label="Foto">x</div>', 0, [], '<div role="img">x</div>', 'fora de <svg>, o role nao e do editor: fica');
wrote('dropLabel', '<a href="/x">x</a>', 0, [], '<a href="/x">x</a>', 'sem rotulo: nada muda');
refused('dropLabel', '<a href="/x" aria-label="{{ t }}">x</a>', 0, [], 'locked', 'rotulo que vem de dado nao e retirado');
refused('dropLabel', '<a href="/x" aria-label>x</a>', 0, [], 'locked', 'rotulo sem aspas nao e retirado');
{
  same(A.dropLabel('<p><a aria-label="x" href="/x">x</a></p>', mapOf('<a aria-label="x" href="/x">x</a>'), 0), { error: 'stale' }, 'mapa defasado: nada e retirado');
  same(A.dropLabel('<a>x</a>', { items: [['a', 'fixed', 0, 3, 3, 4, '', null]] }, 0), { error: 'locked' }, 'tag fechada para o editor');
  same(A.dropLabel('<a>x</a>', null, 0), { error: 'locked' }, 'sem mapa');
}
for (const [src, text] of [['<svg viewBox="0 0 1 1"><path/></svg>', 'Selo'], ['<p>a</p><a href="/x"><i></i></a><p>b</p>', 'Abrir'], ['<nav\n  class="m"></nav>', 'Principal'], ['<button/>', 'Ok']]) {
  const n = mapOf(src).items.findIndex(it => A.LABEL_TAGS.indexOf(it[0]) !== -1), map = mapOf(src);
  const made = A.putAttr(src, map, n, 'aria-label', text);
  check(typeof made.src === 'string' && made.src.indexOf('aria-label="' + text + '"') !== -1, 'criado: ' + src);
  same(A.dropLabel(made.src, map, n), { src: src }, 'criar e retirar o rotulo devolve a fonte original: ' + src);
  mapHolds(src, map, 'criar e retirar: ' + src);
}

// --- 6. junto com as outras escritas na mesma tag --------------------------------
{
  let src = '<p>a</p><a href="/x" class="b"><svg class="i"><path/></svg></a><img src="a.png"><p>z</p>'; const map = mapOf(src);
  [src] = wrote('setStyle', src, 1, ['color', '#ff0000'], '<p>a</p><a style="color:#ff0000" href="/x" class="b"><svg class="i"><path/></svg></a><img src="a.png"><p>z</p>', 'cor no link', map);
  [src] = wrote('putAttr', src, 1, ['aria-label', 'Inicio'], '<p>a</p><a aria-label="Inicio" style="color:#ff0000" href="/x" class="b"><svg class="i"><path/></svg></a><img src="a.png"><p>z</p>', 'rotulo no link', map);
  [src] = wrote('setNewTab', src, 1, [true], '<p>a</p><a aria-label="Inicio" style="color:#ff0000" href="/x" target="_blank" rel="noopener" class="b"><svg class="i"><path/></svg></a><img src="a.png"><p>z</p>', 'nova aba no link', map);
  [src] = wrote('decorate', src, 2, [true], '<p>a</p><a aria-label="Inicio" style="color:#ff0000" href="/x" target="_blank" rel="noopener" class="b"><svg aria-hidden="true" class="i"><path/></svg></a><img src="a.png"><p>z</p>', 'icone do link e enfeite', map);
  [src] = wrote('putAttr', src, 3, ['alt', 'Foto'], '<p>a</p><a aria-label="Inicio" style="color:#ff0000" href="/x" target="_blank" rel="noopener" class="b"><svg aria-hidden="true" class="i"><path/></svg></a><img alt="Foto" src="a.png"><p>z</p>', 'alt na imagem', map);
  [src] = wrote('setValue', src, 1, ['aria-label', 'Pagina inicial'], src.replace('Inicio', 'Pagina inicial'), 'rotulo criado e editado pelo caminho da KZ-05', map);
  [src] = wrote('setValue', src, 3, ['alt', 'Foto da equipe'], src.replace('alt="Foto"', 'alt="Foto da equipe"'), 'alt criado e editado pelo caminho da KZ-05', map);
  [src] = wrote('dropLabel', src, 1, [], src.replace(' aria-label="Pagina inicial"', ''), 'rotulo retirado', map);
  [src] = wrote('decorate', src, 2, [false], src.replace(' aria-hidden="true"', ''), 'icone desmarcado', map);
  [src] = wrote('setStyle', src, 1, ['color', ''], src.replace(' style="color:#ff0000"', ''), 'cor retirada', map);
  [src] = wrote('setNewTab', src, 1, [false], src.replace(' target="_blank" rel="noopener"', ''), 'nova aba desligada', map);
  [src] = wrote('putAttr', src, 3, ['alt', ''], src.replace('alt="Foto da equipe"', 'alt=""'), 'alt esvaziado', map);
  [src] = wrote('decorate', src, 3, [false], '<p>a</p><a href="/x" class="b"><svg class="i"><path/></svg></a><img src="a.png"><p>z</p>', 'tudo desfeito: a fonte original, caractere por caractere', map);
}

// --- 7. o que o servidor manda e conferido ---------------------------------------
same(A.clean([['aria-hidden', 'fixed', 5, 9, 1, 10, ''], ['role', 'fixed', 17, 20, 11, 21, ''], ['aria-labelledby', 'bare', 40, 40, 25, 40, '']]).length, 3, 'os tres nomes novos sao aceitos');
same(A.clean([['title', 'fixed', 5, 9, 1, 10, '']]), [['title', 'fixed', 5, 9, 1, 10, '']], 'title entra na lista no KZ-09 (so para ler)');
check(A.clean([['class', 'fixed', 5, 9, 1, 10, '']]) === null, 'class continua fora da lista');
check(A.clean([['role', 'fixed', 5, 9, 1, 10, ''], ['role', 'fixed', 15, 19, 11, 20, '']]) === null, 'nome repetido fecha a tag');

console.log('Kaz element a11y checker: ' + checks + ' PASS');
