'use strict';
/*
 * KZ-09 (ADR 0102) — link e botao sem nome
 * (dev/admin/view/js/kaz-element-attrs.js: a11y em <a href> e <button>, pending).
 *
 * Tres jeitos de rodar, todos sem navegador:
 *   node tests/check_kaz_element_name.js
 *       as regras, sobre mapas montados pelo leitor de tags daqui;
 *   node tests/check_kaz_element_name.js --dump
 *       so imprime os casos (JSON), para o PHPUnit montar o mapa de cada um
 *       com o leitor de verdade (ThemeSourceMap::describe);
 *   node tests/check_kaz_element_name.js --maps <arquivo.json>
 *       os mesmos casos, julgados com os mapas do servidor, e cada mapa do
 *       servidor comparado com o do leitor daqui (os dois tem de concordar);
 *   node tests/check_kaz_element_name.js --count <arquivo.json>
 *       conta a situacao de cada link e botao de uma lista de fontes com mapa
 *       do servidor (os pacotes entregues) e imprime a contagem (JSON).
 *
 * O leitor daqui segue o formato de ThemeSourceMap::describe(): o que esta
 * dentro de <svg> nao vira item, um <title> la dentro marca o nono campo do
 * <svg>, e o nono campo de <a> e <button> e [marcas, indices de dentro...].
 */
global.window = {};
require('../dev/admin/view/js/kaz-element-attrs.js');
const A = window.KazElementAttrs;
const fs = require('fs');
let checks = 0;
function check(ok, what) { checks++; if (!ok) throw new Error('FAIL: ' + what); }
function same(a, b, what) { check(JSON.stringify(a) === JSON.stringify(b), what + '\n   obtido:   ' + JSON.stringify(a) + '\n   esperado: ' + JSON.stringify(b)); }

const NAMES = { a: ['href', 'target', 'rel', 'title'], button: ['title'], img: ['alt'] };
const ANY = ['aria-label', 'style', 'aria-hidden', 'aria-labelledby', 'role'];
const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
const LOOSE = ['p', 'li', 'dt', 'dd', 'td', 'th', 'tr', 'option', 'optgroup', 'thead', 'tbody', 'tfoot', 'colgroup', 'caption', 'rt', 'rp'];
const TEXT = 1, TWIG = 2, HIDDEN = 4, UNSURE = 8;
const OPEN_TAG = /<([a-zA-Z][a-zA-Z0-9:-]*)((?:\s+[a-zA-Z-]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>"']+))?)*)\s*(\/?)>/y;
const CLOSE_TAG = /<\/([a-zA-Z][a-zA-Z0-9:-]*)\s*>/y;

/** Atributos conhecidos de uma tag (o texto entre o nome e o `>`), nas posicoes da fonte. */
function attrsOf(tag, text, base) {
  const out = [], ar = /([a-zA-Z-]+)(?:(\s*=\s*)(?:"([^"]*)"|'([^']*)'|([^\s>"']+)))?/g;
  let x;
  while ((x = ar.exec(text))) {
    const name = x[1].toLowerCase();
    if (!(NAMES[tag] || []).concat(ANY).includes(name)) continue;
    const a0 = base + x.index, a1 = a0 + x[0].length;
    if (x[3] !== undefined || x[4] !== undefined) {
      const val = x[3] !== undefined ? x[3] : x[4];
      const v0 = a0 + x[1].length + x[2].length + 1;
      out.push([name, /\{\{|\{%/.test(val) ? 'variable' : 'fixed', v0, v0 + val.length, a0, a1, '']);
    } else {
      out.push([name, 'bare', a1 - (x[5] || '').length, a1, a0, a1, '']);
    }
  }
  return out;
}
function hides(src, attrs) {
  const a = attrs.find(x => x[0] === 'aria-hidden');
  return !!a && a[1] !== 'variable' && src.slice(a[2], a[3]).trim().toLowerCase() === 'true';
}

function mapOf(src) {
  const items = [];
  let holders = [];             // <a>/<button> abertos, de fora para dentro
  let hide = null;              // { tag, depth, at }: trecho com aria-hidden="true" em leitura
  let leaf = -1, twigs = [];    // elemento a espera de saber se e folha, e o Twig visto dentro dele
  let svg = 0, svgRoot = null;
  let text = false, twig = false;
  function stray() {
    const mask = (text ? TEXT : 0) | (twig ? TWIG : 0);
    if (mask && !hide) holders.forEach(h => { items[h][8][0] |= mask; });
    text = false; twig = false;
  }
  let i = 0;
  while (i < src.length) {
    const two = src.substr(i, 2);
    if (two === '{{' || two === '{%' || two === '{#') {
      const end = src.indexOf(two === '{{' ? '}}' : two === '{%' ? '%}' : '#}', i + 2) + 2;
      if (leaf >= 0) twigs.push([i, end]);
      if (svg === 0 && two !== '{#') twig = true;
      i = end;
      continue;
    }
    if (src.startsWith('<!--', i)) { leaf = -1; stray(); i = src.indexOf('-->', i) + 3; continue; }
    CLOSE_TAG.lastIndex = i;
    let m = CLOSE_TAG.exec(src);
    if (m) {
      const name = m[1].toLowerCase();
      let closed = false;
      if (leaf >= 0 && items[leaf][0] === name) {
        const it = items[leaf], body = src.slice(it[3], i);
        it[4] = it[3]; it[5] = i;
        if (!twigs.length) it[1] = body.trim() === '' ? 'empty' : 'fixed';
        else {
          const only = twigs.length === 1 && (src.slice(it[3], twigs[0][0]) + src.slice(twigs[0][1], i)).trim() === '' && /^\{\{[\s\S]*\}\}$/.test(src.slice(twigs[0][0], twigs[0][1]));
          it[1] = only ? 'variable' : 'mixed';
        }
        closed = true;
      }
      leaf = -1;
      if (closed) { text = false; twig = false; } else stray();
      if (name === 'svg' && svg > 0) { if (--svg === 0) svgRoot = null; }
      else if (svg === 0) {
        if (hide && name === hide.tag && --hide.depth === 0) hide = null;
        if (name === 'a' || name === 'button') {
          let h = holders.length - 1;
          while (h >= 0 && items[holders[h]][0] !== name) h--;
          if (h >= 0) {
            for (let k = holders.length - 1; k > h; k--) items[holders[k]][8][0] |= UNSURE;
            holders = holders.slice(0, h);
            if (hide && holders.length < hide.at) hide = null;
          }
        }
      }
      i += m[0].length;
      continue;
    }
    OPEN_TAG.lastIndex = i;
    m = OPEN_TAG.exec(src);
    if (m) {
      const tag = m[1].toLowerCase(), isVoid = m[3] === '/' || VOID.includes(tag);
      leaf = -1;
      stray();
      if (svg > 0) {
        if (tag === 'svg' && !isVoid) svg++;
        if (tag === 'title' && svgRoot) svgRoot[8] = 1;
        i += m[0].length;
        continue;
      }
      const n = items.length, attrs = attrsOf(tag, m[2], i + 1 + tag.length);
      const it = [tag, isVoid ? 'void' : 'group', i, i + m[0].length, -1, -1, '', attrs];
      if (tag === 'svg') it.push(0);
      if (tag === 'a' || tag === 'button') it.push([0]);
      items.push(it);
      if (hide) {
        if (tag === 'a' || tag === 'button') it[8][0] |= HIDDEN;
        if (!isVoid && tag === hide.tag) hide.depth++;
      } else {
        const hidden = hides(src, attrs);
        if (!hidden || tag === 'img' || tag === 'svg') holders.forEach(h => { items[h][8].push(n); });
        if (!isVoid && (tag === 'a' || tag === 'button')) holders.push(n);
        if (hidden && !isVoid && tag !== 'svg') {
          if (LOOSE.includes(tag)) holders.forEach(h => { items[h][8][0] |= UNSURE; });
          hide = { tag: tag, depth: 1, at: holders.length };
        }
      }
      if (!isVoid) {
        if (tag === 'svg') { svg = 1; svgRoot = it; }
        leaf = n; twigs = [];
      }
      i += m[0].length;
      continue;
    }
    if (holders.length && svg === 0 && !/\s/.test(src.charAt(i))) text = true;
    i++;
  }
  holders.forEach(h => { items[h][8][0] |= UNSURE; });
  return { sig: '', items: items };
}
function state(src, n, map) { const r = A.a11y(src, map || mapOf(src), n || 0); return r.kind + '/' + r.state + '/' + r.via; }

/* [fonte, indice do elemento, situacao esperada, o que o caso mostra] */
const CASES = [
  // --- tem nome ---
  ['<a href="/x">Texto</a>', 0, 'link/named/text', 'texto do proprio link'],
  ['<a href="/contato">Fale com a gente — é rápido 😀</a>', 0, 'link/named/text', 'texto com acento e emoji'],
  ['<button type="button">Enviar</button>', 0, 'button/named/text', 'texto do proprio botao'],
  ['<a href="/x"> <b>forte</b> </a>', 0, 'link/named/text', 'texto num elemento de dentro'],
  ['<a href="/x"><span><em>fundo</em></span></a>', 0, 'link/named/text', 'texto dois niveis abaixo'],
  ['<a href="/x"><svg aria-hidden="true"><path/></svg> Ver tudo</a>', 0, 'link/named/text', 'icone enfeite e texto solto'],
  ['<a href="/x"><i class="fa"></i>Ver<i class="fb"></i></a>', 0, 'link/named/text', 'texto solto entre dois icones'],
  ['<a href="/x">&nbsp;</a>', 0, 'link/named/text', 'entidade conta como texto: o editor nao acusa'],
  ['<a href="/x" aria-label="Início"><i class="fa"></i></a>', 0, 'link/named/aria-label', 'rotulo'],
  ['<button aria-label=Fechar><i></i></button>', 0, 'button/named/aria-label', 'rotulo sem aspas'],
  ['<a href="/x" aria-labelledby="t"><i></i></a>', 0, 'link/named/aria-labelledby', 'nome em outro elemento'],
  ['<a href="/x" aria-labelledby="t" aria-label="Casa">Texto</a>', 0, 'link/named/aria-labelledby', 'aria-labelledby vem antes do rotulo'],
  ['<a href="/x" aria-label="Casa">Texto</a>', 0, 'link/named/aria-label', 'o rotulo vem antes do texto'],
  ['<a href="/x"><img src="a.png" alt="Foto"></a>', 0, 'link/named/image', 'imagem com alt'],
  ['<a href="/x"><svg role="img" aria-label="Seta"><path/></svg></a>', 0, 'link/named/image', 'desenho com rotulo'],
  ['<a href="/x"><svg><title>Seta</title><path/></svg></a>', 0, 'link/named/image', 'desenho com <title>'],
  ['<a href="/x"><img src="a.png"><span>Legenda</span></a>', 0, 'link/named/text', 'imagem pendente, mas ha texto: o link tem nome'],
  ['<a href="/x" title="Dica"><i></i></a>', 0, 'link/named/title', 'so a dica'],
  ['<button title="Fechar"><i></i></button>', 0, 'button/named/title', 'botao so com a dica'],
  ['<a href="/x" title="Dica">Texto</a>', 0, 'link/named/text', 'o texto vem antes da dica'],
  ['<a href="/x"><span aria-label="Casa"></span></a>', 0, 'link/named/text', 'rotulo num elemento de dentro'],
  ['<a href>x</a>', 0, 'link/named/text', 'href sem valor ainda e link'],
  // --- pendente ---
  ['<a href="/x"></a>', 0, 'link/missing/', 'link vazio'],
  ['<a href="/x">   \n </a>', 0, 'link/missing/', 'so espaco'],
  ['<button></button>', 0, 'button/missing/', 'botao vazio'],
  ['<a href="/x"><i class="fa fa-home"></i></a>', 0, 'link/missing/', 'so icone de fonte'],
  ['<button type="button"><span class="icon"></span></button>', 0, 'button/missing/', 'botao so com icone'],
  ['<a href="/x" aria-label=""><i></i></a>', 0, 'link/missing/', 'rotulo vazio nao e nome'],
  ['<a href="/x" aria-label="  "></a>', 0, 'link/missing/', 'rotulo so com espaco'],
  ['<a href="/x" aria-label><i></i></a>', 0, 'link/missing/', 'rotulo sem valor'],
  ['<a href="/x" aria-labelledby=""><i></i></a>', 0, 'link/missing/', 'aria-labelledby vazio'],
  ['<a href="/x" title=""><i></i></a>', 0, 'link/missing/', 'dica vazia'],
  ['<a href="/x"><svg aria-hidden="true"><path/></svg></a>', 0, 'link/missing/', 'so um desenho enfeite'],
  ['<a href="/x"><img src="a.png" alt=""></a>', 0, 'link/missing/', 'so uma imagem enfeite'],
  ['<a href="/x"><img src="a.png" alt="Foto" aria-hidden="true"></a>', 0, 'link/missing/', 'imagem com alt, mas escondida'],
  ['<a href="/x"><img src="a.png" alt="Foto" role="presentation"></a>', 0, 'link/missing/', 'imagem com alt, mas de apresentacao'],
  ['<a href="/x"><span aria-hidden="true">×</span></a>', 0, 'link/missing/', 'o texto esta escondido do leitor de tela'],
  ['<a href="/x"><span aria-hidden="true"><b>x</b> y</span></a>', 0, 'link/missing/', 'tudo dentro do trecho escondido'],
  ['<a href="/x"><span aria-hidden="true"><span>a</span></span><span></span></a>', 0, 'link/missing/', 'o trecho escondido fecha na tag certa'],
  ['<a href="/x"><br></a>', 0, 'link/missing/', 'so uma quebra de linha'],
  ['<a href="/x"><!-- nada --></a>', 0, 'link/missing/', 'so um comentario'],
  ['<a href="/x"><!-- nada --><i></i>{# nada #}</a>', 0, 'link/missing/', 'comentarios e um icone'],
  ['<a href="/x"><span aria-label=""></span></a>', 0, 'link/missing/', 'rotulo vazio num elemento de dentro'],
  ['<div aria-hidden="true"><div></div></div><a href="/x"><i></i></a>', 2, 'link/missing/', 'o trecho escondido ja fechou'],
  ['<div aria-hidden="false"><a href="/x"><i></i></a></div>', 1, 'link/missing/', 'aria-hidden="false" nao esconde'],
  ['<a href="/x" aria-hidden="false"><i></i></a>', 0, 'link/missing/', 'aria-hidden="false" na propria tag nao esconde'],
  ['<button aria-hidden="false">Ok</button>', 0, 'button/named/text', 'aria-hidden="false" na propria tag: o texto continua valendo'],
  ['<p>a</p><a href="/x">Um</a><a href="/y"><i></i></a>', 2, 'link/missing/', 'o texto do vizinho nao vale'],
  // --- o nome depende de uma imagem ou de um desenho pendente ---
  ['<a href="/x"><img src="a.png"></a>', 0, 'link/waiting/image', 'imagem sem alt dentro'],
  ['<a href="/x"><svg viewBox="0 0 1 1"><path/></svg></a>', 0, 'link/waiting/image', 'desenho sem descricao dentro'],
  ['<button><svg><path/></svg></button>', 0, 'button/waiting/image', 'botao com desenho sem descricao'],
  ['<a href="/x"><svg><a href="#i"><text>oi</text></a></svg></a>', 0, 'link/waiting/image', 'o <a> de dentro do desenho nao e outro link'],
  ['<a href="/x" title="Dica"><img src="a.png"></a>', 0, 'link/named/title', 'imagem pendente, mas ha a dica'],
  // --- vem de dado do site ---
  ['<a href="/x">{{ nome }}</a>', 0, 'link/data/content', 'texto vindo de dado'],
  ['<a href="/x">{{ a }} {{ b }}</a>', 0, 'link/data/content', 'dois dados'],
  ['<a href="/x">{# so comentario #}</a>', 0, 'link/data/content', 'comentario Twig sozinho: o editor nao acusa'],
  ['<a href="/x">{% if a %}<i></i>{% endif %}</a>', 0, 'link/data/content', 'bloco Twig dentro'],
  ['<a href="/x"><span>{{ t }}</span></a>', 0, 'link/data/content', 'dado num elemento de dentro'],
  ['<a href="/x"><img src="a.png" alt="{{ t }}"></a>', 0, 'link/data/content', 'alt vindo de dado'],
  ['<a href="/x"><span aria-label="{{ t }}"></span></a>', 0, 'link/data/content', 'rotulo de dentro vindo de dado'],
  ['<a href="/x" aria-label="{{ t }}"><i></i></a>', 0, 'link/data/aria-label', 'rotulo vindo de dado'],
  ['<a href="/x" aria-labelledby="{{ t }}"><i></i></a>', 0, 'link/data/aria-labelledby', 'aria-labelledby vindo de dado'],
  ['<a href="/x" aria-hidden="{{ h }}">x</a>', 0, 'link/data/aria-hidden', 'aria-hidden vindo de dado'],
  ['<a href="/x" title="{{ t }}"><i></i></a>', 0, 'link/data/title', 'dica vinda de dado'],
  ['<a href="{{ home }}"><i></i></a>', 0, 'link/missing/', 'destino vindo de dado nao da nome'],
  // --- escondido do leitor de tela ---
  ['<a href="/x" aria-hidden="true">x</a>', 0, 'link/hidden/aria-hidden', 'escondido na propria tag'],
  ['<button aria-hidden=true></button>', 0, 'button/hidden/aria-hidden', 'sem aspas'],
  ['<div aria-hidden="true"><a href="/x"><i></i></a></div>', 1, 'link/hidden/inside', 'escondido pelo elemento em volta'],
  ['<div aria-hidden="true"><div><p>a</p></div><button></button></div>', 3, 'button/hidden/inside', 'a tag de mesmo nome de dentro nao fecha o trecho'],
  ['<li aria-hidden="true"><a href="/x"><i></i></a></li>', 1, 'link/hidden/inside', 'tag de fechamento opcional tambem esconde'],
  // --- o editor nao afirma ---
  ['<a href="/x"><i></i>', 0, 'link/unknown/', 'o link nao fecha neste trecho'],
  ['<a href="/x"><button><i></i></a>', 1, 'button/unknown/', 'o botao ficou sem fechamento'],
  ['<a href="/x"><p aria-hidden="true">x</p>y</a>', 0, 'link/unknown/', 'trecho escondido de fim incerto dentro do link'],
  // --- nao e link ---
  ['<a name="topo"></a>', 0, '/none/', '<a> sem href'],
  ['<a>texto</a>', 0, '/none/', '<a> sem atributo nenhum'],
  ['<p>texto</p>', 0, '/none/', 'outra tag'],
];

/* Trechos em que o leitor daqui e o do servidor sao comparados sem julgar nada. */
const SHAPES = [
  '<nav><a href="/">Início</a><a href="/b"><svg aria-hidden="true"><path/></svg></a><button type="button"><span></span></button></nav>',
  '<a href="/x"><span>a</span><img src="b.png"><span aria-hidden="true">c<i>d</i></span>e<svg><title>t</title></svg></a><a href="/y">{{ z }}</a>',
  '<div aria-hidden="true"><a href="/x">x</a><div><button>y</button></div></div><button title="t">z</button>',
  '<a href="/x"><button>dentro</button> fora</a><a href="/y"><i></i>',
  '<ul><li aria-hidden="true"><a href="/x"><i></i></a></li><li><a href="/y"><i></i></a></li></ul>',
];

/** O que os dois leitores tem de ter igual: tag, tipo, posicoes, atributos (nome, tipo, posicoes) e o nono campo. */
function shape(map) {
  return map.items.map(it => [it[0], it[1], it[2], it[3], it[4], it[5],
    Array.isArray(it[7]) ? it[7].map(a => a.slice(0, 6)).sort((p, q) => p[4] - q[4]) : null, it.length > 8 ? it[8] : null]);
}

const mode = process.argv[2] || '';
if (mode === '--dump') {
  process.stdout.write(JSON.stringify(CASES.map(c => c[0]).concat(SHAPES)));
  process.exit(0);
}
if (mode === '--count') {
  const out = {};
  for (const t of JSON.parse(fs.readFileSync(process.argv[3], 'utf8'))) {
    const row = out[t.pack] = out[t.pack] || { link: 0, button: 0, named: 0, data: 0, hidden: 0, waiting: 0, missing: 0, unknown: 0 };
    t.map.items.forEach(it => { it[7] = A.clean(it[7]); });
    t.map.items.forEach((it, n) => {
      const r = A.a11y(t.src, t.map, n);
      if (r.kind !== 'link' && r.kind !== 'button') return;
      row[r.kind]++;
      row[r.state]++;
    });
  }
  process.stdout.write(JSON.stringify(out));
  process.exit(0);
}
if (mode === '--maps') {
  const served = JSON.parse(fs.readFileSync(process.argv[3], 'utf8'));
  same(served.length, CASES.length + SHAPES.length, 'um mapa do servidor por caso');
  CASES.forEach((c, i) => {
    const map = served[i];
    map.items.forEach(it => { it[7] = A.clean(it[7]); });
    same(state(c[0], c[1], map), c[2], 'com o mapa do servidor: ' + c[3] + ' — ' + c[0]);
    same(shape(map), shape(mapOf(c[0])), 'os dois leitores concordam: ' + c[0]);
  });
  SHAPES.forEach((src, i) => {
    const map = served[CASES.length + i];
    map.items.forEach(it => { it[7] = A.clean(it[7]); });
    same(shape(map), shape(mapOf(src)), 'os dois leitores concordam: ' + src);
  });
  console.log('Kaz element name checker (mapas do servidor): ' + checks + ' PASS');
  process.exit(0);
}

// --- 1. situacao de cada caso ----------------------------------------------------
for (const c of CASES) same(state(c[0], c[1]), c[2], c[3] + ' — ' + c[0]);
check(CASES.length >= 70, 'a tabela de casos nao encolheu');
same(A.HOLDER_TAGS, ['a', 'button'], 'so <a> e <button>');

// --- 2. o leitor daqui monta o resumo como o servidor descreve --------------------
{
  const m = mapOf(SHAPES[1]);
  same(m.items.map(it => it[0]), ['a', 'span', 'img', 'span', 'i', 'svg', 'a'], 'itens');
  same(m.items[0][8], [TEXT, 1, 2, 5], 'texto solto; o <span> escondido e o que ha nele ficam fora; imagem e desenho entram');
  same(m.items[5][8], 1, 'o <svg> continua com o nono campo dele');
  same(m.items[6][8], [0], 'link-folha: so as marcas');
  same(m.items[6][1], 'variable', 'link-folha com um dado');
  same(mapOf(SHAPES[2]).items.filter(it => Array.isArray(it[8])).map(it => it[8]), [[HIDDEN], [HIDDEN], [0]], 'dentro do trecho escondido; depois dele, nao');
  same(mapOf(SHAPES[3]).items.filter(it => Array.isArray(it[8])).map(it => it[8]), [[TEXT, 1], [0], [UNSURE, 3]], 'link em volta de botao; link que nao fecha');
}

// --- 3. o que o servidor manda e conferido ---------------------------------------
{
  const src = '<a href="/x"><i></i></a><p>z</p>';
  const forged = function (inner) { const m = mapOf(src); if (inner === undefined) m.items[0].length = 8; else m.items[0][8] = inner; return state(src, 0, m); };
  same(forged([0, 1]), 'link/missing/', 'resumo bem formado');
  same(forged(undefined), 'link/unknown/', 'mapa sem o resumo (servidor de antes do KZ-09)');
  for (const bad of [null, 0, 'x', [], ['0'], [16], [-1], [0.5], [0, 0], [0, 3], [0, 1.5], [0, '1'], [0, 2, 1], [0, 1, 1], [0, -1]]) {
    same(forged(bad), 'link/unknown/', 'resumo fora do formato: ' + JSON.stringify(bad));
  }
  same(forged([4, 1]), 'link/hidden/inside', 'marca de escondido');
  same(forged([8, 1]), 'link/unknown/', 'marca de fim incerto');
  same(forged([12, 1]), 'link/hidden/inside', 'escondido vale antes de incerto');
  same(forged([1]), 'link/named/text', 'marca de texto solto');
  same(forged([2]), 'link/data/content', 'marca de Twig solto');
  same(forged([3]), 'link/named/text', 'texto certo vale mais que dado');
  // tag que o servidor nao soube ler
  let m = mapOf(src); m.items[0][7] = null;
  same(state(src, 0, m), 'link/unknown/', 'atributos do link ilegiveis');
  m = mapOf(src); m.items[1][7] = null;
  same(state(src, 0, m), 'link/unknown/', 'elemento de dentro ilegivel: pode ter um rotulo');
  m = mapOf('<a href="/x"><i>x</i></a>'); m.items[1][7] = null;
  same(state('<a href="/x"><i>x</i></a>', 0, m), 'link/named/text', 'elemento de dentro ilegivel, mas com texto');
  m = mapOf('<a href="/x"><img src="a.png"></a>'); m.items[1][7] = null;
  same(state('<a href="/x"><img src="a.png"></a>', 0, m), 'link/unknown/', 'imagem de dentro ilegivel');
  m = mapOf('<a href="/x"><img src="a.png"><b>x</b></a>'); m.items[1][7] = null;
  same(state('<a href="/x"><img src="a.png"><b>x</b></a>', 0, m), 'link/named/text', 'imagem ilegivel, mas ha texto');
  // mapa defasado
  same(state('<p>' + src, 0, mapOf(src)), 'link/unknown/', 'a tag saiu do lugar');
  same(state('<a href="/x" aria-label="Casa"><i></i></a>'.replace('"Casa"', '\'Cas\''), 0, mapOf('<a href="/x" aria-label="Casa"><i></i></a>')), 'link/unknown/', 'aspas trocadas no rotulo');
  same(state('<a href="/x" title=\'Dic\'><i></i></a>', 0, mapOf('<a href="/x" title="Dica"><i></i></a>')), 'link/unknown/', 'aspas trocadas na dica');
  // folha com intervalo que nao cabe na fonte nao vira nome
  m = mapOf('<a href="/x">Texto</a>'); m.items[0][5] = 999;
  same(state('<a href="/x">Texto</a>', 0, m), 'link/missing/', 'intervalo do texto fora da fonte');
  m = mapOf('<a href="/x">Texto</a>'); m.items[0][4] = -1;
  same(state('<a href="/x">Texto</a>', 0, m), 'link/missing/', 'intervalo do texto ausente');
  // o texto foi apagado pelo painel: o tipo vira 'empty' e o intervalo encolhe
  m = mapOf('<a href="/x">Texto</a><p>z</p>'); m.items[0][1] = 'empty'; m.items[0][5] = m.items[0][4];
  same(state('<a href="/x"></a><p>z</p>', 0, m), 'link/missing/', 'texto apagado no painel');
  m = mapOf('<a href="/x"><span>Texto</span></a>'); m.items[1][5] = m.items[1][4];
  same(state('<a href="/x"><span></span></a>', 0, m), 'link/missing/', 'texto de dentro apagado (o tipo ainda diz fixed)');
  // o texto foi trocado por espacos no painel: o tipo ainda diz 'fixed', e espaco nao e nome
  m = mapOf('<a href="/x">Texto</a>');
  same(state('<a href="/x">     </a>', 0, m), 'link/missing/', 'texto trocado por espacos no painel');
  m = mapOf('<a href="/x"><span>Texto</span></a>');
  same(state('<a href="/x"><span> \t\n  </span></a>', 0, m), 'link/missing/', 'texto de dentro trocado por espacos no painel');
}

// --- 4. pendencias de um trecho ---------------------------------------------------
{
  const src = '<header><a href="/"><svg class="logo"><path/></svg></a><a href="/a"><i class="fa"></i></a><a href="/b">Sobre</a>' +
    '<button type="button"><span class="x"></span></button><img src="c.png"><a href="/d"><img src="d.png" alt=""></a><a href="/e">{{ t }}</a><a href="/f"><i></i></header>';
  const map = mapOf(src);
  same(map.items.map(it => it[0]), ['header', 'a', 'svg', 'a', 'i', 'a', 'button', 'span', 'img', 'a', 'img', 'a', 'a', 'i'], 'itens do caso');
  same(A.pending(src, map), { missing: [2, 3, 6, 8, 9], unknown: 1 }, 'desenho, link, botao, imagem e link, na ordem do codigo; o link que nao fecha fica por conferir');
  same(A.pending('<p>' + src, map), { missing: [], unknown: 10 }, 'mapa defasado: nada e dado como pendente');
  same(A.pending('<a>x</a><a name="y"></a>', mapOf('<a>x</a><a name="y"></a>')), { missing: [], unknown: 0 }, '<a> sem href nao entra na conta');
}

// --- 5. a lista acompanha o que o painel escreve ----------------------------------
{
  let src = '<nav><a href="/x" class="b"><svg class="i"><path/></svg></a><p>z</p></nav>';
  const map = mapOf(src), first = src;
  same(A.pending(src, map).missing, [2], 'de inicio a pendencia e o desenho');
  same(state(src, 1, map), 'link/waiting/image', 'e o link espera por ele');
  src = A.decorate(src, map, 2, true).src;
  same(src, first.replace('<svg', '<svg aria-hidden="true"'), 'desenho marcado como enfeite');
  same(A.pending(src, map).missing, [1], 'agora quem esta sem nome e o link');
  same(state(src, 1, map), 'link/missing/', 'link pendente');
  src = A.putAttr(src, map, 1, 'aria-label', 'Página inicial').src;
  same(src, first.replace('<svg', '<svg aria-hidden="true"').replace('<a href', '<a aria-label="Página inicial" href'), 'rotulo criado logo depois do nome da tag');
  same(A.pending(src, map), { missing: [], unknown: 0 }, 'nada pendente');
  same(state(src, 1, map), 'link/named/aria-label', 'link com nome');
  src = A.putAttr(src, map, 1, 'aria-label', '').src;
  same(A.pending(src, map).missing, [1], 'rotulo esvaziado: pendente de novo');
  src = A.dropLabel(src, map, 1).src;
  same(A.pending(src, map).missing, [1], 'rotulo retirado: continua pendente');
  src = A.decorate(src, map, 2, false).src;
  same(src, first, 'tudo desfeito: a fonte original, caractere por caractere');
  same(A.pending(src, map).missing, [2], 'e a pendencia volta a ser o desenho');
  src = A.putAttr(src, map, 2, 'aria-label', 'Casa').src;
  same(state(src, 1, map), 'link/named/image', 'desenho descrito da nome ao link');
  same(A.pending(src, map), { missing: [], unknown: 0 }, 'um conserto resolve os dois');
}
{
  let src = '<a href="/x"><img src="a.png"></a>';
  const map = mapOf(src);
  src = A.putAttr(src, map, 1, 'alt', 'Foto').src;
  same(state(src, 0, map), 'link/named/image', 'alt escrito da nome ao link');
  src = A.putAttr(src, map, 1, 'alt', '').src;
  same(state(src, 0, map), 'link/missing/', 'alt vazio: imagem enfeite, link sem nome');
  same(A.pending(src, map).missing, [0], 'so o link fica na lista');
}
{
  let src = '<button type="button" class="x"><span class="icon"></span></button>';
  const map = mapOf(src);
  src = A.putAttr(src, map, 0, 'aria-label', 'Fechar "tudo" <já>').src;
  same(src, '<button aria-label="Fechar &quot;tudo&quot; &lt;já&gt;" type="button" class="x"><span class="icon"></span></button>', 'rotulo do botao, com o que foi digitado virando texto');
  same(state(src, 0, map), 'button/named/aria-label', 'botao com nome');
}

// --- 6. nada novo e escrito -------------------------------------------------------
{
  const src = '<a href="/x" title="Dica"><i></i></a><button></button>', map = mapOf(src), before = JSON.stringify(map);
  same(A.decorate(src, map, 0, true), { error: 'locked' }, 'link nao e marcado como enfeite');
  same(A.decorate(src, map, 2, true), { error: 'locked' }, 'botao nao e marcado como enfeite');
  same(A.setValue(src, map, 0, 'title', 'x'), { error: 'locked' }, 'a dica so e lida');
  same(A.putAttr(src, map, 0, 'title', 'x'), { error: 'locked' }, 'a dica nao e criada');
  check(JSON.stringify(map) === before, 'mapa intacto');
  same(A.clean([['title', 'fixed', 5, 9, 1, 10, '']]).length, 1, 'title e aceito na lista de atributos');
  check(A.clean([['name', 'fixed', 5, 9, 1, 10, '']]) === null, 'outro nome continua fora da lista');
}

console.log('Kaz element name checker: ' + checks + ' PASS');
