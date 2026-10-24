/*
 * ReamurCMS native Blog block editor — F9-14 UI component.
 * In-memory only: no form binding, network, persistence, or HTML renderer.
 * Mount with ReamurBlogBlockEditor.mount(element, { document, onChange }).
 */
(function (global) {
  'use strict';

  const TYPES = [
    ['core/paragraph', 'Parágrafo'], ['core/heading', 'Título'], ['core/list', 'Lista'],
    ['core/image', 'Imagem'], ['core/quote', 'Citação'], ['core/video_embed', 'Vídeo'],
    ['core/button', 'Botão'], ['core/separator', 'Separador'], ['core/group', 'Grupo'],
    ['core/columns', 'Colunas']
  ];
  const TEXT_TYPES = new Set(['core/paragraph', 'core/heading', 'core/list-item', 'core/quote', 'core/button']);
  const CLONE = (value) => JSON.parse(JSON.stringify(value));
  let instanceSequence = 0;

  function validLocalImage(src) {
    if (typeof src !== 'string' || !src || /[\x00-\x20\\]/.test(src)) return false;
    try {
      const base = global.location && /^https?:/.test(global.location.origin) ? global.location.origin : 'https://local.invalid';
      const url = new URL(src, base);
      if (url.origin !== base) return false;
      if (url.search || url.hash) return false;
      const path = url.pathname.replace(/^\//, '');
      return path.startsWith('image/') && !path.split('/').some((part) => part === '.' || part === '..');
    } catch (_) { return false; }
  }

  function validEmbed(url) {
    try {
      const parsed = new URL(url);
      return parsed.protocol === 'https:' && ['youtube.com', 'www.youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com'].includes(parsed.hostname.toLowerCase());
    } catch (_) { return false; }
  }

  function validActionUrl(value) {
    if (typeof value !== 'string' || !value.trim() || /[\x00-\x20\\]/.test(value)) return false;
    if (value.startsWith('/') && !value.startsWith('//')) return true;
    try { return ['https:', 'http:'].includes(new URL(value).protocol); } catch (_) { return false; }
  }

  function validateDocument(doc) {
    const errors = [];
    if (!doc || typeof doc !== 'object' || Array.isArray(doc) || Object.keys(doc).sort().join(',') !== 'blocks,format_version' || doc.format_version !== 1 || !Array.isArray(doc.blocks)) return { valid: false, errors: ['root_shape_invalid'] };
    if (doc.blocks.length > 1000) return { valid: false, errors: ['too_many_blocks'] };
    const ids = new Set(); let nodes = 0;
    function visit(node, depth, parent) {
      nodes += 1;
      if (!node || typeof node !== 'object' || Array.isArray(node) || depth > 16 || nodes > 2000 || Object.keys(node).sort().join(',') !== 'attributes,children,content,id,type') { errors.push('node_shape_or_limit'); return; }
      if (typeof node.id !== 'string' || !/^[A-Za-z0-9_-]{1,96}$/.test(node.id) || ids.has(node.id)) errors.push('node_id_invalid');
      else ids.add(node.id);
      if (typeof node.type !== 'string' || !TYPES.some(([type]) => type === node.type) && node.type !== 'core/list-item' && node.type !== 'core/column') errors.push('unknown_type');
      if (!node.attributes || typeof node.attributes !== 'object' || Array.isArray(node.attributes) || !Array.isArray(node.children) || typeof node.content !== 'string') { errors.push('node_fields_invalid'); return; }
      if (node.content.length > 262144) errors.push('content_too_large');
      const a = node.attributes;
      const allowed = {
        'core/paragraph':['align'], 'core/heading':['level'], 'core/list':['ordered'], 'core/list-item':[],
        'core/quote':[], 'core/image':['src','alt','title'], 'core/video_embed':['url'], 'core/button':['url'],
        'core/separator':[], 'core/group':[], 'core/columns':['count'], 'core/column':[]
      }[node.type] || [];
      if (Object.keys(a).some((key) => !allowed.includes(key))) errors.push('attributes_not_allowlisted');
      switch (node.type) {
        case 'core/paragraph': if (!['', 'left', 'center', 'right'].includes(a.align || '')) errors.push('align_invalid'); if (node.children.length) errors.push('paragraph_children'); break;
        case 'core/heading': if (![1,2,3,4,5,6].includes(a.level) || node.children.length) errors.push('heading_invalid'); break;
        case 'core/list': if (typeof a.ordered !== 'boolean' || node.children.length < 1 || node.children.some((child) => !child || child.type !== 'core/list-item')) errors.push('list_invalid'); break;
        case 'core/list-item': if (parent !== 'core/list' || node.children.length) errors.push('list_item_invalid'); break;
        case 'core/quote': if (node.children.length) errors.push('quote_children'); break;
        case 'core/image': if (!validLocalImage(a.src) || typeof a.alt !== 'string' || node.children.length || node.content) errors.push('image_invalid'); break;
        case 'core/video_embed': if (!validEmbed(a.url) || node.children.length || node.content) errors.push('embed_invalid'); break;
        case 'core/button': if (!validActionUrl(a.url) || node.children.length) errors.push('button_invalid'); break;
        case 'core/separator': if (node.children.length || node.content) errors.push('separator_invalid'); break;
        case 'core/group': if (node.content || Object.keys(a).length || node.children.length === 0) errors.push('group_invalid'); break;
        case 'core/columns': if (node.content || Object.keys(a).some((key) => key !== 'count') || ![2,3].includes(a.count) || node.children.length !== a.count || node.children.some((child) => !child || child.type !== 'core/column')) errors.push('columns_invalid'); break;
        case 'core/column': if (parent !== 'core/columns' || node.content || Object.keys(a).length || node.children.length === 0) errors.push('column_invalid'); break;
      }
      node.children.forEach((child) => visit(child, depth + 1, node.type));
    }
    doc.blocks.forEach((node) => visit(node, 0, null));
    return { valid: errors.length === 0, errors: [...new Set(errors)] };
  }

  class Editor {
    constructor(root, options) {
      this.root = root;
      this.options = options || {};
      this.instance = ++instanceSequence;
      this.idCounter = 0;
      this.undoStack = [];
      this.redoStack = [];
      this.selectedId = null;
      this.preview = 'desktop';
      this.dragId = null;
      const initial = this.options.document || { format_version: 1, blocks: [] };
      const validation = validateDocument(initial);
      this.locked = !validation.valid;
      this.originalDocument = this.locked ? initial : null;
      this.state = validation.valid ? CLONE(initial) : { format_version: 1, blocks: [] };
      this.initialError = validation.valid ? '' : validation.errors.join(', ');
      this.onChange = typeof this.options.onChange === 'function' ? this.options.onChange : function () {};
      this.root.classList.add('rb-editor');
      this.root.dataset.instance = String(this.instance);
      this.root.setAttribute('data-preview', this.preview);
      this.bind();
      this.render();
    }

    newId() { this.idCounter += 1; return 'rb' + this.instance + '-' + this.idCounter; }
    node(type, attributes, content, children) { return { id: this.newId(), type, attributes: attributes || {}, children: children || [], content: content || '' }; }
    snapshot() { return JSON.stringify(this.state); }
    label(type) { const found = TYPES.find((entry) => entry[0] === type); return found ? found[1] : (type === 'core/list-item' ? 'Item de lista' : 'Coluna'); }

    bind() {
      this.root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action]');
        if (button && this.root.contains(button)) { this.action(button.dataset.action, button); return; }
        if (event.target.closest('textarea,input,select,button')) return;
        const card = event.target.closest('[data-node-id]');
        if (card && this.root.contains(card) && this.selectedId !== card.dataset.nodeId) { this.selectedId = card.dataset.nodeId; this.render(); }
      });
      this.root.addEventListener('input', (event) => {
        const field = event.target.closest('[data-edit-content]');
        if (!field) return;
        this.updateNode(field.dataset.editContent, (node) => { node.content = field.value; }, false);
      });
      this.root.addEventListener('change', (event) => {
        const field = event.target.closest('[data-attribute]');
        if (!field) return;
        const id = field.dataset.nodeId, key = field.dataset.attribute;
        this.updateNode(id, (node) => {
          if (key === 'ordered') node.attributes[key] = field.value === 'true';
          else if (key === 'level' || key === 'count') node.attributes[key] = Number(field.value);
          else node.attributes[key] = field.value;
        });
      });
      this.root.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') { event.preventDefault(); this.action(event.shiftKey ? 'redo' : 'undo'); return; }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'y') { event.preventDefault(); this.action('redo'); return; }
        if (!['ArrowUp','ArrowDown'].includes(event.key) || !event.altKey) return;
        const card = event.target.closest('[data-node-id]'); if (!card) return;
        event.preventDefault(); this.moveNode(card.dataset.nodeId, event.key === 'ArrowUp' ? -1 : 1);
      });
      this.root.addEventListener('dragstart', (event) => {
        const card = event.target.closest('[data-node-id][data-root-node="1"]');
        if (!card) return; this.dragId = card.dataset.nodeId;
        if (event.dataTransfer) { event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', this.dragId); }
      });
      this.root.addEventListener('dragover', (event) => { if (event.target.closest('[data-root-node="1"]')) event.preventDefault(); });
      this.root.addEventListener('drop', (event) => {
        const target = event.target.closest('[data-node-id][data-root-node="1"]');
        if (!target || !this.dragId) return; event.preventDefault(); this.reorderRoot(this.dragId, target.dataset.nodeId); this.dragId = null;
      });
    }

    action(name, button) {
      if (this.locked) return;
      if (name === 'insert') this.insert(button.dataset.type, button.dataset.count);
      else if (name === 'undo') this.undo();
      else if (name === 'redo') this.redo();
      else if (name === 'delete') this.deleteNode(button.dataset.nodeId || this.selectedId);
      else if (name === 'move-up') this.moveNode(button.dataset.nodeId || this.selectedId, -1);
      else if (name === 'move-down') this.moveNode(button.dataset.nodeId || this.selectedId, 1);
      else if (name === 'preview') { this.preview = button.dataset.size; this.root.dataset.preview = this.preview; this.root.querySelectorAll('[data-action="preview"]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.size === this.preview))); }
    }

    createDefault(type, presetCount) {
      if (type === 'core/heading') return this.node(type, { level: 2 }, 'Título');
      if (type === 'core/list') return this.node(type, { ordered: false }, '', [this.node('core/list-item', {}, 'Novo item'), this.node('core/list-item', {}, 'Novo item')]);
      if (type === 'core/image') return this.node(type, { src: '', alt: '' }, '');
      if (type === 'core/video_embed') return this.node(type, { url: '' }, '');
      if (type === 'core/button') return this.node(type, { url: '/' }, 'Saiba mais');
      if (type === 'core/separator') return this.node(type, {}, '');
      if (type === 'core/group') return this.node(type, {}, '', [this.node('core/paragraph', {}, 'Bloco do grupo')]);
      if (type === 'core/columns') {
        const count = Number(presetCount) === 3 ? 3 : 2;
        const columns = [];
        for (let i = 0; i < count; i += 1) columns.push(this.node('core/column', {}, '', [this.node('core/paragraph', {}, 'Coluna ' + (i + 1))]));
        return this.node(type, { count }, '', columns);
      }
      if (type === 'core/quote') return this.node(type, {}, 'Citação');
      return this.node('core/paragraph', {}, 'Escreva seu conteúdo…');
    }

    insert(type, presetCount) { if (!TYPES.some((entry) => entry[0] === type)) return; const next = CLONE(this.state); const node = this.createDefault(type, presetCount); next.blocks.push(node); this.commit(next, node.id); }

    findPath(nodes, id, parent) {
      for (let i = 0; i < nodes.length; i += 1) {
        if (nodes[i].id === id) return { nodes, index: i, node: nodes[i], parent: parent || null };
        const found = this.findPath(nodes[i].children || [], id, nodes[i]); if (found) return found;
      }
      return null;
    }

    updateNode(id, mutator, rerender) {
      if (this.locked) return;
      const next = CLONE(this.state), found = this.findPath(next.blocks, id); if (!found) return;
      mutator(found.node); this.commit(next, id, rerender !== false);
    }

    deleteNode(id) {
      const next = CLONE(this.state), found = this.findPath(next.blocks, id); if (!found) return;
      found.nodes.splice(found.index, 1);
      if (found.parent && !found.nodes.length) {
        if (found.parent.type === 'core/list') found.nodes.push(this.node('core/list-item', {}, 'Novo item'));
        else if (['core/group','core/column'].includes(found.parent.type)) found.nodes.push(this.node('core/paragraph', {}, 'Escreva seu conteúdo…'));
      }
      this.selectedId = null; this.commit(next, null);
    }

    moveNode(id, delta) {
      const next = CLONE(this.state), found = this.findPath(next.blocks, id); if (!found) return;
      const target = found.index + delta; if (target < 0 || target >= found.nodes.length) return;
      [found.nodes[found.index], found.nodes[target]] = [found.nodes[target], found.nodes[found.index]];
      this.commit(next, id);
    }

    reorderRoot(fromId, toId) {
      if (fromId === toId) return;
      const next = CLONE(this.state), from = next.blocks.findIndex((n) => n.id === fromId), to = next.blocks.findIndex((n) => n.id === toId);
      if (from < 0 || to < 0) return; const [node] = next.blocks.splice(from, 1); next.blocks.splice(to, 0, node); this.commit(next, fromId);
    }

    commit(next, focusId, rerender) {
      this.undoStack.push(this.snapshot()); if (this.undoStack.length > 50) this.undoStack.shift();
      this.redoStack = []; this.state = next; this.selectedId = focusId || this.selectedId;
      const result = validateDocument(this.state);
      this.root.dataset.valid = result.valid ? 'true' : 'false';
      this.onChange(this.getDocument(), result);
      if (rerender !== false) this.render(); else this.updateHistoryButtons();
    }

    undo() { if (!this.undoStack.length) return; this.redoStack.push(this.snapshot()); this.state = JSON.parse(this.undoStack.pop()); this.render(); this.onChange(this.getDocument(), validateDocument(this.state)); }
    redo() { if (!this.redoStack.length) return; this.undoStack.push(this.snapshot()); this.state = JSON.parse(this.redoStack.pop()); this.render(); this.onChange(this.getDocument(), validateDocument(this.state)); }
    getDocument() { return this.locked ? null : CLONE(this.state); }
    serialize() { if (this.locked) throw new Error('document_requires_review'); const check = validateDocument(this.state); if (!check.valid) throw new Error(check.errors.join(',')); return JSON.stringify(this.state); }

    el(tag, className, text) { const element = document.createElement(tag); if (className) element.className = className; if (text !== undefined) element.textContent = text; return element; }
    button(label, action, extra) { const b = this.el('button', 'rb-button', label); b.type = 'button'; b.dataset.action = action; if (extra) Object.keys(extra).forEach((key) => { b.dataset[key] = extra[key]; }); return b; }

    render() {
      this.root.replaceChildren();
      if (this.initialError) { const warn = this.el('p', 'rb-alert', 'Documento inicial inválido: ' + this.initialError + '. Fonte mantida em memória; edição e serialização bloqueadas para revisão.'); this.root.append(warn); return; }
      const toolbar = this.el('div', 'rb-toolbar'); toolbar.setAttribute('role', 'toolbar'); toolbar.setAttribute('aria-label', 'Ferramentas do editor de blocos');
      const insert = this.el('div', 'rb-inserter'); insert.setAttribute('aria-label', 'Inserir bloco');
      TYPES.forEach(([type, label]) => {
        insert.append(this.button('+ ' + label, 'insert', { type }));
        if (type === 'core/columns') insert.append(this.button('+ Colunas 3', 'insert', { type, count: '3' }));
      });
      toolbar.append(insert);
      const history = this.el('div', 'rb-history');
      const undo = this.button('Desfazer', 'undo'); undo.dataset.history = 'undo';
      const redo = this.button('Refazer', 'redo'); redo.dataset.history = 'redo';
      history.append(undo, redo); toolbar.append(history);
      const previews = this.el('div', 'rb-previews');
      [['desktop','Desktop'],['tablet','Tablet'],['mobile','Mobile']].forEach(([size,label]) => { const b = this.button(label, 'preview', { size }); b.setAttribute('aria-pressed', String(size === this.preview)); previews.append(b); });
      toolbar.append(previews); this.root.append(toolbar);

      const layout = this.el('div', 'rb-layout');
      const canvas = this.el('main', 'rb-canvas'); canvas.setAttribute('aria-label', 'Canvas de conteúdo');
      const canvasHead = this.el('div', 'rb-canvas-head', 'Conteúdo'); canvas.append(canvasHead);
      if (!this.state.blocks.length) canvas.append(this.el('p', 'rb-empty', 'Escolha um bloco no inserter para começar.'));
      this.state.blocks.forEach((node) => canvas.append(this.renderNode(node, true)));
      layout.append(canvas, this.renderInspector()); this.root.append(layout);
      const foot = this.el('p', 'rb-note', 'Rascunho em memória · não salva nem publica. O salvamento será conectado numa tarefa posterior.'); this.root.append(foot);
      this.updateHistoryButtons();
    }

    renderNode(node, isRoot) {
      const typeClass = node.type.replace(/[^a-z0-9-]/gi, '-');
      const card = this.el('section', 'rb-block rb-type-' + typeClass + (node.id === this.selectedId ? ' is-selected' : ''));
      if (node.type === 'core/columns') card.classList.add(node.attributes.count === 3 ? 'rb-columns-3' : 'rb-columns-2');
      card.dataset.nodeId = node.id; if (isRoot) { card.dataset.rootNode = '1'; card.draggable = true; }
      const head = this.el('div', 'rb-block-head');
      const title = this.el('span', 'rb-block-title', this.label(node.type)); head.append(title);
      if (isRoot) { const moveUp = this.button('↑', 'move-up', { nodeId: node.id }); moveUp.setAttribute('aria-label', 'Mover para cima'); head.append(moveUp); const moveDown = this.button('↓', 'move-down', { nodeId: node.id }); moveDown.setAttribute('aria-label', 'Mover para baixo'); head.append(moveDown); }
      const del = this.button('Remover', 'delete', { nodeId: node.id }); head.append(del); card.append(head);
      if (TEXT_TYPES.has(node.type)) {
        const textarea = this.el('textarea', 'rb-content'); textarea.value = node.content; textarea.dataset.editContent = node.id; textarea.setAttribute('aria-label', this.label(node.type)); textarea.rows = Math.max(2, Math.min(7, Math.ceil(node.content.length / 55) || 2)); card.append(textarea);
      } else if (node.type === 'core/image') {
        card.append(this.el('p', 'rb-preview-value', node.attributes.alt || 'Imagem sem texto alternativo'));
      } else if (node.type === 'core/video_embed') card.append(this.el('p', 'rb-preview-value', node.attributes.url || 'URL do vídeo será definida no inspetor'));
      else if (node.type === 'core/columns') card.append(this.el('p', 'rb-preview-value', node.attributes.count + ' colunas · empilham em telas estreitas'));
      node.children.forEach((child) => card.append(this.renderNode(child, false)));
      return card;
    }

    renderInspector() {
      const aside = this.el('aside', 'rb-inspector'); aside.setAttribute('aria-label', 'Inspetor de bloco');
      const heading = this.el('h3', 'rb-inspector-title', 'Inspetor'); aside.append(heading);
      const found = this.selectedId ? this.findPath(this.state.blocks, this.selectedId) : null;
      if (!found) { aside.append(this.el('p', 'rb-muted', 'Selecione um bloco para editar seus atributos.')); return aside; }
      aside.append(this.el('p', 'rb-selected-type', this.label(found.node.type)));
      const addField = (label, key, value, type, choices) => {
        const wrap = this.el('label', 'rb-field'); wrap.append(this.el('span', '', label));
        let input;
        if (choices) { input = this.el('select', 'rb-input'); choices.forEach(([val, text]) => { const option = this.el('option', '', text); option.value = val; input.append(option); }); input.value = String(value); }
        else { input = this.el('input', 'rb-input'); input.type = type || 'text'; input.value = value == null ? '' : String(value); }
        input.dataset.attribute = key; input.dataset.nodeId = found.node.id; wrap.append(input); aside.append(wrap);
      };
      const n = found.node;
      if (n.type === 'core/heading') addField('Nível', 'level', n.attributes.level, 'number');
      if (n.type === 'core/paragraph') addField('Alinhamento', 'align', n.attributes.align || 'left', 'text', [['left','Esquerda'],['center','Centro'],['right','Direita']]);
      if (n.type === 'core/list') addField('Tipo de lista', 'ordered', n.attributes.ordered ? 'true' : 'false', 'text', [['false','Marcadores'],['true','Numerada']]);
      if (n.type === 'core/image') { addField('Caminho local (/image/...)', 'src', n.attributes.src, 'text'); addField('Texto alternativo', 'alt', n.attributes.alt, 'text'); }
      if (n.type === 'core/video_embed') addField('URL YouTube/Vimeo', 'url', n.attributes.url, 'url');
      if (n.type === 'core/button') addField('Destino (URL ou caminho local)', 'url', n.attributes.url, 'url');
      if (n.type === 'core/columns') aside.append(this.el('p', 'rb-muted', 'Preset fixo nesta etapa: ' + n.attributes.count + ' colunas.'));
      const actions = this.el('div', 'rb-inspector-actions');
      actions.append(this.button('↑ Mover', 'move-up', { nodeId: n.id }), this.button('↓ Mover', 'move-down', { nodeId: n.id }), this.button('Remover bloco', 'delete', { nodeId: n.id })); aside.append(actions);
      return aside;
    }

    updateHistoryButtons() {
      this.root.querySelectorAll('[data-history="undo"]').forEach((b) => { b.disabled = this.undoStack.length === 0; });
      this.root.querySelectorAll('[data-history="redo"]').forEach((b) => { b.disabled = this.redoStack.length === 0; });
    }
  }

  global.ReamurBlogBlockEditor = {
    mount: function (root, options) { if (!(root instanceof Element)) throw new TypeError('editor root must be an Element'); return new Editor(root, options || {}); },
    validateDocument: validateDocument,
    validLocalImage: validLocalImage,
    validEmbed: validEmbed
  };
})(window);
