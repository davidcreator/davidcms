'use strict';
/*
 * KZ-15 (ADR 0108) — no construtor de condicoes do tema, todo rotulo esta
 * ligado ao que ele nomeia.
 *
 * Ate a KZ-14 a funcao `labelRow` criava `<label>` sem `for`: o texto ficava
 * ao lado do campo, mas o leitor de tela nao dizia o nome ao entrar no campo
 * e clicar no rotulo nao levava o cursor para ele. Eram 8 rotulos na parte
 * do simulador e mais 18 por regra.
 *
 * Este conferidor roda o script de verdade sobre uma pagina de mentira (so o
 * que o script usa do navegador) e olha a arvore que ele monta:
 *   1. todo `<label>` aponta, com `for`, para um input/select/textarea que
 *      existe, ou tem o campo dentro dele;
 *   2. toda linha `.tcb-field` com uma caixa de varios campos tem o titulo em
 *      `<span>` e a caixa como grupo com esse nome (role + aria-labelledby);
 *   3. nenhum `id` se repete, nem depois de redesenhar as regras;
 *   4. os campos continuam sem `name` (sao controles de tela: ADR 0098).
 *
 *   node tests/check_condition_builder_labels.js          confere
 *   node tests/check_condition_builder_labels.js --list   imprime o que achou (JSON)
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const ROOT = path.join(__dirname, '..');
const SCRIPT = 'dev/admin/view/js/theme-condition-builder.js';
let checks = 0;
function check(ok, what) { checks++; if (!ok) throw new Error('FAIL: ' + what); }

/* ---- pagina de mentira: so o que o script usa ---- */
function Node(tag) {
  this.tagName = String(tag).toUpperCase();
  this.children = []; this.attrs = {}; this.parent = null;
  this.className = ''; this.textContent = ''; this.value = ''; this.type = ''; this.checked = false; this.selected = false; this.hidden = false;
  this.dataset = {};
  const self = this;
  this.classList = { add: function () {}, remove: function () {}, toggle: function () {} };
  Object.defineProperty(this, 'id', { get: function () { return self.attrs.id || ''; }, set: function (v) { self.attrs.id = String(v); } });
  Object.defineProperty(this, 'firstChild', { get: function () { return self.children[0] || null; } });
}
Node.prototype.setAttribute = function (k, v) { this.attrs[k] = String(v); };
Node.prototype.getAttribute = function (k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null; };
Node.prototype.appendChild = function (c) { c.parent = this; this.children.push(c); return c; };
Node.prototype.removeChild = function (c) { this.children.splice(this.children.indexOf(c), 1); c.parent = null; return c; };
Node.prototype.addEventListener = function () {};
Node.prototype.all = function (out) { out = out || []; this.children.forEach(function (c) { if (c.tagName !== '#TEXT') { out.push(c); c.all(out); } }); return out; };
Node.prototype.querySelectorAll = function (selector) {
  const m = /^\[([a-z-]+)(?:="([^"]*)")?\]$/.exec(selector);
  if (!m) throw new Error('seletor que a pagina de mentira nao conhece: ' + selector);
  return this.all().filter(function (n) { const v = n.getAttribute(m[1]); return v !== null && (m[2] === undefined || v === m[2]); });
};
Node.prototype.querySelector = function (selector) { return this.querySelectorAll(selector)[0] || null; };

function page(conditions) {
  const body = new Node('body');
  const byId = {};
  function make(tag, id) { const n = new Node(tag); if (id) { n.id = id; byId[id] = n; } body.appendChild(n); return n; }
  const root = make('div', 'theme-condition-builder');
  root.dataset = {
    spec: JSON.stringify({ fields: ['route', 'language', 'site', 'device', 'hour'], query_keys: ['utm_source'], site_ids: ['primary', 'loja'],
      operators: ['eq', 'neq', 'in', 'not_in', 'between', 'exists'], scenes: ['', 'particles'], slots: ['hero', 'background'], groups: ['all', 'any', 'not'], max_depth: 5, max_rules: 40 }),
    labels: JSON.stringify({ rule_id: 'ID da regra', primary: 'Primária', vars: 'Variáveis', tokens: 'Tokens', three: 'Three.js' }),
    routes: '["common/home","blog/blog_list"]', languages: '["pt-br","en-us"]', devices: '["desktop","mobile"]', url: '', csrf: ''
  };
  const textarea = make('textarea'); textarea.setAttribute('name', 'conditions'); textarea.value = JSON.stringify(conditions);
  make('div', 'tcb-rules'); make('div', 'tcb-result'); make('div', 'tcb-context'); make('button', 'tcb-add-rule'); make('button', 'tcb-simulate');
  const document = {
    getElementById: function (id) { return byId[id] || null; },
    createElement: function (tag) { return new Node(tag); },
    createTextNode: function (text) { const n = new Node('#text'); n.textContent = String(text); return n; },
    querySelector: function (selector) {
      if (selector === 'textarea[name="conditions"]') return textarea;
      if (selector === 'select[name="preview_site"]') return null;
      throw new Error('seletor que a pagina de mentira nao conhece: ' + selector);
    },
    querySelectorAll: function (selector) { return body.querySelectorAll(selector); }
  };
  vm.runInNewContext(fs.readFileSync(path.join(ROOT, SCRIPT), 'utf8'), {
    document: document, JSON: JSON, String: String, Object: Object, Array: Array, URLSearchParams: URLSearchParams,
    setTimeout: function () { return 0; }, clearTimeout: function () {}, fetch: function () { throw new Error('o conferidor nao usa a rede'); },
    parseInt: parseInt, isNaN: isNaN
  });
  return { body: body, root: root, textarea: textarea };
}

const RULES = { version: 1, rules: [
  { id: 'campanha', when: { all: [{ field: 'route', op: 'eq', value: 'common/home' }, { any: [{ field: 'hour', op: 'between', value: [9, 18] }, { field: 'site', op: 'in', value: ['loja'] }] }] },
    then: { primary: '#112233', flags: ['a'], vars: { cor: 'azul', lado: 'esq' }, tokens: { radius: 4 }, three: { enabled: true, scene: 'particles', slot: 'hero', count: 200 } } },
  { id: 'noite', when: { not: [{ field: 'device', op: 'exists' }] }, then: {} }
] };
const FIELD = /^(INPUT|SELECT|TEXTAREA)$/;

function inspect(p, what) {
  const nodes = p.body.all();
  const ids = {};
  nodes.forEach(function (n) { if (n.id) ids[n.id] = (ids[n.id] || 0) + 1; });
  const repeated = Object.keys(ids).filter(function (id) { return ids[id] > 1; });
  check(repeated.length === 0, what + ': id repetido: ' + repeated.join(', '));
  const byId = {};
  nodes.forEach(function (n) { if (n.id) byId[n.id] = n; });

  const labels = nodes.filter(function (n) { return n.tagName === 'LABEL'; });
  let linked = 0, wrapping = 0;
  labels.forEach(function (l) {
    const target = l.getAttribute('for');
    if (target !== null) {
      check(target !== '' && byId[target] !== undefined && FIELD.test(byId[target].tagName), what + ': o rotulo "' + l.textContent + '" aponta para um campo que existe (' + target + ')');
      check(l.parent.children.indexOf(byId[target]) !== -1, what + ': o campo do rotulo "' + l.textContent + '" esta na mesma linha');
      linked++;
    } else {
      check(l.all().some(function (c) { return FIELD.test(c.tagName); }), what + ': o rotulo "' + l.textContent + '" sem `for` tem o campo dentro dele');
      wrapping++;
    }
  });

  const rows = nodes.filter(function (n) { return /(^| )tcb-field( |$)/.test(n.className); });
  let groups = 0;
  rows.forEach(function (row) {
    check(row.children.length === 2, what + ': a linha tem rotulo e campo');
    const head = row.children[0], control = row.children[1];
    check(/(^| )tcb-field__label( |$)/.test(head.className) && head.textContent !== '', what + ': a linha tem titulo');
    if (FIELD.test(control.tagName)) {
      check(head.tagName === 'LABEL' && head.getAttribute('for') === control.id && control.id !== '', what + ': "' + head.textContent + '" e <label for> do campo ao lado');
      check(control.getAttribute('name') === null, what + ': o campo "' + head.textContent + '" continua sem name');
    } else {
      check(head.tagName === 'SPAN' && /(^| )tcb-field__label--group( |$)/.test(head.className) && head.id !== '', what + ': "' + head.textContent + '" e titulo de grupo (<span> com id)');
      check(control.getAttribute('role') === 'group' && control.getAttribute('aria-labelledby') === head.id, what + ': a caixa de "' + head.textContent + '" e um grupo com esse nome');
      groups++;
    }
  });
  return { labels: labels.length, linked: linked, wrapping: wrapping, rows: rows.length, groups: groups, ids: Object.keys(ids).length };
}

// ---- sem regra nenhuma: so o simulador
const empty = page({ version: 1, rules: [] });
const e = inspect(empty, 'sem regras');
check(e.rows === 8 && e.linked === 8 && e.groups === 0, 'sem regras: os 8 campos do simulador, todos com rotulo ligado (' + JSON.stringify(e) + ')');
check(empty.body.querySelectorAll('[data-ctx]').length === 8, 'sem regras: os 8 campos do simulador continuam marcados com data-ctx');

// ---- com duas regras
const full = page(RULES);
const f = inspect(full, 'duas regras');
// por regra: ID, 5 campos simples, 3 grupos (variaveis, tokens, Three.js), 4 tokens, 5 campos do Three.js = 18 linhas
check(f.rows === 8 + 2 * 18, 'duas regras: 8 linhas do simulador + 18 por regra (' + f.rows + ')');
check(f.groups === 2 * 3, 'duas regras: 3 grupos por regra (' + f.groups + ')');
check(f.linked === f.rows - f.groups, 'duas regras: toda linha de um campo so tem <label for>');
check(f.wrapping === 2, 'duas regras: o unico rotulo sem `for` e o da caixa de selecao "Three.js", que envolve o campo');
const idInputs = full.body.all().filter(function (n) { return /(^| )tcb-rule__id( |$)/.test(n.className); });
check(idInputs.length === 2 && idInputs.every(function (n) { return n.getAttribute('pattern') === '[a-z0-9_\\-]{1,48}' && n.id !== ''; }), 'o ID da regra mantem o pattern e ganhou id');

// ---- redesenhar nao repete id, e o que e enviado nao muda
const before = full.textarea.value;
full.root.__tcb.renderAll();
const again = inspect(full, 'depois de redesenhar');
check(again.rows === f.rows && again.groups === f.groups, 'depois de redesenhar: mesma quantidade de linhas');
full.root.__tcb.sync(true);
inspect(full, 'depois de sincronizar');
check(JSON.stringify(JSON.parse(full.textarea.value)) === JSON.stringify(JSON.parse(before)), 'o JSON enviado no campo conditions e o mesmo');
check(JSON.stringify(JSON.parse(full.textarea.value)) === JSON.stringify(RULES), 'o JSON enviado e o que foi carregado');

// ---- o detector acusa o defeito antigo
function oldDefect() {
  const source = fs.readFileSync(path.join(ROOT, SCRIPT), 'utf8');
  const cut = /  var fieldSeq = 0;\n  function labelRow\(text, control\) \{[\s\S]*?\n  \}\n/.exec(source);
  check(cut !== null, 'labelRow encontrada no script');
  return cut[0];
}
check(/'for': control\.id/.test(oldDefect()) && /'aria-labelledby', id/.test(oldDefect()), 'labelRow liga o rotulo de um jeito ou de outro');
const loose = new Node('div'); const l = new Node('label'); l.textContent = 'Solto'; l.className = 'tcb-field__label'; const i = new Node('input');
loose.className = 'tcb-field'; loose.appendChild(l); loose.appendChild(i);
const holder = { body: new Node('body') }; holder.body.appendChild(loose);
let caught = '';
try { inspect(holder, 'detector'); } catch (err) { caught = String(err.message); }
check(caught.indexOf('sem `for` tem o campo dentro dele') !== -1, 'detector: <label> solto ao lado do campo e acusado');

if (process.argv.indexOf('--list') !== -1) console.log(JSON.stringify({ semRegras: e, duasRegras: f, depois: again }, null, 2));
else console.log('Condition builder labels checker: ' + checks + ' PASS');
