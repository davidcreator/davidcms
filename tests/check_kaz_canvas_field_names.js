'use strict';
/*
 * KZ-16 (ADR 0109) — no painel "Design" do canvas de pagina, todo campo de uma
 * linha tem o nome da linha.
 *
 * Cada linha e montada por `row(texto, mono, no)` em
 * dev/admin/view/js/kaz-canvas.js. O rotulo aponta, com `for`, para o primeiro
 * campo. Quando o valor tem faixa (min e max), a linha traz dois campos: a
 * barra e o campo de numero. Ate a KZ-15 so a barra tinha nome; o leitor de
 * tela chegava ao numero e nao dizia de que variavel era.
 *
 * Este conferidor recorta do script as funcoes `control` e `row`, roda as duas
 * sobre nos de mentira e exige:
 *   1. o rotulo aponta para o primeiro campo da linha;
 *   2. qualquer outro campo da linha cita o rotulo em `aria-labelledby`;
 *   3. os `id` nao se repetem de uma linha para outra.
 * No fim, tira do recorte as linhas da KZ-16 e confere que o defeito antigo e
 * acusado.
 *
 *   node tests/check_kaz_canvas_field_names.js
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const SCRIPT = path.join(__dirname, '..', 'dev/admin/view/js/kaz-canvas.js');
let checks = 0;
function check(ok, what) { checks++; if (!ok) throw new Error('FAIL: ' + what); }

function Node(tag, cls, text) {
  this.tagName = String(tag).toUpperCase(); this.className = cls || ''; this.textContent = text || '';
  this.children = []; this.attrs = {}; this.id = ''; this.htmlFor = ''; this.hidden = false;
}
Node.prototype.appendChild = function (c) { this.children.push(c); return c; };
Node.prototype.setAttribute = function (k, v) { this.attrs[k] = String(v); };
Node.prototype.getAttribute = function (k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null; };
Node.prototype.fields = function (out) {
  out = out || [];
  this.children.forEach(function (c) { if (c.tagName === 'INPUT' || c.tagName === 'SELECT') out.push(c); c.fields(out); });
  return out;
};
Node.prototype.querySelectorAll = function (selector) {
  if (selector !== 'input,select') throw new Error('seletor que o conferidor nao conhece: ' + selector);
  return this.fields();
};
Node.prototype.querySelector = function (selector) { return this.querySelectorAll(selector)[0] || null; };

const source = fs.readFileSync(SCRIPT, 'utf8');
const cut = /  function control\(kind, id, type, value, spec\) \{[\s\S]*?\n  row\.n = 0;\n/.exec(source);
check(cut !== null, 'as funcoes control e row foram encontradas em kaz-canvas.js');

function build(code) {
  const box = {
    make: function (tag, cls, text) { return new Node(tag, cls, text); },
    label: function (key, fallback) { return fallback || key; },
    HEX: /^#[0-9a-fA-F]{6}$/, String: String
  };
  vm.runInNewContext(code + '\nthis.control = control; this.row = row;', box);
  return box;
}

/** Monta as quatro formas de linha e devolve o que achou; lanca erro no primeiro campo sem nome. */
function inspect(code, what) {
  const api = build(code);
  const rows = [
    ['Raio', api.row('Raio', false, api.control('var', 'a', 'number', '4', { min: 0, max: 48, step: 1 }), 'u1', 'tema')],
    ['Largura', api.row('Largura', false, api.control('var', 'b', 'number', '1200'))],
    ['Cor', api.row('Cor', true, api.control('var', 'c', 'color', '#112233'))],
    ['Densidade', api.row('Densidade', false, api.control('var', 'd', 'map', 'compact', { options: ['compact', 'comfortable'] }))],
    ['Escala', api.row('Escala', false, api.control('var', 'e', 'number', '1.2', { min: 1, max: 2, step: 0.05 }))]
  ];
  const ids = {};
  let twins = 0, fields = 0;
  rows.forEach(function (pair) {
    const text = pair[0], r = pair[1];
    const name = r.children[0], list = r.fields();
    check(name.tagName === 'LABEL' && name.textContent === text, what + ': a linha "' + text + '" comeca pelo rotulo');
    check(list.length >= 1, what + ': a linha "' + text + '" tem campo');
    check(list[0].id !== '' && name.htmlFor === list[0].id, what + ': o rotulo "' + text + '" aponta para o primeiro campo');
    list.forEach(function (f, i) {
      fields++;
      if (f.id) ids[f.id] = (ids[f.id] || 0) + 1;
      if (i === 0) { check(f.getAttribute('aria-labelledby') === null, what + ': o primeiro campo de "' + text + '" nao recebe dois nomes'); return; }
      twins++;
      check(name.id !== '' && f.getAttribute('aria-labelledby') === name.id, what + ': o campo ' + (i + 1) + ' de "' + text + '" (' + f.className + ') tem o nome da linha');
    });
    if (name.id) ids[name.id] = (ids[name.id] || 0) + 1;
  });
  const repeated = Object.keys(ids).filter(function (k) { return ids[k] > 1; });
  check(repeated.length === 0, what + ': id repetido: ' + repeated.join(', '));
  return { rows: rows.length, fields: fields, twins: twins };
}

const found = inspect(cut[0], 'kaz-canvas.js');
// "Raio" e "Escala" tem faixa: barra + numero. As outras tres linhas tem um campo so.
check(found.rows === 5 && found.fields === 7 && found.twins === 2, 'cinco linhas, sete campos, dois deles ao lado de uma barra (' + JSON.stringify(found) + ')');

// ---- o detector acusa o defeito antigo: sem as linhas da KZ-16, o numero fica sem nome
const old = cut[0].replace(/\n      name\.id = 'kaz-canvas-lbl-' \+ row\.n;\n[^\n]*\n[^\n]*aria-labelledby[^\n]*\n/, '\n');
check(old !== cut[0], 'as linhas da KZ-16 foram encontradas em row()');
let caught = '';
try { inspect(old, 'defeito antigo'); } catch (err) { caught = String(err.message); }
check(caught.indexOf('tem o nome da linha') !== -1, 'detector: o campo de numero sem nome e acusado (' + caught + ')');

console.log('Kaz canvas field names checker: ' + checks + ' PASS');
