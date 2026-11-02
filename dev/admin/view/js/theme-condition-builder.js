/**
 * ReamurCMS — Builder visual de condicoes de tema (Frente A).
 *
 * Progressivo: o <textarea name="conditions"> continua sendo o campo enviado
 * no POST. Sem JS (ou se este arquivo falhar) o modo JSON segue funcionando.
 * Nenhum HTML é injetado via innerHTML: todo no é criado com createElement /
 * textContent. A validacao autoritativa é a do ConditionEngine no servidor;
 * este arquivo apenas serializa e exibe o resultado de /simulate.
 */
(function () {
  'use strict';

  var root = document.getElementById('theme-condition-builder');
  var textarea = document.querySelector('textarea[name="conditions"]');
  if (!root || !textarea) return;

  function parse(s, fallback) {
    try { var v = JSON.parse(s); return (v && typeof v === 'object') ? v : fallback; }
    catch (e) { return fallback; }
  }
  function L(k, fallback) { return labels[k] || fallback || k; }

  var spec = parse(root.dataset.spec, {});
  var labels = parse(root.dataset.labels, {});
  var routes = parse(root.dataset.routes, []);
  var languages = parse(root.dataset.languages, []);
  var devices = parse(root.dataset.devices, []);
  var url = root.dataset.url || '';
  var csrf = root.dataset.csrf || '';
  var fields = spec.fields || [];
  var queryKeys = spec.query_keys || [];
  var operators = spec.operators || [];
  var scenes = spec.scenes || [];
  var slots = spec.slots || [];
  var groups = spec.groups || ['all', 'any', 'not'];
  var maxDepth = spec.max_depth || 5;
  var maxRules = spec.max_rules || 40;
  var noValueOps = ['exists', 'truthy', 'falsy', 'empty'];

  var state = parse(textarea.value, null);
  if (!state || !Array.isArray(state.rules)) state = { version: 1, rules: [] };
  if (!state.version) state.version = 1;

  var rulesHost = document.getElementById('tcb-rules');
  var resultHost = document.getElementById('tcb-result');
  var contextHost = document.getElementById('tcb-context');
  if (!rulesHost || !resultHost || !contextHost) return;

  // ---------------------------------------------------------------- helpers
  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    attrs = attrs || {};
    Object.keys(attrs).forEach(function (k) {
      var v = attrs[k];
      if (k === 'class') n.className = v;
      else if (k === 'text') n.textContent = v;
      else if (k === 'value') n.value = v;
      else if (k === 'type') n.type = v;
      else if (k === 'checked') n.checked = !!v;
      else if (k === 'selected') n.selected = !!v;
      else n.setAttribute(k, v);
    });
    (kids || []).forEach(function (c) { n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return n;
  }
  function sel(options, value, onChange, cls) {
    var s = el('select', { 'class': cls || 'form-select form-select-sm' });
    options.forEach(function (o) {
      var val = (typeof o === 'string') ? o : o.value;
      var lab = (typeof o === 'string') ? o : o.label;
      var opt = el('option', { value: val, text: lab });
      if (String(val) === String(value)) opt.selected = true;
      s.appendChild(opt);
    });
    if (onChange) s.addEventListener('change', function () { onChange(s.value); });
    return s;
  }
  function btn(label, cls, onClick, title) {
    var b = el('button', { type: 'button', 'class': cls, text: label, title: title || label });
    b.addEventListener('click', function (e) { e.preventDefault(); onClick(); });
    return b;
  }
  function input(value, onChange, type, cls) {
    var i = el('input', { type: type || 'text', 'class': cls || 'form-control form-control-sm', value: value == null ? '' : String(value) });
    if (onChange) i.addEventListener('input', onChange);
    if (onChange) i.addEventListener('change', onChange);
    return i;
  }
  function fieldOptions() {
    var out = fields.map(function (f) { return { value: f, label: f }; });
    queryKeys.forEach(function (k) { out.push({ value: 'query.' + k, label: 'query.' + k }); });
    return out;
  }
  function clear(n) { while (n.firstChild) n.removeChild(n.firstChild); }
  function groupKind(n) {
    for (var i = 0; i < groups.length; i++) if (Object.prototype.hasOwnProperty.call(n, groups[i])) return groups[i];
    return null;
  }
  function isGroup(n) { return !!(n && typeof n === 'object' && groupKind(n)); }
  function needsValue(op) { return noValueOps.indexOf(op) === -1; }
  function labelRow(text, control) {
    return el('div', { 'class': 'tcb-field' }, [
      el('label', { 'class': 'form-label tcb-field__label', text: text }), control
    ]);
  }

  // ---------------------------------------------------------------- serializacao
  var simTimer = null;
  function sync(rerender) {
    textarea.value = JSON.stringify(state, null, 2);
    if (rerender) renderAll();
    if (simTimer) clearTimeout(simTimer);
    simTimer = setTimeout(simulate, 450);
  }

  // ---------------------------------------------------------------- nos (when)
  function renderValue(node, host) {
    clear(host);
    var op = node.op || 'eq';
    if (!needsValue(op)) {
      host.appendChild(el('span', { 'class': 'tcb-muted', text: L('no_value', 'sem valor') }));
      return;
    }
    if (op === 'between') {
      var a = Array.isArray(node.value) ? node.value[0] : '';
      var b = Array.isArray(node.value) ? node.value[1] : '';
      var ia = input(a, function () { node.value = [ia.value, ib.value]; sync(false); }, 'number');
      var ib = input(b, function () { node.value = [ia.value, ib.value]; sync(false); }, 'number');
      host.appendChild(el('div', { 'class': 'tcb-pair' }, [ia, el('span', { text: '…' }), ib]));
      return;
    }
    if (op === 'in' || op === 'not_in') {
      var list = Array.isArray(node.value) ? node.value.join(', ') : (node.value == null ? '' : String(node.value));
      var il = input(list, function () {
        node.value = il.value.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; });
        sync(false);
      });
      il.setAttribute('placeholder', L('list_hint', 'um, dois, tres'));
      host.appendChild(il);
      return;
    }
    var iv = input(node.value, function () { node.value = iv.value; sync(false); });
    host.appendChild(iv);
  }

  function renderNode(node, host, depth, onRemove) {
    if (isGroup(node)) {
      var kind = groupKind(node);
      var children = Array.isArray(node[kind]) ? node[kind] : (node[kind] ? [node[kind]] : []);
      node[kind] = children;
      var wrap = el('div', { 'class': 'tcb-group tcb-group--' + kind });
      var bar = el('div', { 'class': 'tcb-group__bar' });
      bar.appendChild(sel(groups, kind, function (v) {
        var kids = node[kind];
        delete node[kind];
        node[v] = kids;
        sync(true);
      }, 'form-select form-select-sm tcb-group__kind'));
      bar.appendChild(el('span', { 'class': 'tcb-group__badge', text: L('group_' + kind, kind.toUpperCase()) }));
      bar.appendChild(btn('+ ' + L('add_condition', 'condicao'), 'btn btn-sm btn-outline-secondary', function () {
        children.push({ field: 'route', op: 'eq', value: '' });
        sync(true);
      }));
      if (depth + 1 < maxDepth) {
        bar.appendChild(btn('+ ' + L('add_group', 'grupo'), 'btn btn-sm btn-outline-secondary', function () {
          children.push({ all: [{ field: 'route', op: 'eq', value: '' }] });
          sync(true);
        }));
      }
      if (onRemove) bar.appendChild(btn('×', 'btn btn-sm btn-outline-danger tcb-x', onRemove, L('remove', 'remover')));
      wrap.appendChild(bar);
      var kids = el('div', { 'class': 'tcb-group__kids' });
      children.forEach(function (child) {
        renderNode(child, kids, depth + 1, function () {
          var i = children.indexOf(child);
          if (i >= 0) children.splice(i, 1);
          sync(true);
        });
      });
      wrap.appendChild(kids);
      host.appendChild(wrap);
      return;
    }

    var row = el('div', { 'class': 'tcb-cond' });
    row.appendChild(sel(fieldOptions(), node.field || 'route', function (v) { node.field = v; sync(false); }, 'form-select form-select-sm tcb-cond__field'));
    row.appendChild(sel(operators, node.op || 'eq', function (v) {
      node.op = v;
      if (!needsValue(v)) delete node.value;
      else if (!Array.isArray(node.value) && v !== 'in' && v !== 'not_in' && v !== 'between') { /* mantem */ }
      else node.value = Array.isArray(node.value) ? node.value : '';
      sync(true);
    }, 'form-select form-select-sm tcb-cond__op'));
    var valueHost = el('div', { 'class': 'tcb-cond__value' });
    renderValue(node, valueHost);
    row.appendChild(valueHost);
    if (onRemove) row.appendChild(btn('×', 'btn btn-sm btn-outline-danger tcb-x', onRemove, L('remove', 'remover')));
    host.appendChild(row);
  }

  // ---------------------------------------------------------------- then
  function setOrDelete(obj, key, value) {
    if (value === '' || value == null || (Array.isArray(value) && value.length === 0)) delete obj[key];
    else obj[key] = value;
  }
  function renderThen(then, host) {
    clear(host);
    var grid = el('div', { 'class': 'tcb-then' });
    grid.appendChild(labelRow(L('primary', 'Primaria'), input(then.primary || '', function () { setOrDelete(then, 'primary', this.value); sync(false); }, 'color', 'form-control form-control-color form-control-sm')));
    grid.appendChild(labelRow(L('surface', 'Superficie'), input(then.surface || '', function () { setOrDelete(then, 'surface', this.value); sync(false); }, 'color', 'form-control form-control-color form-control-sm')));
    grid.appendChild(labelRow(L('scene', 'Cena'), sel(scenes.map(function (s) { return { value: s, label: s || L('no_scene', 'nenhuma') }; }), then.scene || '', function (v) { setOrDelete(then, 'scene', v); sync(false); })));
    grid.appendChild(labelRow(L('flags', 'Flags (csv)'), input((then.flags || []).join(', '), function () { setOrDelete(then, 'flags', this.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean)); sync(false); })));
    grid.appendChild(labelRow(L('classes', 'Classes (csv)'), input((then.classes || []).join(', '), function () { setOrDelete(then, 'classes', this.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean)); sync(false); })));

    var varsHost = el('div', { 'class': 'tcb-vars' });
    var vars = (then.vars && typeof then.vars === 'object') ? then.vars : {};
    Object.keys(vars).forEach(function (k) {
      const vk = input(k, function () {
        const old = vars[k]; delete vars[k]; vars[this.value] = old; sync(false);
      });
      const vv = input(vars[k], function () { vars[k] = vv.value; sync(false); });
      varsHost.appendChild(el('div', { 'class': 'tcb-pair' }, [vk, vv, btn('×', 'btn btn-sm btn-outline-danger tcb-x', function () { delete vars[k]; if (Object.keys(vars).length === 0) delete then.vars; sync(true); })]));
    });
    varsHost.appendChild(btn('+ ' + L('add_var', 'variavel'), 'btn btn-sm btn-outline-secondary', function () {
      if (!then.vars || typeof then.vars !== 'object') then.vars = {};
      then.vars['var_' + (Object.keys(then.vars).length + 1)] = '';
      sync(true);
    }));
    grid.appendChild(labelRow(L('vars', 'Variaveis'), varsHost));

    var three = then.three || {};
    var threeBox = el('div', { 'class': 'tcb-three' });
    var enabled = el('input', { type: 'checkbox', 'class': 'form-check-input', checked: !!three.enabled });
    enabled.addEventListener('change', function () {
      if (!then.three || typeof then.three !== 'object') then.three = {};
      then.three.enabled = enabled.checked;
      sync(false);
    });
    threeBox.appendChild(el('label', { 'class': 'form-check-label' }, [enabled, document.createTextNode(' ' + L('three', 'Three.js'))]));
    ['scene', 'slot', 'count', 'color', 'speed'].forEach(function (key) {
      var ctrl;
      if (key === 'scene') ctrl = sel(scenes.map(function (s) { return { value: s, label: s || L('no_scene', 'nenhuma') }; }), three[key] || '', function (v) { if (!then.three) then.three = {}; then.three[key] = v; sync(false); });
      else if (key === 'slot') ctrl = sel(slots, three[key] || 'hero', function (v) { if (!then.three) then.three = {}; then.three[key] = v; sync(false); });
      else ctrl = input(three[key] == null ? '' : three[key], function () { if (!then.three) then.three = {}; then.three[key] = this.value; sync(false); }, key === 'color' ? 'color' : 'number');
      threeBox.appendChild(labelRow(key, ctrl));
    });
    // F13-B: patch parcial de tokens (then.tokens). O TokenRegistry valida no
    // servidor; aqui so coletamos valores allowlistados, sem defaults.
    var tok = (then.tokens && typeof then.tokens === 'object') ? then.tokens : {};
    var tokBox = el('div', { 'class': 'tcb-tokens' });
    function setToken(key, value) {
      if (!then.tokens || typeof then.tokens !== 'object') then.tokens = {};
      if (value === '' || value == null) delete then.tokens[key]; else then.tokens[key] = value;
      if (Object.keys(then.tokens).length === 0) delete then.tokens;
      sync(false);
    }
    [['color_text', 'color'], ['radius', 'number'], ['density', 'enum'], ['font_scale', 'number']].forEach(function (pair) {
      const key = pair[0], type = pair[1];
      let ctrl;
      if (type === 'enum') {
        const opts = [{ value: '', label: L('no_value', 'nenhuma') }].concat(['compact', 'comfortable', 'spacious'].map(function (o) { return { value: o, label: o }; }));
        ctrl = sel(opts, tok[key] || '', function (v) { setToken(key, v); });
      } else {
        ctrl = input(tok[key] == null ? '' : tok[key], function () { setToken(key, this.value); }, type === 'color' ? 'color' : 'number');
      }
      tokBox.appendChild(labelRow(key, ctrl));
    });
    grid.appendChild(labelRow(L('tokens', 'Tokens'), tokBox));
    grid.appendChild(labelRow(L('three', 'Three.js'), threeBox));
    host.appendChild(grid);
  }

  // ---------------------------------------------------------------- regras
  var dragIndex = null;
  function moveRule(from, to) {
    if (from === to || from < 0 || to < 0 || from >= state.rules.length || to >= state.rules.length) return;
    var item = state.rules.splice(from, 1)[0];
    state.rules.splice(to, 0, item);
    sync(true);
  }
  function renderAll() {
    clear(rulesHost);
    if (state.rules.length === 0) clear(resultHost);
    state.rules.forEach(function (rule, index) {
      if (!isGroup(rule.when)) rule.when = { all: [rule.when || { field: 'route', op: 'eq', value: '' }] };
      if (!rule.then || typeof rule.then !== 'object') rule.then = {};
      var card = el('div', { 'class': 'tcb-rule', draggable: 'true', 'data-rule-index': String(index) });
      var head = el('div', { 'class': 'tcb-rule__head' });
      head.appendChild(el('span', { 'class': 'tcb-handle', text: '⋮⋮', title: L('drag', 'arrastar') }));
      var idInput = input(rule.id || '', function () { rule.id = idInput.value; sync(false); }, 'text', 'form-control form-control-sm tcb-rule__id');
      idInput.setAttribute('pattern', '[a-z0-9_-]{1,48}');
      head.appendChild(labelRow(L('rule_id', 'ID'), idInput));
      head.appendChild(btn('↑', 'btn btn-sm btn-outline-secondary tcb-x', function () { moveRule(index, index - 1); }, L('up', 'subir')));
      head.appendChild(btn('↓', 'btn btn-sm btn-outline-secondary tcb-x', function () { moveRule(index, index + 1); }, L('down', 'descer')));
      head.appendChild(btn('×', 'btn btn-sm btn-outline-danger tcb-x', function () { state.rules.splice(index, 1); sync(true); }, L('remove', 'remover')));
      card.appendChild(head);

      var whenHost = el('div', { 'class': 'tcb-rule__section' });
      whenHost.appendChild(el('div', { 'class': 'tcb-section-title', text: L('when', 'Quando') }));
      renderNode(rule.when, whenHost, 0, null);
      card.appendChild(whenHost);

      var thenHost = el('div', { 'class': 'tcb-rule__section' });
      thenHost.appendChild(el('div', { 'class': 'tcb-section-title', text: L('then', 'Aplicar') }));
      renderThen(rule.then, thenHost);
      card.appendChild(thenHost);

      card.addEventListener('dragstart', function (e) {
        dragIndex = index;
        if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', String(index)); } catch (x) {} }
      });
      card.addEventListener('dragover', function (e) { e.preventDefault(); card.classList.add('tcb-rule--over'); });
      card.addEventListener('dragleave', function () { card.classList.remove('tcb-rule--over'); });
      card.addEventListener('drop', function (e) {
        e.preventDefault(); card.classList.remove('tcb-rule--over');
        var from = dragIndex;
        if (from === null && e.dataTransfer) { try { from = parseInt(e.dataTransfer.getData('text/plain'), 10); } catch (x) {} }
        if (typeof from === 'number' && !isNaN(from)) moveRule(from, index);
        dragIndex = null;
      });
      rulesHost.appendChild(card);
    });
  }

  var addRule = document.getElementById('tcb-add-rule');
  if (addRule) addRule.addEventListener('click', function (e) {
    e.preventDefault();
    if (state.rules.length >= maxRules) return;
    state.rules.push({
      id: 'rule_' + (state.rules.length + 1),
      when: { all: [{ field: 'route', op: 'eq', value: 'common/home' }] },
      then: { flags: [] }
    });
    sync(true);
  });

  // ---------------------------------------------------------------- abas
  function showTab(which) {
    document.querySelectorAll('[data-tcb-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-tcb-pane') !== which; });
    document.querySelectorAll('[data-tcb-tab]').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-tcb-tab') === which); });
    if (which === 'visual') {
      var fresh = parse(textarea.value, null);
      if (fresh && Array.isArray(fresh.rules)) { state = fresh; renderAll(); }
      else resultHost.textContent = L('json_invalid', 'JSON inválido: corrija na aba JSON');
    }
  }
  document.querySelectorAll('[data-tcb-tab]').forEach(function (b) {
    b.addEventListener('click', function (e) { e.preventDefault(); showTab(b.getAttribute('data-tcb-tab')); });
  });

  // ---------------------------------------------------------------- simulador
  function contextValue() {
    var out = { query: {} };
    contextHost.querySelectorAll('[data-ctx]').forEach(function (c) {
      var key = c.getAttribute('data-ctx');
      var v = c.type === 'checkbox' ? (c.checked ? '1' : '0') : c.value;
      if (key.indexOf('query.') === 0) out.query[key.slice(6)] = v;
      else out[key] = v;
    });
    return out;
  }
  function paintResult(data) {
    clear(resultHost);
    if (!data || typeof data !== 'object') return;
    var badge = el('span', { 'class': 'badge ' + (data.ok ? 'text-bg-success' : 'text-bg-danger'), text: data.ok ? L('valid', 'valido') : L('invalid', 'invalido') });
    resultHost.appendChild(el('div', { 'class': 'tcb-result__head' }, [badge, el('span', { 'class': 'tcb-muted', text: ' ' + (data.matched || []).length + ' ' + L('matched', 'regra(s) casada(s)') })]));
    if (data.errors && data.errors.length) {
      var ul = el('ul', { 'class': 'tcb-errors' });
      data.errors.forEach(function (er) { ul.appendChild(el('li', { text: (er.path ? er.path + ': ' : '') + er.code })); });
      resultHost.appendChild(ul);
    }
    var matched = data.matched || [];
    if (matched.length) resultHost.appendChild(el('p', { 'class': 'tcb-matched', text: L('matched', 'Casaram') + ': ' + matched.join(', ') }));
    var ov = data.overrides || {};
    var table = el('div', { 'class': 'tcb-overrides' });
    Object.keys(ov).forEach(function (k) {
      var v = ov[k];
      if (v === null || v === undefined || (Array.isArray(v) && !v.length) || (typeof v === 'object' && !Array.isArray(v) && Object.keys(v).length === 0)) return;
      table.appendChild(el('div', { 'class': 'tcb-override' }, [
        el('code', { text: k }), el('span', { text: typeof v === 'object' ? JSON.stringify(v) : String(v) })
      ]));
    });
    resultHost.appendChild(table);
  }
  function simulate() {
    if (!url || !csrf) return;
    var body = new URLSearchParams();
    body.set('csrf_token', csrf);
    body.set('conditions', JSON.stringify(state));
    var ctx = contextValue();
    Object.keys(ctx).forEach(function (k) {
      if (k === 'query') Object.keys(ctx.query).forEach(function (q) { body.set('context[query][' + q + ']', ctx.query[q]); });
      else body.set('context[' + k + ']', String(ctx[k]));
    });
    resultHost.textContent = L('simulating', 'simulando…');
    fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
      body: body.toString()
    }).then(function (r) { return r.json(); }).then(paintResult).catch(function () {
      resultHost.textContent = L('offline', 'simulacao indisponível; o salvamento continua normal');
    });
  }
  var simBtn = document.getElementById('tcb-simulate');
  if (simBtn) simBtn.addEventListener('click', function (e) { e.preventDefault(); simulate(); });

  // ---------------------------------------------------------------- contexto (UI)
  function ctxRow(label, key, control) { return labelRow(label, control); }
  var ctxFields = [
    [L('route', 'Rota'), 'route', sel(routes.length ? routes : ['common/home'], (routes[0] || 'common/home'), null, 'form-select form-select-sm')],
    [L('language', 'Idioma'), 'language', sel(languages.length ? languages : ['en-us'], (languages[0] || 'en-us'), null, 'form-select form-select-sm')],
    [L('device', 'Dispositivo'), 'device', sel(devices.length ? devices : ['desktop'], (devices[0] || 'desktop'), null, 'form-select form-select-sm')],
    [L('variant', 'Variante'), 'variant', sel([{ value: '', label: '-' }, 'a', 'b'], '', null, 'form-select form-select-sm')],
    [L('hour', 'Hora'), 'hour', input('9', null, 'number')],
    [L('logged', 'Logado'), 'logged_in', el('input', { type: 'checkbox', 'class': 'form-check-input', checked: false })],
    [L('query', 'UTM source'), 'query.utm_source', input('', null, 'text')]
  ];
  ctxFields.forEach(function (f) {
    f[2].setAttribute('data-ctx', f[1]);
    f[2].addEventListener('change', function () {
      if (simTimer) clearTimeout(simTimer);
      simTimer = setTimeout(simulate, 250);
    });
    contextHost.appendChild(el('div', { 'class': 'col-md-3 mb-2' }, [ctxRow(f[0], f[1], f[2])]));
  });

  renderAll();
  // Exposicao minima para testes automatizados (sem alterar comportamento).
  root.__tcb = { state: function () { return state; }, renderAll: renderAll, simulate: simulate, sync: sync, moveRule: moveRule };
})();
