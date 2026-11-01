const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'dev/admin/view/js/blog-block-editor.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'dev/admin/view/css/blog-block-editor.css'), 'utf8');
const page = fs.readFileSync(path.join(root, 'dev/admin/controller/cms/page.php'), 'utf8');
const blog = fs.readFileSync(path.join(root, 'dev/admin/controller/blog/blog.php'), 'utf8');
const checks = [
  ['classic top menu renderer and action are removed', !js.includes('renderOfficeMenu') && !js.includes("name === 'office-menu'") && !js.includes("this.root.append(this.renderOfficeMenu())")],
  ['ribbon remains the sole editor menu and tabs still render', js.includes("this.el('div','rb-ribbon-tabs')") && js.includes('this.renderRibbonContents()')],
  ['fullscreen Page context contains BDREditor brand and does not duplicate masthead', js.includes("this.el('div','rb-page-context-brand')") && js.includes('contextBar.append(brand,contextDetails,contextActions)') && js.includes('if(!hasFullscreenContext)this.root.append(masthead)')],
  ['brand styling exists and all unused classic-menu styles are gone', css.includes('.rb-page-context-brand{') && !css.includes('rb-office-menu')],
  ['Page and Blog use the matching new cache key', page.includes('threejs-42-block-lock') && blog.includes('threejs-42-block-lock')],
];
let failures = 0;
for (const [label, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}`);
  if (!ok) failures++;
}
if (failures) process.exit(1);
console.log(`F9-89: ${checks.length} checks passed.`);
