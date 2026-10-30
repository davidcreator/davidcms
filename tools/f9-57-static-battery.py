#!/usr/bin/env python3
"""Static coverage battery for every editor block type (no browser/runtime claims)."""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
JS = (ROOT / 'dev/admin/view/js/blog-block-editor.js').read_text(encoding='utf-8')
CSS = (ROOT / 'dev/admin/view/css/blog-block-editor.css').read_text(encoding='utf-8')

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
    ('button label has rich-text inspector editor', "'core/quote','core/button','core/callout','core/details','core/code'].includes(n.type)" in JS and 'buttonTextTitle:' in JS),
    ('button destination remains editable as local path or URL', "this.strings.buttonDestination, 'url', n.attributes.url, 'text'" in JS and "if (key === 'url') input.maxLength = 2048" in JS),
    ('button style and contextual actions are identified', 'buttonStyleHeading:' in JS and 'buttonActions:' in JS),
    ('button joins labeled toolbar and inspector actions', "'core/quote','core/button','core/callout','core/details','core/code'].includes(node.type)" in JS and "'core/quote','core/button','core/callout','core/details','core/code'].includes(n.type)" in JS),
    ('callout text has a rich-text inspector editor', "'core/button','core/callout','core/details','core/code'].includes(n.type)" in JS and 'calloutTextTitle:' in JS),
    ('callout style heading and tone field both remain', "node.type==='core/callout')panel.append" in JS and "n.type === 'core/callout') addField(this.strings.callout.tone" in JS),
    ('callout joins labeled toolbar and inspector actions', "'core/button','core/callout','core/details','core/code'].includes(node.type)" in JS and "'core/button','core/callout','core/details','core/code'].includes(n.type)" in JS and 'calloutActions:' in JS),
    ('More Informations body has a rich-text inspector editor', "'core/callout','core/details','core/code'].includes(n.type)" in JS and 'detailsTextTitle:' in JS),
    ('More Informations summary field remains editable', "n.type === 'core/details') addField(this.strings.details.summaryLabel" in JS),
    ('More Informations appearance and actions have labels', "node.type==='core/details')panel.append" in JS and 'detailsActions:' in JS),
    ('More Informations uses the labeled toolbar', "'core/callout','core/details','core/code'].includes(node.type)" in JS),
    ('Preformatted Code has a literal textarea in the inspector', 'rb-inspector-code-editor' in JS and 'codeEditor.dataset.editCode=n.id' in JS),
    ('string-valued core/code is excluded from buildInlineDom rich-text route', "if(['core/paragraph','core/heading','core/list-item','core/quote','core/button','core/callout','core/details'].includes(n.type))" in JS),
    ('Preformatted Code inspector/canvas editors synchronize without repaint', "peer.dataset.editCode===id&&peer.value!==editor.value" in JS),
    ('Preformatted Code remains literal and non-executable', 'preformattedTextHint:' in JS and 'never executed' in JS and 'não é executado' in JS),
    ('Preformatted Code has labeled style/actions and toolbar', 'preformattedStyleHeading:' in JS and 'preformattedActions:' in JS and "'core/details','core/code'].includes(node.type)" in JS),
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
