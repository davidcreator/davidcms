'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '../..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
let failed = false;
function check(ok, message) { console.log((ok ? 'PASS: ' : 'FAIL: ') + message); if (!ok) failed = true; }

const fixture = JSON.parse(read('tests/fixtures/navigation_tree.json'));
const context = { window: {}, URL, TextEncoder, Set, Map, JSON, Array, Object, String, Number, Math, RegExp, Error, TypeError, console, setTimeout, clearTimeout };
vm.runInNewContext(read('dev/admin/view/js/blog-block-editor.js'), context, { filename: 'blog-block-editor.js' });
const api = context.window.ReamurBlogBlockEditor;
check(api.validateDocument(fixture.document).valid, 'fixture hierárquica é válida no schema v2');
const chain = api.findNodeChain(fixture.document.blocks, fixture.target_id);
check(JSON.stringify(chain.map((node) => node.id)) === JSON.stringify(fixture.expected_ids), 'helper retorna caminho completo da raiz ao bloco selecionado');
check(api.findNodeChain(fixture.document.blocks, 'missing-node').length === 0, 'alvo ausente retorna caminho vazio sem inventar pai');
check(fixture.document.blocks[0].id === 'group-root' && fixture.document.blocks[0].children[0].id === 'group-nested', 'consulta de caminho não altera a árvore');

const editor = read('dev/admin/view/js/blog-block-editor.js');
const controller = read('dev/admin/controller/blog/blog.php');
const css = read('dev/admin/view/css/blog-block-editor.css');
check(editor.includes("this.el('nav', 'rb-breadcrumbs')") && editor.includes("nav.setAttribute('aria-label', this.strings.navigationPath)"), 'breadcrumbs são um nav com nome acessível localizado');
check(editor.includes("this.button(this.navigationLabel(node), 'outline-select'") && editor.includes("current.setAttribute('aria-current', 'location')"), 'pais são botões e o bloco atual é identificado semanticamente');
check(editor.includes('aside.append(this.renderBreadcrumbs(found.node.id))'), 'breadcrumbs aparecem junto ao inspetor do bloco selecionado');
check(editor.includes("this.focusNode(id); this.announceStatus(this.strings.statusFocusChanged)"), 'seleção na árvore/breadcrumb move foco e anuncia o destino');
check(editor.includes('navigationLabel(node)') && editor.includes('outline_label'), 'rótulos personalizados de grupos são preservados na navegação');
check(!/innerHTML\s*=/.test(editor), 'rótulos e conteúdo não são interpretados como HTML');
check(css.includes('.rb-breadcrumbs') && css.includes('.rb-breadcrumb-button:focus-visible') && css.includes('flex-wrap:wrap'), 'estilo responsivo fornece foco visível nos breadcrumbs');
check(controller.includes('text_block_navigation_path') && controller.includes('navigationPath'), 'controller repassa strings de navegação');
['pt-br','en-us','es-eu'].forEach((locale) => {
  const language = read('dev/admin/language/' + locale + '/blog/blog.php');
  ['text_block_navigation_path','text_block_navigation_row','text_block_navigation_cell','text_block_status_focus_changed'].forEach((key) => check(language.includes("$_['" + key + "']"), locale + ' contém ' + key));
});
console.log('STATIC_ONLY: fixture e helper JS em VM sem DOM/browser; sem PHP runtime, aplicação, banco, staging, deploy ou migrations.');
if (failed) process.exitCode = 1;
