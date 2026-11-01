const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'dev/admin/view/js/blog-block-editor.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'dev/admin/view/css/blog-block-editor.css'), 'utf8');
const battery = fs.readFileSync(path.join(root, 'tools/f9-57-static-battery.py'), 'utf8');
const page = fs.readFileSync(path.join(root, 'dev/admin/controller/cms/page.php'), 'utf8');
const blog = fs.readFileSync(path.join(root, 'dev/admin/controller/blog/blog.php'), 'utf8');
const paletteMethods = js.slice(js.indexOf('getCommandPaletteItems()'), js.indexOf('filterWorkspacePatterns()'));
const checks = [
  ['palette copy is localized for PT, ES and EN', js.includes("if(language.startsWith('pt'))return {title:'Paleta de comandos'") && js.includes("if(language.startsWith('es'))return {title:'Paleta de comandos'") && js.includes("return {title:'Command palette'")],
  ['launcher is persistent in the ribbon and advertises the keyboard shortcut', js.includes("this.button(this.commandPalette.open,'command-palette-open'") && js.includes("aria-keyshortcuts','Control+Shift+P Meta+Shift+P'")],
  ['shortcut opens or closes palette without overriding Escape fullscreen behavior', js.includes("String(event.key).toLowerCase()==='p'") && js.includes('if(this.commandPaletteOpen||this.groupSelectionConfirmOpen||this.ungroupConfirmOpen||this.styleConfirmOpen||this.stylePresetLibraryOpen||this.stylePresetSaveOpen||this.stylePresetImportOpen)return; event.preventDefault(); event.stopPropagation(); this.setFullscreen(false)')],
  ['palette provides accessible modal, search combobox and filtered listbox', js.includes("setAttribute('role','dialog')") && js.includes("setAttribute('role','combobox')") && js.includes("setAttribute('role','listbox')") && js.includes('updateCommandPaletteResults()')],
  ['keyboard navigation supports arrows, Enter, Escape, and trapped Tab', js.includes("event.key==='ArrowDown'||event.key==='ArrowUp'") && js.includes("event.key==='Enter'") && js.includes("event.key==='Escape'") && js.includes("event.key==='Tab'")],
  ['block insert commands derive from safe insertion context and stay hidden when locked', js.includes('const available=this.insertTypes(this.selectedId||null)') && js.includes("if(!this.locked){\n        const available=this.insertTypes") && js.includes("this.action('insert',button)")],
  ['commands call existing navigation/actions and never inject HTML or fetch remote services', js.includes('this.activateWorkspaceTool({dataset:{tool:item.tool}})') && js.includes('this.action(item.action,button)') && !paletteMethods.includes('insertAdjacentHTML') && !paletteMethods.includes('fetch(')],
  ['dismissal returns focus to the palette launcher and styles adapt to mobile/forced colors', js.includes("this.root.querySelector('[data-action=\"command-palette-open\"]')") && css.includes('.rb-command-overlay{') && css.includes('@media(max-width:560px){.rb-ribbon-head') && css.includes('@media(forced-colors:active){.rb-command-dialog')],
  ['Page and Blog use the same updated cache key and static battery includes F9-91 coverage', page.includes('threejs-46-visual-audit') && blog.includes('threejs-46-visual-audit') && battery.includes('F9-91 command palette')],
];
let failures = 0;
for (const [label, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}`);
  if (!ok) failures++;
}
if (failures) process.exit(1);
console.log(`F9-91: ${checks.length} checks passed.`);
