/*
 * ReamurCMS native Blog block editor — F9-14 base + F9-22 inline text + F9-23 block UX.
 * In-memory model; form submission binding stays in blog-block-editor-init.js.
 * Mount with ReamurBlogBlockEditor.mount(element, { document, onChange }).
 */
(function (global) {
  'use strict';

  const TYPES = [
    ['core/paragraph', 'Parágrafo'], ['core/heading', 'Título'], ['core/list', 'Lista'],
    ['core/image', 'Imagem'], ['core/quote', 'Citação'], ['core/video_embed', 'Vídeo'],
    ['core/button', 'Botão'], ['core/separator', 'Separador'], ['core/group', 'Grupo'],
    ['core/columns', 'Colunas'], ['core/gallery', 'Galeria'], ['core/cover', 'Capa'], ['core/table', 'Tabela'], ['core/file', 'Arquivo'], ['core/audio', 'Áudio']
  ];
  const TEXT_TYPES = new Set(['core/paragraph', 'core/heading', 'core/list-item', 'core/quote', 'core/button', 'core/file', 'core/audio']);
  const BLOCK_CATEGORIES = { 'core/paragraph':'content', 'core/heading':'content', 'core/list':'content', 'core/quote':'content', 'core/button':'content', 'core/image':'media', 'core/video_embed':'media', 'core/gallery':'media', 'core/cover':'media', 'core/separator':'layout', 'core/group':'layout', 'core/columns':'layout', 'core/table':'content', 'core/file':'media', 'core/audio':'media' };
  const TRANSFORMABLE_TEXT = new Set(['core/paragraph', 'core/heading', 'core/quote', 'core/button']);
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

  function validLocalDownload(src) {
    if (typeof src !== 'string' || !/^view\/download\/blog\/[A-Za-z0-9][A-Za-z0-9._-]{0,179}$/.test(src) || src.includes('..') || /[\x00-\x20\x7F\\?#]/.test(src)) return false;
    return ['pdf','docx','xlsx','pptx','odt','ods','csv','txt'].includes(src.split('.').pop().toLowerCase());
  }

  function validLocalAudio(src) {
    if (typeof src !== 'string' || !/^view\/audio\/blog\/[A-Za-z0-9][A-Za-z0-9._-]{0,179}$/.test(src) || src.includes('..') || /[\x00-\x20\x7F\\?#]/.test(src)) return false;
    return ['mp3','ogg','wav'].includes(src.split('.').pop().toLowerCase());
  }

  function classifyKeyboardShortcut(event, context) {
    if (!event || !context) return null;
    const key = String(event.key || '').toLowerCase();
    const modifier = !!(event.ctrlKey || event.metaKey) && !event.altKey;
    if (modifier && !context.inFormControl) {
      if (key === 'z') return event.shiftKey ? 'redo' : 'undo';
      if (key === 'y') return 'redo';
    }
    if (modifier && context.inTextEditor && !context.inFormControl) {
      const marks = { b: 'bold', i: 'italic', u: 'underline' };
      if (marks[key]) return marks[key];
      if (key === 'k') return 'link';
    }
    if (event.altKey && !event.ctrlKey && !event.metaKey && !context.inFormControl && context.inBlock) {
      if (key === 'arrowup') return 'move-up';
      if (key === 'arrowdown') return 'move-down';
    }
    return null;
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
  const BLOCK_TEXT_TYPES = new Set(['core/paragraph', 'core/heading', 'core/list-item', 'core/quote', 'core/button', 'core/table-cell', 'core/file', 'core/audio']);
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
    const hasV2OnlyAudio = (nodes) => Array.isArray(nodes) && nodes.some((node) => node && (node.type === 'core/audio' || hasV2OnlyAudio(node.children)));
    if (hasV2OnlyAudio(doc.blocks)) return doc;
    function visit(node) {
      if (!node || typeof node !== 'object') return;
      if (BLOCK_TEXT_TYPES.has(node.type) && typeof node.content === 'string') node.content = runsFromText(node.content);
      if (Array.isArray(node.children)) node.children.forEach(visit);
    }
    doc.blocks.forEach(visit);
    doc.format_version = 2;
    return doc;
  }

  function utf8Length(value) {
    try {
      const text = String(value);
      if (typeof TextEncoder !== 'undefined') return new TextEncoder().encode(text).length;
      return encodeURIComponent(text).replace(/%[0-9A-F]{2}/gi, 'x').length;
    } catch (_) { return Infinity; }
  }

  function validInlineRuns(runs, type) {
    if (!Array.isArray(runs) || runs.length > 1000) return false;
    let chars = 0;
    return runs.every((run) => {
      if (!run || typeof run !== 'object' || Array.isArray(run) || Object.keys(run).sort().join(',') !== 'href,marks,text') return false;
      if (typeof run.text !== 'string' || run.text.length === 0 || utf8Length(run.text) > 262144 || !Array.isArray(run.marks) || run.marks.length > INLINE_MARKS.length) return false;
      chars += utf8Length(run.text);
      if (chars > 262144 || new Set(run.marks).size !== run.marks.length || run.marks.some((mark) => !INLINE_MARKS.includes(mark))) return false;
      if (run.href !== null && (!validActionUrl(run.href) || ['core/button','core/file','core/audio'].includes(type))) return false;
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
      if (typeof node.type !== 'string' || !TYPES.some(([type]) => type === node.type) && node.type !== 'core/list-item' && node.type !== 'core/column' && node.type !== 'core/table-row' && node.type !== 'core/table-cell') errors.push('unknown_type');
      if (!node.attributes || typeof node.attributes !== 'object' || Array.isArray(node.attributes) || !Array.isArray(node.children)) { errors.push('node_fields_invalid'); return; }
      const textBlock = BLOCK_TEXT_TYPES.has(node.type);
      if (version === 1 && typeof node.content !== 'string') errors.push('legacy_content_invalid');
      else if (version === 2 && textBlock && !validInlineRuns(node.content, node.type)) errors.push('rich_content_invalid');
      else if ((!textBlock || version === 1) && (typeof node.content !== 'string' || (!textBlock && version === 2 && node.content !== ''))) errors.push('content_invalid');
      if (typeof node.content === 'string' && node.content.length > 262144) errors.push('content_too_large');
      const a = node.attributes;
      const allowedAttributes = {
        'core/paragraph':['align'], 'core/heading':['level'], 'core/list':['ordered'], 'core/list-item':[],
        'core/quote':[], 'core/image':['src','alt','title'], 'core/video_embed':['url'], 'core/button':['url'],
        'core/separator':[], 'core/group':[], 'core/columns':['count'], 'core/column':[],
        'core/gallery':['columns','align'], 'core/cover':['src','alt','align'], 'core/table':['header'], 'core/table-row':[], 'core/table-cell':[], 'core/file':['src'], 'core/audio':['src']
      };
      if (version === 2) {
        allowedAttributes['core/image'] = ['src','alt','title','caption','align','size','mobile_size'];
        allowedAttributes['core/group'] = ['outline_label'];
        allowedAttributes['core/table'] = ['header'];
      }
      const allowed = allowedAttributes[node.type] || [];
      if (Object.keys(a).some((key) => !allowed.includes(key))) errors.push('attributes_not_allowlisted');
      if (version === 1 && ['core/gallery','core/cover'].includes(node.type)) errors.push('media_block_requires_v2');
      if (version === 1 && node.type === 'core/table') errors.push('table_requires_v2');
      if (version === 1 && node.type === 'core/audio') errors.push('audio_requires_v2');
      switch (node.type) {
        case 'core/paragraph': if (!['', 'left', 'center', 'right'].includes(a.align || '')) errors.push('align_invalid'); if (node.children.length) errors.push('paragraph_children'); break;
        case 'core/heading': if (![1,2,3,4,5,6].includes(a.level) || node.children.length) errors.push('heading_invalid'); break;
        case 'core/list': if (typeof a.ordered !== 'boolean' || node.children.length < 1 || node.children.some((child) => !child || child.type !== 'core/list-item')) errors.push('list_invalid'); break;
        case 'core/list-item': if (parent !== 'core/list' || node.children.length) errors.push('list_item_invalid'); break;
        case 'core/quote': if (node.children.length) errors.push('quote_children'); break;
        case 'core/file': {
          const label = Array.isArray(node.content) ? node.content.map((run) => run && typeof run.text === 'string' ? run.text : '').join('').trim() : '';
          if (version !== 2 || !validLocalDownload(a.src) || !label || node.children.length || !validInlineRuns(node.content, node.type)) errors.push('file_invalid');
          break;
        }
        case 'core/audio': {
          const label = Array.isArray(node.content) ? node.content.map((run) => run && typeof run.text === 'string' ? run.text : '').join('').trim() : '';
          if (version !== 2 || !validLocalAudio(a.src) || !label || node.children.length || !validInlineRuns(node.content, node.type)) errors.push('audio_invalid');
          break;
        }
        case 'core/image':
          if (!validLocalImage(a.src) || typeof a.alt !== 'string' || node.children.length || node.content) errors.push('image_invalid');
          ['alt','title','caption'].forEach((key) => { if (a[key] !== undefined && (typeof a[key] !== 'string' || utf8Length(a[key]) > 4096)) errors.push('image_text_invalid'); });
          if (a.align !== undefined && !['left','center','right','wide','full'].includes(a.align)) errors.push('image_alignment_invalid');
          if (a.size !== undefined && !['small','medium','large','full'].includes(a.size)) errors.push('image_size_invalid');
          if (a.mobile_size !== undefined && !['full','large','medium'].includes(a.mobile_size)) errors.push('image_mobile_size_invalid');
          break;
        case 'core/gallery': if (version !== 2 || node.content || ![2,3,4].includes(a.columns) || !['content','wide','full'].includes(a.align) || node.children.length < 1 || node.children.length > 20 || node.children.some((child) => !child || child.type !== 'core/image')) errors.push('gallery_invalid'); break;
        case 'core/cover':
          if (version !== 2 || !validLocalImage(a.src) || typeof a.alt !== 'string' || utf8Length(a.alt) > 4096 || !['content','wide','full'].includes(a.align) || node.content || node.children.length < 1 || node.children.length > 12 || node.children.some((child) => !child || !['core/heading','core/paragraph','core/quote','core/button'].includes(child.type))) errors.push('cover_invalid');
          break;
        case 'core/video_embed': if (!validEmbed(a.url) || node.children.length || node.content) errors.push('embed_invalid'); break;
        case 'core/button': if (!validActionUrl(a.url) || node.children.length) errors.push('button_invalid'); break;
        case 'core/separator': if (node.children.length || node.content) errors.push('separator_invalid'); break;
        case 'core/group':
          if (node.content || Object.keys(a).some((key) => key !== 'outline_label') || node.children.length === 0) errors.push('group_invalid');
          if (a.outline_label !== undefined && (typeof a.outline_label !== 'string' || utf8Length(a.outline_label) > 160)) errors.push('outline_label_invalid');
          break;
        case 'core/columns': if (node.content || Object.keys(a).some((key) => key !== 'count') || ![2,3].includes(a.count) || node.children.length !== a.count || node.children.some((child) => !child || child.type !== 'core/column')) errors.push('columns_invalid'); break;
        case 'core/column': if (parent !== 'core/columns' || node.content || Object.keys(a).length || node.children.length === 0) errors.push('column_invalid'); break;
        case 'core/table': {
          const rows = node.children;
          const columns = rows.length && rows[0] && Array.isArray(rows[0].children) ? rows[0].children.length : 0;
          let tableBytes = 0;
          rows.forEach((row) => { if (!row || !Array.isArray(row.children)) return; row.children.forEach((cell) => { if (!cell || !Array.isArray(cell.content)) return; cell.content.forEach((run) => { if (run && typeof run.text === 'string') tableBytes += utf8Length(run.text); }); }); });
          if (version !== 2 || typeof a.header !== 'boolean' || node.content || rows.length < 1 || rows.length > 20 || columns < 1 || columns > 12 || tableBytes > 262144 || rows.some((row) => !row || row.type !== 'core/table-row' || !Array.isArray(row.children) || row.children.length !== columns)) errors.push('table_invalid');
          break;
        }
        case 'core/table-row': if (parent !== 'core/table' || node.content || Object.keys(a).length || node.children.length < 1 || node.children.length > 12 || node.children.some((cell) => !cell || cell.type !== 'core/table-cell')) errors.push('table_row_invalid'); break;
        case 'core/table-cell': if (parent !== 'core/table-row' || Object.keys(a).length || node.children.length || version !== 2 || !validInlineRuns(node.content, node.type)) errors.push('table_cell_invalid'); break;
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
      this.undoWeight = 0;
      this.redoWeight = 0;
      this.textUndoPending = false;
      this.textUndoTimer = null;
      this.selectedId = null;
      this.preview = 'desktop';
      this.dragId = null;
      this.inlineBookmark = null;
      this.linkDraft = '';
      this.inlineNotice = '';
      this.inserterOpen = false;
      this.inserterReturnFocus = null;
      this.insertAfterId = null;
      this.slashTrigger = null;
      this.inserterQuery = '';
      this.statusMessage = '';
      this.strings = Object.assign({bold:'Negrito',italic:'Itálico',underline:'Sublinhado',strike:'Tachado',code:'Código',link:'Link',linkPlaceholder:'https:// ou /caminho',applyLink:'Aplicar link',removeLink:'Remover link',invalidLink:'URL de link inválida.',toolbar:'Formatação inline',textLabel:'Texto do bloco',placeholder:'Digite o texto…',addBlock:'Adicionar bloco',searchBlocks:'Buscar blocos…',noBlocks:'Nenhum bloco encontrado.',categoryContent:'Conteúdo',categoryMedia:'Mídia',categoryLayout:'Layout',outline:'Estrutura do documento',outlineLabel:'Título na estrutura',initialInvalid:'Documento inicial inválido; a fonte foi preservada para revisão e a edição está bloqueada',documentTooLarge:'Documento maior que o limite de 4 MiB.',outlineEmpty:'O documento ainda não tem blocos.',moveUp:'Mover para cima',moveDown:'Mover para baixo',duplicate:'Duplicar',transform:'Transformar bloco',remove:'Remover bloco',addAfter:'Inserir depois deste bloco',dragHint:'Arraste para reordenar; Alt+↑/↓ também move.',transformHint:'Transformação preserva somente conteúdo textual compatível.',insertEmpty:'Nenhum tipo de bloco pode ser inserido neste ponto.',statusInserted:'Bloco inserido.',statusMoved:'Bloco movido.',statusDuplicated:'Bloco duplicado.',statusTransformed:'Bloco transformado.',statusRemoved:'Bloco removido.',statusUndo:'Alteração desfeita.',statusRedo:'Alteração refeita.',undo:'Desfazer',redo:'Refazer',keyboardShortcutsTitle:'Atalhos de teclado',keyboardShortcuts:'Ctrl/⌘+B negrito · Ctrl/⌘+I itálico · Ctrl/⌘+U sublinhado · Ctrl/⌘+K link · Ctrl/⌘+Z desfazer · Ctrl/⌘+Shift+Z ou Ctrl+Y refazer · Alt+↑/↓ mover bloco · / inserir bloco',previewDesktop:'Desktop',previewTablet:'Tablet',previewMobile:'Celular',editorTools:'Ferramentas do editor de blocos',canvas:'Canvas de conteúdo',canvasEmpty:'Escolha um bloco no inserter para começar.',inspector:'Inspetor de bloco',inspectorEmpty:'Selecione um bloco para editar seus atributos.',columnCount:'colunas · empilham em telas estreitas',media:{caption:'Legenda',alignment:'Alinhamento da imagem',imageSize:'Tamanho',mobileSize:'Tamanho no celular',galleryColumns:'Colunas da galeria',source:'Caminho local da imagem',alt:'Texto alternativo',title:'Título da imagem',videoPreview:'Prévia do vídeo',videoPreviewHint:'Prévia administrativa segura; a reprodução ocorre na página pública.',placeholder:'Selecione uma imagem na biblioteca.',alignments:{left:'Esquerda',center:'Centro',right:'Direita',content:'Conteúdo',wide:'Largo',full:'Largura total'},sizes:{small:'Pequeno',medium:'Médio',large:'Grande',full:'Largura total'},mobileSizes:{full:'Largura total',large:'Grande',medium:'Médio'}},patternsCategory:'Padrões de layout',patternHint:'Padrões locais prontos para inserir; não são salvos como templates globais.',patternLabels:{two_columns:'Destaque em duas colunas',quote_cta:'Citação e chamada',section_intro:'Introdução de seção',cover_hero:'Capa de destaque'},file:{source:'Caminho local do arquivo',choose:'Selecionar arquivo'},audio:{source:'Caminho local do áudio',choose:'Selecionar áudio'},table:{header:'Primeira linha é cabeçalho',addRow:'Adicionar linha',removeRow:'Remover última linha',addColumn:'Adicionar coluna',removeColumn:'Remover última coluna',toggleHeader:'Alternar cabeçalho',cellLabel:'Célula %1$d, %2$d'},typeLabels:{}}, this.options.strings || {});
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

    newId() { let candidate; do { this.idCounter += 1; candidate = 'rb' + this.instance + '-' + this.idCounter; } while (this.findPath && this.findPath(this.state ? this.state.blocks : [], candidate)); return candidate; }
    node(type, attributes, content, children) { const value = BLOCK_TEXT_TYPES.has(type) ? (Array.isArray(content) ? canonicalRuns(content) : runsFromText(String(content || ''))) : (content || ''); return { id: this.newId(), type, attributes: attributes || {}, children: children || [], content: value }; }
    snapshot() { return JSON.stringify(this.state); }
    label(type) { const localized = this.strings.typeLabels && this.strings.typeLabels[type]; if (localized) return localized; const found = TYPES.find((entry) => entry[0] === type); return found ? found[1] : (type === 'core/list-item' ? 'Item de lista' : 'Coluna'); }

    categoryLabel(category) { return category === 'media' ? this.strings.categoryMedia : (category === 'layout' ? this.strings.categoryLayout : this.strings.categoryContent); }

    bind() {
      global.document.addEventListener('blog-block-file-selected', (event) => { const detail = event.detail || {}; if (detail.editor !== this || !detail.nodeId || typeof detail.path !== 'string') return; this.updateNode(detail.nodeId, (node) => { if (node.type === 'core/file') node.attributes.src = detail.path; }); });
      global.document.addEventListener('blog-block-audio-selected', (event) => { const detail = event.detail || {}; if (detail.editor !== this || !detail.nodeId || typeof detail.path !== 'string') return; this.updateNode(detail.nodeId, (node) => { if (node.type === 'core/audio') node.attributes.src = detail.path; }); });
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
        const search = event.target.closest('[data-inserter-search]');
        if (search) { this.inserterQuery = search.value; this.filterInserter(); return; }
        const linkInput = event.target.closest('[data-inline-link-input]');
        if (linkInput) { this.linkDraft = linkInput.value; return; }
        const field = event.target.closest('[data-edit-content]');
        if (!field) return;
        const id = field.dataset.editContent;
        this.updateTextNode(id, field);
        this.captureInlineSelection();
      });
      this.root.addEventListener('change', (event) => {
        const transform = event.target.closest('[data-transform-node]');
        if (transform && transform.value) { this.transformNode(transform.dataset.transformNode, transform.value); return; }
        const field = event.target.closest('[data-attribute]');
        if (!field) return;
        const id = field.dataset.nodeId, key = field.dataset.attribute;
        this.updateNode(id, (node) => {
          if (key === 'ordered') node.attributes[key] = field.value === 'true';
          else if (key === 'level' || key === 'count' || key === 'columns') node.attributes[key] = Number(field.value);
          else node.attributes[key] = field.value;
        });
      });
      this.root.addEventListener('keydown', (event) => {
        if (event.target.closest('[data-inserter-search]')) {
          if (event.key === 'Escape') { event.preventDefault(); this.closeInserter(true); return; }
          if (event.key === 'Enter') { event.preventDefault(); const first = this.root.querySelector('.rb-inserter-option:not([hidden])'); if (first) { if (first.dataset.pattern) this.insertPattern(first.dataset.pattern); else this.insert(first.dataset.type, first.dataset.count); } return; }
        }
        if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey && event.target.closest('[data-edit-content]')) {
          const editor = event.target.closest('[data-edit-content]'); const offset = this.getCaretOffset(editor);
          const text = editor.textContent || '';
          if (offset === 0 || /\s/.test(text.charAt(offset - 1))) {
            event.preventDefault(); this.insertPlainText(editor, '/');
            this.slashTrigger = { nodeId: editor.dataset.editContent, offset: this.getCaretOffset(editor) };
            this.openInserter(editor.dataset.editContent, true, editor); return;
          }
        }
        const inTextEditor = !!event.target.closest('[data-edit-content]');
        const inFormControl = !!event.target.closest('input,textarea,select,[data-inserter-search]');
        const inBlock = !!event.target.closest('.rb-block[data-node-id]');
        const command = classifyKeyboardShortcut(event, { inTextEditor, inFormControl, inBlock });
        if (command === 'undo' || command === 'redo') { event.preventDefault(); this.action(command); return; }
        if (['bold','italic','underline'].includes(command)) { event.preventDefault(); this.captureInlineSelection(); this.applyInlineMark(command); return; }
        if (command === 'link') { event.preventDefault(); this.captureInlineSelection(); const link = this.root.querySelector('[data-inline-link-input]'); if (link) link.focus(); return; }
        if (event.key === 'Enter' && event.target.closest('[data-edit-content]')) { event.preventDefault(); this.insertPlainText(event.target.closest('[data-edit-content]'), '\n'); return; }
        if (command !== 'move-up' && command !== 'move-down') return;
        const card = event.target.closest('.rb-block[data-node-id]'); if (!card) return;
        event.preventDefault(); this.moveNode(card.dataset.nodeId, command === 'move-up' ? -1 : 1);
      });
      this.root.addEventListener('paste', (event) => {
        const field = event.target.closest('[data-edit-content]');
        if (!field) return;
        event.preventDefault();
        const text = event.clipboardData ? event.clipboardData.getData('text/plain') : '';
        this.insertPlainText(field, text);
      });
      this.root.addEventListener('dragstart', (event) => {
        const handle = event.target.closest('[data-drag-handle]');
        const card = handle && handle.closest('[data-node-id]');
        if (!card) { event.preventDefault(); return; }
        this.dragId = card.dataset.nodeId;
        if (event.dataTransfer) { event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', this.dragId); }
      });
      this.root.addEventListener('dragover', (event) => { if (event.target.closest('.rb-block[data-node-id]') && this.dragId) event.preventDefault(); });
      this.root.addEventListener('drop', (event) => {
        const editor = event.target.closest('[data-edit-content]');
        if (editor && !this.dragId) { event.preventDefault(); this.insertPlainText(editor, event.dataTransfer ? event.dataTransfer.getData('text/plain') : ''); return; }
        const target = event.target.closest('.rb-block[data-node-id]');
        if (!target || !this.dragId) return; event.preventDefault(); this.reorderSibling(this.dragId, target.dataset.nodeId); this.dragId = null;
      });
      this.root.addEventListener('dragend', () => { this.dragId = null; });
    }

    action(name, button) {
      if (this.locked) return;
      if (name === 'insert') this.insert(button.dataset.type, button.dataset.count);
      else if (name === 'insert-pattern') this.insertPattern(button.dataset.pattern);
      else if (name === 'toggle-inserter') this.inserterOpen ? this.closeInserter(true) : this.openInserter(button.dataset.afterId || null, false, button);
      else if (name === 'duplicate') this.duplicateNode(button.dataset.nodeId || this.selectedId);
      else if (name === 'outline-select') { const id = button.dataset.nodeId; this.selectNode(id); const card = Array.from(this.root.querySelectorAll('.rb-block[data-node-id]')).find((item) => item.dataset.nodeId === id); if (card) card.scrollIntoView({ block:'nearest' }); }
      else if (name === 'undo') this.undo();
      else if (name === 'redo') this.redo();
      else if (name === 'delete') this.deleteNode(button.dataset.nodeId || this.selectedId);
      else if (name === 'move-up') this.moveNode(button.dataset.nodeId || this.selectedId, -1);
      else if (name === 'move-down') this.moveNode(button.dataset.nodeId || this.selectedId, 1);
      else if (name === 'media') { global.document.dispatchEvent(new CustomEvent('blog-block-media-open', { detail: { editor: this } })); }
      else if (name === 'file-media') { global.document.dispatchEvent(new CustomEvent('blog-block-file-open', { detail: { editor: this, nodeId: button.dataset.nodeId || this.selectedId } })); }
      else if (name === 'audio-media') { global.document.dispatchEvent(new CustomEvent('blog-block-audio-open', { detail: { editor: this, nodeId: button.dataset.nodeId || this.selectedId } })); }
      else if (name === 'format-mark') this.applyInlineMark(button.dataset.mark);
      else if (name.startsWith('table-')) this.updateTable(name, button.dataset.nodeId || this.selectedId);
      else if (name === 'link-apply') this.applyInlineLink(this.linkDraft);
      else if (name === 'link-remove') this.applyInlineLink(null);
      else if (name === 'preview') { this.preview = button.dataset.size; this.root.dataset.preview = this.preview; this.root.querySelectorAll('[data-action="preview"]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.size === this.preview))); }
    }

    findEditor(id) { return Array.from(this.root.querySelectorAll('[data-edit-content]')).find((node) => node.dataset.editContent === id) || null; }

    focusNode(id) {
      const editor = this.findEditor(id);
      const card = Array.from(this.root.querySelectorAll('.rb-block[data-node-id]')).find((item) => item.dataset.nodeId === id);
      const target = editor || card; if (!target) return;
      if (!editor) target.tabIndex = -1;
      target.focus({ preventScroll:true }); target.scrollIntoView({ block:'nearest' });
    }

    getCaretOffset(editor) {
      const selection = global.getSelection(); if (!selection || !selection.rangeCount || !editor.contains(selection.anchorNode)) return 0;
      const range = selection.getRangeAt(0).cloneRange(); range.selectNodeContents(editor); range.setEnd(selection.anchorNode, selection.anchorOffset); return range.toString().length;
    }

    selectNode(id) {
      if (!id || this.selectedId === id) return;
      this.selectedId = id;
      this.root.querySelectorAll('.rb-block').forEach((card) => card.classList.toggle('is-selected', card.dataset.nodeId === id));
      this.root.querySelectorAll('[data-action="outline-select"]').forEach((button) => { if (button.dataset.nodeId === id) button.setAttribute('aria-current','location'); else button.removeAttribute('aria-current'); });
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
      if (!['core/button','core/file','core/audio'].includes(node.type)) {
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

    removeInlineRange(runs, start, end) {
      let cursor = 0; const out = [];
      runs.forEach((run) => {
        const from = cursor, to = cursor + run.text.length; cursor = to;
        if (to <= start || from >= end) { out.push(CLONE(run)); return; }
        const left = Math.max(0, start - from), right = Math.min(run.text.length, end - from);
        if (left > 0) out.push({ text:run.text.slice(0,left), marks:run.marks.slice(), href:run.href });
        if (right < run.text.length) out.push({ text:run.text.slice(right), marks:run.marks.slice(), href:run.href });
      });
      return canonicalRuns(out);
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
      const found = this.findPath(this.state.blocks, bookmark.nodeId); if (!found || ['core/button','core/file'].includes(found.node.type)) return;
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
      if (type === 'core/list-item') return this.node(type, {}, 'Novo item');
      if (type === 'core/image') return this.node(type, { src: '', alt: '' }, '');
      if (type === 'core/file') return this.node(type, { src: '' }, 'Baixar arquivo');
      if (type === 'core/audio') return this.node(type, { src: '' }, 'Faixa de áudio');
      if (type === 'core/gallery') return this.node(type, { columns:2, align:'content' }, '', [this.node('core/image',{src:'',alt:''},''),this.node('core/image',{src:'',alt:''},'')]);
      if (type === 'core/cover') return this.node(type, { src:'', alt:'', align:'wide' }, '', [this.node('core/heading',{level:2},'Título da capa'),this.node('core/paragraph',{},'Adicione uma descrição.'),this.node('core/button',{url:'/'},'Saiba mais')]);
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
      if (type === 'core/table') {
        const rows = [];
        for (let r = 0; r < 3; r += 1) {
          const cells = [];
          for (let c = 0; c < 3; c += 1) cells.push(this.node('core/table-cell', {}, r === 0 ? 'Cabeçalho ' + (c + 1) : 'Célula'));
          rows.push(this.node('core/table-row', {}, '', cells));
        }
        return this.node(type, { header:true }, '', rows);
      }
      return this.node('core/paragraph', {}, 'Escreva seu conteúdo…');
    }

    insertTypes(afterId) {
      const found = afterId ? this.findPath(this.state.blocks, afterId) : null;
      if (found && (found.node.type === 'core/table-cell' || (found.parent && found.parent.type === 'core/table-row'))) return [];
      if (found && (found.node.type === 'core/list' || (found.parent && found.parent.type === 'core/list'))) return [{ type:'core/list-item', category:'content' }];
      if (found && found.parent && found.parent.type === 'core/gallery') return found.parent.children.length < 20 ? [{ type:'core/image', category:'media' }] : [];
      if (found && found.parent && found.parent.type === 'core/cover') return found.parent.children.length < 12 ? ['core/heading','core/paragraph','core/quote','core/button'].map((type) => ({ type, category:'content' })) : [];
      if (found && ['core/group','core/column'].includes(found.node.type)) return TYPES.map(([type]) => ({ type, category: BLOCK_CATEGORIES[type] || 'content' }));
      if (found && (found.node.type === 'core/columns' || (found.parent && found.parent.type === 'core/columns'))) return [];
      return TYPES.map(([type]) => ({ type, category: BLOCK_CATEGORIES[type] || 'content' }));
    }

    canInsertPatterns(afterId) {
      const found = afterId ? this.findPath(this.state.blocks, afterId) : null;
      if (found && found.parent && ['core/cover','core/gallery'].includes(found.parent.type)) return false;
      return this.insertTypes(afterId).some((item) => !['core/list-item','core/column'].includes(item.type));
    }

    createPattern(pattern) {
      if (pattern === 'two_columns') return this.node('core/columns',{count:2},'',[
        this.node('core/column',{},'', [this.node('core/heading',{level:2},'Destaque principal'),this.node('core/paragraph',{},'Descreva o primeiro benefício.')]),
        this.node('core/column',{},'', [this.node('core/heading',{level:2},'Outro benefício'),this.node('core/paragraph',{},'Descreva o segundo benefício.')])
      ]);
      if (pattern === 'quote_cta') return this.node('core/group',{},'',[
        this.node('core/quote',{},'Uma frase de destaque para seus leitores.'),this.node('core/button',{url:'/contato'},'Fale conosco')
      ]);
      if (pattern === 'section_intro') return this.node('core/group',{},'',[
        this.node('core/heading',{level:2},'Título da seção'),this.node('core/paragraph',{},'Apresente o conteúdo desta seção.'),this.node('core/separator',{},'')
      ]);
      if (pattern === 'cover_hero') return this.node('core/cover',{src:'',alt:'',align:'wide'},'',[
        this.node('core/heading',{level:2},'Título de destaque'),this.node('core/paragraph',{},'Uma breve introdução.'),this.node('core/button',{url:'/'},'Saiba mais')
      ]);
      return null;
    }

    renderInserter() {
      const menu = this.el('div', 'rb-inserter-menu'); menu.hidden = !this.inserterOpen;
      menu.setAttribute('role', 'region'); menu.setAttribute('aria-label', this.strings.addBlock);
      const search = this.el('input', 'rb-inserter-search'); search.type = 'search'; search.placeholder = this.strings.searchBlocks;
      search.setAttribute('aria-label', this.strings.searchBlocks); search.setAttribute('aria-controls','rb-inserter-results-' + this.instance); search.dataset.inserterSearch = '1'; search.value = this.inserterQuery;
      menu.append(search);
      const types = this.insertTypes(this.slashTrigger ? this.slashTrigger.nodeId : this.insertAfterId);
      const list = this.el('div', 'rb-inserter-results'); list.id = 'rb-inserter-results-' + this.instance;
      if (!types.length) menu.append(this.el('p', 'rb-inserter-empty', this.strings.insertEmpty));
      ['content','media','layout'].forEach((category) => {
        const group = this.el('section', 'rb-inserter-category');
        group.append(this.el('h4', 'rb-inserter-category-title', this.categoryLabel(category)));
        types.filter((item) => item.category === category).forEach((item) => {
          const option = this.button(this.label(item.type), 'insert', { type:item.type });
          option.classList.add('rb-inserter-option');
          option.dataset.searchText = this.label(item.type).toLocaleLowerCase(); group.append(option);
          if (item.type === 'core/columns') { const three = this.button(this.label(item.type) + ' (3)', 'insert', { type:item.type, count:'3' }); three.classList.add('rb-inserter-option'); three.dataset.searchText = (this.label(item.type) + ' 3').toLocaleLowerCase(); group.append(three); }
        });
        if (group.querySelector('.rb-inserter-option')) list.append(group);
      });
      if (this.canInsertPatterns(this.slashTrigger ? this.slashTrigger.nodeId : this.insertAfterId)) {
        const patterns = this.el('section','rb-inserter-category rb-pattern-category'); patterns.append(this.el('h4','rb-inserter-category-title',this.strings.patternsCategory),this.el('p','rb-pattern-hint',this.strings.patternHint));
        ['two_columns','quote_cta','section_intro','cover_hero'].forEach((pattern) => {
          const label = (this.strings.patternLabels || {})[pattern] || pattern;
          const option = this.button(label,'insert-pattern',{pattern}); option.classList.add('rb-inserter-option'); option.dataset.searchText = label.toLocaleLowerCase(); patterns.append(option);
        });
        list.append(patterns);
      }
      const noResults = this.el('p', 'rb-inserter-empty rb-inserter-no-results', this.strings.noBlocks); noResults.setAttribute('role','status'); noResults.setAttribute('aria-live','polite'); noResults.hidden = true;
      menu.append(list, noResults);
      return menu;
    }

    filterInserter() {
      const query = this.inserterQuery.trim().toLocaleLowerCase(); let visible = 0;
      this.root.querySelectorAll('.rb-inserter-option').forEach((option) => { const show = !query || option.dataset.searchText.includes(query); option.hidden = !show; if (show) visible += 1; });
      this.root.querySelectorAll('.rb-inserter-category').forEach((group) => { group.hidden = !group.querySelector('.rb-inserter-option:not([hidden])'); });
      const empty = this.root.querySelector('.rb-inserter-no-results'); if (empty) empty.hidden = visible > 0;
    }

    openInserter(afterId, fromSlash, opener) {
      this.inserterOpen = true; this.insertAfterId = afterId || null;
      this.inserterReturnFocus = fromSlash
        ? { type:'editor', id:afterId }
        : (opener && opener.closest('.rb-toolbar') ? { type:'top' } : (opener && opener.dataset.afterId ? { type:'block', id:opener.dataset.afterId } : { type:'top' }));
      if (!fromSlash) this.slashTrigger = null;
      this.inserterQuery = ''; this.render();
      const search = this.root.querySelector('[data-inserter-search]'); if (search) search.focus();
    }

    closeInserter(returnFocus) {
      this.inserterOpen = false; this.slashTrigger = null; this.inserterQuery = ''; this.render();
      if (returnFocus) {
        let target = null;
        const restore = this.inserterReturnFocus;
        if (restore && restore.type === 'editor') target = this.findEditor(restore.id);
        else if (restore && restore.type === 'block') target = Array.from(this.root.querySelectorAll('[data-action="toggle-inserter"]')).find((button) => button.dataset.afterId === restore.id) || null;
        else target = this.root.querySelector('.rb-toolbar [data-action="toggle-inserter"]');
        if (!target) target = this.root.querySelector('[data-action="toggle-inserter"]');
        if (target) target.focus();
      }
      this.inserterReturnFocus = null;
    }

    insertionLocation(blocks, afterId) {
      const found = afterId ? this.findPath(blocks, afterId) : null;
      const container = found && ['core/group','core/column','core/list'].includes(found.node.type);
      const target = !found ? blocks : (container ? found.node.children : found.nodes);
      return { found, target, index: !found ? target.length : (container ? target.length : found.index + 1) };
    }

    prepareSlashTarget(next, afterId) {
      const found = afterId ? this.findPath(next.blocks, afterId) : null;
      if (this.slashTrigger && found && TEXT_TYPES.has(found.node.type)) {
        const at = this.slashTrigger.offset; found.node.content = this.removeInlineRange(found.node.content, at - 1, at);
      }
      return found;
    }

    finishInsert(next, node) {
      this.inserterOpen = false; this.inserterReturnFocus = null; this.slashTrigger = null; this.insertAfterId = node.id; this.inserterQuery = ''; this.statusMessage = this.strings.statusInserted;
      this.commit(next, node.id); this.focusNode(node.id);
    }

    insert(type, presetCount) {
      const afterId = this.slashTrigger ? this.slashTrigger.nodeId : this.insertAfterId || this.selectedId;
      if (!this.insertTypes(afterId).some((item) => item.type === type)) return;
      const next = CLONE(this.state); this.prepareSlashTarget(next, afterId);
      const location = this.insertionLocation(next.blocks, afterId);
      const node = this.createDefault(type, presetCount); location.target.splice(location.index, 0, node);
      this.finishInsert(next,node);
    }

    insertPattern(pattern) {
      const afterId = this.slashTrigger ? this.slashTrigger.nodeId : this.insertAfterId || this.selectedId;
      if (!this.canInsertPatterns(afterId)) return;
      const next = CLONE(this.state); this.prepareSlashTarget(next, afterId);
      const location = this.insertionLocation(next.blocks, afterId); const node = this.createPattern(pattern);
      if (!node) return;
      location.target.splice(location.index,0,node); this.finishInsert(next,node);
    }

    updateTable(action, id) {
      if (this.locked) return;
      const next = CLONE(this.state), found = this.findPath(next.blocks, id);
      if (!found || found.node.type !== 'core/table') return;
      const table = found.node, rows = table.children;
      const cols = rows.length ? rows[0].children.length : 0;
      if (action === 'table-toggle-header') table.attributes.header = !table.attributes.header;
      else if (action === 'table-add-row' && rows.length < 20) {
        const cells = []; for (let c = 0; c < cols; c += 1) cells.push(this.node('core/table-cell', {}, ''));
        rows.push(this.node('core/table-row', {}, '', cells));
      } else if (action === 'table-remove-row' && rows.length > 1) rows.pop();
      else if (action === 'table-add-column' && cols < 12) rows.forEach((row) => row.children.push(this.node('core/table-cell', {}, '')));
      else if (action === 'table-remove-column' && cols > 1) rows.forEach((row) => row.children.pop());
      else return;
      this.commit(next, table.id);
    }

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

    pushHistory(stack, side, snapshot) {
      const weightKey = side + 'Weight';
      stack.push(snapshot);
      this[weightKey] += snapshot.length;
      while (stack.length > 50 || (stack.length > 1 && this[weightKey] > 8388608)) {
        this[weightKey] -= stack.shift().length;
      }
    }

    popHistory(stack, side) {
      const snapshot = stack.pop();
      if (snapshot !== undefined) this[side + 'Weight'] -= snapshot.length;
      return snapshot;
    }

    clearRedoHistory() {
      this.redoStack = [];
      this.redoWeight = 0;
    }

    updateTextNode(id, editor) {
      if (this.locked) return;
      const found = this.findPath(this.state.blocks, id);
      if (!found || !BLOCK_TEXT_TYPES.has(found.node.type)) return;
      const runs = normalizeDomInline(editor, found.node.type);
      this.recordTextEditSnapshot();
      found.node.content = runs;
      let valid = validInlineRuns(runs, found.node.type);
      let errors = valid ? [] : ['rich_content_invalid'];
      if (valid && found.node.type === 'core/table-cell') {
        let ancestor = found.parent;
        while (ancestor && ancestor.type !== 'core/table') {
          const ancestorPath = this.findPath(this.state.blocks, ancestor.id);
          ancestor = ancestorPath ? ancestorPath.parent : null;
        }
        if (ancestor && ancestor.type === 'core/table') {
          let totalBytes = 0;
          ancestor.children.forEach((row) => row.children.forEach((cell) => cell.content.forEach((run) => { totalBytes += utf8Length(run.text); })));
          if (totalBytes > 262144) { valid = false; errors = ['table_text_too_large']; }
        }
      }
      const result = { valid, errors };
      this.root.dataset.valid = valid ? 'true' : 'false';
      this.onChange(valid ? this.state : null, result);
      this.updateHistoryButtons();
    }

    recordTextEditSnapshot() {
      if (!this.textUndoPending) {
        this.pushHistory(this.undoStack, 'undo', this.snapshot());
        this.clearRedoHistory();
        this.textUndoPending = true;
      }
      if (this.textUndoTimer) global.clearTimeout(this.textUndoTimer);
      this.textUndoTimer = global.setTimeout(() => {
        this.textUndoPending = false;
        this.textUndoTimer = null;
      }, 700);
    }

    clearTextEditSnapshot() {
      if (this.textUndoTimer) global.clearTimeout(this.textUndoTimer);
      this.textUndoTimer = null;
      this.textUndoPending = false;
    }

    deleteNode(id) {
      const next = CLONE(this.state), found = this.findPath(next.blocks, id); if (!found) return;
      if (found.parent && found.parent.type === 'core/columns' && found.node.type === 'core/column') return;
      if (found.parent && found.parent.type === 'core/gallery' && found.parent.children.length <= 1) return;
      if (found.parent && found.parent.type === 'core/cover' && found.parent.children.length <= 1) return;
      found.nodes.splice(found.index, 1);
      let focusId = found.nodes[Math.min(found.index, found.nodes.length - 1)] ? found.nodes[Math.min(found.index, found.nodes.length - 1)].id : null;
      if (found.parent && !found.nodes.length) {
        if (found.parent.type === 'core/list') { const fallback = this.node('core/list-item', {}, 'Novo item'); found.nodes.push(fallback); focusId = fallback.id; }
        else if (['core/group','core/column'].includes(found.parent.type)) { const fallback = this.node('core/paragraph', {}, 'Escreva seu conteúdo…'); found.nodes.push(fallback); focusId = fallback.id; }
        else if (found.parent.type === 'core/columns') focusId = found.parent.id;
      }
      if (!focusId && next.blocks.length) focusId = next.blocks[0].id;
      this.selectedId = focusId; this.statusMessage = this.strings.statusRemoved; this.commit(next, focusId);
      if (focusId) this.focusNode(focusId); else { const add = this.root.querySelector('[data-action="toggle-inserter"]'); if (add) add.focus(); }
    }

    moveNode(id, delta) {
      const next = CLONE(this.state), found = this.findPath(next.blocks, id); if (!found) return;
      const target = found.index + delta; if (target < 0 || target >= found.nodes.length) return;
      [found.nodes[found.index], found.nodes[target]] = [found.nodes[target], found.nodes[found.index]];
      this.statusMessage = this.strings.statusMoved; this.commit(next, id); this.focusNode(id);
    }

    duplicateNode(id) {
      const next = CLONE(this.state); const found = this.findPath(next.blocks, id); if (!found) return;
      if (found.parent && found.parent.type === 'core/columns' && found.node.type === 'core/column') return;
      if (found.parent && found.parent.type === 'core/gallery' && found.parent.children.length >= 20) return;
      if (found.parent && found.parent.type === 'core/cover' && found.parent.children.length >= 12) return;
      const copy = CLONE(found.node);
      const refreshIds = (node) => { node.id = this.newId(); node.children.forEach(refreshIds); };
      refreshIds(copy); found.nodes.splice(found.index + 1, 0, copy); this.statusMessage = this.strings.statusDuplicated; this.commit(next, copy.id); this.focusNode(copy.id);
    }

    canTransformNode(node, type) {
      if (!node || !TRANSFORMABLE_TEXT.has(node.type) || !TRANSFORMABLE_TEXT.has(type) || node.type === type) return false;
      if (node.type === 'core/button' && !node.content.length) return false;
      if (type === 'core/button') {
        const hrefs = new Set(node.content.map((run) => run.href || null));
        return hrefs.size <= 1;
      }
      return true;
    }

    transformNode(id, type) {
      const next = CLONE(this.state); const found = this.findPath(next.blocks, id); if (!found || !this.canTransformNode(found.node, type)) return;
      const node = found.node; const previous = node.type; const buttonUrl = previous === 'core/button' ? node.attributes.url : null;
      const inlineLinks = new Set(node.content.map((run) => run.href || null));
      const targetButtonUrl = type === 'core/button' ? (Array.from(inlineLinks)[0] || buttonUrl || '/') : null;
      node.type = type;
      node.attributes = type === 'core/paragraph' ? (previous === type ? node.attributes : { align:'left' })
        : (type === 'core/heading' ? { level: previous === 'core/heading' ? node.attributes.level : 2 }
          : (type === 'core/button' ? { url: targetButtonUrl } : {}));
      if (previous === 'core/button' && type !== 'core/button') node.content = node.content.map((run) => Object.assign({}, run, { href:buttonUrl }));
      if (type === 'core/button') node.content = node.content.map((run) => Object.assign({}, run, { href:null }));
      this.statusMessage = this.strings.statusTransformed; this.commit(next, id); this.focusNode(id);
    }

    reorderSibling(fromId, toId) {
      if (fromId === toId) return;
      const next = CLONE(this.state); const from = this.findPath(next.blocks, fromId); const to = this.findPath(next.blocks, toId);
      if (!from || !to || from.nodes !== to.nodes) return;
      const [node] = from.nodes.splice(from.index, 1); from.nodes.splice(to.index, 0, node); this.statusMessage = this.strings.statusMoved; this.commit(next, fromId); this.focusNode(fromId);
    }

    commit(next, focusId, rerender) {
      this.clearTextEditSnapshot();
      this.pushHistory(this.undoStack, 'undo', this.snapshot());
      this.clearRedoHistory(); this.state = next; this.selectedId = focusId || this.selectedId;
      const result = validateDocument(this.state);
      this.root.dataset.valid = result.valid ? 'true' : 'false';
      this.onChange(this.getDocument(), result);
      if (rerender !== false) this.render(); else this.updateHistoryButtons();
    }

    undo() { this.clearTextEditSnapshot(); if (!this.undoStack.length) return; this.pushHistory(this.redoStack, 'redo', this.snapshot()); this.state = JSON.parse(this.popHistory(this.undoStack, 'undo')); if (this.selectedId && !this.findPath(this.state.blocks, this.selectedId)) this.selectedId = this.state.blocks[0] ? this.state.blocks[0].id : null; this.statusMessage = this.strings.statusUndo; this.render(); this.onChange(this.getDocument(), validateDocument(this.state)); }
    redo() { this.clearTextEditSnapshot(); if (!this.redoStack.length) return; this.pushHistory(this.undoStack, 'undo', this.snapshot()); this.state = JSON.parse(this.popHistory(this.redoStack, 'redo')); if (this.selectedId && !this.findPath(this.state.blocks, this.selectedId)) this.selectedId = this.state.blocks[0] ? this.state.blocks[0].id : null; this.statusMessage = this.strings.statusRedo; this.render(); this.onChange(this.getDocument(), validateDocument(this.state)); }
    setSelectedAttribute(key, value) { if (this.locked) return false; const found = this.selectedId ? this.findPath(this.state.blocks, this.selectedId) : null; if (!found || !['core/image','core/cover','core/file','core/audio'].includes(found.node.type) || key !== 'src') return false; this.updateNode(found.node.id, (node) => { node.attributes.src = value; }); return true; }
    getDocument() { return this.locked ? null : CLONE(this.state); }
    serialize() {
      if (this.locked) throw new Error('document_requires_review');
      const check = validateDocument(this.state);
      if (!check.valid) throw new Error(check.errors.join(','));
      const json = JSON.stringify(this.state);
      if (utf8Length(json) > 4194304) throw new Error('document_too_large');
      return json;
    }

    el(tag, className, text) { const element = document.createElement(tag); if (className) element.className = className; if (text !== undefined) element.textContent = text; return element; }
    button(label, action, extra) { const b = this.el('button', 'rb-button', label); b.type = 'button'; b.dataset.action = action; if (extra) Object.keys(extra).forEach((key) => { b.dataset[key] = extra[key]; }); return b; }

    render() {
      this.root.replaceChildren();
      if (this.initialError) { const warn = this.el('p', 'rb-alert', this.strings.initialInvalid + ' (' + this.initialError + ').'); warn.setAttribute('role','alert'); this.root.setAttribute('aria-invalid','true'); this.root.append(warn); return; }
      this.root.setAttribute('aria-invalid','false');
      const toolbar = this.el('div', 'rb-toolbar'); toolbar.setAttribute('role', 'toolbar'); toolbar.setAttribute('aria-label', this.strings.editorTools);
      const insert = this.button('+ ' + this.strings.addBlock, 'toggle-inserter', { afterId:this.selectedId || '' });
      insert.setAttribute('aria-expanded', String(this.inserterOpen)); insert.setAttribute('aria-controls', 'rb-inserter-' + this.instance); toolbar.append(insert);
      const history = this.el('div', 'rb-history');
      const undo = this.button(this.strings.undo, 'undo'); undo.dataset.history = 'undo';
      const redo = this.button(this.strings.redo, 'redo'); redo.dataset.history = 'redo';
      history.append(undo, redo); toolbar.append(history);
      const previews = this.el('div', 'rb-previews');
      [['desktop',this.strings.previewDesktop],['tablet',this.strings.previewTablet],['mobile',this.strings.previewMobile]].forEach(([size,label]) => { const b = this.button(label, 'preview', { size }); b.setAttribute('aria-pressed', String(size === this.preview)); previews.append(b); });
      toolbar.append(previews);
      const shortcutHelp = this.el('details', 'rb-keyboard-help');
      shortcutHelp.append(this.el('summary', '', this.strings.keyboardShortcutsTitle));
      shortcutHelp.append(this.el('p', 'rb-keyboard-help-text', this.strings.keyboardShortcuts));
      toolbar.append(shortcutHelp); this.root.append(toolbar);
      const inserterMenu = this.renderInserter(); inserterMenu.id = 'rb-inserter-' + this.instance; this.root.append(inserterMenu);

      const layout = this.el('div', 'rb-layout');
      const canvas = this.el('div', 'rb-canvas'); canvas.setAttribute('role','region'); canvas.setAttribute('aria-label', this.strings.canvas);
      const canvasHead = this.el('div', 'rb-canvas-head', this.strings.canvas); canvas.append(canvasHead);
      if (!this.state.blocks.length) canvas.append(this.el('p', 'rb-empty', this.strings.canvasEmpty));
      this.state.blocks.forEach((node) => canvas.append(this.renderNode(node, true)));
      layout.append(this.renderOutline(), canvas, this.renderInspector()); this.root.append(layout);
      const footText = this.options.connectedSave === true
        ? 'As alterações ficam no formulário e só são persistidas ao clicar em Salvar; não há autosave.'
        : 'Rascunho em memória · não salva nem publica. O salvamento será conectado numa tarefa posterior.';
      const foot = this.el('p', 'rb-note', footText); this.root.append(foot);
      const live = this.el('span','rb-sr-only',this.statusMessage); live.setAttribute('role','status'); live.setAttribute('aria-live','polite'); live.dataset.editorStatus = '1'; this.root.append(live);
      this.updateHistoryButtons();
      if (this.inserterOpen && this.inserterQuery) this.filterInserter();
    }

    renderBlockToolbar(node) {
      const toolbar = this.el('div', 'rb-block-toolbar'); toolbar.setAttribute('role','toolbar'); toolbar.setAttribute('aria-label', this.label(node.type) + ' · ' + this.strings.transform);
      const path = this.findPath(this.state.blocks, node.id);
      const drag = this.el('span','rb-drag-handle','⠿'); drag.draggable = true; drag.dataset.dragHandle = '1'; drag.title = this.strings.dragHint; drag.setAttribute('aria-hidden','true'); toolbar.append(drag,this.el('span','rb-sr-only',this.strings.dragHint));
      const up = this.button('↑', 'move-up', { nodeId:node.id }); up.setAttribute('aria-label', this.strings.moveUp); up.disabled = !path || path.index === 0; toolbar.append(up);
      const down = this.button('↓', 'move-down', { nodeId:node.id }); down.setAttribute('aria-label', this.strings.moveDown); down.disabled = !path || path.index === path.nodes.length - 1; toolbar.append(down);
      const fixedColumn = !!(path && path.parent && path.parent.type === 'core/columns' && node.type === 'core/column');
      const atParentLimit = !!(path && path.parent && ((path.parent.type === 'core/gallery' && path.parent.children.length >= 20) || (path.parent.type === 'core/cover' && path.parent.children.length >= 12)));
      const lastRequiredChild = !!(path && path.parent && ['core/gallery','core/cover'].includes(path.parent.type) && path.parent.children.length <= 1);
      const duplicate = this.button(this.strings.duplicate, 'duplicate', { nodeId:node.id }); duplicate.disabled = fixedColumn || atParentLimit; toolbar.append(duplicate);
      if (TRANSFORMABLE_TEXT.has(node.type)) {
        const wrap = this.el('label','rb-transform-wrap'); wrap.append(this.el('span','rb-sr-only',this.strings.transform));
        const select = this.el('select','rb-input rb-transform'); select.dataset.transformNode = node.id;
        const empty = this.el('option','','↔'); empty.value = ''; empty.selected = true; select.append(empty);
        [...TRANSFORMABLE_TEXT].filter((type) => this.canTransformNode(node,type)).forEach((type) => { const option = this.el('option','',this.label(type)); option.value = type; select.append(option); });
        select.setAttribute('aria-label', this.strings.transform); select.title = this.strings.transformHint; wrap.append(select); toolbar.append(wrap);
      } else {
        const hint = this.el('span','rb-transform-hint',this.strings.transformHint); toolbar.append(hint);
      }
      const remove = this.button(this.strings.remove,'delete',{nodeId:node.id}); remove.disabled = fixedColumn || lastRequiredChild; toolbar.append(remove);
      const add = this.button('+', 'toggle-inserter', { afterId:node.id }); add.setAttribute('aria-label', this.strings.addAfter); add.setAttribute('aria-expanded', String(this.inserterOpen && this.insertAfterId === node.id)); add.setAttribute('aria-controls','rb-inserter-' + this.instance); toolbar.append(add);
      return toolbar;
    }

    renderAdminImagePreview(node, className) {
      const preview = this.el('figure',className || 'rb-admin-image-preview');
      if (validLocalImage(node.attributes.src)) {
        const image = this.el('img',''); image.src = node.attributes.src; image.alt = node.attributes.alt || ''; preview.append(image);
      } else preview.append(this.el('p','rb-admin-media-empty',this.strings.media.placeholder));
      if (node.attributes.caption) preview.append(this.el('figcaption','',node.attributes.caption));
      return preview;
    }

    renderAdminVideoPreview(url) {
      const preview = this.el('div','rb-video-admin-preview'); preview.setAttribute('role','img'); preview.setAttribute('aria-label',this.strings.media.videoPreview);
      preview.append(this.el('span','rb-video-admin-play','▶'));
      let provider = this.strings.media.videoPreviewHint;
      if (validEmbed(url)) { try { const host = new URL(url).hostname.toLowerCase(); provider = host.includes('vimeo') ? 'Vimeo' : 'YouTube'; } catch (_) {} }
      preview.append(this.el('span','rb-video-admin-label',this.strings.media.videoPreview + ' · ' + provider));
      return preview;
    }

    renderNode(node, isRoot) {
      const typeClass = node.type.replace(/[^a-z0-9-]/gi, '-');
      const card = this.el('section', 'rb-block rb-type-' + typeClass + (node.id === this.selectedId ? ' is-selected' : ''));
      if (node.type === 'core/columns') card.classList.add(node.attributes.count === 3 ? 'rb-columns-3' : 'rb-columns-2');
      if (node.type === 'core/gallery') card.classList.add('rb-gallery-preview','rb-gallery-preview--columns-' + node.attributes.columns);
      card.dataset.nodeId = node.id; card.tabIndex = -1; if (isRoot) card.dataset.rootNode = '1';
      const head = this.el('div', 'rb-block-head');
      head.append(this.el('span', 'rb-block-title', this.label(node.type)));
      card.append(head, this.renderBlockToolbar(node));
      if (TEXT_TYPES.has(node.type)) {
        if (node.id === this.selectedId) card.append(this.renderInlineToolbar(node));
        const editor = this.el('div', 'rb-content' + (node.type === 'core/button' ? ' rb-content-button-preview' : '')); editor.contentEditable = 'true'; editor.dataset.editContent = node.id;
        editor.setAttribute('role', 'textbox'); editor.setAttribute('aria-multiline', 'true');
        editor.setAttribute('aria-label', this.strings.textLabel + ': ' + this.label(node.type)); editor.setAttribute('data-placeholder', this.strings.placeholder || '');
        buildInlineDom(editor, node.content); card.append(editor);
      } else if (node.type === 'core/image') {
        card.append(this.renderAdminImagePreview(node));
      } else if (node.type === 'core/file') {
        card.append(this.el('p', 'rb-preview-value', node.content.map((run) => run.text).join('') + (node.attributes.src ? ' · ' + node.attributes.src.split('/').pop() : '')));
      } else if (node.type === 'core/audio') {
        const audio = this.el('audio','rb-audio-admin-preview'); audio.controls = true; audio.preload = 'none';
        const label = node.content.map((run) => run.text).join('').trim(); audio.setAttribute('aria-label', label || this.label('core/audio'));
        if (validLocalAudio(node.attributes.src) && this.strings.audio.catalogBase) {
          try { const base = String(this.strings.audio.catalogBase); const baseUrl = new URL(base.endsWith('/') ? base : base + '/', global.location.href); if (baseUrl.origin !== global.location.origin) throw new Error('audio_preview_origin_invalid'); audio.src = new URL(node.attributes.src, baseUrl).href; } catch (_) {}
        }
        card.append(audio, this.el('p', 'rb-preview-value', label + (node.attributes.src ? ' · ' + node.attributes.src.split('/').pop() : '')));
      } else if (node.type === 'core/video_embed') card.append(this.renderAdminVideoPreview(node.attributes.url));
      else if (node.type === 'core/gallery') card.append(this.el('p','rb-preview-value',this.label('core/gallery') + ' · ' + node.children.length));
      else if (node.type === 'core/cover') card.append(this.renderAdminImagePreview(node,'rb-cover-admin-preview'));
      else if (node.type === 'core/columns') card.append(this.el('p', 'rb-preview-value', node.attributes.count + ' ' + this.strings.columnCount));
      else if (node.type === 'core/table') {
        const wrap = this.el('div','rb-table-preview'); const table = this.el('table','');
        const body = this.el('tbody','');
        node.children.forEach((row,rowIndex) => {
          const tr = this.el('tr','');
          row.children.forEach((cell,colIndex) => {
            const isHeader = !!node.attributes.header && rowIndex === 0; const td = this.el(isHeader ? 'th' : 'td','');
            if (isHeader) td.setAttribute('scope','col');
            const editor = this.el('div','rb-table-cell-content'); editor.contentEditable = 'true'; editor.dataset.editContent = cell.id; editor.setAttribute('role','textbox'); editor.setAttribute('aria-multiline','true');
            editor.setAttribute('aria-label',(this.strings.table.cellLabel || 'Cell %1$d, %2$d').replace('%1$d',String(rowIndex+1)).replace('%2$d',String(colIndex+1)));
            buildInlineDom(editor,cell.content); if (cell.id === this.selectedId) td.append(this.renderInlineToolbar(cell)); td.append(editor); tr.append(td);
          });
          body.append(tr);
        });
        if (node.attributes.header && body.firstElementChild) { const head = this.el('thead',''); head.append(body.firstElementChild); table.append(head); }
        if (body.childNodes.length) table.append(body); wrap.append(table); card.append(wrap);
      }
      if (node.type !== 'core/table') node.children.forEach((child) => card.append(this.renderNode(child, false)));
      return card;
    }

    renderOutline() {
      const nav = this.el('nav','rb-outline'); nav.setAttribute('aria-label',this.strings.outline); nav.append(this.el('h3','rb-inspector-title',this.strings.outline));
      if (!this.state.blocks.length) { nav.append(this.el('p','rb-muted',this.strings.outlineEmpty)); return nav; }
      const list = this.el('ol','rb-outline-list');
      const append = (nodes,parent,depth) => nodes.forEach((node) => {
        if (['core/table-row','core/table-cell'].includes(node.type)) return;
        const item = this.el('li','rb-outline-item');
        const outlineName = node.type === 'core/group' && typeof node.attributes.outline_label === 'string' && node.attributes.outline_label.trim() ? node.attributes.outline_label.trim() : this.label(node.type);
        const button = this.button(outlineName,'outline-select',{nodeId:node.id});
        button.classList.add('rb-outline-button'); button.style.setProperty('--rb-outline-depth', String(depth));
        if (node.id === this.selectedId) button.setAttribute('aria-current','location'); item.append(button);
        if (node.children && node.children.length) { const nested = this.el('ol','rb-outline-list'); append(node.children,nested,depth+1); item.append(nested); }
        parent.append(item);
      });
      append(this.state.blocks,list,0); nav.append(list); return nav;
    }

    renderInspector() {
      const aside = this.el('aside', 'rb-inspector'); aside.setAttribute('aria-label', this.strings.inspector);
      const heading = this.el('h3', 'rb-inspector-title', this.strings.inspector); aside.append(heading);
      const found = this.selectedId ? this.findPath(this.state.blocks, this.selectedId) : null;
      if (!found) { aside.append(this.el('p', 'rb-muted', this.strings.inspectorEmpty)); return aside; }
      aside.append(this.el('p', 'rb-selected-type', this.label(found.node.type)));
      const addField = (label, key, value, type, choices) => {
        const wrap = this.el('label', 'rb-field'); wrap.append(this.el('span', '', label));
        let input;
        if (choices) { input = this.el('select', 'rb-input'); choices.forEach(([val, text]) => { const option = this.el('option', '', text); option.value = val; input.append(option); }); input.value = String(value); }
        else if (type === 'textarea') { input = this.el('textarea','rb-input'); input.value = value == null ? '' : String(value); input.maxLength = 4096; }
        else { input = this.el('input', 'rb-input'); input.type = type || 'text'; input.value = value == null ? '' : String(value); if (['alt','title','caption'].includes(key)) input.maxLength = 4096; if (key === 'outline_label') input.maxLength = 160; }
        input.dataset.attribute = key; input.dataset.nodeId = found.node.id; wrap.append(input); aside.append(wrap);
      };
      const n = found.node;
      if (n.type === 'core/heading') addField('Nível', 'level', n.attributes.level, 'number');
      if (n.type === 'core/paragraph') addField('Alinhamento', 'align', n.attributes.align || 'left', 'text', [['left','Esquerda'],['center','Centro'],['right','Direita']]);
      if (n.type === 'core/list') addField('Tipo de lista', 'ordered', n.attributes.ordered ? 'true' : 'false', 'text', [['false','Marcadores'],['true','Numerada']]);
      if (n.type === 'core/file') {
        addField(this.strings.file.source, 'src', n.attributes.src, 'text');
        aside.append(this.button(this.strings.file.choose, 'file-media', {nodeId:n.id}));
      }
      if (n.type === 'core/audio') {
        addField(this.strings.audio.source, 'src', n.attributes.src, 'text');
        aside.append(this.button(this.strings.audio.choose, 'audio-media', {nodeId:n.id}));
      }
      if (n.type === 'core/image') {
        addField(this.strings.media.source, 'src', n.attributes.src, 'text'); aside.append(this.button(this.strings.media.placeholder, 'media'));
        addField(this.strings.media.alt, 'alt', n.attributes.alt, 'text'); addField(this.strings.media.title, 'title', n.attributes.title || '', 'text');
        addField(this.strings.media.caption, 'caption', n.attributes.caption || '', 'textarea');
        addField(this.strings.media.alignment, 'align', n.attributes.align || 'center', 'text', [['left',this.strings.media.alignments.left],['center',this.strings.media.alignments.center],['right',this.strings.media.alignments.right],['wide',this.strings.media.alignments.wide],['full',this.strings.media.alignments.full]]);
        addField(this.strings.media.imageSize, 'size', n.attributes.size || 'large', 'text', Object.entries(this.strings.media.sizes));
        addField(this.strings.media.mobileSize, 'mobile_size', n.attributes.mobile_size || 'full', 'text', Object.entries(this.strings.media.mobileSizes));
      }
      if (n.type === 'core/cover') {
        addField(this.strings.media.source, 'src', n.attributes.src, 'text'); aside.append(this.button(this.strings.media.placeholder, 'media'));
        addField(this.strings.media.alt, 'alt', n.attributes.alt, 'text');
        addField(this.strings.media.alignment, 'align', n.attributes.align || 'wide', 'text', [['content',this.strings.media.alignments.content],['wide',this.strings.media.alignments.wide],['full',this.strings.media.alignments.full]]);
      }
      if (n.type === 'core/gallery') {
        addField(this.strings.media.galleryColumns, 'columns', n.attributes.columns, 'number', [[2,'2'],[3,'3'],[4,'4']]);
        addField(this.strings.media.alignment, 'align', n.attributes.align || 'content', 'text', [['content',this.strings.media.alignments.content],['wide',this.strings.media.alignments.wide],['full',this.strings.media.alignments.full]]);
      }
      if (n.type === 'core/video_embed') addField('URL YouTube/Vimeo', 'url', n.attributes.url, 'url');
      if (n.type === 'core/button') addField('Destino (URL ou caminho local)', 'url', n.attributes.url, 'url');
      if (n.type === 'core/group') addField(this.strings.outlineLabel, 'outline_label', n.attributes.outline_label || '', 'text');
      if (n.type === 'core/table') {
        const tableActions = this.el('div','rb-table-actions');
        const addRow = this.button(this.strings.table.addRow,'table-add-row',{nodeId:n.id}); addRow.disabled = n.children.length >= 20; tableActions.append(addRow);
        const removeRow = this.button(this.strings.table.removeRow,'table-remove-row',{nodeId:n.id}); removeRow.disabled = n.children.length <= 1; tableActions.append(removeRow);
        const columns = n.children[0] ? n.children[0].children.length : 0;
        const addColumn = this.button(this.strings.table.addColumn,'table-add-column',{nodeId:n.id}); addColumn.disabled = columns >= 12; tableActions.append(addColumn);
        const removeColumn = this.button(this.strings.table.removeColumn,'table-remove-column',{nodeId:n.id}); removeColumn.disabled = columns <= 1; tableActions.append(removeColumn);
        const header = this.button(this.strings.table.toggleHeader,'table-toggle-header',{nodeId:n.id}); header.setAttribute('aria-pressed',String(!!n.attributes.header)); tableActions.append(header);
        aside.append(tableActions);
      }
      if (n.type === 'core/columns') aside.append(this.el('p', 'rb-muted', 'Preset fixo nesta etapa: ' + n.attributes.count + ' colunas.'));
      const actions = this.el('div', 'rb-inspector-actions');
      const fixedColumn = found.parent && found.parent.type === 'core/columns' && n.type === 'core/column';
      const lastRequiredChild = found.parent && ['core/gallery','core/cover'].includes(found.parent.type) && found.parent.children.length <= 1;
      const remove = this.button(this.strings.remove, 'delete', { nodeId:n.id }); remove.disabled = !!fixedColumn || !!lastRequiredChild;
      actions.append(this.button(this.strings.moveUp, 'move-up', { nodeId:n.id }), this.button(this.strings.moveDown, 'move-down', { nodeId:n.id }), remove); aside.append(actions);
      return aside;
    }

    updateHistoryButtons() {
      this.root.querySelectorAll('[data-history="undo"]').forEach((b) => { b.disabled = this.undoStack.length === 0; });
      this.root.querySelectorAll('[data-history="redo"]').forEach((b) => { b.disabled = this.redoStack.length === 0; });
    }
  }

  global.ReamurBlogBlockEditor = {
    classifyKeyboardShortcut: classifyKeyboardShortcut,
    mount: function (root, options) { if (!(root instanceof Element)) throw new TypeError('editor root must be an Element'); return new Editor(root, options || {}); },
    validateDocument: validateDocument,
    validLocalImage: validLocalImage,
    validLocalDownload: validLocalDownload,
    validLocalAudio: validLocalAudio,
    validEmbed: validEmbed
  };
})(window);
