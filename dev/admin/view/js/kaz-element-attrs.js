/**
 * @package   ReamurCMS
 * @license   https://opensource.org/licenses/GPL-3.0
 *
 * KZ-05 (ADR 0098) — atributos de um elemento do tema: destino de link
 * (`href`), abrir em nova aba (`target` + `rel`), texto alternativo de imagem
 * (`alt`) e rotulo para leitor de tela (`aria-label`).
 *
 * Este arquivo so tem as REGRAS, sem tela: recebe a fonte Twig de um trecho e
 * o mapa de elementos que o servidor mandou (ThemeSourceMap::describe) e
 * devolve a fonte nova. O painel fica em kaz-canvas.js.
 *
 * Regras:
 *  - So o valor entre as aspas do atributo e trocado; o resto da fonte fica
 *    igual, caractere por caractere.
 *  - O que e digitado nunca vira tag, atributo novo nem Twig: `&`, `<`, `>`,
 *    `{` e as duas aspas viram entidades, e os padroes que a validacao do tema
 *    recusa (`on...=`, `srcdoc=`, `formaction=`, `|raw`, `javascript:`) sao
 *    quebrados com entidades.
 *  - Destino de link: so caminho do site (`/...`), ancora (`#...`), consulta
 *    (`?...`), `https://`, `http://`, `mailto:` e `tel:`. O resto e recusado e
 *    a fonte nao muda.
 *  - Nova aba e a unica escrita que acrescenta ou retira atributos, e so
 *    `target` e `rel`.
 *  - Nada aqui e barreira de seguranca: quem edita o tema pode escrever o
 *    codigo a mao. A barreira continua sendo a validacao do servidor ao salvar.
 *
 * KZ-06 (ADR 0099) — cor do texto, cor de fundo e tamanho do texto de um
 * elemento, escritos no atributo `style` da propria tag.
 *
 * Regras:
 *  - So tres propriedades: `color`, `background-color` e `font-size`. As
 *    outras declaracoes do `style` ficam iguais, caractere por caractere.
 *  - Valores de gramatica fechada: cor `#rgb` ou `#rrggbb`, cor do tema
 *    `var(--nome)`, tamanho `Npx` (de 6 a 400). Nada digitado livremente entra.
 *  - Tag sem `style`: o atributo e escrito logo depois do nome da tag. Valor
 *    apagado: a declaracao sai; se nao sobrar nenhuma, o atributo sai.
 *  - `style` com Twig, com aspas, entidades, comentario ou chaves dentro, ou
 *    com a mesma propriedade duas vezes: o editor nao escreve.
 *
 * KZ-07 (ADR 0100) — imagem sem texto alternativo e desenho (<svg>) sem
 * descricao: dizer quais estao pendentes e criar o que falta.
 *
 * Regras:
 *  - Pendente e a <img> sem `alt` e o <svg> sem nome (`aria-label`,
 *    `aria-labelledby` ou <title> dentro) e sem marca de enfeite
 *    (`aria-hidden="true"`, `role="presentation"` ou `role="none"`).
 *  - Criar: `alt="..."` na imagem; `aria-label="..."` no desenho (com
 *    `role="img"` quando a tag nao tem `role`) e nos elementos em que o rotulo
 *    e anunciado (link, botao, blocos de navegacao e secao). Em outras tags o
 *    editor nao cria rotulo.
 *  - Enfeite: `alt=""` na imagem, `aria-hidden="true"` no desenho. So quando
 *    o elemento esta pendente: uma descricao que ja existe nunca e apagada
 *    por esse caminho.
 *  - O atributo novo entra logo depois do nome da tag. Retirar devolve a
 *    fonte ao que era, caractere por caractere.
 *
 * KZ-08 (ADR 0101) — espacamento e alinhamento de um elemento, tambem no
 * atributo `style` da propria tag.
 *
 * Regras:
 *  - Nove propriedades: os quatro lados de `margin` e de `padding`, cada um
 *    em `Npx` (de 0 a 400), e `text-align` (`left`, `center`, `right` ou
 *    `justify`). Valor negativo, `auto` ou outra unidade so pelo codigo.
 *  - O editor nunca escreve nem reescreve a forma curta (`margin: 0 auto`):
 *    o lado pedido entra no fim do `style`, onde vale por cima dela.
 *  - Um lado escrito ANTES de uma forma curta do mesmo grupo e anulado por
 *    ela: o editor nao mexe nesse lado.
 *  - Alinhamento so nas tags em que cor e tamanho se aplicam, menos <svg> e
 *    <hr>; espacamento tambem em imagem, video e afins.
 *
 * KZ-09 (ADR 0102) — link e botao sem nome: o <a href> e o <button> em que o
 * leitor de tela nao tem o que anunciar.
 *
 * Regras:
 *  - O nome vem, nesta ordem, de `aria-labelledby`, de `aria-label`, do que
 *    ha dentro (texto, imagem com `alt`, desenho com nome) e de `title`.
 *  - Pendente e so o que o editor consegue afirmar: nada disso existe e nada
 *    depende de dado do site. Twig dentro do elemento, tag ilegivel ou
 *    fechamento em outro trecho: o editor nao acusa.
 *  - Elemento escondido do leitor de tela (`aria-hidden="true"` nele ou em
 *    volta dele) nao e pendente; o que esta escondido dentro dele nao da nome.
 *  - Imagem ou desenho pendente dentro dele: quem aparece na lista e a imagem
 *    (uma pendencia, um conserto). Marcada como enfeite, o link passa a ser o
 *    pendente.
 *  - Nada novo e escrito: o rotulo e o `aria-label` que o KZ-07 ja cria.
 *
 * Testes: tests/check_kaz_element_attrs.js, tests/check_kaz_element_style.js,
 * tests/check_kaz_element_a11y.js, tests/check_kaz_element_space.js e
 * tests/check_kaz_element_name.js (Node, sem navegador).
 */
(function (root) {
  'use strict';

  var LINK_MAX = 2000;     // caracteres de um destino de link
  var LABEL_MAX = 300;     // caracteres de alt e aria-label
  var SOURCE_MAX = 131072; // o mesmo teto do formulario do tema
  var WS = ' \t\n\r\f';
  var NAMES = ['href', 'target', 'rel', 'alt', 'aria-label', 'style', 'aria-hidden', 'aria-labelledby', 'role', 'title'];
  var KINDS = ['fixed', 'variable', 'bare'];
  /* Que tag pode ter cada atributo editavel ('' = qualquer uma). */
  var EDITABLE = { href: 'a', alt: 'img', 'aria-label': '' };

  function trim(text) {
    var a = 0, b = text.length;
    while (a < b && WS.indexOf(text.charAt(a)) !== -1) a++;
    while (b > a && WS.indexOf(text.charAt(b - 1)) !== -1) b--;
    return text.slice(a, b);
  }

  /**
   * Lista de atributos vinda do servidor, conferida. Qualquer coisa fora do
   * formato fecha a tag inteira (null): melhor nao oferecer campo do que
   * escrever no lugar errado.
   */
  function clean(list) {
    if (!Array.isArray(list)) return null;
    var out = [], seen = [];
    for (var i = 0; i < list.length; i++) {
      var a = list[i];
      if (!Array.isArray(a) || a.length < 7) return null;
      if (typeof a[0] !== 'string' || NAMES.indexOf(a[0]) === -1 || seen.indexOf(a[0]) !== -1) return null;
      if (typeof a[1] !== 'string' || KINDS.indexOf(a[1]) === -1 || typeof a[6] !== 'string') return null;
      for (var k = 2; k <= 5; k++) {
        if (typeof a[k] !== 'number' || !isFinite(a[k]) || a[k] < 0 || Math.floor(a[k]) !== a[k]) return null;
      }
      /* nome ... valor ... fim, nessa ordem */
      if (a[4] > a[2] || a[2] > a[3] || a[3] > a[5]) return null;
      seen.push(a[0]);
      out.push([a[0], a[1], a[2], a[3], a[4], a[5], a[6]]);
    }
    return out;
  }

  /** Atributo `name` de um item do mapa, ou null. */
  function find(item, name) {
    var list = item && Array.isArray(item[7]) ? item[7] : null;
    if (!list) return null;
    for (var i = 0; i < list.length; i++) if (list[i][0] === name) return list[i];
    return null;
  }

  /** O destino digitado e de um dos tipos aceitos? */
  function linkOk(value) {
    var v = String(value);
    if (v === '' || v.length > LINK_MAX) return false;
    /* Controle e barra invertida: o navegador le `/\site` como outro site. */
    if (/[\u0000-\u001F\u007F\\]/.test(v)) return false;
    if (/^mailto:/i.test(v)) return /^mailto:[^\s@<>"']+@[^\s@<>"'?]+(\?[^<>"]*)?$/i.test(v);
    if (/^tel:/i.test(v)) return /^tel:\+?[0-9][0-9 ().-]{2,30}$/i.test(v);
    if (/\s/.test(v)) return false;
    var first = v.charAt(0);
    if (first === '#' || first === '?') return true;
    /* `//site` troca de site sem dizer o protocolo: peca https:// por extenso. */
    if (first === '/') return v.charAt(1) !== '/';
    var m = /^https?:\/\/([^\/?#]+)/i.exec(v);
    /* `usuario@site` antes da primeira barra esconde o site verdadeiro. */
    return !!m && m[1].indexOf('@') === -1 && !/^[.:]/.test(m[1]);
  }

  /**
   * Texto digitado -> texto seguro para ficar entre aspas no codigo. Nunca
   * fecha o atributo, nunca vira Twig e nao esbarra na validacao do tema.
   */
  function encode(value) {
    var v = String(value).replace(/[\u0000-\u001F\u007F]/g, '');
    v = v.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/\{/g, '&#123;');
    if (/\b(on[a-z]+|srcdoc|formaction)\s*=/i.test(v)) v = v.replace(/=/g, '&#61;');
    if (/\|\s*raw\b/i.test(v)) v = v.replace(/\|/g, '&#124;');
    return v.replace(/(javascript\s*):/gi, '$1&#58;');
  }

  /**
   * A fonte tem mesmo este atributo neste lugar? Confere o nome, a aspa que
   * abre e a que fecha. Protege contra um mapa que nao e desta fonte.
   */
  function matches(src, a) {
    if (src.slice(a[4], a[4] + a[0].length).toLowerCase() !== a[0]) return false;
    var q = src.charAt(a[2] - 1);
    if (q !== '"' && q !== "'") return false;
    return src.charAt(a[3]) === q && a[5] === a[3] + 1 && src.slice(a[2], a[3]).indexOf(q) === -1;
  }

  /**
   * Depois de uma escrita, desloca as posicoes do mapa que ficam de `at` em
   * diante. So os itens de `n` em diante podem ter posicao ali. `skip` e o
   * atributo que quem chamou ajusta por conta propria.
   */
  function shift(map, n, at, delta, skip) {
    for (var j = n; j < map.items.length; j++) {
      var it = map.items[j], k;
      for (k = 2; k <= 5; k++) if (it[k] >= at) it[k] += delta;
      var list = Array.isArray(it[7]) ? it[7] : [];
      for (var i = 0; i < list.length; i++) {
        if (list[i] === skip) continue;
        for (k = 2; k <= 5; k++) if (list[i][k] >= at) list[i][k] += delta;
      }
    }
  }

  /**
   * KZ-06: o servidor mede o teto em bytes UTF-8 (`strlen`). Contar caracteres
   * aqui deixava passar uma fonte com acentos que o servidor recusava em
   * seguida. Meio par substituto conta como U+FFFD (3 bytes).
   *
   * KZ-22 (ADR 0115): o servidor guarda a fonte com quebra de linha `\n` e
   * mede depois disso. Cada quebra de linha (LF, CR LF ou CR solto) vale UM
   * byte; antes valia dois, porque a fonte era medida como o formulario chega.
   */
  function tooLong(s) {
    var n = s.length, i, c, bytes = 0;
    if (n * 3 <= SOURCE_MAX) return false;
    for (i = 0; i < n; i++) {
      c = s.charCodeAt(i);
      if (c === 13) { bytes += 1; if (i + 1 < n && s.charCodeAt(i + 1) === 10) i++; }
      else if (c < 0x80) bytes += 1;
      else if (c < 0x800) bytes += 2;
      else if (c >= 0xD800 && c <= 0xDBFF && i + 1 < n && s.charCodeAt(i + 1) >= 0xDC00 && s.charCodeAt(i + 1) <= 0xDFFF) { bytes += 4; i++; }
      else bytes += 3;
      if (bytes > SOURCE_MAX) return true;
    }
    return false;
  }

  /** Troca o valor (ja codificado) de um atributo. Fonte nova, ou null se passar do teto. */
  function replaceValue(src, map, n, a, raw) {
    var next = src.slice(0, a[2]) + raw + src.slice(a[3]);
    if (tooLong(next)) return null;
    var delta = raw.length - (a[3] - a[2]);
    shift(map, n, a[3], delta, a);
    a[3] += delta;
    a[5] += delta;
    return next;
  }

  /** Escreve ` nome="valor"` logo depois do atributo `after`. Devolve [fonte, atributo novo]. */
  function insertAfter(src, map, n, after, name, raw) {
    var at = after[5], text = ' ' + name + '="' + raw + '"';
    var next = src.slice(0, at) + text + src.slice(at);
    if (tooLong(next)) return null;
    shift(map, n, at, text.length, after);
    var v0 = at + name.length + 3;
    var made = [name, 'fixed', v0, v0 + raw.length, at + 1, v0 + raw.length + 1, ''];
    map.items[n][7].push(made);
    return [next, made];
  }

  /** Retira um atributo inteiro, com o espaco que o separa do anterior. */
  function removeAttr(src, map, n, a) {
    var from = a[4];
    while (from > 0 && WS.indexOf(src.charAt(from - 1)) !== -1) from--;
    if (from === a[4]) return null;
    /* Colado no atributo seguinte (`a="x"b="y"`): tirar este juntaria o vizinho ao que vem antes. */
    if (a[5] >= src.length || (WS + '/>').indexOf(src.charAt(a[5])) === -1) return null;
    var list = map.items[n][7];
    list.splice(list.indexOf(a), 1);
    shift(map, n, a[5], from - a[5], null);
    return src.slice(0, from) + src.slice(a[5]);
  }

  /**
   * Troca o valor de `href`, `alt` ou `aria-label` do elemento `n`.
   * Devolve { src } com a fonte nova (o mapa ja acompanha), ou { error }:
   * `locked` (nao ha valor fixo para trocar), `stale` (o mapa nao e desta
   * fonte), `link` (destino recusado) ou `long` (a fonte passaria do teto).
   * Com erro, nem a fonte nem o mapa mudam.
   */
  function setValue(src, map, n, name, value) {
    var it = map && Array.isArray(map.items) ? map.items[n] : null, a = find(it, name);
    if (!a || a[1] !== 'fixed' || !Object.prototype.hasOwnProperty.call(EDITABLE, name)) return { error: 'locked' };
    if (EDITABLE[name] !== '' && it[0] !== EDITABLE[name]) return { error: 'locked' };
    src = String(src);
    if (!matches(src, a)) return { error: 'stale' };
    var v = trim(String(value));
    if (name === 'href') {
      if (!linkOk(v)) return { error: 'link' };
    } else {
      v = v.slice(0, LABEL_MAX);
    }
    var next = replaceValue(src, map, n, a, encode(v));
    return next === null ? { error: 'long' } : { src: next };
  }

  /**
   * Situacao de "abrir em nova aba" do link `n`: `can` diz se o editor pode
   * ligar e desligar; `on` diz se a tag ja tem `target="_blank"`.
   */
  function newTab(src, map, n) {
    var it = map && Array.isArray(map.items) ? map.items[n] : null, out = { can: false, on: false };
    if (!it || it[0] !== 'a' || !Array.isArray(it[7])) return out;
    var href = find(it, 'href'), target = find(it, 'target'), rel = find(it, 'rel');
    if (target && target[1] === 'fixed') out.on = trim(String(src).slice(target[2], target[3])).toLowerCase() === '_blank';
    if (!href || href[1] === 'bare') return out;
    /* Destino com Twig que usa a mesma aspa do atributo (`href="{{ f("x") }}"`): o fim do valor
       nao e conferivel daqui, entao o editor nao escreve ao lado dele. */
    if (href[1] === 'variable' && !matches(String(src), href)) return out;
    if ((target && target[1] !== 'fixed') || (rel && rel[1] !== 'fixed')) return out;
    out.can = true;
    return out;
  }

  function hasToken(raw, token) {
    var parts = String(raw).toLowerCase().split(/[ \t\n\r\f]+/);
    return parts.indexOf(token) !== -1;
  }

  /**
   * Liga ou desliga "abrir em nova aba".
   *  - Ligar: `target="_blank"` (troca o valor que houver, ou escreve o
   *    atributo logo depois do `href`) e `noopener` no `rel` (acrescenta a
   *    palavra, ou escreve `rel="noopener"`).
   *  - Desligar: retira `target`; retira `rel` so quando ele e exatamente
   *    `noopener`, que e o que o proprio editor escreve.
   * Devolve { src } ou { error } (`locked`, `stale`, `long`). Com erro, nem a
   * fonte nem o mapa mudam.
   */
  function setNewTab(src, map, n, on) {
    src = String(src);
    if (!newTab(src, map, n).can) return { error: 'locked' };
    /* Varios passos: trabalha numa copia do mapa e so troca no fim. */
    var work = { items: JSON.parse(JSON.stringify(map.items)) }, it = work.items[n];
    var href = find(it, 'href'), target = find(it, 'target'), rel = find(it, 'rel'), next = src, made, raw;
    if (!matches(src, href) || (target && !matches(src, target)) || (rel && !matches(src, rel))) return { error: 'stale' };

    if (on) {
      if (target) {
        next = replaceValue(next, work, n, target, '_blank');
      } else {
        made = insertAfter(next, work, n, href, 'target', '_blank');
        next = made ? made[0] : null;
        target = made ? made[1] : null;
      }
      if (next === null) return { error: 'long' };
      if (rel) {
        raw = next.slice(rel[2], rel[3]);
        if (!hasToken(raw, 'noopener')) {
          raw = raw.replace(/[ \t\n\r\f]+$/, '');
          next = replaceValue(next, work, n, rel, raw + (raw === '' ? '' : ' ') + 'noopener');
        }
      } else {
        made = insertAfter(next, work, n, target, 'rel', 'noopener');
        next = made ? made[0] : null;
      }
      if (next === null) return { error: 'long' };
    } else {
      /* Sem `target` nao ha nova aba para desligar: o `rel` do autor fica. */
      if (!target) return { src: src };
      next = removeAttr(next, work, n, target);
      if (next === null) return { error: 'stale' };
      if (rel && trim(next.slice(rel[2], rel[3])).toLowerCase() === 'noopener') next = removeAttr(next, work, n, rel);
      if (next === null) return { error: 'stale' };
    }
    map.items = work.items;
    return { src: next };
  }

  /* ---------------- KZ-06 (ADR 0099): cor e tamanho do texto ---------------- */
  var STYLE_PROPS = ['color', 'background-color', 'font-size'];
  var SIZE_MIN = 6, SIZE_MAX = 400; // px
  /* Tags em que cor e tamanho de texto nao tem o que mudar: o painel nao oferece os campos. */
  var STYLE_SKIP = ['br', 'wbr', 'source', 'track', 'param', 'area', 'map', 'col', 'embed', 'img', 'picture', 'iframe', 'object', 'video', 'audio', 'canvas'];
  /* KZ-08 (ADR 0101): os quatro lados de margem e de espaco interno, e o alinhamento do texto. */
  var SPACE_PROPS = ['margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left'];
  var ALIGN_VALUES = ['left', 'center', 'right', 'justify'];
  var LAYOUT_PROPS = SPACE_PROPS.concat(['text-align']);
  var SPACE_MIN = 0, SPACE_MAX = 400; // px
  /* Tags sem caixa propria: nem espacamento. As outras de STYLE_SKIP (imagem, video...) tem caixa. */
  var SPACE_SKIP = ['br', 'wbr', 'source', 'track', 'param', 'area', 'map', 'col'];
  /* Tags com cor e tamanho, mas sem texto para alinhar. */
  var ALIGN_SKIP = ['svg', 'hr'];

  /** Cor aceita: `#rgb`, `#rrggbb` ou uma variavel do tema, `var(--nome)`. */
  function colorOk(value) {
    var v = String(value);
    return /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/.test(v) || /^var\(--[a-z][a-z0-9-]{0,47}\)$/.test(v);
  }

  /** Tamanho aceito: `Npx`, de SIZE_MIN a SIZE_MAX, com ate duas casas. */
  function sizeOk(value) {
    var m = /^(\d{1,3}(?:\.\d{1,2})?)px$/.exec(String(value));
    return !!m && Number(m[1]) >= SIZE_MIN && Number(m[1]) <= SIZE_MAX;
  }

  /** Valor pedido -> valor como sera escrito, ou null quando esta fora da gramatica. */
  function styleValue(prop, value) {
    var v = trim(String(value));
    if (prop === 'font-size') {
      v = v.toLowerCase();
      return sizeOk(v) ? String(Number(v.slice(0, -2))) + 'px' : null;
    }
    if (SPACE_PROPS.indexOf(prop) !== -1) {
      v = v.toLowerCase();
      return spaceOk(v) ? String(Number(v.slice(0, -2))) + 'px' : null;
    }
    if (prop === 'text-align') {
      v = v.toLowerCase();
      return ALIGN_VALUES.indexOf(v) !== -1 ? v : null;
    }
    if (v.charAt(0) === '#') v = v.toLowerCase();
    return colorOk(v) ? v : null;
  }

  /**
   * Le o valor de um `style`. Devolve as declaracoes na ordem em que estao
   * escritas, ou null quando ha algo que este leitor nao acompanha com
   * certeza: aspas, entidade (`&`), barra invertida, comentario, chaves, `<`,
   * `>`, parenteses sem par ou um pedaco que nao e `propriedade: valor`.
   * Cada item: { prop, value, from, to, v0, v1 }, posicoes dentro do valor
   * (`from`..`to` e a declaracao inteira, sem o `;`; `v0`..`v1` e so o valor).
   */
  function styleParse(raw) {
    raw = String(raw);
    if (/[&"'\\{}<>\u0000-\u0008\u000B\u000E-\u001F\u007F]/.test(raw) || raw.indexOf('/*') !== -1) return null;
    var out = [], depth = 0, from = 0;
    for (var i = 0; i <= raw.length; i++) {
      var c = i < raw.length ? raw.charAt(i) : '';
      if (c === '(') { depth++; continue; }
      if (c === ')') { if (--depth < 0) return null; continue; }
      /* Um `;` dentro de parenteses (url(data:...;base64,...)) nao separa declaracoes. */
      if (i < raw.length && (c !== ';' || depth !== 0)) continue;
      if (depth !== 0) return null;
      var seg = raw.slice(from, i);
      if (trim(seg) !== '') {
        var colon = seg.indexOf(':');
        if (colon === -1) return null;
        var prop = trim(seg.slice(0, colon)).toLowerCase(), val = seg.slice(colon + 1), body = trim(val);
        if (!/^-{0,2}[a-z_][a-z0-9_-]*$/.test(prop) || body === '') return null;
        var v0 = from + colon + 1 + val.indexOf(body.charAt(0));
        out.push({ prop: prop, value: body, from: from, to: i, v0: v0, v1: v0 + body.length });
      }
      from = i + 1;
    }
    return out;
  }

  /**
   * Declaracoes de um `style` fixo; null se nao da para ler ou se uma das
   * propriedades de `props` esta repetida (sem `props`: as tres da KZ-06).
   */
  function styleList(raw, props) {
    var list = styleParse(raw), seen = [];
    props = props || STYLE_PROPS;
    if (!list) return null;
    for (var i = 0; i < list.length; i++) {
      if (props.indexOf(list[i].prop) === -1) continue;
      if (seen.indexOf(list[i].prop) !== -1) return null;
      seen.push(list[i].prop);
    }
    return list;
  }

  /** A fonte tem mesmo a tag do item neste lugar? Devolve onde um atributo novo entra, ou -1. */
  function tagAt(src, it) {
    var at = it[2] + 1 + it[0].length;
    if (src.charAt(it[2]) !== '<' || src.slice(it[2] + 1, at).toLowerCase() !== it[0]) return -1;
    return at < src.length && (WS + '/>').indexOf(src.charAt(at)) !== -1 ? at : -1;
  }

  /**
   * O que o `style` do elemento `n` tem hoje. `state`:
   *  - `skip`:     tag em que estes campos nao se aplicam;
   *  - `closed`:   o servidor nao soube ler a tag (attrs null);
   *  - `none`:     a tag nao tem `style` (o editor pode escrever um);
   *  - `fixed`:    `style` lido; `values` traz o que esta escrito para cada uma das tres propriedades;
   *  - `variable`: o `style` tem Twig;
   *  - `locked`:   `style` escrito de um jeito que o editor nao acompanha.
   */
  function styleOf(src, map, n) {
    var it = map && Array.isArray(map.items) ? map.items[n] : null, out = { state: 'closed', values: {} };
    if (!it || !Array.isArray(it[7])) return out;
    if (STYLE_SKIP.indexOf(it[0]) !== -1) { out.state = 'skip'; return out; }
    var a = find(it, 'style');
    if (!a) { out.state = 'none'; return out; }
    if (a[1] === 'variable') { out.state = 'variable'; return out; }
    out.state = 'locked';
    src = String(src);
    if (a[1] !== 'fixed' || !matches(src, a)) return out;
    var list = styleList(src.slice(a[2], a[3]));
    if (!list) return out;
    for (var i = 0; i < list.length; i++) if (STYLE_PROPS.indexOf(list[i].prop) !== -1) out.values[list[i].prop] = list[i].value;
    out.state = 'fixed';
    return out;
  }

  /**
   * Escreve, troca ou retira (`value` vazio) uma propriedade no `style` do
   * elemento `n`: uma das tres da KZ-06 ou uma das nove da KZ-08. Devolve
   * { src } com a fonte nova (o mapa ja acompanha), ou { error }: `locked` (o
   * editor nao escreve neste `style`), `stale` (o mapa nao e desta fonte),
   * `value` (valor fora da gramatica) ou `long` (a fonte passaria do teto).
   * Com erro, nem a fonte nem o mapa mudam.
   */
  function setStyle(src, map, n, prop, value) {
    var it = map && Array.isArray(map.items) ? map.items[n] : null;
    /* Cada familia so se trava com a repeticao das suas proprias propriedades. */
    var family = STYLE_PROPS.indexOf(prop) !== -1 ? STYLE_PROPS : LAYOUT_PROPS;
    if (!it || !Array.isArray(it[7]) || !applies(it[0], prop)) return { error: 'locked' };
    var v = trim(String(value));
    if (v !== '') {
      v = styleValue(prop, v);
      if (v === null) return { error: 'value' };
    }
    src = String(src);
    var a = find(it, 'style'), at = tagAt(src, it), next;
    if (at === -1) return { error: 'stale' };

    if (!a) {
      if (v === '') return { src: src };
      var text = ' style="' + prop + ':' + v + '"';
      next = src.slice(0, at) + text + src.slice(at);
      if (tooLong(next)) return { error: 'long' };
      shift(map, n, at, text.length, null);
      it[7].push(['style', 'fixed', at + 8, at + text.length - 1, at + 1, at + text.length, '']);
      return { src: next };
    }

    if (a[1] !== 'fixed') return { error: 'locked' };
    if (!matches(src, a)) return { error: 'stale' };
    var raw = src.slice(a[2], a[3]), list = styleList(raw, family), hit = null, body;
    if (!list) return { error: 'locked' };
    /* Lado anulado por uma forma curta que vem depois: trocar ou retirar nao mudaria a pagina. */
    if (layoutScan(list).blocked.indexOf(prop) !== -1) return { error: 'locked' };
    for (var i = 0; i < list.length; i++) if (list[i].prop === prop) hit = list[i];

    if (v !== '') {
      if (hit) {
        body = raw.slice(0, hit.v0) + v + raw.slice(hit.v1);
      } else {
        body = raw.replace(/[ \t\n\r\f]+$/, '');
        body += (body === '' || body.charAt(body.length - 1) === ';' ? '' : ';') + prop + ':' + v;
      }
    } else {
      if (!hit) return { src: src };
      /* Sai a declaracao e UM `;`: o que vem depois dela, ou o de antes quando ela e a ultima. */
      var from = hit.from, to = hit.to < raw.length ? hit.to + 1 : hit.to;
      if (hit.to >= raw.length && from > 0) from--;
      body = raw.slice(0, from) + raw.slice(to);
      if (from === 0) body = body.replace(/^[ \t\n\r\f]+/, '');
      if (trim(body) === '') {
        next = removeAttr(src, map, n, a);
        return next === null ? { error: 'stale' } : { src: next };
      }
    }
    next = replaceValue(src, map, n, a, body);
    return next === null ? { error: 'long' } : { src: next };
  }

  /* ---------------- KZ-08 (ADR 0101): espacamento e alinhamento ---------------- */
  /** A propriedade `prop` e uma das que o editor escreve, e vale para uma tag `tag`? */
  function applies(tag, prop) {
    if (SPACE_PROPS.indexOf(prop) !== -1) return SPACE_SKIP.indexOf(tag) === -1;
    if (prop === 'text-align') return STYLE_SKIP.indexOf(tag) === -1 && ALIGN_SKIP.indexOf(tag) === -1;
    return STYLE_PROPS.indexOf(prop) !== -1 && STYLE_SKIP.indexOf(tag) === -1;
  }

  /** Espacamento aceito: `Npx`, de SPACE_MIN a SPACE_MAX, com ate duas casas. */
  function spaceOk(value) {
    var m = /^(\d{1,3}(?:\.\d{1,2})?)px$/.exec(String(value));
    return !!m && Number(m[1]) >= SPACE_MIN && Number(m[1]) <= SPACE_MAX;
  }

  /**
   * Valor escrito no codigo -> numero de px que o campo mostra, ou null quando
   * o campo nao sabe mostrar (negativo, `auto`, outra unidade, `!important`).
   * `0` sem unidade e zero px.
   */
  function pxOf(value) {
    var v = trim(String(value)).toLowerCase();
    if (v === '0') return 0;
    return spaceOk(v) ? Number(v.slice(0, -2)) : null;
  }

  /** `margin` ou `padding` quando a declaracao mexe em mais de um lado de uma vez (forma curta ou logica); senao ''. */
  function coverOf(prop) {
    var m = /^(margin|padding)(?:-(?:block|inline)(?:-(?:start|end))?)?$/.exec(prop);
    return m ? m[1] : '';
  }

  /**
   * Das declaracoes de um `style`: o que esta escrito em cada uma das nove
   * propriedades (`values`), as formas curtas de cada grupo (`cover`) e os
   * lados escritos antes de uma forma curta do mesmo grupo (`blocked`).
   */
  function layoutScan(list) {
    var out = { values: {}, cover: { margin: [], padding: [] }, blocked: [] }, last = { margin: -1, padding: -1 }, i, g;
    for (i = 0; i < list.length; i++) {
      g = coverOf(list[i].prop);
      if (g === '') continue;
      last[g] = i;
      out.cover[g].push(list[i].prop + ': ' + list[i].value);
    }
    for (i = 0; i < list.length; i++) {
      if (LAYOUT_PROPS.indexOf(list[i].prop) === -1) continue;
      out.values[list[i].prop] = list[i].value;
      g = list[i].prop.slice(0, list[i].prop.indexOf('-'));
      if ((g === 'margin' || g === 'padding') && i < last[g]) out.blocked.push(list[i].prop);
    }
    return out;
  }

  /**
   * O que o `style` do elemento `n` diz sobre espacamento e alinhamento.
   * `state` como em styleOf (`skip` aqui e a tag sem caixa propria). Com
   * `fixed`: `values`, `cover` e `blocked` de layoutScan. `align` diz se o
   * alinhamento se aplica a esta tag.
   */
  function layoutOf(src, map, n) {
    var it = map && Array.isArray(map.items) ? map.items[n] : null;
    var out = { state: 'closed', values: {}, cover: { margin: [], padding: [] }, blocked: [], align: false };
    if (!it || !Array.isArray(it[7])) return out;
    if (SPACE_SKIP.indexOf(it[0]) !== -1) { out.state = 'skip'; return out; }
    out.align = applies(it[0], 'text-align');
    var a = find(it, 'style');
    if (!a) { out.state = 'none'; return out; }
    if (a[1] === 'variable') { out.state = 'variable'; return out; }
    out.state = 'locked';
    src = String(src);
    if (a[1] !== 'fixed' || !matches(src, a)) return out;
    var list = styleList(src.slice(a[2], a[3]), LAYOUT_PROPS);
    if (!list) return out;
    var scan = layoutScan(list);
    if (!out.align) delete scan.values['text-align'];
    out.values = scan.values;
    out.cover = scan.cover;
    out.blocked = scan.blocked;
    out.state = 'fixed';
    return out;
  }

  /* ---------------- KZ-07 (ADR 0100): texto alternativo e rotulo que faltam ---------------- */
  /* Tags e papeis em que o leitor de tela anuncia o `aria-label`. Lista fechada. */
  var LABEL_TAGS = ['a', 'button', 'svg', 'nav', 'section', 'aside', 'form', 'header', 'footer', 'main'];
  var LABEL_ROLES = ['button', 'link', 'img', 'navigation', 'region', 'complementary', 'banner', 'contentinfo', 'main', 'form', 'search', 'dialog', 'group'];
  var DECOR_ROLES = ['presentation', 'none'];

  function tokens(raw) {
    var t = trim(String(raw)).toLowerCase();
    return t === '' ? [] : t.split(/[ \t\n\r\f]+/);
  }

  /**
   * Situacao de uma <img> ou de um <svg> para quem nao ve a pagina.
   * Devolve { kind, state, via }:
   *  - kind:  'img', 'svg' ou '' (outra tag; state 'none');
   *  - state: 'missing' (pendente), 'named' (tem nome), 'decorative' (imagem
   *    com `alt` vazio), 'hidden' (escondida do leitor de tela), 'data' (a
   *    resposta vem de dado do site) ou 'unknown' (a tag nao pode ser lida);
   *  - via:   o atributo que decidiu ('alt', 'aria-hidden', 'role',
   *    'aria-label', 'aria-labelledby', 'title').
   */
  /** O atributo ainda esta onde o mapa diz? Com aspas, vale `matches`; sem aspas (ou sem valor), o nome e o fim do trecho. */
  function present(src, a) {
    if (a[1] !== 'bare') return matches(src, a);
    if (src.slice(a[4], a[4] + a[0].length).toLowerCase() !== a[0] || /["'<>]/.test(src.slice(a[4], a[5]))) return false;
    return a[5] === a[3] && (WS + '/>').indexOf(src.charAt(a[5])) !== -1;
  }
  /** Valor que so se conhece com a pagina montada (Twig), com ou sem aspas. */
  function dynamic(src, a) {
    return a[1] === 'variable' || (a[1] === 'bare' && /\{[{%#]/.test(src.slice(a[2], a[3])));
  }
  /** Troca o atributo sem aspas (ou sem valor) por nome="valor" e acerta o registro dele no mapa. */
  function requote(src, map, n, a, raw) {
    var text = a[0] + '="' + raw + '"', next = src.slice(0, a[4]) + text + src.slice(a[5]);
    if (tooLong(next)) return null;
    shift(map, n, a[5], text.length - (a[5] - a[4]), a);
    a[1] = 'fixed';
    a[2] = a[4] + a[0].length + 2;
    a[3] = a[2] + raw.length;
    a[5] = a[3] + 1;
    return next;
  }
  function a11y(src, map, n) {
    var it = map && Array.isArray(map.items) ? map.items[n] : null, out = { kind: '', state: 'none', via: '' };
    if (it && HOLDER_TAGS.indexOf(it[0]) !== -1) return holder(String(src), map, n, out);
    if (!it || (it[0] !== 'img' && it[0] !== 'svg')) return out;
    out.kind = it[0];
    out.state = 'unknown';
    if (!Array.isArray(it[7])) return out;
    src = String(src);
    if (tagAt(src, it) === -1) return out;
    var hid = find(it, 'aria-hidden'), role = find(it, 'role'), lab = find(it, 'aria-label'), by = find(it, 'aria-labelledby');
    var alt = it[0] === 'img' ? find(it, 'alt') : null, all = [hid, role, lab, by, alt], i;
    for (i = 0; i < all.length; i++) if (all[i] && all[i][1] !== 'variable' && !present(src, all[i])) return out;
    function is(state, via) { out.state = state; out.via = via; return out; }

    /* O valor sem aspas (`aria-hidden=true`) e lido como o navegador le; sem valor nenhum, vale vazio. */
    if (hid) {
      if (dynamic(src, hid)) return is('data', 'aria-hidden');
      if (trim(src.slice(hid[2], hid[3])).toLowerCase() === 'true') return is('hidden', 'aria-hidden');
    }
    if (role) {
      if (dynamic(src, role)) return is('data', 'role');
      var roles = tokens(src.slice(role[2], role[3]));
      for (i = 0; i < roles.length; i++) if (DECOR_ROLES.indexOf(roles[i]) !== -1) return is('hidden', 'role');
    }
    if (lab) {
      if (dynamic(src, lab)) return is('data', 'aria-label');
      if (trim(src.slice(lab[2], lab[3])) !== '') return is('named', 'aria-label');
    }
    if (by) {
      if (dynamic(src, by)) return is('data', 'aria-labelledby');
      if (trim(src.slice(by[2], by[3])) !== '') return is('named', 'aria-labelledby');
    }
    if (it[0] === 'img') {
      if (!alt) return is('missing', '');
      if (dynamic(src, alt)) return is('data', 'alt');
      return is(trim(src.slice(alt[2], alt[3])) === '' ? 'decorative' : 'named', 'alt');
    }
    return it[8] === 1 ? is('named', 'title') : is('missing', '');
  }

  /* ---------------- KZ-09 (ADR 0102): link e botao sem nome ---------------- */
  var HOLDER_TAGS = ['a', 'button'];
  /* As marcas de ThemeSourceMap::INNER_*. */
  var INNER_TEXT = 1, INNER_TWIG = 2, INNER_HIDDEN = 4, INNER_UNSURE = 8;

  /**
   * Resumo do que ha dentro de um <a>/<button>, conferido: { flags, list } ou
   * null. O servidor manda [marcas, indices...]; os indices sao de elementos
   * que vem DEPOIS dele no mapa, em ordem crescente.
   */
  function innerOf(items, n) {
    var raw = items[n][8];
    if (!Array.isArray(raw) || raw.length < 1) return null;
    var flags = raw[0], list = [], prev = n;
    if (typeof flags !== 'number' || Math.floor(flags) !== flags || flags < 0 || flags > 15) return null;
    for (var i = 1; i < raw.length; i++) {
      var c = raw[i];
      if (typeof c !== 'number' || Math.floor(c) !== c || c <= prev || c >= items.length || !Array.isArray(items[c])) return null;
      list.push(c);
      prev = c;
    }
    return { flags: flags, list: list };
  }
  /** Texto de um elemento-folha: 'named' (tem algo escrito), 'data' (vem de Twig) ou '' (nada). */
  function leafText(src, it) {
    if (it[1] === 'variable' || it[1] === 'mixed') return 'data';
    if (it[1] !== 'fixed' || it[4] < 0 || it[5] < it[4] || it[5] > src.length) return '';
    return trim(src.slice(it[4], it[5])) !== '' ? 'named' : '';
  }

  /**
   * Situacao de um <a href> ou de um <button> para quem nao ve a pagina.
   * Devolve { kind, state, via }, como a11y():
   *  - kind:  'link' ou 'button' ('' e state 'none' no <a> sem `href`);
   *  - state: 'missing' (pendente), 'named', 'hidden', 'data', 'unknown' ou
   *    'waiting' (o nome depende de uma imagem ou de um desenho de dentro que
   *    esta pendente: quem entra na lista e ela);
   *  - via:   'aria-hidden', 'inside' (escondido por um elemento em volta),
   *    'aria-labelledby', 'aria-label', 'text', 'image', 'content' ou 'title'.
   */
  function holder(src, map, n, out) {
    var items = map.items, it = items[n], i;
    out.kind = it[0] === 'a' ? 'link' : 'button';
    out.state = 'unknown';
    if (!Array.isArray(it[7])) return out;
    /* <a> sem `href` nao e link para o leitor de tela: nada a conferir. */
    if (it[0] === 'a' && !find(it, 'href')) { out.kind = ''; out.state = 'none'; return out; }
    if (tagAt(src, it) === -1) return out;
    var hid = find(it, 'aria-hidden'), lab = find(it, 'aria-label'), by = find(it, 'aria-labelledby'), tip = find(it, 'title');
    var all = [hid, lab, by, tip];
    for (i = 0; i < all.length; i++) if (all[i] && all[i][1] !== 'variable' && !present(src, all[i])) return out;
    function is(state, via) { out.state = state; out.via = via; return out; }
    function filled(a) { return trim(src.slice(a[2], a[3])) !== ''; }

    if (hid) {
      if (dynamic(src, hid)) return is('data', 'aria-hidden');
      if (trim(src.slice(hid[2], hid[3])).toLowerCase() === 'true') return is('hidden', 'aria-hidden');
    }
    var inner = innerOf(items, n);
    if (!inner) return out;
    if (inner.flags & INNER_HIDDEN) return is('hidden', 'inside');
    if (inner.flags & INNER_UNSURE) return out;
    if (by) {
      if (dynamic(src, by)) return is('data', 'aria-labelledby');
      if (filled(by)) return is('named', 'aria-labelledby');
    }
    if (lab) {
      if (dynamic(src, lab)) return is('data', 'aria-label');
      if (filled(lab)) return is('named', 'aria-label');
    }
    /* O que ha dentro. Vale o achado mais forte: nome certo, depois dado do site, depois "nao sei". */
    var own = leafText(src, it), named = own === 'named' || (inner.flags & INNER_TEXT) ? 'text' : '';
    var data = own === 'data' || !!(inner.flags & INNER_TWIG), blind = false, waiting = false;
    for (i = 0; i < inner.list.length && named === ''; i++) {
      var c = inner.list[i], ct = items[c];
      if (ct[0] === 'img' || ct[0] === 'svg') {
        var st = a11y(src, map, c).state;
        if (st === 'named') named = 'image';
        else if (st === 'data') data = true;
        else if (st === 'unknown') blind = true;
        else if (st === 'missing') waiting = true;
        continue;
      }
      var text = leafText(src, ct);
      if (text === 'named') { named = 'text'; continue; }
      if (text === 'data') data = true;
      /* Rotulo num elemento de dentro tambem entra na conta do nome. */
      if (!Array.isArray(ct[7])) { blind = true; continue; }
      var sub = find(ct, 'aria-label');
      if (!sub) continue;
      if (dynamic(src, sub)) data = true;
      else if (!present(src, sub)) blind = true;
      else if (filled(sub)) named = 'text';
    }
    if (named !== '') return is('named', named);
    if (data) return is('data', 'content');
    if (tip) {
      if (dynamic(src, tip)) return is('data', 'title');
      if (filled(tip)) return is('named', 'title');
    }
    if (blind) return out;
    return waiting ? is('waiting', 'image') : is('missing', '');
  }

  /**
   * Pendencias de um trecho: { missing: [indices pendentes, na ordem do
   * codigo], unknown: quantos nao puderam ser conferidos }. Entram <img>,
   * <svg> e, desde o KZ-09, <a href> e <button>.
   */
  function pending(src, map) {
    var out = { missing: [], unknown: 0 }, items = map && Array.isArray(map.items) ? map.items : [];
    for (var n = 0; n < items.length; n++) {
      var st = a11y(src, map, n).state;
      if (st === 'missing') out.missing.push(n);
      else if (st === 'unknown') out.unknown++;
    }
    return out;
  }

  /** O editor pode criar `aria-label` neste elemento? (tag ou `role` da lista fechada) */
  function canLabel(src, map, n) {
    var it = map && Array.isArray(map.items) ? map.items[n] : null;
    if (!it || !Array.isArray(it[7]) || it[0] === 'img') return false;
    if (LABEL_TAGS.indexOf(it[0]) !== -1) return true;
    var role = find(it, 'role');
    if (!role || role[1] !== 'fixed' || !matches(String(src), role)) return false;
    var roles = tokens(String(src).slice(role[2], role[3]));
    for (var i = 0; i < roles.length; i++) if (LABEL_ROLES.indexOf(roles[i]) !== -1) return true;
    return false;
  }

  /**
   * Escreve atributos novos (` nome="valor"`, valor ja codificado) logo depois
   * do nome da tag, na ordem dada. Devolve { src } ou { error: 'long' }.
   */
  function insertAtTag(src, map, n, at, pairs) {
    var text = '', i;
    for (i = 0; i < pairs.length; i++) text += ' ' + pairs[i][0] + '="' + pairs[i][1] + '"';
    var next = src.slice(0, at) + text + src.slice(at);
    if (tooLong(next)) return { error: 'long' };
    shift(map, n, at, text.length, null);
    var pos = at;
    for (i = 0; i < pairs.length; i++) {
      var v0 = pos + pairs[i][0].length + 3, v1 = v0 + pairs[i][1].length;
      map.items[n][7].push([pairs[i][0], 'fixed', v0, v1, pos + 1, v1 + 1, '']);
      pos = v1 + 1;
    }
    return { src: next };
  }

  /**
   * Texto alternativo (`alt`, so em <img>) ou rotulo (`aria-label`): troca o
   * valor quando o atributo existe (como setValue) e CRIA o atributo quando
   * falta e ha texto. Rotulo novo so onde canLabel() deixa; num <svg> sem
   * `role`, vai junto `role="img"`.
   * Devolve { src } ou { error } (`locked`, `stale`, `long`).
   */
  function putAttr(src, map, n, name, value) {
    var it = map && Array.isArray(map.items) ? map.items[n] : null;
    if (!it || !Array.isArray(it[7]) || (name !== 'alt' && name !== 'aria-label')) return { error: 'locked' };
    if (name === 'alt' && it[0] !== 'img') return { error: 'locked' };
    if (find(it, name)) return setValue(src, map, n, name, value);
    src = String(src);
    if (name === 'aria-label' && !canLabel(src, map, n)) return { error: 'locked' };
    var at = tagAt(src, it);
    if (at === -1) return { error: 'stale' };
    var v = trim(String(value)).slice(0, LABEL_MAX);
    /* Campo vazio em atributo que nao existe: nada a criar. `alt=""` e decisao de decorate(). */
    if (v === '') return { src: src };
    var pairs = [[name, encode(v)]];
    if (name === 'aria-label' && it[0] === 'svg' && !find(it, 'role')) pairs.unshift(['role', 'img']);
    return insertAtTag(src, map, n, at, pairs);
  }

  /**
   * Marca (on) ou desmarca (off) uma imagem ou um desenho como enfeite.
   *  - Marcar so vale para elemento pendente: `alt=""` na <img>;
   *    `aria-hidden="true"` no <svg> (troca o valor se o atributo ja existe).
   *  - Desmarcar retira so a marca que o editor escreve: o `alt` vazio da
   *    <img>, o `aria-hidden="true"` do <svg>. Em outra situacao nada muda.
   * Devolve { src } ou { error } (`locked`, `stale`, `long`).
   */
  function decorate(src, map, n, on) {
    var it = map && Array.isArray(map.items) ? map.items[n] : null;
    if (!it || !Array.isArray(it[7]) || (it[0] !== 'img' && it[0] !== 'svg')) return { error: 'locked' };
    src = String(src);
    var at = tagAt(src, it), st = a11y(src, map, n), name = it[0] === 'img' ? 'alt' : 'aria-hidden', a = find(it, name), next;
    /* 'unknown' aqui e o mapa que nao combina com a fonte (tag em outro lugar ou aspas trocadas). */
    if (st.state === 'unknown') return { error: 'stale' };
    if (on) {
      if (st.state !== 'missing') return { error: 'locked' };
      if (!a) return insertAtTag(src, map, n, at, [[name, it[0] === 'img' ? '' : 'true']]);
      /* So chega aqui o <svg> com um `aria-hidden` que nao esconde (por exemplo "false", ou sem valor). */
      next = a[1] === 'fixed' ? replaceValue(src, map, n, a, 'true') : requote(src, map, n, a, 'true');
      return next === null ? { error: 'long' } : { src: next };
    }
    if (!a || st.via !== name || (st.state !== 'decorative' && st.state !== 'hidden')) return { src: src };
    next = removeAttr(src, map, n, a);
    return next === null ? { error: 'stale' } : { src: next };
  }

  /**
   * Retira o `aria-label` fixo de um elemento. Num <svg>, o `role="img"` sai
   * junto (e o par que o editor escreve); outro `role` fica.
   * Devolve { src } ou { error } (`locked`, `stale`).
   */
  function dropLabel(src, map, n) {
    var it = map && Array.isArray(map.items) ? map.items[n] : null;
    if (!it || !Array.isArray(it[7])) return { error: 'locked' };
    src = String(src);
    if (!find(it, 'aria-label')) return { src: src };
    /* Dois passos: trabalha numa copia do mapa e so troca no fim. */
    var work = { items: JSON.parse(JSON.stringify(map.items)) }, wit = work.items[n];
    var a = find(wit, 'aria-label'), role = find(wit, 'role'), next;
    if (a[1] !== 'fixed') return { error: 'locked' };
    if (tagAt(src, wit) === -1 || !matches(src, a) || (role && role[1] === 'fixed' && !matches(src, role))) return { error: 'stale' };
    next = removeAttr(src, work, n, a);
    if (next !== null && wit[0] === 'svg' && role && role[1] === 'fixed' && trim(next.slice(role[2], role[3])).toLowerCase() === 'img') {
      next = removeAttr(next, work, n, role);
    }
    if (next === null) return { error: 'stale' };
    map.items = work.items;
    return { src: next };
  }


  /* ---------------- KZ-23 (ADR 0116): texto do tema dentro de atributo ---------------- */
  /* Um atributo cujo valor e so `{{ theme_text.chave }}` (com, no maximo, um filtro de caixa) pode ser editado
     pelo painel como texto do tema: o que e digitado vai para o JSON dos textos (`strings_json`), no idioma do
     canvas, e a fonte do tema nao muda. So estes atributos entram: `alt`, `aria-label`, `placeholder` e os
     `data-du-*` que a cena 3D le. */
  var THEME_ATTR_RE = /^theme_text\.([a-z][a-z0-9_]{0,63})(?: ?\| ?(upper|lower))?$/;
  var THEME_ATTR_NAMES = ['alt', 'aria-label', 'placeholder'];
  var THEME_ATTR_PREFIX = 'data-du-';
  var THEME_ATTR_DU_RE = /^data-du-[a-z0-9-]+$/;
  /** O atributo `name` e um dos que podem ser texto do tema? */
  function themeAttrName(name) {
    name = String(name);
    return THEME_ATTR_NAMES.indexOf(name) !== -1 || THEME_ATTR_DU_RE.test(name);
  }
  /** Atributo do mapa que e so um texto do tema: { name, key, filter }; null quando nao e. */
  function themeAttr(a) {
    if (!a || a[1] !== 'variable') return null;
    if (!themeAttrName(a[0])) return null;
    var m = THEME_ATTR_RE.exec(String(a[6] || ''));
    return m ? { name: String(a[0]), key: m[1], filter: m[2] || '' } : null;
  }

  root.KazElementAttrs = {
    LINK_MAX: LINK_MAX, LABEL_MAX: LABEL_MAX, SOURCE_MAX: SOURCE_MAX,
    clean: clean, find: find, linkOk: linkOk, encode: encode, tooLong: tooLong,
    setValue: setValue, newTab: newTab, setNewTab: setNewTab,
    STYLE_PROPS: STYLE_PROPS.slice(), SIZE_MIN: SIZE_MIN, SIZE_MAX: SIZE_MAX,
    colorOk: colorOk, sizeOk: sizeOk, styleParse: styleParse, styleOf: styleOf, setStyle: setStyle,
    SPACE_PROPS: SPACE_PROPS.slice(), ALIGN_VALUES: ALIGN_VALUES.slice(), SPACE_MIN: SPACE_MIN, SPACE_MAX: SPACE_MAX,
    spaceOk: spaceOk, pxOf: pxOf, layoutOf: layoutOf,
    LABEL_TAGS: LABEL_TAGS.slice(), LABEL_ROLES: LABEL_ROLES.slice(), HOLDER_TAGS: HOLDER_TAGS.slice(),
    a11y: a11y, pending: pending, canLabel: canLabel, putAttr: putAttr, decorate: decorate, dropLabel: dropLabel,
    THEME_ATTR_NAMES: THEME_ATTR_NAMES.slice(), THEME_ATTR_PREFIX: THEME_ATTR_PREFIX, themeAttrName: themeAttrName, themeAttr: themeAttr
  };
})(typeof window !== 'undefined' ? window : globalThis);
