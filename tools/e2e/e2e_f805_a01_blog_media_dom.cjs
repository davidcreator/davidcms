#!/usr/bin/env node
'use strict';

/**
 * F8-05 / Ajuste 01 — regressão da inicialização de blog-media.js.
 * Executa o arquivo REAL em node:vm com DOM, Bootstrap e HTTP simulados.
 * Não é navegador real, não valida PHP/ACL/CSRF no servidor e não acessa rede.
 * Sem dependências externas. Uso: node <este arquivo> <blog-media.js|->
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const sourcePath = process.argv[2] || path.resolve(__dirname, '../../dev/admin/view/js/blog-media.js');
let source;
try {
  source = fs.readFileSync(sourcePath === '-' ? 0 : sourcePath, 'utf8');
} catch (error) {
  console.error('ERRO: não foi possível ler o JavaScript: ' + error.message);
  process.exit(2);
}

class SimulatedEvent {
  constructor(type, options = {}) {
    this.type = type;
    this.bubbles = !!options.bubbles;
    this.target = null;
  }
}

class Target {
  constructor() {
    this.listeners = new Map();
  }
  addEventListener(type, callback, options = {}) {
    const list = this.listeners.get(type) || [];
    list.push({ callback, once: !!options.once });
    this.listeners.set(type, list);
  }
  listenerCount(type) {
    return (this.listeners.get(type) || []).length;
  }
  dispatchEvent(event) {
    event.target = this;
    for (const item of [...(this.listeners.get(event.type) || [])]) {
      if (item.once) {
        const list = this.listeners.get(event.type) || [];
        this.listeners.set(event.type, list.filter((entry) => entry !== item));
      }
      item.callback.call(this, event);
    }
    return true;
  }
}

class Element extends Target {
  constructor(tagName = 'div') {
    super();
    this.tagName = tagName.toUpperCase();
    this.attributes = new Map();
    this.children = [];
    this.style = {};
    this.value = '';
    this.textContent = '';
    this.className = '';
    this.hidden = false;
    this.disabled = false;
    this.files = [];
  }
  setAttribute(name, value) {
    this.attributes.set(name, String(value));
  }
  getAttribute(name) {
    return this.attributes.get(name) ?? null;
  }
  appendChild(child) {
    this.children.push(child);
    return child;
  }
  replaceChildren(...children) {
    this.children = [...children];
    this.textContent = '';
  }
  click() {
    if (!this.disabled) {
      this.dispatchEvent(new SimulatedEvent('click'));
    }
  }
}

// O corpo do FormData também é simulado; nenhum upload real é executado.
class SimulatedFormData {
  constructor() { this.values = new Map(); }
  append(key, value) { this.values.set(key, value); }
  get(key) { return this.values.get(key) ?? null; }
}

const elementIds = [
  'blogMediaModal', 'input-image', 'blogMediaAlert', 'blogMediaFolders',
  'blogMediaFiles', 'blogMediaEmpty', 'blogMediaBreadcrumb', 'blogMediaPageLabel',
  'blogMediaSearch', 'blogMediaPrevious', 'blogMediaNext', 'blogMediaUploadButton',
  'blogMediaCreateFolder', 'blogMediaUseUrl', 'blogMediaFile',
  'blogMediaFolderName', 'blogMediaExternalUrl',
];

function pageResponse(url) {
  const folder = url.searchParams.get('folder') || '';
  const localPath = 'view/image/blog/' + (folder ? folder + '/' : '') + 'capa.png';
  return {
    success: true,
    folder,
    folders: folder ? [] : [{ name: 'ensaio', folder: 'ensaio', empty: true }],
    files: [{ name: 'capa.png', path: localPath, url: 'https://example.test/catalog/' + localPath, referenced: false }],
    total: 1, page: 1, pages: 1,
  };
}

function fixture({ readyState = 'complete', visible = true, invalidConfig = false, responder } = {}) {
  const nodes = new Map(elementIds.map((id) => [id, new Element(id === 'input-image' ? 'input' : 'div')]));
  const document = new Target();
  document.readyState = readyState;
  document.visible = visible;
  document.lookups = 0;
  document.getElementById = (id) => {
    document.lookups += 1;
    return document.visible ? nodes.get(id) || null : null;
  };
  document.createElement = (tag) => new Element(tag);
  const config = {
    listUrl: 'https://example.test/admin/index.php?route=blog/blog.mediaList',
    uploadUrl: 'https://example.test/admin/index.php?route=blog/blog.mediaUpload',
    folderUrl: 'https://example.test/admin/index.php?route=blog/blog.mediaFolder',
    deleteUrl: 'https://example.test/admin/index.php?route=blog/blog.mediaDelete',
    csrfToken: 'fixture-csrf-not-a-credential',
    strings: {
      root: 'Biblioteca', select: 'Selecionar', delete: 'Excluir',
      page: 'Página %d de %d', loading: 'Carregando', empty: 'Vazio',
      noMatches: 'Sem resultados', genericError: 'Falha de teste',
      invalidUrl: 'URL inválida', chooseFile: 'Escolha um arquivo',
      confirmFileDelete: 'Excluir arquivo?', confirmFolderDelete: 'Excluir pasta?',
    },
  };
  nodes.get('blogMediaModal').setAttribute('data-media-config', invalidConfig ? '{' : JSON.stringify(config));

  const calls = [];
  const timers = new Map();
  let timerSequence = 0;
  let hidden = 0;
  const window = {
    location: { href: 'https://example.test/admin/index.php?route=blog/blog.add' },
    bootstrap: { Modal: { getInstance: () => ({ hide: () => { hidden += 1; } }) } },
    confirm: () => true,
    clearTimeout: (id) => timers.delete(id),
    setTimeout: (callback) => { const id = ++timerSequence; timers.set(id, callback); return id; },
  };
  const context = vm.createContext({
    document, window, URL, Event: SimulatedEvent, FormData: SimulatedFormData,
    fetch: async (url, options = {}) => {
      const parsed = new URL(String(url));
      calls.push({ url: parsed, options });
      const result = responder ? await responder(parsed, options) : pageResponse(parsed);
      return { json: async () => result };
    },
  });
  return {
    nodes, document, calls, config,
    hiddenCount: () => hidden,
    run: () => vm.runInContext(source, context, { filename: sourcePath, timeout: 1000 }),
    ready: () => {
      document.visible = true;
      document.readyState = 'interactive';
      document.dispatchEvent(new SimulatedEvent('DOMContentLoaded'));
    },
    open: () => nodes.get('blogMediaModal').dispatchEvent(new SimulatedEvent('shown.bs.modal')),
    flushTimers: () => {
      const pending = [...timers.values()];
      timers.clear();
      for (const callback of pending) { callback(); }
    },
  };
}

function findElement(root, predicate) {
  if (predicate(root)) { return root; }
  for (const child of root.children) {
    const found = findElement(child, predicate);
    if (found) { return found; }
  }
  return null;
}

async function settle() {
  await new Promise((resolve) => setImmediate(resolve));
  await new Promise((resolve) => setImmediate(resolve));
}

function assertBindings(f) {
  const contracts = [
    ['blogMediaModal', 'shown.bs.modal'], ['blogMediaSearch', 'input'],
    ['blogMediaPrevious', 'click'], ['blogMediaNext', 'click'],
    ['blogMediaUploadButton', 'click'], ['blogMediaCreateFolder', 'click'],
    ['blogMediaUseUrl', 'click'],
  ];
  for (const [id, event] of contracts) {
    assert.equal(f.nodes.get(id).listenerCount(event), 1, id + ': listener ausente/duplicado');
  }
}

const tests = [
  ['head/loading: aguarda o DOM e registra os eventos após DOMContentLoaded', async () => {
    const f = fixture({ readyState: 'loading', visible: false });
    f.run();
    assert.equal(f.document.listenerCount('DOMContentLoaded'), 1, 'script encerrou sem aguardar o DOM');
    assert.equal(f.document.lookups, 0, 'não deve consultar campos antes de o DOM estar pronto');
    assert.equal(f.calls.length, 0);
    f.ready();
    assertBindings(f);
    f.open();
    await settle();
    assert.equal(f.calls.length, 1, 'abertura do modal não iniciou a listagem');
    assert.equal(f.nodes.get('blogMediaFiles').children.length, 1);
  }],
  ['interactive: inicializa imediatamente sem esperar outro evento', async () => {
    const f = fixture({ readyState: 'interactive' });
    f.run();
    assert.equal(f.document.listenerCount('DOMContentLoaded'), 0);
    assertBindings(f);
  }],
  ['complete: inicializa imediatamente com o documento carregado', async () => {
    const f = fixture();
    f.run();
    assertBindings(f);
  }],
  ['DOMContentLoaded: listener é consumido uma vez e não duplica handlers', async () => {
    const f = fixture({ readyState: 'loading', visible: false });
    f.run();
    f.ready();
    assert.equal(f.document.listenerCount('DOMContentLoaded'), 0);
    f.ready();
    assertBindings(f);
  }],
  ['página sem modal/campo: saída silenciosa e nenhuma requisição', async () => {
    const f = fixture({ visible: false });
    f.run();
    assert.equal(f.calls.length, 0);
    assert.equal(f.nodes.get('blogMediaModal').listenerCount('shown.bs.modal'), 0);
  }],
  ['configuração inválida: preserva a saída segura existente', async () => {
    const f = fixture({ invalidConfig: true });
    f.run();
    assert.equal(f.nodes.get('blogMediaModal').listenerCount('shown.bs.modal'), 0);
    assert.equal(f.calls.length, 0);
  }],
  ['listagem: usa a rota existente e credenciais same-origin', async () => {
    const f = fixture({ readyState: 'loading', visible: false });
    f.run();
    f.ready();
    f.open();
    await settle();
    assert.equal(f.calls.length, 1);
    const request = f.calls[0];
    assert.equal(request.url.searchParams.get('route'), 'blog/blog.mediaList');
    assert.equal(request.url.searchParams.get('folder'), '');
    assert.equal(request.url.searchParams.get('page'), '1');
    assert.equal(request.options.credentials, 'same-origin');
    assert.equal(f.nodes.get('blogMediaPageLabel').textContent, 'Página 1 de 1');
  }],
  ['seleção: preenche a capa, emite input/change e fecha o modal', async () => {
    const f = fixture({ readyState: 'loading', visible: false });
    f.run();
    f.ready();
    const events = [];
    f.nodes.get('input-image').addEventListener('input', (event) => events.push([event.type, event.bubbles]));
    f.nodes.get('input-image').addEventListener('change', (event) => events.push([event.type, event.bubbles]));
    f.open();
    await settle();
    const button = findElement(f.nodes.get('blogMediaFiles'), (el) => el.getAttribute('aria-label') === 'Selecionar: capa.png');
    assert.ok(button, 'botão de seleção não renderizado');
    button.click();
    assert.equal(f.nodes.get('input-image').value, 'view/image/blog/capa.png');
    assert.deepEqual(events, [['input', true], ['change', true]]);
    assert.equal(f.hiddenCount(), 1);
  }],
  ['pastas e busca: mantêm os parâmetros e a listagem do módulo', async () => {
    const f = fixture({ readyState: 'loading', visible: false });
    f.run();
    f.ready();
    f.open();
    await settle();
    const folder = findElement(f.nodes.get('blogMediaFolders'), (el) => el.getAttribute('aria-label') === 'ensaio');
    assert.ok(folder, 'pasta não renderizada');
    folder.click();
    await settle();
    assert.equal(f.calls.at(-1).url.searchParams.get('folder'), 'ensaio');
    f.nodes.get('blogMediaSearch').value = ' capa ';
    f.nodes.get('blogMediaSearch').dispatchEvent(new SimulatedEvent('input'));
    f.flushTimers();
    await settle();
    assert.equal(f.calls.at(-1).url.searchParams.get('folder'), 'ensaio');
    assert.equal(f.calls.at(-1).url.searchParams.get('search'), 'capa');
    assert.equal(f.calls.at(-1).url.searchParams.get('page'), '1');
  }],
  ['URL externa: rejeita outro protocolo e mantém HTTP(S)', async () => {
    const f = fixture({ readyState: 'loading', visible: false });
    f.run();
    f.ready();
    f.nodes.get('blogMediaExternalUrl').value = 'ftp://example.test/capa.png';
    f.nodes.get('blogMediaUseUrl').click();
    assert.equal(f.nodes.get('input-image').value, '');
    assert.equal(f.nodes.get('blogMediaAlert').hidden, false);
    assert.equal(f.nodes.get('blogMediaAlert').textContent, 'URL inválida');
    f.nodes.get('blogMediaExternalUrl').value = 'https://example.test/capa.png';
    f.nodes.get('blogMediaUseUrl').click();
    assert.equal(f.nodes.get('input-image').value, 'https://example.test/capa.png');
    assert.equal(f.hiddenCount(), 1);
    assert.equal(f.calls.length, 0);
  }],
];

(async () => {
  let passed = 0;
  let failed = 0;
  console.log('F8-05 A01 — regressão JS; DOM/HTTP/Bootstrap simulados; sem banco/rede');
  for (const [name, test] of tests) {
    try {
      await test();
      passed += 1;
      console.log('PASS ' + passed + ' — ' + name);
    } catch (error) {
      failed += 1;
      console.error('FAIL — ' + name + ': ' + error.message);
    }
  }
  console.log('Resumo DOM: ' + passed + ' pass / ' + failed + ' fail');
  console.log('Navegador real, PHP, ACL/CSRF de servidor e persistência: NÃO avaliados por este teste.');
  process.exitCode = failed ? 1 : 0;
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
