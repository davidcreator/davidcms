'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '../..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
let failed = false;
function check(ok, message) { console.log((ok ? 'PASS: ' : 'FAIL: ') + message); if (!ok) failed = true; }

const fixture = JSON.parse(read('tests/fixtures/bdre_file.json'));
const context = { window: {}, URL, TextEncoder, Set, Map, JSON, Array, Object, String, Number, Math, RegExp, Error, TypeError, console, setTimeout, clearTimeout };
vm.runInNewContext(read('dev/admin/view/js/blog-block-editor.js'), context, { filename: 'blog-block-editor.js' });
const api = context.window.ReamurBlogBlockEditor;
check(api.validateDocument(fixture.valid).valid, 'fixture core/file local e acessível válida');
fixture.invalid.forEach((item) => check(!api.validateDocument(item.document).valid, 'rejeita fixture inválida: ' + item.name));
check(api.validLocalDownload('view/download/blog/arquivo-0a1b.pdf'), 'helper cliente aceita caminho local permitido');
['https://evil.invalid/a.pdf','//evil.invalid/a.pdf','view/download/blog/../a.pdf','view/download/blog/a.html','view/image/blog/a.pdf','view/download/blog/a%2epdf'].forEach((value) => check(!api.validLocalDownload(value), 'helper cliente rejeita ' + value));

const schema = read('dev/system/library/blog/block_document.php');
const renderer = read('dev/system/library/blog/block_renderer.php');
const catalog = read('dev/catalog/controller/blog/blog.php');
const model = read('dev/admin/model/blog/media.php');
const admin = read('dev/admin/controller/blog/blog.php');
const ui = read('dev/admin/view/js/blog-file-media.js');
const template = read('dev/admin/view/template/blog/blog_form.twig');
check(schema.includes("'core/file' => ['src']") && schema.includes('isSafeDownloadPath') && schema.includes("['core/button','core/file']"), 'schema server-side limita path e impede link inline aninhado');
check(renderer.includes("case 'core/file':") && renderer.includes('downloadFileExists') && renderer.includes(' download>'), 'renderer fail-closed gera link download com rótulo escapado');
check(catalog.includes("BlockRenderer($downloadRoot)") && catalog.includes("'/catalog/view/download/blog'"), 'catálogo passa raiz física confiável ao renderer');
check(model.includes('2097152') && model.includes('is_uploaded_file($tmp)') && model.includes('finfo(FILEINFO_MIME_TYPE)') && model.includes('move_uploaded_file($tmp, $target)'), 'upload tem limite, validação MIME e destino aleatório no modelo Blog');
check(['pdf','docx','xlsx','pptx','odt','ods','csv','txt'].every((ext) => model.includes("'" + ext + "' =>")) && model.includes("'index.html'"), 'allowlist e guard anti-listagem presentes');
check(admin.includes('hasPermission(\'modify\', \'blog/blog\')') && admin.includes('csrfValid()') && admin.includes('downloadList') && admin.includes('downloadUpload') && admin.includes('blockDownloadsAvailable') && admin.includes('downloadReferenceExists'), 'endpoints mantêm ACL/CSRF e salvamento verifica arquivo físico, sem exclusão de downloads');
check(ui.includes('blog-block-file-open') && ui.includes('blog-block-file-selected') && ui.includes('downloadUploadUrl'), 'picker dedicado conecta editor, listagem e upload');
const editor = read('dev/admin/view/js/blog-block-editor.js');
check(editor.includes("['core/button','core/file'].includes(node.type)") && editor.includes('validLocalDownload'), 'toolbar impede link aninhado e validador cliente restringe tipo/caminho');
check(template.includes('blogDownloadModal') && template.includes('blogDownloadFile'), 'modal acessível de biblioteca/upload está incluído');

['pt-br','en-us','es-eu'].forEach((locale) => {
  const language = read('dev/admin/language/' + locale + '/blog/blog.php');
  ['text_block_type_file','text_block_file_source','text_block_file_choose','text_download_library','text_download_hint','button_download_select','button_download_upload','error_download_upload_type'].forEach((key) => check(language.includes("$_['" + key + "']"), locale + ' contém ' + key));
});
check(!/blog\.downloadDelete|deleteDownload|downloadDelete/.test(admin + ui), 'não há exclusão de documentos');

console.log('STATIC_ONLY: JS/fixtures e varredura de fontes; sem PHP, SQL, browser/app, staging, deploy, migration ou banco.');
if (failed) process.exitCode = 1;
