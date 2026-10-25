'use strict';
const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'dev/admin/view/js/blog-block-editor.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'dev/admin/view/css/blog-block-editor.css'), 'utf8');
const checks = [
  ['Inline SVG icons are created locally', /createElementNS\('http:\/\/www\.w3\.org\/2000\/svg', 'svg'\)/.test(js) && /ICON_PATHS/.test(js)],
  ['SVG icons are decorative to assistive technology', /setAttribute\('aria-hidden', 'true'\)/.test(js)],
  ['Controls keep readable labels next to icons', /rb-button-label/.test(js) && /aria-label/.test(js)],
  ['Major block types have distinct icon mapping', /TYPE_ICONS/.test(js) && /'core\/table':'table'/.test(js)],
  ['Editor masthead uses existing localized strings', /rb-editor-masthead/.test(js) && /this\.strings\.editorTools/.test(js)],
  ['Viewport actions use distinct responsive icons', /extra\.size === 'mobile'/.test(js) && /extra\.size === 'tablet'/.test(js)],
  ['Layout has tablet/mobile breakpoints', /@media\(max-width:1120px\)/.test(css) && /@media\(max-width:560px\)/.test(css)],
  ['Reduced-motion preference is respected', /prefers-reduced-motion:reduce/.test(css)],
  ['High-contrast forced-colors mode is supported', /forced-colors:active/.test(css)],
];
let failed=0;
for (const [name, ok] of checks) { console.log(`${ok?'PASS':'FAIL'} ${name}`); if (!ok) failed++; }
process.exitCode = failed ? 1 : 0;
