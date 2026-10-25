/*
 * ReamurCMS native Blog block editor — F9-14 base + F9-22 structured inline rich text.
 * In-memory model; form submission binding stays in blog-block-editor-init.js.
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
      return (/^(?:image|view\/image\/blog)\//).test(path) && !path.split('/').some((part) => part === '.' || part === '..');
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
    try { const parsed = new URL(value); return ['https:', 'http:'].includes(parsed.protocol) && !parsed.username && !parsed.password; } catch (_) { return false; }
  }

  const INLINE_MARKS = ['bold', 'italic', 'underline', 'strike', 'code'];
  const BLOCK_TEXT_TYPES = new Set(['core/paragraph', 'core/heading', 'core/list-item', 'core/quote', 'core/button']);
  const INLINE_TAG_MARK = { STRONG: 'bold', B: 'bold', EM: 'italic', I: 'italic', U: 'underline', S: 'strike', DEL: 'strike', CODE: 'code' };

  function runsFromText(text) {
    return text === '' ? [] : [{ text: String(text), marks: [], href: null }];
  }

  function canonicalRuns(runs) {
    const markOrder = new Map(INLINE_MARKS.map((mark, index) => [mark, index]));
    const out = [];
    (Array.isArray(runs) ? runs : []).forEach((run) => {
      const marks = (run.marks || []).slice().sort((a, b) => (markOrder.get(a) || 0) - (markOrder.get(b) || 0));
      const next = { text: String(run.text || ''), marks, href: run.href == null ? null : String(run.href) };
      const last = out[out.length - 1];
      if (last && JSON.stringify(last.marks) === JSON.stringify(next.marks) && last.href === next.href) last.text += next.text;
      else out.push(next);
    });
    return out;
  }

  function upgradeDocument(input) {
    const doc = CLONE(input);
    if (!doc || doc.format_version !== 1 || !Array.isArray(doc.blocks)) return doc;
    function visit(node) {
      if (!node || typeof node !== 'object') return;
      if (BLOCK_TEXT_TYPES.has(node.type) && typeof node.content === 'string') node.content = runsFromText(node.content);
      if (Array.isArray(node.children)) node.children.forEach(visit);
    }
    doc.blocks.forEach(visit);
    doc.format_version = 2;
    return doc;
  }

  function validInlineRuns(runs, type) {
    if (!Array.isArray(runs) || runs.length > 1000) return false;
    let chars = 0;
    return runs.every((run) => {
      if (!run || typeof run !== 'object' || Array.isArray(run) || Object.keys(run).sort().join(',') !== 'href,marks,text') return false;
      if (typeof run.text !== 'string' || run.text.length === 0 || run.text.length > 262144 || !Array.isArray(run.marks) || run.marks.length > INLINE_MARKS.length) return false;
      chars += run.text.length;
      if (chars > 262144 || new Set(run.marks).size !== run.marks.length || run.marks.some((mark) => !INLINE_MARKS.includes(mark))) return false;
      if (run.href !== null && (!validActionUrl(run.href) || type === 'core/button')) return false;
      return true;
    });
  }

  function validateDocument(doc) {
    const errors = [];
    if (!doc || typeof doc !== 'object' || Array.isArray(doc) || Object.keys(doc).sort().join(',') !== 'blocks,format_version' || ![1,2].includes(doc.format_version) || !Array.isArray(doc.blocks)) return { valid: false, errors: ['root_shape_invalid'] };
    const version = doc.format_version;
    if (doc.blocks.length > 1000) return { valid: false, errors: ['too_many_blocks'] };
    const ids = new Set(); let nodes = 0;
    function visit(node, depth, parent) {
      nodes += 1;
      if (!node || typeof node !== 'object' || Array.isArray(node) || depth > 16 || nodes > 2000 || Object.keys(node).sort().join(',') !== 'attributes,children,content,id,type') { errors.push('node_shape_or_limit'); return; }
      if (typeof node.id !== 'string' || !/^[A-Za-z0-9_-]{1,96}$/.test(node.id) || ids.has(node.id)) errors.push('node_id_invalid');
      else ids.add(node.id);
      if (typeof node.type !== 'string' || !TYPES.some(([type]) => type === node.type) && node.type !== 'core/list-item' && node.type !== 'core/column') errors.push('unknown_type');
      if (!node.attributes || typeof node.attributes !== 'object' || Array.isArray(node.attributes) || !Array.isArray(node.children)) { errors.push('node_fields_invalid'); return; }
      const textBlock = BLOCK_TEXT_TYPES.has(node.type);
      if (version === 1 && typeof node.content !== 'string') errors.push('legacy_content_invalid');
      else if (version === 2 && textBlock && !validInlineRuns(node.content, node.type)) errors.push('rich_content_invalid');
      else if ((!textBlock || version === 1) && (typeof node.content !== 'string' || (!textBlock && version === 2 && node.content !== ''))) errors.push('content_invalid');
      if (typeof node.content === 'string' && node.content.length > 262144) errors.push('content_too_large');
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

  function normalizeDomInline(root, blockType) {
    const runs = [];
    function append(text, marks, href) {
      if (!text) return;
      const safeHref = href && validActionUrl(href) && blockType !== 'core/button' ? href : null;
      runs.push({ text, marks: [...new Set(marks)].filter((mark) => INLINE_MARKS.includes(mark)), href: safeHref });
    }
    function visit(node, marks, href) {
      if (node.nodeType === 3) { append(node.nodeValue || '', marks, href); return; }
      if (node.nodeType !== 1) return;
      const element = node;
      const nextMarks = marks.slice();
      if (INLINE_TAG_MARK[element.tagName] && !nextMarks.includes(INLINE_TAG_MARK[element.tagName])) nextMarks.push(INLINE_TAG_MARK[element.tagName]);
      const nextHref = element.tagName === 'A' ? (element.getAttribute('href') || null) : href;
      if (element.tagName === 'BR') { append('\n', marks, href); return; }
      visitChildren(element, nextMarks, nextHref);
    }
    function visitChildren(parent, marks, href) {
      const children = Array.from(parent.childNodes);
      children.forEach((child, index) => {
        const blockChild = child.nodeType === 1 && ['DIV','P'].includes(child.tagName);
        if (blockChild && index > 0) append('\n', marks, href);
        if (blockChild) visitChildren(child, marks, href);
        else visit(child, marks, href);
      });
    }
    visitChildren(root, [], null);
    return canonicalRuns(runs);
  }

  function buildInlineDom(root, runs) {
    root.replaceChildren();
    runs.forEach((run) => {
      let parent = root;
      if (run.href) { const link = document.createElement('a'); link.href = run.href; link.rel = 'nofollow noopener noreferrer'; parent.append(link); parent = link; }
      run.marks.forEach((mark) => {
        const tags = { bold:'strong', italic:'em', underline:'u', strike:'s', code:'code' };
        const wrapper = document.createElement(tags[mark] || 'span'); parent.append(wrapper); parent = wrapper;
      });
      parent.append(document.createTextNode(run.text));
    });
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
      this.inlineBookmark = null;
      this.linkDraft = '';
      this.inlineNotice = '';
      this.strings = Object.assign({bold:'Negrito',italic:'Itálico',underline:'Sublinhado',strike:'Tachado',code:'Código',link:'Link',linkPlaceholder:'https:// ou /caminho',applyLink:'Aplicar link',removeLink:'Remover link',invalidLink:'URL de link inválida.',toolbar:'Formatação inline',textLabel:'Texto do bloco',placeholder:'Digite o texto…'}, this.options.strings || {});
      const rawInitial = this.options.document || { format_version: 1, blocks: [] };
      const initial = upgradeDocument(rawInitial);
      const validation = validateDocument(initial);
      this.locked = !validation.valid;
      this.originalDocument = this.locked ? initial : null;
      this.state = validation.valid ? CLONE(initial) : { format_version: 2, blocks: [] };
      this.initialError = validation.valid ? '' : validation.errors.join(', ');
      this.onChange = typeof this.options.onChange === 'function' ? this.options.onChange : function () {};
      this.root.classList.add('rb-editor');
      this.root.dataset.instance = String(this.instance);
      this.root.setAttribute('data-preview', this.preview);
      this.bind();
      this.render();
    }

    newId() { this.idCounter += 1; return 'rb' + this.instance + '-' + this.idCounter; }
    node(type, attributes, content, children) { const value = BLOCK_TEXT_TYPES.has(type) ? (Array.isArray(content) ? canonicalRuns(content) : runsFromText(String(content || ''))) : (content || ''); return { id: this.newId(), type, attributes: attributes || {}, children: children || [], content: value }; }
    snapshot() { return JSON.stringify(this.state); }
    label(type) { const found = TYPES.find((entry) => entry[0] === type); return found ? found[1] : (type === 'core/list-item' ? 'Item de lista' : 'Coluna'); }

    bind() {
      this.root.addEventListener('mousedown', (event) => {
        const control = event.target.closest('[data-action="format-mark"],[data-action="link-apply"],[data-action="link-remove"]');
        if (!control || !this.root.contains(control)) return;
        this.captureInlineSelection();
        event.preventDefault();
      });
      this.root.addEventListener('focusin', (event) => {
        const editor = event.target.closest('[data-edit-content]');
        if (editor) this.selectNode(editor.dataset.editContent);
      });
      this.root.addEventListener('mouseup', () => this.captureInlineSelection());
      this.root.addEventListener('keyup', (event) => {
        if (event.target.closest('[data-edit-content]')) this.captureInlineSelection();
      });
      global.document.addEventListener('selectionchange', () => this.captureInlineSelection());
      this.root.addEventListener('click', (event) => {
        const editorLink = event.target.closest('[data-edit-content] a');
        if (editorLink) { event.preventDefault(); return; }
        const button = event.target.closest('[data-action]');
        if (button && this.root.contains(button)) { this.action(button.dataset.action, button); return; }
        if (event.target.closest('textarea,input,select,button,[contenteditable="true"]')) return;
        const card = event.target.closest('[data-node-id]');
        if (card && this.root.contains(card)) this.selectNode(card.dataset.nodeId);
      });
      this.root.addEventListener('input', (event) => {
        const linkInput = event.target.closest('[data-inline-link-input]');
        if (linkInput) { this.linkDraft = linkInput.value; return; }
        const field = event.target.closest('[data-edit-content]');
        if (!field) return;
        const id = field.dataset.editContent;
        this.updateNode(id, (node) => { node.content = normalizeDomInline(field, node.type); }, false);
        this.captureInlineSelection();
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
        if (event.ctrlKey || event.metaKey) {
          const key = event.key.toLowerCase();
          const marks = { b: 'bold', i: 'italic', u: 'underline' };
          if (marks[key] && event.target.closest('[data-edit-content]')) { event.preventDefault(); this.captureInlineSelection(); this.applyInlineMark(marks[key]); return; }
          if (key === 'k' && event.target.closest('[data-edit-content]')) { event.preventDefault(); this.captureInlineSelection(); const link = this.root.querySelector('[data-inline-link-input]'); if (link) link.focus(); return; }
        }
        if (event.key === 'Enter' && event.target.closest('[data-edit-content]')) { event.preventDefault(); this.insertPlainText(event.target.closest('[data-edit-content]'), '\n'); return; }
        if (!['ArrowUp','ArrowDown'].includes(event.key) || !event.altKey) return;
        const card = event.target.closest('[data-node-id]'); if (!card) return;
        event.preventDefault(); this.moveNode(card.dataset.nodeId, event.key === 'ArrowUp' ? -1 : 1);
      });
      this.root.addEventListener('paste', (event) => {
        const field = event.target.closest('[data-edit-content]');
        if (!field) return;
        event.preventDefault();
        const text = event.clipboardData ? event.clipboardData.getData('text/plain') : '';
        this.insertPlainText(field, text);
      });
      this.root.addEventListener('dragstart', (event) => {
        if (event.target.closest('button,input,select,[contenteditable="true"]')) return;
        const card = event.target.closest('[data-node-id][data-root-node="1"]');
        if (!card) return; this.dragId = card.dataset.nodeId;
        if (event.dataTransfer) { event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', this.dragId); }
      });
      this.root.addEventListener('dragover', (event) => { if (event.target.closest('[data-root-node="1"]')) event.preventDefault(); });
      this.root.addEventListener('drop', (event) => {
        const editor = event.target.closest('[data-edit-content]');
        if (editor) { event.preventDefault(); this.insertPlainText(editor, event.dataTransfer ? event.dataTransfer.getData('text/plain') : ''); return; }
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
      else if (name === 'media') { global.document.dispatchEvent(new CustomEvent('blog-block-media-open', { detail: { editor: this } })); }
      else if (name === 'format-mark') this.applyInlineMark(button.dataset.mark);
      else if (name === 'link-apply') this.applyInlineLink(this.linkDraft);
      else if (name === 'link-remove') this.applyInlineLink(null);
      else if (name === 'preview') { this.preview = button.dataset.size; this.root.dataset.preview = this.preview; this.root.querySelectorAll('[data-action="preview"]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.size === this.preview))); }
    }

    findEditor(id) { return Array.from(this.root.querySelectorAll('[data-edit-content]')).find((node) => node.dataset.editContent === id) || null; }

    selectNode(id) {
      if (!id || this.selectedId === id) return;
      this.selectedId = id;
      this.root.querySelectorAll('.rb-block').forEach((card) => card.classList.toggle('is-selected', card.dataset.nodeId === id));
      this.root.querySelectorAll('.rb-inline-toolbar').forEach((toolbar) => toolbar.remove());
      const found = this.findPath(this.state.blocks, id);
      const editor = this.findEditor(id);
      if (found && editor && BLOCK_TEXT_TYPES.has(found.node.type)) editor.before(this.renderInlineToolbar(found.node));
      const oldInspector = this.root.querySelector('.rb-inspector');
      if (oldInspector) oldInspector.replaceWith(this.renderInspector());
    }

    renderInlineToolbar(node) {
      const toolbar = this.el('div', 'rb-inline-toolbar');
      toolbar.setAttribute('role', 'toolbar');
      toolbar.setAttribute('aria-label', this.strings.toolbar);
      INLINE_MARKS.forEach((mark) => {
        const button = this.button(this.strings[mark] || mark, 'format-mark', { mark });
        button.setAttribute('aria-label', this.strings[mark] || mark);
        button.setAttribute('aria-pressed', 'false');
        toolbar.append(button);
      });
      if (node.type !== 'core/button') {
        const link = this.el('input', 'rb-inline-link');
        link.type = 'text'; link.value = this.linkDraft; link.placeholder = this.strings.linkPlaceholder;
        link.setAttribute('aria-label', this.strings.link); link.dataset.inlineLinkInput = '1';
        toolbar.append(link, this.button(this.strings.applyLink, 'link-apply'), this.button(this.strings.removeLink, 'link-remove'));
      }
      const status = this.el('span', 'rb-inline-status', this.inlineNotice);
      status.setAttribute('role', 'status'); toolbar.append(status);
      return toolbar;
    }

    captureInlineSelection() {
      const selection = global.getSelection();
      if (!selection || !selection.rangeCount || selection.isCollapsed) return;
      const range = selection.getRangeAt(0);
      const editor = range.commonAncestorContainer.nodeType === 1
        ? range.commonAncestorContainer.closest && range.commonAncestorContainer.closest('[data-edit-content]')
        : range.commonAncestorContainer.parentElement && range.commonAncestorContainer.parentElement.closest('[data-edit-content]');
      if (!editor || !this.root.contains(editor)) return;
      const startRange = global.document.createRange(); startRange.selectNodeContents(editor); startRange.setEnd(range.startContainer, range.startOffset);
      const endRange = global.document.createRange(); endRange.selectNodeContents(editor); endRange.setEnd(range.endContainer, range.endOffset);
      this.inlineBookmark = { nodeId: editor.dataset.editContent, start: startRange.toString().length, end: endRange.toString().length };
      if (this.selectedId !== editor.dataset.editContent) this.selectNode(editor.dataset.editContent);
      this.updateInlineToolbarState();
    }

    updateInlineToolbarState() {
      const bookmark = this.inlineBookmark;
      if (!bookmark || bookmark.start === bookmark.end) return;
      const found = this.findPath(this.state.blocks, bookmark.nodeId); if (!found || !Array.isArray(found.node.content)) return;
      let cursor = 0; const selected = [];
      found.node.content.forEach((run) => {
        const start = cursor, end = cursor + run.text.length; cursor = end;
        if (start < bookmark.end && end > bookmark.start) selected.push(run);
      });
      INLINE_MARKS.forEach((mark) => {
        const button = Array.from(this.root.querySelectorAll('[data-action="format-mark"]')).find((item) => item.dataset.mark === mark);
        if (button) button.setAttribute('aria-pressed', String(selected.length > 0 && selected.every((run) => run.marks.includes(mark))));
      });
      const links = selected.map((run) => run.href);
      this.linkDraft = links.length && links.every((href) => href === links[0]) ? (links[0] || '') : '';
      const input = this.root.querySelector('[data-inline-link-input]');
      if (input && global.document.activeElement !== input) input.value = this.linkDraft;
    }

    restoreInlineSelection(bookmark) {
      if (!bookmark) return;
      const editor = this.findEditor(bookmark.nodeId); if (!editor) return;
      const walker = global.document.createTreeWalker(editor, global.NodeFilter.SHOW_TEXT);
      const nodes = []; let current; let total = 0;
      while ((current = walker.nextNode())) { nodes.push({ node: current, start: total, end: total + current.nodeValue.length }); total += current.nodeValue.length; }
      if (!nodes.length) { editor.focus(); return; }
      const locate = (offset) => {
        const item = nodes.find((entry) => offset <= entry.end) || nodes[nodes.length - 1];
        return [item.node, Math.max(0, Math.min(item.node.nodeValue.length, offset - item.start))];
      };
      const [startNode, startOffset] = locate(bookmark.start); const [endNode, endOffset] = locate(bookmark.end);
      const range = global.document.createRange(); range.setStart(startNode, startOffset); range.setEnd(endNode, endOffset);
      editor.focus(); const selection = global.getSelection(); selection.removeAllRanges(); selection.addRange(range);
    }

    insertPlainText(editor, text) {
      if (!editor || !text) return;
      const selection = global.getSelection(); let range;
      if (selection && selection.rangeCount && editor.contains(selection.anchorNode)) range = selection.getRangeAt(0);
      else { range = global.document.createRange(); range.selectNodeContents(editor); range.collapse(false); }
      range.deleteContents();
      const node = global.document.createTextNode(String(text).replace(/\r\n?/g, '\n'));
      range.insertNode(node); range.setStartAfter(node); range.collapse(true);
      if (selection) { selection.removeAllRanges(); selection.addRange(range); }
      editor.dispatchEvent(new Event('input', { bubbles: true }));
    }

    transformRuns(runs, start, end, transform) {
      let cursor = 0; const out = [];
      runs.forEach((run) => {
        const from = cursor, to = cursor + run.text.length; cursor = to;
        if (to <= start || from >= end) { out.push(CLONE(run)); return; }
        const left = Math.max(0, start - from), right = Math.min(run.text.length, end - from);
        if (left > 0) out.push({ text: run.text.slice(0, left), marks: run.marks.slice(), href: run.href });
        const selected = { text: run.text.slice(left, right), marks: run.marks.slice(), href: run.href };
        transform(selected); out.push(selected);
        if (right < run.text.length) out.push({ text: run.text.slice(right), marks: run.marks.slice(), href: run.href });
      });
      return canonicalRuns(out);
    }

    applyInlineMark(mark) {
      const bookmark = this.inlineBookmark;
      if (!bookmark || bookmark.start === bookmark.end || !INLINE_MARKS.includes(mark)) return;
      const found = this.findPath(this.state.blocks, bookmark.nodeId); if (!found || !BLOCK_TEXT_TYPES.has(found.node.type)) return;
      const overlapping = found.node.content.filter((run, index) => {
        const start = found.node.content.slice(0, index).reduce((sum, item) => sum + item.text.length, 0);
        return start < bookmark.end && start + run.text.length > bookmark.start;
      });
      const remove = overlapping.length > 0 && overlapping.every((run) => run.marks.includes(mark));
      const next = CLONE(this.state); const node = this.findPath(next.blocks, bookmark.nodeId).node;
      node.content = this.transformRuns(node.content, bookmark.start, bookmark.end, (run) => {
        run.marks = remove ? run.marks.filter((item) => item !== mark) : [...new Set(run.marks.concat(mark))];
      });
      this.commit(next, bookmark.nodeId); this.inlineBookmark = bookmark; this.restoreInlineSelection(bookmark);
    }

    applyInlineLink(href) {
      const bookmark = this.inlineBookmark;
      if (!bookmark || bookmark.start === bookmark.end) return;
      const found = this.findPath(this.state.blocks, bookmark.nodeId); if (!found || found.node.type === 'core/button') return;
      const target = href == null ? null : String(href).trim();
      if (target !== null && !validActionUrl(target)) {
        this.inlineNotice = this.strings.invalidLink || 'Invalid link.';
        const status = this.root.querySelector('.rb-inline-status'); if (status) status.textContent = this.inlineNotice;
        return;
      }
      const next = CLONE(this.state); const node = this.findPath(next.blocks, bookmark.nodeId).node;
      node.content = this.transformRuns(node.content, bookmark.start, bookmark.end, (run) => { run.href = target; });
      this.linkDraft = target || ''; this.inlineNotice = '';
      this.commit(next, bookmark.nodeId); this.inlineBookmark = bookmark; this.restoreInlineSelection(bookmark);
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
    setSelectedAttribute(key, value) { if (this.locked) return false; const found = this.selectedId ? this.findPath(this.state.blocks, this.selectedId) : null; if (!found || found.node.type !== 'core/image' || key !== 'src') return false; this.updateNode(found.node.id, (node) => { node.attributes.src = value; }); return true; }
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
      const footText = this.options.connectedSave === true
        ? 'As alterações ficam no formulário e só são persistidas ao clicar em Salvar; não há autosave.'
        : 'Rascunho em memória · não salva nem publica. O salvamento será conectado numa tarefa posterior.';
      const foot = this.el('p', 'rb-note', footText); this.root.append(foot);
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
        if (node.id === this.selectedId) card.append(this.renderInlineToolbar(node));
        const editor = this.el('div', 'rb-content'); editor.contentEditable = 'true'; editor.dataset.editContent = node.id;
        editor.setAttribute('role', 'textbox'); editor.setAttribute('aria-multiline', 'true');
        editor.setAttribute('aria-label', this.strings.textLabel + ': ' + this.label(node.type)); editor.setAttribute('data-placeholder', this.strings.placeholder || '');
        buildInlineDom(editor, node.content); card.append(editor);
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
      if (n.type === 'core/image') { addField('Caminho local da mídia (view/image/blog/...)', 'src', n.attributes.src, 'text'); aside.append(this.button('Escolher da biblioteca', 'media'));  addField('Texto alternativo', 'alt', n.attributes.alt, 'text'); }
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
