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
