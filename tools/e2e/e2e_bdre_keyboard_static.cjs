'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '../..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
let failed = false;
function check(ok, message) { console.log((ok ? 'PASS: ' : 'FAIL: ') + message); if (!ok) failed = true; }

const fixture = JSON.parse(read('tests/fixtures/keyboard_shortcuts.json'));
const context = { window: {}, URL, TextEncoder, Set, Map, JSON, Array, Object, String, Number, Math, RegExp, Error, TypeError, console, setTimeout, clearTimeout };
vm.runInNewContext(read('dev/admin/view/js/blog-block-editor.js'), context, { filename: 'blog-block-editor.js' });
const api = context.window.ReamurBlogBlockEditor;
fixture.cases.forEach((test) => {
  const actual = api.classifyKeyboardShortcut(test.event, test.context);
  check(actual === test.expected, test.name + ' -> ' + String(actual));
});
const editor = read('dev/admin/view/js/blog-block-editor.js');
const controller = read('dev/admin/controller/blog/blog.php');
const css = read('dev/admin/view/css/blog-block-editor.css');
check(editor.includes('classifyKeyboardShortcut(event, { inTextEditor, inFormControl, inBlock })'), 'keydown usa classificação centralizada e escopo por contexto');
check(editor.includes("closest('input,textarea,select,[data-inserter-search]')"), 'inputs, selects e busca do inserter preservam atalhos nativos de texto');
check(!/\.execCommand\s*\(/.test(editor), 'formatação não usa execCommand');
check(editor.includes("this.el('details', 'rb-keyboard-help')") && editor.includes("this.el('summary', '', this.strings.keyboardShortcutsTitle)"), 'ajuda de atalhos usa disclosure nativo acessível');
check(editor.includes('this.captureInlineSelection(); this.applyInlineMark(command)') && editor.includes("this.captureInlineSelection(); const link = this.root.querySelector('[data-inline-link-input]')"), 'marks e link capturam seleção antes de executar o comando');
check(editor.includes('element.textContent = text') && editor.includes("this.el('p', 'rb-keyboard-help-text', this.strings.keyboardShortcuts)"), 'texto localizado é renderizado como texto, sem interpretar HTML');
check(controller.includes('text_block_keyboard_shortcuts_title') && controller.includes('keyboardShortcutsTitle'), 'controller passa strings de ajuda ao editor');
check(css.includes('.rb-keyboard-help') && css.includes(':focus-visible'), 'ajuda tem estilo responsivo e foco visível');
['pt-br','en-us','es-eu'].forEach((locale) => {
  const language = read('dev/admin/language/' + locale + '/blog/blog.php');
  check(language.includes("$_['text_block_keyboard_shortcuts_title']") && language.includes("$_['text_block_keyboard_shortcuts']"), locale + ' inclui título e conteúdo da ajuda de atalhos');
});
console.log('STATIC_ONLY: fixture de comandos e validador JS isolado em VM; sem PHP runtime, browser real, app, banco, staging, deploy ou migrations.');
if (failed) process.exitCode = 1;
