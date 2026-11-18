#!/usr/bin/env node
'use strict';
const fs=require('fs'); const path=require('path');
const root=path.resolve(process.argv[2]||path.join(__dirname,'../..'));
const read=(p)=>fs.readFileSync(path.join(root,p),'utf8');
const errors=[]; const need=(ok,msg)=>{if(!ok)errors.push(msg)};
const between=(source,startMark,endMark)=>{const start=source.indexOf(startMark);const end=start<0?-1:source.indexOf(endMark,start+startMark.length);return start>=0&&end>start?source.slice(start,end):''};
const php=read('dev/system/library/blog/block_document.php');
const js=read('dev/admin/view/js/blog-block-editor.js');
const init=read('dev/admin/view/js/blog-block-editor-init.js');
const controller=read('dev/admin/controller/blog/blog.php');
const pageController=read('dev/admin/controller/cms/page.php');
const patternController=read('dev/admin/controller/cms/block_pattern.php');
const patternModel=read('dev/admin/model/cms/block_pattern.php');
const schema=read('dev/install/sql/schema-complete.sql');
for(const marker of ["['outline_label']",'outline_label_invalid','group_attributes_invalid']) need(php.includes(marker),`PHP contract missing ${marker}`);
for(const marker of ['outline_label','outline_label_invalid','outlineLabel','data-action']) need(js.includes(marker),`editor contract missing ${marker}`);
for(const marker of ['beforeunload','allEditorsValid','changeState.bypass','state.dirty = true']) need(init.includes(marker),`unsaved-change guard missing ${marker}`);
need(controller.includes("'text_block_outline_label'"),'controller misses outline label language key');
need(controller.includes("'outlineLabel' => $data['text_block_outline_label']"),'controller does not expose outlineLabel editor string');
for(const locale of ['pt-br','en-us','es-eu']) need(read(`dev/admin/language/${locale}/blog/blog.php`).includes("$_['text_block_outline_label']"),`${locale} missing outline label`);

// D08=A: describe and check existing persistence by mechanism; do not block safe/local behavior by generic tokens.
const draftStorage=between(init,'function draftStorage(','function recoverDraft(');
const draftRecover=between(init,'function recoverDraft(','function storeDraft(');
const draftWriter=between(init,'function storeDraft(','function revisionDocumentText(');
need(draftStorage.includes('global.sessionStorage'),'temporary draft recovery uses sessionStorage');
need(draftWriter.includes('slot.storage.setItem')&&draftWriter.includes('validation.valid')&&init.includes('storeDraft(patternConfig, doc, validation)'),'only validated editor changes are stored in the local recovery slot');
need(draftWriter.includes('4194304')&&draftWriter.includes('524288')&&draftWriter.includes('slice(-5)'),'draft and bounded history snapshots keep their existing size/count limits');
need(draftRecover.includes('86400000')&&draftRecover.includes('global.confirm(promptText)'),'draft recovery expires after 24h and requires explicit restore confirmation');
need(!/\bfetch\s*\(|\bnew\s+XMLHttpRequest\s*\(/.test(draftWriter),'draft recovery writes locally only (no server request)');
need(controller.includes("'draftKey' => 'reamur:bdre:draft:v1:u'")&&controller.includes(":blog:' . (int)($post['post_id'] ?? 0)"),'Blog draft key is scoped by admin user and post');
need(pageController.includes("'draftKey' => 'reamur:bdre:draft:v1:u'")&&pageController.includes(":pages:' . (int)($page['page_id'] ?? 0)"),'Page draft key is scoped by admin user and page');

const localCalls=js.match(/global\.localStorage\.(?:getItem|setItem)\s*\(/g)||[];
need(localCalls.length===4&&js.includes('readEditorNotes()')&&js.includes('writeEditorNotes(')&&js.includes('readStylePresets()')&&js.includes('writeStylePresets('),'localStorage call sites are limited to editor notes and style presets');
for(const [name,source] of [['Blog',controller],['Page',pageController]]){
 need(source.includes("'stylePresetKey' => 'reamur:bdre:style-presets:v1:u'"),`${name} style presets are scoped by admin user/context`);
 need(source.includes("'editorNotesKey' => !empty(")&&source.includes("'reamur:bdre:editor-notes:v1:u'"),`${name} editor notes are scoped by admin user and saved record`);
}

// Pattern CRUD is existing same-origin API, private to owner, protected by context + dedicated ACL and CSRF.
const patternRequest=between(js,'async patternRequest(','async loadSavedPatterns(');
need(patternRequest.includes('fetch(url, options)')&&patternRequest.includes("credentials: 'same-origin'"),'pattern API uses same-origin fetch');
for(const [name,start,end] of [
 ['save','async saveSelectedPattern(','async insertSavedPattern('],
 ['rename','async renameSavedPattern(','async deleteSavedPattern('],
 ['delete','async deleteSavedPattern(','insertPattern(']
]) need(between(js,start,end).includes('csrf_token:this.patternConfig.csrfToken ||'),`pattern ${name} request includes configured CSRF token`);
for(const [name,source] of [['Blog',controller],['Page',pageController]]){
 need(['listUrl','getUrl','saveUrl','renameUrl','deleteUrl'].every(k=>source.includes("'"+k+"'")),`${name} editor receives explicit pattern CRUD endpoints`);
 need(source.includes("'csrfToken' => \\Reamur\\System\\Library\\Security\\Csrf::token($this->session)"),`${name} editor receives session CSRF token`);
}
const authorization=between(patternController,'private function authorize(','private function inputId(');
need(authorization.includes("hasPermission('access', 'cms/block_pattern')")&&authorization.includes("$operation === 'write'")&&authorization.includes("hasPermission('modify', 'cms/block_pattern')"),'pattern API enforces access/read and modify/write ACL on cms/block_pattern');
need(authorization.includes("$context === 'blog' ? 'blog/blog' : 'cms/page'")&&authorization.includes("hasPermission('modify', $contextRoute)"),'pattern API requires modify on its current Blog/Page context');
const writes=[
 ['savePatternFor','private function savePatternFor(','private function renamePatternFor(','addPattern($owner'],
 ['renamePatternFor','private function renamePatternFor(','private function deletePatternFor(','renamePattern($patternId, $owner'],
 ['deletePatternFor','private function deletePatternFor(','private function authorize(','deletePattern($patternId, $owner']
];
for(const [name,start,end,ownerCall] of writes){
 const body=between(patternController,start,end);
 need(body.includes("$this->authorize('write', $context)")&&body.includes('$this->csrfValid()'),`${name} requires write ACL and CSRF`);
 need(body.includes('$this->user->getUserId()')&&body.includes(ownerCall),`${name} derives owner from authenticated user and passes it to storage`);
}
need(patternController.includes('getPattern($patternId, $this->user->getUserId())'),'pattern reads query only the authenticated owner');
const modelMethods=[
 ['getPatterns','public function getPatterns(','public function getPattern('],
 ['getPattern','public function getPattern(','public function getTotalPatterns('],
 ['addPattern','public function addPattern(','public function renamePattern('],
 ['renamePattern','public function renamePattern(','public function deletePattern('],
 ['deletePattern','public function deletePattern(','/** Manual cascade']
];
for(const [name,start,end] of modelMethods){const body=between(patternModel,start,end);need(body.includes('owner_user_id')&&body.includes('$owner_user_id'),`pattern model ${name} scopes storage by owner_user_id`);}
need(patternModel.includes('MAX_PATTERNS_PER_USER = 50')&&patternController.includes('MAX_DOCUMENT_BYTES')&&patternController.includes('containsCustomJs'),'pattern API keeps existing count/size limits and rejects custom_js');
need(patternModel.includes('owner_user_id')&&schema.includes('CREATE TABLE IF NOT EXISTS `rms_cms_block_pattern`'),'private pattern table already exists in schema-complete');

// These are the only editor fetch call sites: pattern CRUD plus D05-approved, click-triggered revision history.
const fetchCalls=(js.match(/\bfetch\s*\(/g)||[]).length+(init.match(/\bfetch\s*\(/g)||[]).length;
need(fetchCalls===2,`expected exactly 2 reviewed same-origin fetch call sites; found ${fetchCalls}`);
const historyPost=between(init,'var post = function (url, values) {','\n      };');
need(historyPost.includes("method: 'POST'")&&historyPost.includes("credentials: 'same-origin'")&&historyPost.includes('csrf_token: token.value'),'revision history requests are same-origin and include the form CSRF token');
const historyHandler=between(init,'function bindServerRevisionHistory() {','  bindServerRevisionHistory();');
need(historyHandler.includes("document.addEventListener('click'")&&historyHandler.includes('data-bdre-history-load')&&historyHandler.includes('post(panel.dataset.itemUrl'),'revision history access is triggered by explicit user clicks');
need(init.includes("'X-Requested-With': 'XMLHttpRequest'"),'revision fetch keeps XMLHttpRequest only as the X-Requested-With header value');
need(!/\bnew\s+XMLHttpRequest\s*\(/.test(js+init),'editor does not instantiate the XMLHttpRequest API');

let fixtures=[];try{fixtures=JSON.parse(read('tests/fixtures/block_patterns_f925.json'));}catch(e){errors.push(`fixture JSON parse failed: ${e.message}`)}
need(Array.isArray(fixtures)&&fixtures.length===4,'expected four synthetic fixtures');
if(Array.isArray(fixtures)){
 const ids=new Set(); for(const f of fixtures){need(typeof f.id==='string'&&!ids.has(f.id),`fixture ID missing/duplicate ${f.id}`);ids.add(f.id);}
 const tooLong=fixtures.find(f=>f.id==='outline-label-too-long-rejected');
 need(!!tooLong&&tooLong.document.blocks[0].attributes.outline_label.length>160,'oversize label fixture does not exceed byte/code-unit limit');
}
let contract={};try{contract=JSON.parse(read('docs/F9-25-Contrato-Editor-Editorial.json'));}catch(e){errors.push(`contract JSON parse failed: ${e.message}`)}
need(contract.patterns_persistence&&contract.patterns_persistence.status==='IMPLEMENTED_EXISTING','F9-25 documents existing pattern persistence (D08=A)');
need(contract.patterns_persistence&&contract.patterns_persistence.authorization&&contract.patterns_persistence.authorization.includes('cms/block_pattern'),'contract records dedicated pattern ACL');
need(contract.patterns_persistence&&contract.patterns_persistence.schema&&contract.patterns_persistence.schema.includes('rms_cms_block_pattern'),'contract records existing pattern schema');
need(contract.patterns_persistence&&contract.patterns_persistence.new_changes_authorized===false,'contract authorizes no new persistence/features');
need(contract.implemented_local&&contract.implemented_local.unsaved_changes&&contract.implemented_local.unsaved_changes.client_draft_recovery&&contract.implemented_local.unsaved_changes.client_draft_recovery.storage==='sessionStorage','contract documents browser-local session draft recovery');
need(contract.implemented_local.unsaved_changes.client_draft_recovery.server_write===false&&contract.implemented_local.unsaved_changes.client_draft_recovery.explicit_save_remains_authoritative===true,'draft recovery cannot replace explicit form save or write to server');
need(contract.browser_local_storage&&contract.browser_local_storage.storage==='localStorage'&&contract.browser_local_storage.server_write===false,'contract documents local-only notes and style presets');
need(String(contract.implemented_local.unsaved_changes.revision_history_fetch).includes('D05=A')&&String(contract.implemented_local.unsaved_changes.revision_history_fetch).includes('click-triggered')&&String(contract.implemented_local.unsaved_changes.revision_history_fetch).includes('explicit Blog/Page form Save'),'contract preserves separate D05 history decision and explicit Save');
need(contract.catalog_context_preview&&contract.catalog_context_preview.status==='DESIGN_GATE_PENDING','catalog preview remains explicitly gated');
if(errors.length){console.error('F9-25 STATIC CONTRACT CHECK: FAIL\n- '+errors.join('\n- '));process.exit(1)}
console.log(`F9-25 STATIC CONTRACT CHECK: PASS — 1 key × 3 locales; private pattern API/ACL/CSRF; local-only session recovery and scoped localStorage; D05 click history; ${fixtures.length} synthetic fixtures. No PHP/SQL/browser/app executed.`);
