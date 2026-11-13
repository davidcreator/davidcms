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
 * Testes: tests/check_kaz_element_attrs.js (Node, sem navegador).
 */
(function (root) {
  'use strict';

  var LINK_MAX = 2000;     // caracteres de um destino de link
  var LABEL_MAX = 300;     // caracteres de alt e aria-label
  var SOURCE_MAX = 131072; // o mesmo teto do formulario do tema
  var WS = ' \t\n\r\f';
  var NAMES = ['href', 'target', 'rel', 'alt', 'aria-label'];
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

  /** Troca o valor (ja codificado) de um atributo. Fonte nova, ou null se passar do teto. */
  function replaceValue(src, map, n, a, raw) {
    var next = src.slice(0, a[2]) + raw + src.slice(a[3]);
    if (next.length > SOURCE_MAX) return null;
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
    if (next.length > SOURCE_MAX) return null;
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

  root.KazElementAttrs = {
    LINK_MAX: LINK_MAX, LABEL_MAX: LABEL_MAX, SOURCE_MAX: SOURCE_MAX,
    clean: clean, find: find, linkOk: linkOk, encode: encode,
    setValue: setValue, newTab: newTab, setNewTab: setNewTab
  };
})(typeof window !== 'undefined' ? window : globalThis);
