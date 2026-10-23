#!/usr/bin/env node
'use strict';
/**
 * F8-05 A02 — regressão de contrato sobre o admin.js real, executado inteiro.
 * DOM e CKEDITOR são simulados. NÃO executa o core/iframe do editor, navegador,
 * HTTP, banco, upload ou persistência. Sem pacotes externos, rede ou instalações.
 * A fila de configuração reproduz somente a ordem relevante do core 4.22.1:
 * customConfig local é lido antes do callback legado; os demais overrides, depois.
 * Em modo CLI, só lê arquivos locais/stdin: nenhuma fonte é baixada pelo runner.
 *
 * Uso: node tools/e2e/e2e_f805_a02_ckeditor_dom.cjs [caminho/admin.js|-]
 * Controle com fontes em memória: --stdin-json recebe {admin, legacyConfig}.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
let source;
let legacySource;
let legacyOrigin;
try {
  if (process.argv.length > 3) throw new Error('Informe somente uma origem.');
  const input = process.argv[2] || path.join(root, 'dev/admin/view/js/admin.js');
  if (input === '--stdin-json') {
    const payload = JSON.parse(fs.readFileSync(0, 'utf8'));
    if (typeof payload.admin !== 'string' || typeof payload.legacyConfig !== 'string') {
      throw new Error('JSON exige admin e legacyConfig como strings.');
    }
    source = payload.admin;
    legacySource = payload.legacyConfig;
    legacyOrigin = 'fonte fornecida por JSON (execução em memória)';
  } else {
    source = fs.readFileSync(input === '-' ? 0 : input, 'utf8');
    const legacyFile = path.join(root, 'dev/admin/view/js/ckeditor/config.js');
    if (fs.existsSync(legacyFile)) {
      legacySource = fs.readFileSync(legacyFile, 'utf8');
      legacyOrigin = 'config.js local do projeto';
    } else {
      // Recorte intencional: o ZIP incremental não inclui/copia os vendors.
      legacySource = "CKEDITOR.editorConfig = function (config) { config.language = $(this).attr('data-lang'); config.extraPlugins = 'reamur,codemirror'; };";
      legacyOrigin = 'recorte mínimo da linha legada que exige $ (vendor ausente)';
    }
  }
  new vm.Script(source, { filename: 'admin.js' });
  new vm.Script(legacySource, { filename: 'ckeditor/config.js (fixture)' });
} catch (error) {
  console.error('ERRO de entrada: ' + error.message);
  process.exit(2);
}

function element(id = '', attrs = {}) {
  const handlers = new Map();
  const classes = new Set();
  return {
    id, name: '', value: '', dataset: {}, style: { display: '', visibility: '' },
    hidden: false, attributes: { ...attrs }, handlers,
    classList: {
      add: token => classes.add(token), remove: token => classes.delete(token),
      contains: token => classes.has(token),
      toggle(token, force) {
        const next = force === undefined ? !classes.has(token) : Boolean(force);
        if (next) classes.add(token); else classes.delete(token);
        return next;
      }
    },
    getAttribute(key) { return Object.hasOwn(this.attributes, key) ? this.attributes[key] : null; },
    setAttribute(key, value) { this.attributes[key] = String(value); },
    addEventListener(type, callback, options) {
      if (!handlers.has(type)) handlers.set(type, []);
      handlers.get(type).push({ callback, once: Boolean(options && options.once) });
    },
    dispatch(type, extra = {}) {
      for (const item of [...(handlers.get(type) || [])]) {
        if (item.once) handlers.set(type, handlers.get(type).filter(other => other !== item));
        item.callback({ target: this, type, preventDefault() {}, ...extra });
      }
    },
    querySelector() { return null; }, querySelectorAll() { return []; }
  };
}

function fixture(options = {}) {
  const textareas = [
    ['1', '<p>English <strong>body</strong> &amp; links.</p>'],
    ['2', '<h2>Ação e edição</h2><p>Conteúdo em português: ç, ã, €.</p>'],
    ['3', '<p>Español: edición, ñ y <em>énfasis</em>.</p>']
  ].map(([id, value]) => {
    const ta = element('input-description-' + id, { 'data-rte': '1' });
    ta.name = 'blog_post_description[' + id + '][description]';
    ta.value = value;
    return ta;
  });
  const html = element('html', { lang: options.lang === undefined ? 'en-us' : options.lang, 'data-bs-theme': 'light' });
  if (options.lang === null) delete html.attributes.lang;
  const doc = element('document');
  doc.documentElement = html;
  doc.readyState = options.readyState || 'interactive';
  const queue = [];
  const calls = [];
  const configLoads = [];
  const configFailures = [];
  const destroyed = [];
  const tabShows = [];
  const history = [];
  const tabs = options.tabs ? ['en-us', 'pt-br', 'es-eu'].map(lang => element('tab-' + lang, { 'data-lang-tab': lang })) : [];
  const coverInput = options.cover ? element('input-image', { 'data-preview-base': 'https://example.invalid/catalog/' }) : null;
  const coverEmpty = element('cover-empty');
  let coverImage = null;
  const coverBox = options.cover ? {
    firstChild: coverEmpty,
    querySelector(selector) { return selector === 'img' ? coverImage : selector === '.cover-preview--empty' ? coverEmpty : null; },
    insertBefore(image) {
      coverImage = image;
      image.remove = () => { coverImage = null; };
    }
  } : null;
  doc.getElementById = id => id === 'input-image' ? coverInput : id === 'coverPreview' ? coverBox : textareas.find(ta => ta.id === id) || null;
  doc.querySelectorAll = selector => {
    if (selector === 'textarea[data-rte]') return doc.readyState === 'loading' ? [] : textareas;
    if (selector === '#blogLangTabs [data-bs-toggle="tab"]') return tabs;
    return [];
  };
  doc.querySelector = selector => tabs.find(tab => selector === '#blogLangTabs [data-lang-tab="' + tab.getAttribute('data-lang-tab') + '"]') || null;
  doc.createElement = tag => element(tag);

  const ckeditor = {
    instances: Object.create(null),
    replace(ta, config) {
      calls.push({ ta, config });
      if (options.returnNull) return null;
      const editor = {
        name: ta.id, status: 'unloaded', config: null,
        destroy(skipUpdate) {
          destroyed.push({ id: ta.id, skipUpdate });
          if (options.cleanupThrows) throw new Error('Falha de cleanup simulada');
          delete ckeditor.instances[ta.id];
        }
      };
      ckeditor.instances[ta.id] = editor;
      ta.style.display = 'none';
      ta.style.visibility = 'hidden';
      if (options.throwOn === ta.id) throw new Error('Falha síncrona simulada');
      queue.push(() => {
        const current = { customConfig: 'config.js', extraPlugins: '', autoUpdateElement: true };
        // Assim como no core, só customConfig é lido antes do arquivo legado.
        if (config.customConfig != null) current.customConfig = config.customConfig;
        if (current.customConfig) {
          configLoads.push(current.customConfig);
          try {
            vm.runInContext(legacySource, context, { filename: 'ckeditor/config.js (fixture)', timeout: 1000 });
            ckeditor.editorConfig.call(editor, current);
          } catch (error) {
            configFailures.push(error);
            return; // Uma falha assíncrona não atravessa o try/catch de replace().
          }
        }
        Object.assign(current, config);
        editor.config = current;
        editor.status = 'ready';
      });
      return editor;
    }
  };
  const win = element('window');
  win.CKEDITOR = options.noEditor ? undefined : options.noReplace ? { instances: {} } : ckeditor;
  win.matchMedia = () => ({ matches: false });
  win.location = { hash: options.hash || '' };
  win.history = { replaceState: (...args) => history.push(args) };
  win.bootstrap = { Tab: function (tab) { this.show = () => tabShows.push(tab.id); } };
  const context = vm.createContext({
    window: win, document: doc, CKEDITOR: ckeditor,
    localStorage: { getItem: () => null, setItem() {} }, console
  });
  const run = () => vm.runInContext(source, context, { filename: 'admin.js', timeout: 1000 });
  const flush = () => { while (queue.length) queue.shift()(); };
  const domReady = () => { doc.readyState = 'interactive'; doc.dispatch('DOMContentLoaded'); };
  return {
    textareas, html, doc, win, context, ckeditor, calls, configLoads, configFailures,
    destroyed, tabShows, history, tabs, coverInput, coverBox, coverEmpty,
    run, flush, domReady
  };
}

let passed = 0;
let failed = 0;
function test(label, callback) {
  try { callback(); passed++; console.log('PASS ' + label); }
  catch (error) { failed++; console.error('FAIL ' + label + '\n     ' + error.message.replace(/\n/g, '\n     ')); }
}
function started(options) {
  const fx = fixture(options);
  fx.run(); fx.flush();
  return fx;
}
function readyEditors(fx) { return Object.values(fx.ckeditor.instances).filter(editor => editor.status === 'ready'); }
function allConfigs(fx, check) {
  assert.equal(fx.calls.length, 3, 'esperados três textareas de idiomas');
  fx.calls.forEach(({ config }) => check(config));
}
function snapshot(textareas) {
  return textareas.map(ta => ({ id: ta.id, name: ta.name, value: ta.value, display: ta.style.display, visibility: ta.style.visibility }));
}

console.log('F8-05 A02 — contrato JS real; DOM/CKEDITOR simulados (sem navegador/servidor)');
console.log('Configuração legada na fixture: ' + legacyOrigin);

test('01 · sem $/jQuery: não agenda config.js legado e libera as três instâncias', () => {
  const fx = started();
  assert.equal(vm.runInContext('typeof $', fx.context), 'undefined');
  assert.equal(fx.win.jQuery, undefined);
  assert.equal(fx.configLoads.length, 0, 'config.js não pode ser solicitado');
  assert.equal(fx.configFailures.length, 0, 'nenhum callback legado deve falhar');
  assert.equal(readyEditors(fx).length, 3);
  allConfigs(fx, config => assert.equal(config.customConfig, ''));
});

test('02 · en-us usa idioma local en', () => {
  allConfigs(started({ lang: 'en-us' }), config => assert.equal(config.language, 'en'));
});
test('03 · pt-br usa idioma local pt-br', () => {
  allConfigs(started({ lang: 'pt-br' }), config => assert.equal(config.language, 'pt-br'));
});
test('04 · es-eu usa idioma local es', () => {
  allConfigs(started({ lang: 'es-eu' }), config => assert.equal(config.language, 'es'));
});
test('05 · normaliza caixa/underscore sem depender de data-lang ou jQuery', () => {
  for (const [lang, expected] of [['PT_BR', 'pt-br'], ['ES_EU', 'es'], ['EN_US', 'en']]) {
    allConfigs(started({ lang }), config => assert.equal(config.language, expected));
  }
});
test('06 · idioma ausente/desconhecido, inclusive chave herdada, recua para en', () => {
  for (const lang of [null, '', 'fr-fr', 'constructor']) {
    allConfigs(started({ lang }), config => assert.equal(config.language, 'en'));
  }
});

test('07 · loading aguarda DOMContentLoaded; callback é once e não duplica instâncias', () => {
  const fx = fixture({ readyState: 'loading' });
  fx.run();
  assert.equal(fx.calls.length, 0);
  const rteListener = (fx.doc.handlers.get('DOMContentLoaded') || []).find(item => item.callback.name === 'initRichText');
  assert.ok(rteListener && rteListener.once, 'inicializador RTE deve aguardar o DOM uma vez');
  fx.domReady(); fx.flush(); fx.domReady(); fx.flush();
  assert.equal(fx.calls.length, 3);
  assert.equal(readyEditors(fx).length, 3);
});
test('08 · DOM complete inicializa sem aguardar um evento que já ocorreu', () => {
  const fx = started({ readyState: 'complete' });
  assert.equal(readyEditors(fx).length, 3);
});
test('09 · CKEDITOR ausente mantém textarea, conteúdo, ids, nomes e estilos', () => {
  const fx = fixture({ noEditor: true });
  const before = snapshot(fx.textareas);
  fx.run(); fx.flush();
  assert.deepEqual(snapshot(fx.textareas), before);
  assert.equal(fx.calls.length, 0);
  fx.textareas.forEach(ta => assert.notEqual(ta.dataset.rteReady, '1'));
});
test('10 · CKEDITOR incompleto/sem replace também mantém o fallback', () => {
  const fx = fixture({ noReplace: true });
  const before = snapshot(fx.textareas);
  fx.run(); fx.flush();
  assert.deepEqual(snapshot(fx.textareas), before);
  assert.equal(fx.calls.length, 0);
  fx.textareas.forEach(ta => assert.notEqual(ta.dataset.rteReady, '1'));
});
test('11 · falha síncrona limpa instância parcial sem atualizar/apagar o textarea', () => {
  const fx = fixture({ throwOn: 'input-description-1' });
  fx.textareas[0].style.display = 'block';
  fx.textareas[0].style.visibility = 'visible';
  const before = snapshot([fx.textareas[0]]);
  fx.run(); fx.flush();
  assert.deepEqual(snapshot([fx.textareas[0]]), before);
  assert.deepEqual(fx.destroyed, [{ id: 'input-description-1', skipUpdate: true }]);
  assert.equal(fx.ckeditor.instances['input-description-1'], undefined);
  assert.notEqual(fx.textareas[0].dataset.rteReady, '1');
  assert.equal(readyEditors(fx).length, 2, 'outros idiomas devem continuar');
});
test('12 · mesmo se destroy falhar, restaura visibilidade e preserva o conteúdo', () => {
  const fx = fixture({ throwOn: 'input-description-1', cleanupThrows: true });
  const before = snapshot([fx.textareas[0]]);
  fx.run(); fx.flush();
  assert.deepEqual(snapshot([fx.textareas[0]]), before);
  assert.equal(fx.destroyed.length, 1);
  assert.equal(readyEditors(fx).length, 2);
});
test('13 · replace retorna null: não marca textarea como inicializado', () => {
  const fx = started({ returnNull: true });
  assert.equal(fx.calls.length, 3);
  fx.textareas.forEach(ta => assert.notEqual(ta.dataset.rteReady, '1'));
});
test('14 · marcador e instância já existente evitam reinicialização/duplicidade', () => {
  const fx = fixture();
  fx.textareas[0].dataset.rteReady = '1';
  const existing = { status: 'ready', sentinel: true };
  fx.ckeditor.instances[fx.textareas[1].id] = existing;
  fx.run(); fx.flush(); fx.run(); fx.flush();
  assert.equal(fx.calls.length, 1);
  assert.equal(fx.calls[0].ta.id, fx.textareas[2].id);
  assert.equal(fx.ckeditor.instances[fx.textareas[1].id], existing);
});
test('15 · não habilita reamur/codemirror nem URLs de filemanager/upload', () => {
  allConfigs(started(), config => {
    assert.equal(config.extraPlugins, '');
    const removed = config.removePlugins.split(',');
    ['elementspath', 'exportpdf', 'reamur', 'codemirror'].forEach(plugin => assert.ok(removed.includes(plugin)));
    ['filebrowserBrowseUrl', 'filebrowserImageBrowseUrl', 'filebrowserUploadUrl', 'filebrowserImageUploadUrl'].forEach(key => assert.equal(config[key], ''));
    assert.equal(Object.hasOwn(config, 'versionCheck'), false, 'não desabilitar verificação/aviso de versão');
  });
});
test('16 · preserva toolbar, altura, formato e contrato HTML/updateElement', () => {
  const fx = fixture();
  const before = fx.textareas.map(ta => ({ id: ta.id, name: ta.name, value: ta.value }));
  fx.run(); fx.flush();
  assert.deepEqual(fx.textareas.map(ta => ({ id: ta.id, name: ta.name, value: ta.value })), before);
  allConfigs(fx, config => {
    assert.equal(config.height, 320);
    assert.equal(config.format_tags, 'p;h2;h3;h4');
    assert.equal(config.allowedContent, true);
    assert.equal(config.autoUpdateElement, true);
    assert.equal(config.htmlEncodeOutput, false);
    assert.equal(config.entities, false);
    assert.equal(config.resize_enabled, true);
    assert.equal(config.removeButtons, '');
    assert.deepEqual(JSON.parse(JSON.stringify(config.toolbar)), [
      { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', 'RemoveFormat'] },
      { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Blockquote'] },
      { name: 'links', items: ['Link', 'Unlink'] },
      { name: 'insert', items: ['Image', 'Table', 'HorizontalRule'] },
      { name: 'styles', items: ['Format'] },
      { name: 'tools', items: ['Maximize', 'Source'] }
    ]);
  });
});
test('17 · tabs continuam usando o código do conteúdo na âncora, sem remapeá-lo', () => {
  const fx = started({ lang: 'pt-br', tabs: true, hash: '#lang-es-eu' });
  assert.deepEqual(fx.tabShows, ['tab-es-eu']);
  fx.tabs[1].dispatch('shown.bs.tab');
  assert.equal(fx.history.at(-1)[2], '#lang-pt-br');
});
test('18 · evento input da capa ainda atualiza/limpa o preview fora do RTE', () => {
  const fx = started({ cover: true });
  fx.coverInput.value = 'catalog/view/image/blog/capa.webp';
  fx.coverInput.dispatch('input');
  const image = fx.coverBox.querySelector('img');
  assert.ok(image);
  assert.equal(image.src, 'https://example.invalid/catalog/view/image/blog/capa.webp');
  image.onload();
  assert.equal(fx.coverEmpty.hidden, true);
  fx.coverInput.value = '';
  fx.coverInput.dispatch('input');
  assert.equal(fx.coverBox.querySelector('img'), null);
  assert.equal(fx.coverEmpty.hidden, false);
});

console.log('\nResultado: ' + passed + ' PASS / ' + failed + ' FAIL — 18 casos; DOM/CKEDITOR simulados.');
console.log('NÃO valida iframe/editor real, envio de formulário, banco, arquivos, ACL/CSRF ou persistência.');
process.exitCode = failed ? 1 : 0;
