'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '../..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
let failed = false;
function check(ok, message) { console.log((ok ? 'PASS: ' : 'FAIL: ') + message); if (!ok) failed = true; }

const fixture = JSON.parse(read('tests/fixtures/outline_navigation.json'));
const context = { window: {}, URL, TextEncoder, Set, Map, JSON, Array, Object, String, Number, Math, RegExp, Error, TypeError, console, setTimeout, clearTimeout };
vm.runInNewContext(read('dev/admin/view/js/blog-block-editor.js'), context, { filename: 'blog-block-editor.js' });
const api = context.window.ReamurBlogBlockEditor;
check(api.validateDocument(fixture.document).valid, 'fixture outline hierárquica valida no schema v2');
fixture.cases.forEach((test) => {
  const actual = api.getOutlineNavigationTarget(fixture.document.blocks, test.current, test.key);
  check(actual === test.expected, test.key + ' a partir de ' + test.current + ' → ' + String(actual));
});

const editor = read('dev/admin/view/js/blog-block-editor.js');
const controller = read('dev/admin/controller/blog/blog.php');
const css = read('dev/admin/view/css/blog-block-editor.css');
check(editor.includes("event.target.closest('.rb-outline-button')") && editor.includes("['ArrowUp','ArrowDown','ArrowLeft','ArrowRight','Home','End']"), 'atalhos de seta/Home/End ficam escopados à outline');
check(editor.includes('getOutlineNavigationTarget(this.state.blocks, outlineButton.dataset.nodeId, event.key)'), 'handler usa helper puro de navegação da árvore');
check(editor.includes('targetButton.focus(); this.selectNode(targetId); this.announceStatus(this.strings.statusOutlineChanged)'), 'navegação move foco, seleção e anúncio sem rerender da outline');
check(editor.includes('event.preventDefault();') && editor.includes('if (targetButton)'), 'limites da árvore não rolam a página nem mudam conteúdo');
check(editor.includes("this.el('p','rb-outline-keyboard-hint',this.strings.outlineKeyboardHint)"), 'a outline mostra instrução localizada de teclado');
check(css.includes('.rb-outline-keyboard-hint'), 'instrução de teclado tem estilo compacto');
check(controller.includes('text_block_outline_keyboard_hint') && controller.includes('statusOutlineChanged'), 'controller repassa hint e anúncio');
['pt-br','en-us','es-eu'].forEach((locale) => {
  const language = read('dev/admin/language/' + locale + '/blog/blog.php');
  ['text_block_outline_keyboard_hint','text_block_status_outline_changed'].forEach((key) => check(language.includes("$_['" + key + "']"), locale + ' contém ' + key));
});
console.log('STATIC_ONLY: fixture + helper JS em VM sem DOM/browser e inspeção de fontes; sem PHP runtime, app, banco, staging, deploy ou migrations.');
if (failed) process.exitCode = 1;
