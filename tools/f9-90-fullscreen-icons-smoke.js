const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'dev/admin/view/js/blog-block-editor.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'dev/admin/view/css/blog-block-editor.css'), 'utf8');
const page = fs.readFileSync(path.join(root, 'dev/admin/controller/cms/page.php'), 'utf8');
const blog = fs.readFileSync(path.join(root, 'dev/admin/controller/blog/blog.php'), 'utf8');
const checks = [
  ['expand glyph has four outward-facing corner arrows', js.includes("expand:'M8 3H3v5M3 3l7 7M16 3h5v5M21 3l-7 7M3 16v5h5M3 21l7-7M16 21h5v-5m0 5-7-7'")],
  ['collapse glyph has four inward-facing corner arrows', js.includes("collapse:'M3 3l7 7M3 10h7V3M21 3l-7 7M21 10h-7V3M3 21l7-7M3 14h7v7M21 21l-7-7M21 14h-7v7'")],
  ['fullscreen ribbon control swaps expand and collapse according to mode', js.includes("this.fullscreen?'collapse':'expand'")],
  ['Back to form button uses the collapse glyph', js.includes("this.button(this.strings.fullscreenExit,'toggle-fullscreen',{icon:'collapse'})")],
  ['fullscreen controls use a stronger stroke without changing other editor icons', css.includes('.rb-button[data-action="toggle-fullscreen"] .rb-icon,.rb-fullscreen-done .rb-icon{stroke-width:2.5}')],
  ['Page and Blog share the updated icon asset cache key', page.includes('threejs-48-editor-notes') && blog.includes('threejs-48-editor-notes')],
];
let failures = 0;
for (const [label, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}`);
  if (!ok) failures++;
}
if (failures) process.exit(1);
console.log(`F9-90: ${checks.length} checks passed.`);
