'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '../..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
let failed = false;
function check(ok, message) { console.log((ok ? 'PASS: ' : 'FAIL: ') + message); if (!ok) failed = true; }

const fixture = JSON.parse(read('tests/fixtures/block_copy.json'));
const context = { window: {}, URL, TextEncoder, Set, Map, JSON, Array, Object, String, Number, Math, RegExp, Error, TypeError, console, setTimeout, clearTimeout };
vm.runInNewContext(read('dev/admin/view/js/blog-block-editor.js'), context, { filename: 'blog-block-editor.js' });
const api = context.window.ReamurBlogBlockEditor;
let sequence = 0;
const copy = api.cloneBlockWithNewIds(fixture.block, () => 'copy-' + sequence++);
check(!!copy && copy.id === 'copy-0' && copy.children[0].id === 'copy-1', 'clone recursivo cria IDs novos para bloco e descendentes');
check(fixture.block.id === 'group-original' && fixture.block.children[0].id === 'paragraph-original', 'cópia não altera o documento-fonte');
check(api.validateDocument({format_version:2, blocks:[copy]}).valid, 'grupo copiado continua válido no contrato v2');
check(api.cloneBlockWithNewIds({id:'invalid',children:'not-a-list'}, () => 'x') === null, 'estrutura de bloco inválida não é copiada');
check(api.cloneBlockWithNewIds(fixture.block, () => 'same') === null, 'gerador de ID repetido falha fechado');

const editor = read('dev/admin/view/js/blog-block-editor.js');
const controller = read('dev/admin/controller/blog/blog.php');
const template = read('dev/admin/view/template/blog/blog_form.twig');
check(editor.includes('this.blockClipboard = null') && editor.includes('this.blockClipboard = CLONE(found.node)'), 'buffer é interno à instância e fica em memória');
check(editor.includes("this.button(this.strings.copyBlock, 'copy-block'") && editor.includes("this.button(this.strings.pasteBlock, 'paste-block')"), 'ações copiar/colar são explícitas e acessíveis na interface');
check(editor.includes('pasteLocation(next.blocks, afterId)') && editor.includes('validateDocument(next).valid') && editor.includes('utf8Length(JSON.stringify(next)) <= 4194304'), 'colar valida estrutura/contexto e limite JSON antes de alterar estado');
const pasteMethod = editor.split('    pasteBlock() {')[1].split('\n    updateClipboardButtons()')[0];
check(pasteMethod.includes('this.commit(next, copy.id)') && (pasteMethod.match(/this\.commit\(next, copy\.id\)/g) || []).length === 1, 'colar executa uma única alteração atômica no histórico');
check(editor.includes("live.textContent = message") && editor.includes('data-editor-status'), 'cópia e colagem anunciam status na região live');
check(!/navigator\.clipboard|document\.execCommand|addEventListener\(['\"]copy/.test(editor), 'buffer de bloco não depende do clipboard do sistema nem intercepta cópia nativa');
check(editor.includes("event.clipboardData.getData('text/plain')"), 'colar texto existente continua limitado a text/plain');
check(controller.includes('text_block_copy') && controller.includes('text_block_paste') && controller.includes('text_block_status_pasted'), 'controller repassa strings de copiar/colar');
check(template.includes('data-blog-block-editor'), 'integração permanece no formulário BDREditor existente');
['pt-br','en-us','es-eu'].forEach((locale) => {
  const language = read('dev/admin/language/' + locale + '/blog/blog.php');
  ['text_block_copy','text_block_paste','text_block_status_copied','text_block_status_pasted','text_block_paste_unavailable'].forEach((key) => check(language.includes("$_['" + key + "']"), locale + ' contém ' + key));
});
console.log('STATIC_ONLY: fixture + clone helper JS isolado em VM e inspeção de fonte; sem PHP runtime, browser real, app, banco, staging, deploy ou migrations.');
if (failed) process.exitCode = 1;
