#!/usr/bin/env python3
"""Static coverage battery for every editor block type (no browser/runtime claims)."""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
JS = (ROOT / 'dev/admin/view/js/blog-block-editor.js').read_text(encoding='utf-8')
CSS = (ROOT / 'dev/admin/view/css/blog-block-editor.css').read_text(encoding='utf-8')
PHP = (ROOT / 'dev/system/library/blog/block_document.php').read_text(encoding='utf-8')
RENDERER = (ROOT / 'dev/system/library/blog/block_renderer.php').read_text(encoding='utf-8')
PAGE_MEDIA_MODEL = (ROOT / 'dev/admin/model/cms/page_media.php').read_text(encoding='utf-8') if (ROOT / 'dev/admin/model/cms/page_media.php').exists() else ''
PAGE_MEDIA_JS = (ROOT / 'dev/admin/view/js/cms-page-media.js').read_text(encoding='utf-8') if (ROOT / 'dev/admin/view/js/cms-page-media.js').exists() else ''
PAGE_CONTROLLER = (ROOT / 'dev/admin/controller/cms/page.php').read_text(encoding='utf-8') if (ROOT / 'dev/admin/controller/cms/page.php').exists() else ''
BLOG_CONTROLLER = (ROOT / 'dev/admin/controller/blog/blog.php').read_text(encoding='utf-8') if (ROOT / 'dev/admin/controller/blog/blog.php').exists() else ''

m = re.search(r'const TYPES = \[(.*?)\n  \];', JS, re.S)
assert m, 'Block catalog not found'
catalog = re.findall(r"\['(core/[^']+)'", m.group(1))
assert len(catalog) == 21, f'Unexpected top-level block count: {len(catalog)}'
children = ['core/list-item', 'core/column', 'core/table-row', 'core/table-cell']
all_types = catalog + children

def section(start, end):
    a = JS.index(start)
    b = JS.index(end, a)
    return JS[a:b]

validation = section('switch (node.type) {', 'node.children.forEach((child) => visit')
preview = section('renderPreviewBlock(node) {', 'renderBlockToolbar(node) {')
defaults = section('createDefault(type, presetCount) {', 'insertTypes(afterId) {')
categories = section('const BLOCK_CATEGORIES = {', 'const RIBBON_BLOCK_TYPES = {')
icons = section('const TYPE_ICONS = {', 'const BLOCK_TOOL_DESCRIPTIONS =')

results = []
for t in all_types:
    checks = {
        'schema validation': f"case '{t}'" in validation,
        'public preview renderer': f"case '{t}'" in preview,
        'block icon': f"'{t}':" in icons,
    }
    if t in catalog:
        checks['inserter category'] = f"'{t}':" in categories
        checks['creation path'] = (f"type === '{t}'" in defaults) or t == 'core/paragraph'
    elif t == 'core/list-item':
        checks['creation path'] = "type === 'core/list-item'" in defaults
    for label, ok in checks.items():
        results.append((t, label, ok))

# Distinct editing modes and the toolbar label/tooltip presentation policy.
for name, ok in [
    ('rich-text editor types cover paragraph, heading, quote, list-item, button, callout, details, file and audio',
     "const TEXT_TYPES = new Set(['core/paragraph', 'core/heading', 'core/list-item', 'core/quote', 'core/button', 'core/callout', 'core/details', 'core/file', 'core/audio']);" in JS),
    ('code editor control exists', "node.type === 'core/code'" in JS and 'data-edit-code' in JS),
    ('table controls exist', all(x in JS for x in ['table-add-row','table-remove-row','table-add-column','table-toggle-header'])),
    ('list editing safeguards preserve minimum item in inspector', 'removeItem.disabled=n.children.length<=1' in JS),
    ('button label has rich-text inspector editor', "'core/quote','core/button','core/callout','core/details','core/code','core/table'].includes(n.type)" in JS and 'buttonTextTitle:' in JS),
    ('button destination remains editable as local path or URL', "this.strings.buttonDestination, 'url', n.attributes.url, 'text'" in JS and "if (key === 'url') input.maxLength = 2048" in JS),
    ('button style and contextual actions are identified', 'buttonStyleHeading:' in JS and 'buttonActions:' in JS),
    ('button joins labeled toolbar and inspector actions', "'core/quote','core/button','core/callout','core/details','core/code','core/image','core/table'].includes(node.type)" in JS and "'core/quote','core/button','core/callout','core/details','core/code','core/table'].includes(n.type)" in JS),
    ('callout text has a rich-text inspector editor', "'core/button','core/callout','core/details','core/code','core/table'].includes(n.type)" in JS and 'calloutTextTitle:' in JS),
    ('callout style heading and tone field both remain', "node.type==='core/callout')panel.append" in JS and "n.type === 'core/callout') addField(this.strings.callout.tone" in JS),
    ('callout joins labeled toolbar and inspector actions', "'core/button','core/callout','core/details','core/code','core/image','core/table'].includes(node.type)" in JS and "'core/button','core/callout','core/details','core/code','core/table'].includes(n.type)" in JS and 'calloutActions:' in JS),
    ('More Informations body has a rich-text inspector editor', "'core/callout','core/details','core/code','core/table'].includes(n.type)" in JS and 'detailsTextTitle:' in JS),
    ('More Informations summary field remains editable', "n.type === 'core/details') addField(this.strings.details.summaryLabel" in JS),
    ('More Informations appearance and actions have labels', "node.type==='core/details')panel.append" in JS and 'detailsActions:' in JS),
    ('More Informations uses the labeled toolbar', "'core/callout','core/details','core/code','core/image','core/table'].includes(node.type)" in JS),
    ('Preformatted Code has a literal textarea in the inspector', 'rb-inspector-code-editor' in JS and 'codeEditor.dataset.editCode=n.id' in JS),
    ('string-valued core/code is excluded from buildInlineDom rich-text route', "if(['core/paragraph','core/heading','core/list-item','core/quote','core/button','core/callout','core/details'].includes(n.type))" in JS),
    ('Preformatted Code inspector/canvas editors synchronize without repaint', "peer.dataset.editCode===id&&peer.value!==editor.value" in JS),
    ('Preformatted Code remains literal and non-executable', 'preformattedTextHint:' in JS and 'never executed' in JS and 'não é executado' in JS),
    ('Preformatted Code has labeled style/actions and toolbar', 'preformattedStyleHeading:' in JS and 'preformattedActions:' in JS and "'core/details','core/code','core/image','core/table'].includes(node.type)" in JS),
    ('CMS Pages media browser script exists and listens to the independent page-media event', "document.addEventListener('cms-page-media-open'" in PAGE_MEDIA_JS and 'cmsPageMediaModal' in PAGE_MEDIA_JS),
    ('Admin image previews resolve relative media paths from catalog base', 'resolveAdminMediaUrl(src)' in JS and 'this.resolveAdminMediaUrl(node.attributes.src)' in JS and "'catalogBase' => $this->pageCatalogAssetUrl()" in PAGE_CONTROLLER and "'catalogBase' => $this->catalogAssetUrl()" in BLOG_CONTROLLER),
    ('Admin media path has a catalog fallback when server strings omit catalogBase', "current.pathname.indexOf('/admin/')" in JS and "current.pathname.slice(0,adminIndex+1)+'catalog/'" in JS),
    ('Selecting media returns focus to the editor before hiding the modal', "activeEditor.focusNode(activeEditor.selectedId)" in PAGE_MEDIA_JS),
    ('Page and Blog asset versions bust cached media preview code', "'-threejs-10-media-base'" in PAGE_CONTROLLER and "'-threejs-10-media-base'" in BLOG_CONTROLLER),
    ('Page and Blog inspector string arrays keep valid alignment keys after catalog base addition', "'alignments' => ['left'" in PAGE_CONTROLLER and "'alignments' => ['left'" in BLOG_CONTROLLER and "'alignments => ['left'" not in PAGE_CONTROLLER+BLOG_CONTROLLER),
    ('CMS Pages media open event is acknowledged and missing listener is announced accessibly', 'cancelable:true' in JS and 'if(!event.defaultPrevented)' in JS and 'event.preventDefault()' in PAGE_MEDIA_JS and 'pageMediaUnavailable:' in JS),
    ('CMS Pages media modal and backdrop stack above the fullscreen editor', 'body.rb-bdre-fullscreen-open.rb-page-media-open #cmsPageMediaModal{z-index:12050}' in CSS and 'body.rb-bdre-fullscreen-open.rb-page-media-open .modal-backdrop{z-index:12040}' in CSS and 'z-index:12000' in CSS and "classList.add('rb-page-media-open')" in PAGE_MEDIA_JS),
    ('CMS Pages media API builds query parameters safely and normalizes escaped ampersands', 'url.searchParams.set(key' in PAGE_MEDIA_JS and "replace(/&amp;/g, '&')" in PAGE_MEDIA_JS),
    ('CMS Pages upload request sends same-origin credentials and CSRF token', "body.append('csrf_token'" in PAGE_MEDIA_JS and "credentials: 'same-origin'" in PAGE_MEDIA_JS and "method: 'POST'" in PAGE_MEDIA_JS),
    ('CMS Pages media endpoint model lists and stores per-kind media in separate filesystem paths', 'class Page_media' in PAGE_MEDIA_MODEL and 'listPageMedia' in PAGE_MEDIA_MODEL and 'storePageMedia' in PAGE_MEDIA_MODEL and 'view/image/cms/page/' in PAGE_MEDIA_MODEL),
    ('CMS Pages upload model validates size, extension, MIME and upload provenance', 'is_uploaded_file($tmp)' in PAGE_MEDIA_MODEL and 'detectedMime' in PAGE_MEDIA_MODEL and 'MAX_IMAGE_BYTES' in PAGE_MEDIA_MODEL),
    ('CMS Pages controller routes use the separate media model and load its modal script', "public function mediaList(): void" in PAGE_CONTROLLER and "load->model('cms/page_media')" in PAGE_CONTROLLER and "view/js/cms-page-media.js" in PAGE_CONTROLLER),
    ('Image inspector labels content, alt-text guidance and image styling in pt/en/es', 'function imageInspectorCopy()' in JS and 'imageContentTitle:' in JS and 'imageContentHint:' in JS and 'imageStyleHeading:' in JS),
    ('Image inspector keeps source, alt text, title, caption, alignment and responsive size fields', "if (n.type === 'core/image')" in JS and all(x in JS for x in ["this.strings.media.source, 'src'", "this.strings.media.alt, 'alt'", "this.strings.media.title, 'title'", "this.strings.media.caption, 'caption'", "this.strings.media.alignment, 'align'", "this.strings.media.imageSize, 'size'", "this.strings.media.mobileSize, 'mobile_size'"])),
    ('Image block toolbar shows labels and offers direct replace-image picker', "'core/code','core/image','core/table'].includes(node.type)" in JS and "node.type==='core/image'" in JS and 'this.strings.imageReplace' in JS),
    ('Image picker action selects the target node before opening its media event', "if(nodeId)this.selectedId=nodeId" in JS and "detail:{editor:this,nodeId}" in JS),
    ('Table cells allow optional independent header format and rowspan/colspan in the JS schema', "allowedAttributes['core/table-cell'] = ['header','rowspan','colspan'];" in JS and 'tableCellIsHeader' in JS),
    ('Table span grid validates bounds, overlap and complete rectangular coverage', 'function tableGridInfo(table)' in JS and 'if(overlap)return null' in JS and 'if(!grid[r][c])return null' in JS),
    ('Table merge is restricted to a complete rectangle and preserves cell content', 'function tableMergeSelection(table, selectedIds)' in JS and "action==='table-merge-cells'" in JS and 'canonicalRuns(merged)' in JS),
    ('Merged cells can be split back into editable cells', 'function splitTableCells(table,selectedIds,createCell)' in JS and "action==='table-unmerge-cells'" in JS),
    ('Table inspector exposes selection, merge, unmerge and individual header/content actions', 'data-table-cell-select' in JS and 'table-merge-cells' in JS and 'table-unmerge-cells' in JS and 'table-mark-header' in JS and 'table-mark-normal' in JS),
    ('PHP document validator supports cell spans and rejects table-grid holes or overlaps', "'core/table-cell'] = ['header','rowspan','colspan']" in PHP and 'table_grid_hole' in PHP and 'table_cell_overlap' in PHP),
    ('PHP table renderer emits rowspan/colspan and cell-specific th/td with legacy header fallback', "' rowspan=\"'" in RENDERER and "' colspan=\"'" in RENDERER and "array_key_exists('header', $cellAttrs)" in RENDERER and "!empty($attributes['header']) && $rowIndex === 0" in RENDERER),
    ('Table inspector builds editable cell matrix for every row/cell', 'rb-table-inspector-grid' in JS and 'editor.dataset.editContent=cell.id' in JS and 'buildInlineDom(editor,cell.content)' in JS),
    ('Table inspector cells reuse canvas synchronization path', "this.root.querySelectorAll('[data-edit-content]').forEach((peer)=>{if(peer!==editor&&peer.dataset.editContent===id)buildInlineDom(peer,runs);});" in JS),
    ('Table summary/header and structural controls remain', 'this.strings.tableContentTitle' in JS and 'table-toggle-header' in JS and 'table-add-row' in JS and 'table-remove-column' in JS),
    ('Table style, actions and contextual toolbar are named', 'tableStyleHeading:' in JS and 'tableActions:' in JS and "'core/details','core/code','core/image','core/table'].includes(node.type)" in JS and "'core/details','core/code','core/table'].includes(n.type)" in JS),
    ('viewport actions accept only Desktop, Tablet and Mobile and update state', "if(!['desktop','tablet','mobile'].includes(size))return; this.preview=size; this.root.dataset.preview=size" in JS),
    ('Tablet/Mobile breakpoints override host canvas widths', 'rb-editor-host.rb-editor[data-preview="tablet"] .rb-canvas' in CSS and 'bdre-page-editor.rb-editor[data-preview="mobile"] .rb-canvas' in CSS and 'max-width:768px' in CSS and 'max-width:390px' in CSS),
    ('responsive selectors cover fullscreen viewport modes', 'rb-editor.is-fullscreen[data-preview="tablet"] .rb-builder-layout>.rb-canvas' in CSS and 'rb-editor.is-fullscreen[data-preview="mobile"] .rb-builder-layout>.rb-canvas' in CSS),
    ('preview canvas no longer forces max-width:none with !important', '.rb-preview-canvas{max-width:100%}' in CSS and '.rb-preview-canvas{max-width:none!important}' not in CSS),
    ('safeActionUrl helper is defined using the URL validator', 'function safeActionUrl(value)' in JS and 'return validActionUrl(value) ? value : null;' in JS),
    ('all preview URL paths call the defined helper', JS.count('safeActionUrl(') >= 4),
    ('toolbar visible labels stack under icon', 'flex-direction:column' in CSS and '.rb-block-toolbar .rb-button .rb-button-label:not(.rb-sr-only)' in CSS),
    ('icon-only tools receive native hover title and description', "b.setAttribute('title',name+'\\n'+tooltipDescription)" in JS),
]:
    results.append(('editor-wide', name, ok))

failed = [x for x in results if not x[2]]
for block, check, ok in results:
    print(('PASS' if ok else 'FAIL') + f' | {block} | {check}')
print(f'\nCoverage: {len(catalog)} top-level block types + {len(children)} nested types; {len(results)} checks.')
if failed:
    print(f'FAILED: {len(failed)} static checks')
    sys.exit(1)
print('All static battery checks passed. Browser interaction/runtime testing is not performed by this script.')
