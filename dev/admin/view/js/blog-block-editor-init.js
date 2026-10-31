/* BDRE form binding with explicit server save and tab-scoped recovery of interrupted editor drafts. */
(function (global, document) {
  'use strict';
  var pendingMediaEditor = null;

  function unsavedState(form, strings) {
    if (!form.__blogBlockUnsavedState) {
      var status = document.createElement('p');
      status.className = 'rb-save-status';
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      status.setAttribute('aria-atomic', 'true');
      status.dataset.saveStatus = '1';
      form.insertBefore(status, form.firstChild);
      var state = { dirty: !!form.querySelector('.alert-danger'), bypass: false, status: status, strings: strings || {}, invalidRecoveryScheduled: false };
      state.render = function (mode) {
        var key = mode === 'saving' ? 'saveStateSaving' : (state.dirty ? 'saveStateUnsaved' : 'saveStateSaved');
        status.textContent = state.strings[key] || (mode === 'saving' ? 'Saving…' : (state.dirty ? 'Changes not saved' : 'All changes saved'));
        status.dataset.state = mode || (state.dirty ? 'unsaved' : 'saved');
        Array.prototype.forEach.call(form.querySelectorAll('[data-page-context-save-state]'), function (mirror) {
          mirror.textContent = status.textContent;
          mirror.dataset.state = status.dataset.state;
        });
      };
      state.markDirty = function () { state.dirty = true; state.render(); };
      state.render();
      form.__blogBlockUnsavedState = state;
      form.addEventListener('input', function (event) {
        if (event.target && !event.target.matches('[name^="blog_blocks["],[name^="blog_editor_ready["],[name="csrf_token"]')) state.markDirty();
      });
      form.addEventListener('change', function (event) {
        if (event.target && !event.target.matches('[name^="blog_blocks["],[name^="blog_editor_ready["],[name="csrf_token"]')) state.markDirty();
      });
      if (form.matches('[data-cms-page-form],[data-blog-post-form]')) form.addEventListener('invalid', function (event) {
        var field = event.target;
        if (!field || state.invalidRecoveryScheduled) return;
        var fullscreenRoot = form.querySelector('.rb-editor.is-fullscreen');
        if (!fullscreenRoot) return;
        state.invalidRecoveryScheduled = true;
        var instance = fullscreenRoot.__blogBlockEditorInstance;
        if (instance && typeof instance.setFullscreen === 'function') instance.setFullscreen(false);
        var pane = field.closest && field.closest('.tab-pane');
        var tab = pane && pane.getAttribute('aria-labelledby') ? document.getElementById(pane.getAttribute('aria-labelledby')) : null;
        if (tab && !pane.classList.contains('active')) tab.click();
        global.setTimeout(function () {
          state.invalidRecoveryScheduled = false;
          if (!field.isConnected) return;
          field.focus();
          if (field.scrollIntoView) field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 0);
      }, true);
      global.addEventListener('beforeunload', function (event) {
        if (!state.dirty || state.bypass) return;
        event.preventDefault();
        event.returnValue = '';
      });
      global.addEventListener('pageshow', function () {
        state.bypass = false;
        state.render();
      });
    } else if (strings) {
      form.__blogBlockUnsavedState.strings = strings;
      form.__blogBlockUnsavedState.render();
    }
    return form.__blogBlockUnsavedState;
  }

  document.addEventListener('blog-block-media-open', function (event) {
    pendingMediaEditor = event.detail && event.detail.editor || null;
    var trigger = document.querySelector('[data-bs-target="#blogMediaModal"]');
    if (trigger) trigger.click();
  });
  document.addEventListener('blog-block-media-selected', function (event) {
    if (pendingMediaEditor && event.detail && typeof event.detail.path === 'string') pendingMediaEditor.setSelectedAttribute('src', event.detail.path);
    pendingMediaEditor = null;
  });
  document.addEventListener('hidden.bs.modal', function (event) { if (event.target && event.target.id === 'blogMediaModal') pendingMediaEditor = null; });
  function byteLength(value) {
    try { return new Blob([value]).size; }
    catch (_) { try { return unescape(encodeURIComponent(value)).length; } catch (error) { return Infinity; } }
  }

  function draftStorage(config) {
    if (!config || typeof config.draftKey !== 'string' || !config.draftKey) return null;
    try { return { storage: global.sessionStorage, key: config.draftKey }; }
    catch (_) { return null; }
  }

  function recoverDraft(root, config, initial, strings) {
    var slot = draftStorage(config);
    if (!slot) return { document: initial, recovered: false };
    try {
      var initialValidation = global.ReamurBlogBlockEditor.validateDocument(initial, !!root.dataset.pageContext);
      if (!initialValidation.valid) return { document: initial, recovered: false };
    } catch (_) { return { document: initial, recovered: false }; }
    try {
      var raw = slot.storage.getItem(slot.key);
      if (!raw) return { document: initial, recovered: false };
      var saved = JSON.parse(raw);
      if (!saved || ![1,2].includes(saved.version) || !saved.document || !saved.savedAt || Date.now() - saved.savedAt > 86400000) {
        slot.storage.removeItem(slot.key);
        return { document: initial, recovered: false };
      }
      var allowPageMedia = !!root.dataset.pageContext;
      var validation = global.ReamurBlogBlockEditor.validateDocument(saved.document, allowPageMedia);
      if (!validation.valid || byteLength(JSON.stringify(saved.document)) > 4194304) {
        slot.storage.removeItem(slot.key);
        return { document: initial, recovered: false };
      }
      if (JSON.stringify(saved.document) === JSON.stringify(initial)) {
        slot.storage.removeItem(slot.key);
        return { document: initial, recovered: false };
      }
      var promptText = strings.draftRecoveryPrompt || 'An unsaved draft was found in this browser tab. Restore it?';
      if (global.confirm(promptText)) return { document: saved.document, recovered: true };
      slot.storage.removeItem(slot.key);
    } catch (_) {}
    return { document: initial, recovered: false };
  }

  function storeDraft(config, doc, validation) {
    var slot = draftStorage(config);
    if (!slot || !doc || !validation || !validation.valid) return;
    try {
      var encoded = JSON.stringify(doc);
      if (byteLength(encoded) > 4194304) return;
      var previous = null;
      try { previous = JSON.parse(slot.storage.getItem(slot.key) || 'null'); } catch (_) {}
      var history = previous && Array.isArray(previous.history) ? previous.history.slice(-5) : [];
      if (previous && previous.document && JSON.stringify(previous.document) !== encoded) {
        var previousBytes = byteLength(JSON.stringify(previous.document));
        if (previousBytes <= 524288) history.push({ savedAt:previous.savedAt || Date.now(), document:previous.document });
      }
      history = history.filter(function (item) { return item && item.document && item.savedAt && Date.now() - item.savedAt <= 86400000 && byteLength(JSON.stringify(item.document)) <= 524288; }).slice(-5);
      var total = function () { return history.reduce(function (sum, item) { return sum + byteLength(JSON.stringify(item.document)); }, 0); };
      while (history.length && total() > 524288) history.shift();
      var record = { version:2, savedAt:Date.now(), document:doc, history:history };
      while (true) {
        try { slot.storage.setItem(slot.key, JSON.stringify(record)); return; }
        catch (_) { if (!history.length) break; history.shift(); }
      }
      // Keep recovery even if quota forces revision-history eviction.
      slot.storage.setItem(slot.key, JSON.stringify({ version:1, savedAt:record.savedAt, document:doc }));
    } catch (_) {
      // Storage may be disabled/full; ordinary explicit saving remains available.
    }
  }

  function revisionDocumentText(doc) {
    var parts = [];
    function inline(value) {
      if (typeof value === 'string') { parts.push(value); return; }
      if (Array.isArray(value)) { value.forEach(inline); return; }
      if (!value || typeof value !== 'object') return;
      if (typeof value.text === 'string') parts.push(value.text);
      else if (Array.isArray(value.content)) inline(value.content);
    }
    function blocks(nodes) {
      if (!Array.isArray(nodes)) return;
      nodes.forEach(function (node) {
        if (!node || typeof node !== 'object') return;
        if (typeof node.content === 'string' || Array.isArray(node.content)) inline(node.content);
        if (Array.isArray(node.children)) blocks(node.children);
      });
    }
    if (doc && Array.isArray(doc.blocks)) blocks(doc.blocks);
    return parts.join('\n').replace(/\n{3,}/g, '\n\n').trim().slice(0, 30000);
  }

  function revisionSnapshotText(snapshot) {
    var doc = snapshot && snapshot.block_document || null;
    if (!doc && snapshot && snapshot.block_content && snapshot.block_content.content_json) {
      try { doc = JSON.parse(snapshot.block_content.content_json); } catch (_) {}
    }
    var raw = String(snapshot && snapshot.description && snapshot.description.description || '');
    if (!doc && raw.indexOf('REAMURCMS_BDRE_JSON_V2:') === 0) { try { doc = JSON.parse(raw.slice('REAMURCMS_BDRE_JSON_V2:'.length)); } catch (_) {} }
    return doc ? revisionDocumentText(doc) : raw.slice(0, 30000);
  }

  function currentEditorText(panel, form) {
    var root = document.getElementById(panel.dataset.editorId || '');
    var editor = root && root.__blogBlockEditorInstance;
    if (editor && typeof editor.getDocument === 'function') return revisionDocumentText(editor.getDocument());
    var field = form.querySelector('[name="' + panel.dataset.kind + '_description[' + panel.dataset.languageId + '][description]"]') || document.getElementById(panel.dataset.descriptionId || '');
    var raw = field && typeof field.value === 'string' ? field.value : '';
    if (raw.indexOf('REAMURCMS_BDRE_JSON_V2:') === 0) { try { return revisionDocumentText(JSON.parse(raw.slice('REAMURCMS_BDRE_JSON_V2:'.length))); } catch (_) {} }
    return raw.slice(0, 30000);
  }

  function appendCompareColumn(container, heading, metadata, body, panel) {
    var column=document.createElement('div'); column.className='col-12 col-lg-6';
    var card=document.createElement('section'); card.className='border rounded p-3 h-100';
    var title=document.createElement('h5'); title.className='h6'; title.textContent=heading; card.appendChild(title);
    var metaTitle=document.createElement('h6'); metaTitle.className='small fw-semibold mt-3'; metaTitle.textContent=panel.dataset.historyMetaLabel || 'Editorial metadata'; card.appendChild(metaTitle);
    var meta=document.createElement('pre'); meta.className='small text-wrap'; meta.textContent=JSON.stringify(metadata,null,2); card.appendChild(meta);
    var bodyTitle=document.createElement('h6'); bodyTitle.className='small fw-semibold mt-3'; bodyTitle.textContent=panel.dataset.historyBodyLabel || 'Content'; card.appendChild(bodyTitle);
    var content=document.createElement('pre'); content.className='small text-wrap mb-0'; content.style.maxHeight='24rem'; content.style.overflow='auto'; content.textContent=body || '—'; card.appendChild(content);
    column.appendChild(card); container.appendChild(column);
  }

  function appendRevisionDiff(container, oldText, newText, panel) {
    var section=document.createElement('section'); section.className='border rounded p-3 mt-3'; section.setAttribute('aria-label',panel.dataset.historyDiffLabel||'Text differences');
    var heading=document.createElement('h5'); heading.className='h6'; heading.textContent=panel.dataset.historyDiffLabel||'Text differences'; section.appendChild(heading);
    var oldTokens=String(oldText||'').match(/\\s+|[^\\s]+/g)||[], newTokens=String(newText||'').match(/\\s+|[^\\s]+/g)||[];
    if(oldTokens.length>450||newTokens.length>450){var limited=document.createElement('p');limited.className='small mb-0';limited.textContent=panel.dataset.historyDiffLimit||'Document too large for word-level comparison.';section.appendChild(limited);container.appendChild(section);return;}
    var rows=oldTokens.length+1,cols=newTokens.length+1,dp=Array.from({length:rows},function(){return new Uint16Array(cols);});
    for(var i=oldTokens.length-1;i>=0;i--) for(var j=newTokens.length-1;j>=0;j--) dp[i][j]=oldTokens[i]===newTokens[j]?dp[i+1][j+1]+1:Math.max(dp[i+1][j],dp[i][j+1]);
    var ops=[],a=0,b=0;
    function push(kind,text){if(!text)return;var last=ops[ops.length-1];if(last&&last.kind===kind)last.text+=text;else ops.push({kind:kind,text:text});}
    while(a<oldTokens.length&&b<newTokens.length){if(oldTokens[a]===newTokens[b]){push('same',oldTokens[a]);a++;b++;}else if(dp[a+1][b]>=dp[a][b+1]){push('del',oldTokens[a++]);}else{push('ins',newTokens[b++]);}}
    while(a<oldTokens.length)push('del',oldTokens[a++]); while(b<newTokens.length)push('ins',newTokens[b++]);
    if(!ops.some(function(op){return op.kind!=='same';})){var unchanged=document.createElement('p');unchanged.className='small mb-0';unchanged.textContent=panel.dataset.historyUnchanged||'No textual differences.';section.appendChild(unchanged);container.appendChild(section);return;}
    var pre=document.createElement('pre');pre.className='small text-wrap mb-0';pre.style.whiteSpace='pre-wrap';pre.style.maxHeight='20rem';pre.style.overflow='auto';
    ops.forEach(function(op){if(op.kind==='same'){pre.appendChild(document.createTextNode(op.text));return;}var mark=document.createElement(op.kind==='del'?'del':'ins');mark.className=op.kind==='del'?'text-danger':'text-success';mark.textContent=op.text;pre.appendChild(mark);});
    section.appendChild(pre);container.appendChild(section);
  }

  function bindServerRevisionHistory() {
    document.addEventListener('click', function (event) {
      var loadButton = event.target.closest && event.target.closest('[data-bdre-history-load]');
      var restoreButton = event.target.closest && event.target.closest('[data-bdre-history-restore]');
      var compareButton = event.target.closest && event.target.closest('[data-bdre-history-compare]');
      if (!loadButton && !restoreButton && !compareButton) return;
      var panel = (loadButton || restoreButton || compareButton).closest('[data-bdre-history]');
      var form = panel && panel.closest('form');
      var list = panel && panel.querySelector('[data-bdre-history-items]');
      var token = form && form.querySelector('[name="csrf_token"]');
      if (!panel || !form || !list || !token) return;
      event.preventDefault();
      var languageId = panel.dataset.languageId || '';
      var post = function (url, values) {
        var body = new URLSearchParams(Object.assign({csrf_token: token.value, language_id: languageId}, values || {}));
        return fetch(url, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest'}, body: body.toString()}).then(function (response) { if (!response.ok) throw new Error('request_failed'); return response.json(); });
      };
      if (loadButton) {
        loadButton.disabled = true; list.textContent = 'Carregando…';
        post(panel.dataset.listUrl).then(function (data) {
          list.replaceChildren();
          if (!data || !data.success || !Array.isArray(data.items)) throw new Error('history_unavailable');
          if (!data.items.length) { list.textContent = panel.dataset.historyEmpty || 'No saved revisions available.'; return; }
          data.items.forEach(function (item) {
            var row = document.createElement('div'); row.className = 'd-flex align-items-center gap-2 mb-1';
            var label = document.createElement('span'); label.className = 'small text-body-secondary'; label.textContent = item.date_added || ('#' + item.revision_id);
            var compare = document.createElement('button'); compare.type = 'button'; compare.className = 'btn btn-sm btn-outline-secondary'; compare.textContent = panel.dataset.historyCompareLabel || 'Compare'; compare.dataset.bdreHistoryCompare = '1'; compare.dataset.revisionId = String(item.revision_id);
            var restore = document.createElement('button'); restore.type = 'button'; restore.className = 'btn btn-sm btn-outline-primary'; restore.textContent = panel.dataset.historyRestoreLabel || 'Restore'; restore.dataset.bdreHistoryRestore = '1'; restore.dataset.revisionId = String(item.revision_id);
            row.append(label, compare, restore); list.appendChild(row);
            var preview=document.createElement('div'); preview.className='row g-2 my-2 d-none'; preview.dataset.bdreHistoryCompareResult=String(item.revision_id); list.appendChild(preview);
          });
        }).catch(function () { list.textContent = 'Não foi possível carregar o histórico.'; }).finally(function () { loadButton.disabled = false; });
        return;
      }
      if (compareButton) {
        compareButton.disabled=true;
        var compareResult=list.querySelector('[data-bdre-history-compare-result="' + compareButton.dataset.revisionId + '"]');
        post(panel.dataset.itemUrl,{revision_id:compareButton.dataset.revisionId}).then(function(data){
          if(!data||!data.success||!data.snapshot) throw new Error('revision_unavailable');
          if(!compareResult) throw new Error('comparison_unavailable');
          compareResult.replaceChildren(); compareResult.classList.remove('d-none');
          var fields=data.snapshot.description||{}, lid=panel.dataset.languageId, kind=panel.dataset.kind;
          var oldMeta={title:fields.title||'',meta_title:fields.meta_title||'',meta_description:fields.meta_description||'',keyword:fields.keyword||''};
          if(data.snapshot.entity) oldMeta.entity=data.snapshot.entity;
          var currentMeta={};
          ['title','meta_title','meta_description','keyword'].forEach(function(key){var input=form.querySelector('[name="'+kind+'_description['+lid+']['+key+']"]');currentMeta[key]=input?input.value:'';});
          var currentStatus=form.querySelector('[name="status"]'); currentMeta.entity={status:currentStatus&&currentStatus.checked?1:0};
          if(kind==='blog'){var currentImage=form.querySelector('[name="image"]'),currentDate=form.querySelector('[name="date_published"]');currentMeta.entity.image=currentImage?currentImage.value:'';currentMeta.entity.date_published=currentDate?currentDate.value:'';}
          else {var currentSort=form.querySelector('[name="sort_order"]');currentMeta.entity.sort_order=currentSort?currentSort.value:'';}
          var oldText=revisionSnapshotText(data.snapshot), currentText=currentEditorText(panel,form);
          appendCompareColumn(compareResult,(panel.dataset.historyVersionLabel||'Selected revision')+' · '+String(compareButton.previousElementSibling && compareButton.previousElementSibling.textContent||''),oldMeta,oldText,panel);
          appendCompareColumn(compareResult,panel.dataset.historyCurrentLabel||'Current form',currentMeta,currentText,panel);
          appendRevisionDiff(compareResult,oldText,currentText,panel);
        }).catch(function(){ if(compareResult){compareResult.classList.remove('d-none');compareResult.textContent=panel.dataset.historyError||'Could not load the revision comparison.';} }).finally(function(){compareButton.disabled=false;});
        return;
      }
      if (!global.confirm(panel.dataset.historyConfirm || 'Load this revision as unsaved changes? Save to apply it.')) return;
      restoreButton.disabled = true;
      post(panel.dataset.itemUrl, {revision_id: restoreButton.dataset.revisionId}).then(function (data) {
        if (!data || !data.success || !data.snapshot) throw new Error('revision_unavailable');
        var snapshot = data.snapshot, fields = snapshot.description || {}, lid = languageId;
        var kind = panel.dataset.kind;
        ['title','meta_title','meta_description','keyword'].forEach(function (key) {
          var input = form.querySelector('[name="' + kind + '_description[' + lid + '][' + key + ']"]');
          if (input && fields[key] !== undefined) { input.value = fields[key] || ''; input.dispatchEvent(new Event('input', {bubbles:true})); input.dispatchEvent(new Event('change', {bubbles:true})); }
        });
        var rawDescription = String(fields.description || '');
        var descriptionField = form.querySelector('[name="' + kind + '_description[' + lid + '][description]"]') || document.getElementById(panel.dataset.descriptionId || '');
        var doc = snapshot.block_document || null;
        if (!doc && kind === 'blog' && snapshot.block_content && snapshot.block_content.content_json) { try { doc = JSON.parse(snapshot.block_content.content_json); } catch (_) {} }
        if (!doc && rawDescription.indexOf('REAMURCMS_BDRE_JSON_V2:') === 0) { try { doc = JSON.parse(rawDescription.slice('REAMURCMS_BDRE_JSON_V2:'.length)); } catch (_) {} }
        var editorRoot = document.getElementById(panel.dataset.editorId || '');
        var editor = editorRoot && editorRoot.__blogBlockEditorInstance;
        if (doc && editor && typeof editor.replaceDocument === 'function') {
          if (!editor.replaceDocument(doc)) throw new Error('revision_invalid');
          if (descriptionField && descriptionField.type !== 'hidden') descriptionField.value = rawDescription;
        } else if (descriptionField && descriptionField.tagName === 'TEXTAREA' && !descriptionField.hidden) {
          descriptionField.value = rawDescription;
          descriptionField.dispatchEvent(new Event('input', {bubbles:true}));
        } else if (rawDescription.indexOf('REAMURCMS_PAGE_LAYOUT_V1:') === 0) {
          throw new Error('layout_revision_requires_review');
        } else if (doc && !editor) {
          throw new Error('editor_not_ready');
        } else if (descriptionField && descriptionField.hidden) {
          throw new Error('revision_conversion_unavailable');
        }
        if (kind === 'page' && Array.isArray(snapshot.page_layout_sections)) {
          var layoutList = form.querySelector('[data-page-layout-list][data-page-layout-language="' + lid + '"]');
          if (layoutList) {
            var layoutItems = Array.prototype.slice.call(layoutList.querySelectorAll('[data-page-layout-item]'));
            var bySection = {};
            layoutItems.forEach(function (item) { var input=item.querySelector('input[type="checkbox"]'); if(input) { bySection[input.value]=item; input.checked=snapshot.page_layout_sections.indexOf(input.value)!==-1; } });
            var ordered = snapshot.page_layout_sections.map(function (section) { return bySection[section]; }).filter(Boolean);
            layoutItems.forEach(function (item) { if (ordered.indexOf(item) === -1) ordered.push(item); });
            ordered.forEach(function (item) { layoutList.appendChild(item); });
          }
        }
        if (snapshot.entity) {
          var status=form.querySelector('[name="status"]'); if (status && snapshot.entity.status !== undefined) { status.checked=!!Number(snapshot.entity.status); status.dispatchEvent(new Event('change',{bubbles:true})); }
          if (kind === 'blog') {
            var image=form.querySelector('[name="image"]'); if(image && snapshot.entity.image !== undefined) { image.value=snapshot.entity.image; image.dispatchEvent(new Event('input',{bubbles:true})); }
            var date=form.querySelector('[name="date_published"]'); if(date && snapshot.entity.date_published !== undefined) { date.value=snapshot.entity.date_published.replace(' ','T'); date.dispatchEvent(new Event('change',{bubbles:true})); }
          } else {
            var sort=form.querySelector('[name="sort_order"]'); if(sort && snapshot.entity.sort_order !== undefined) { sort.value=String(snapshot.entity.sort_order); sort.dispatchEvent(new Event('input',{bubbles:true})); }
          }
        }
        var state=form.__blogBlockUnsavedState; if(state) state.markDirty();
        var statusMsg=list; statusMsg.textContent=panel.dataset.historyRestored || 'Revision loaded as unsaved changes. Save to apply it.';
      }).catch(function (error) { list.textContent = error && error.message === 'layout_revision_requires_review' ? 'Esta versão contém layout especial; restaure-a pelo fluxo de revisão assistida.' : 'Não foi possível restaurar esta versão.'; }).finally(function () { restoreButton.disabled = false; });
    });
  }

  function init() {
    if (!global.ReamurBlogBlockEditor) return;
    document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-page-layout-move]');
    if (!button) return;
    var item = button.closest('[data-page-layout-item]');
    var list = item && item.parentElement;
    if (!item || !list) return;
    if (button.dataset.pageLayoutMove === 'up' && item.previousElementSibling) list.insertBefore(item, item.previousElementSibling);
    if (button.dataset.pageLayoutMove === 'down' && item.nextElementSibling) list.insertBefore(item.nextElementSibling, item);
  });

  document.querySelectorAll('[data-blog-block-editor]').forEach(function (root) {
      var hidden = document.getElementById(root.dataset.hiddenInput || '');
      var ready = document.getElementById(root.dataset.readyInput || '');
      var form = root.closest('form');
      var editorStrings = {};
      try { editorStrings = JSON.parse(root.dataset.editorStrings || '{}'); } catch (_) {}
      var patternConfig = {};
      try { patternConfig = JSON.parse(root.dataset.patternConfig || '{}'); } catch (_) {}
      var pageContext = null;
      try {
        var contextLabels = JSON.parse(root.dataset.pageContext || '');
        pageContext = Object.assign({}, contextLabels, {
          pageTitle: root.dataset.pageTitle || '',
          pageTitleInput: document.getElementById(root.dataset.pageTitleInput || ''),
          languageName: root.dataset.pageLanguage || '',
          languageCode: root.dataset.pageLanguageCode || ''
        });
      } catch (_) {}
      var changeState = form ? unsavedState(form, editorStrings) : null;
      if (pageContext) pageContext.saveStatus = changeState ? changeState.status : null;
      var workspaceContext = pageContext;
      if (!workspaceContext) {
        try {
          var workspaceLabels = JSON.parse(root.dataset.workspaceContext || '');
          workspaceContext = Object.assign({}, workspaceLabels, {
            pageTitleInput: document.getElementById(root.dataset.pageTitleInput || ''),
            pageTitle: (document.getElementById(root.dataset.pageTitleInput || '') || {}).value || '',
            languageName: root.dataset.pageLanguage || '',
            languageCode: root.dataset.pageLanguageCode || '',
            saveStatus: changeState ? changeState.status : null
          });
        } catch (_) {}
      }
      var tabPane = root.closest('.tab-pane');
      var workspaceForm = form && form.matches('[data-cms-page-form],[data-blog-post-form]');
      var startFullscreen = !!workspaceForm && root.dataset.fullscreenStart !== 'false' && (!tabPane || tabPane.classList.contains('active'));
      try {
        var initial = JSON.parse(root.dataset.document || '');
        var recovery = recoverDraft(root, patternConfig, initial, editorStrings);
        initial = recovery.document;
        root.setAttribute('aria-label', editorStrings.editorLabel || 'Blog block editor');
        var editor = global.ReamurBlogBlockEditor.mount(root, {
          document: initial,
          strings: editorStrings,
          patternConfig: patternConfig,
          workspaceContext: workspaceContext,
          startFullscreen: startFullscreen,
          connectedSave: true,
          mediaPickers: root.dataset.mediaPickers !== 'false',
          pageImagePicker: !!(pageContext && pageContext.canSave),
          pageMediaPicker: !!(pageContext && pageContext.canSave),
          filePicker: root.dataset.filePicker === 'false' ? false : (root.dataset.mediaPickers !== 'false'),
          audioPicker: root.dataset.audioPicker === 'true' || root.dataset.mediaPickers !== 'false',
          videoPicker: root.dataset.videoPicker === 'true' || root.dataset.mediaPickers !== 'false',
          onChange: function (doc, validation) {
            storeDraft(patternConfig, doc, validation);
            if (hidden && (!validation.valid || !doc)) hidden.value = '';
            root.dataset.valid = validation.valid ? 'true' : 'false';
            root.setAttribute('aria-invalid', validation.valid ? 'false' : 'true');
            root.dataset.dirty = 'true';
            if (changeState) changeState.markDirty();
          }
        });
        root.__blogBlockEditorInstance = editor;
        if (recovery.recovered) {
          root.dataset.dirty = 'true';
          if (changeState) changeState.markDirty();
        }
        if (hidden && editor.getDocument()) {
          hidden.value = (root.dataset.storagePrefix || '') + editor.serialize();
          if (ready) ready.value = '1';
          root.dataset.valid = 'true';
          root.setAttribute('aria-invalid', 'false');
          root.dataset.dirty = recovery.recovered ? 'true' : 'false';
        } else {
          if (hidden) hidden.value = '';
          if (ready) ready.value = '';
          root.dataset.valid = 'false';
        }
        if (form) form.addEventListener('submit', function (event) {
          var hosts = Array.prototype.slice.call(form.querySelectorAll('[data-blog-block-editor]'));
          var allEditorsValid = hosts.every(function (host) { return host.dataset.valid === 'true'; });
          if (allEditorsValid && !event.__blogBlockSerialized) {
            hosts.forEach(function (host) {
              var instance = host.__blogBlockEditorInstance;
              var target = document.getElementById(host.dataset.hiddenInput || '');
              try {
                if (!instance || !target) throw new Error('editor_submission_unavailable');
                target.value = (host.dataset.storagePrefix || '') + instance.serialize();
                host.dataset.valid = 'true';
                host.setAttribute('aria-invalid', 'false');
              } catch (error) {
                host.dataset.valid = 'false';
                host.setAttribute('aria-invalid', 'true');
                var message = 'O documento excede o limite ou contém dados inválidos.';
                try {
                  var strings = JSON.parse(host.dataset.editorStrings || '{}');
                  message = error && error.message === 'document_too_large' ? (strings.documentTooLarge || message) : (strings.initialInvalid || message);
                } catch (_) {}
                var live = host.querySelector('[data-editor-status]');
                if (live) live.textContent = message;
              }
            });
            allEditorsValid = hosts.every(function (host) { return host.dataset.valid === 'true'; });
            event.__blogBlockSerialized = true;
          }
          if (!allEditorsValid || !hidden || !hidden.value || hosts.some(function (host) { return host.dataset.valid === 'false'; })) {
            event.preventDefault();
            if (changeState) { changeState.bypass = false; changeState.markDirty(); }
            root.focus();
            root.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
          }
          if (changeState) {
            changeState.bypass = true;
            changeState.status.textContent = changeState.strings.saveStateSaving || 'Saving…';
            changeState.status.dataset.state = 'saving';
          }
        });
      } catch (error) {
        root.replaceChildren();
        root.setAttribute('role', 'alert');
        var invalidMessage = 'Documento indisponível para edição; a fonte original foi preservada.';
        try { var failureStrings = JSON.parse(root.dataset.editorStrings || '{}'); if (failureStrings.initialInvalid) invalidMessage = failureStrings.initialInvalid; } catch (_) {}
        root.textContent = invalidMessage;
        if (hidden) hidden.value = '';
        if (ready) ready.value = '';
      }
    });
  }
  bindServerRevisionHistory();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})(window, document);
