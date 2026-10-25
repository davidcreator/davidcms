'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '../..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
let failed = false;
function check(ok, message) { console.log((ok ? 'PASS: ' : 'FAIL: ') + message); if (!ok) failed = true; }

const fixture = JSON.parse(read('tests/fixtures/core_audio.json'));
const context = { window: {}, URL, TextEncoder, Set, Map, JSON, Array, Object, String, Number, Math, RegExp, Error, TypeError, console, setTimeout, clearTimeout };
vm.runInNewContext(read('dev/admin/view/js/blog-block-editor.js'), context, { filename: 'blog-block-editor.js' });
const api = context.window.ReamurBlogBlockEditor;
check(api.validateDocument(fixture.valid).valid, 'fixture core/audio local em formato v2 é válida');
fixture.invalid.forEach((item) => check(!api.validateDocument(item.document).valid, 'schema cliente rejeita: ' + item.name));
check(api.validLocalAudio('view/audio/blog/faixa-demo.mp3'), 'helper cliente aceita MP3 local permitido');
['https://example.invalid/a.mp3','//example.invalid/a.ogg','view/audio/blog/../a.wav','view/audio/blog/a.html','view/audio/blog/a.mp3?x=1','view/audio/blog/a%2emp3','view/download/blog/a.mp3'].forEach((value) => check(!api.validLocalAudio(value), 'helper cliente rejeita caminho proibido: ' + value));

const schema = read('dev/system/library/blog/block_document.php');
const renderer = read('dev/system/library/blog/block_renderer.php');
const admin = read('dev/admin/controller/blog/blog.php');
const catalog = read('dev/catalog/controller/blog/blog.php');
const model = read('dev/admin/model/blog/media.php');
const editor = read('dev/admin/view/js/blog-block-editor.js');
const picker = read('dev/admin/view/js/blog-audio-media.js');
const template = read('dev/admin/view/template/blog/blog_form.twig');
check(schema.includes("'core/audio' => ['src']") && schema.includes('isSafeAudioPath') && schema.includes("['core/button','core/file','core/audio']"), 'schema server-side restringe atributos/caminho e bloqueia link inline');
check(renderer.includes("case 'core/audio':") && renderer.includes('audioFileExists') && renderer.includes('controls preload="none"') && renderer.includes('aria-label=') && renderer.includes("'mp3' => 'audio/mpeg'"), 'renderer verifica arquivo/MIME e usa player nativo acessível sem autoplay');
check(admin.includes("BlockRenderer($downloadRoot, $audioRoot)") && catalog.includes("BlockRenderer($downloadRoot, $audioRoot)"), 'admin e catálogo passam raízes confiáveis ao renderer');
check(model.includes('getAudioPage') && model.includes('audioReferenceExists') && model.includes('finfo(FILEINFO_MIME_TYPE)') && model.includes("'mp3' => ['audio/mpeg']") && model.includes("'ogg' => ['audio/ogg'") && model.includes("'wav' => ['audio/wav'"), 'biblioteca valida arquivos locais por extensão, MIME e caminho');
check(admin.includes('public function audioList()') && admin.includes("REQUEST_METHOD'] !== 'GET'") && admin.includes("hasPermission('modify', 'blog/blog')") && admin.includes('audioReferenceExists'), 'endpoint de listagem é somente GET, ACL-protected, e referências são verificadas ao salvar');
check(editor.includes("['core/audio', 'Áudio']") && editor.includes('validLocalAudio') && editor.includes('blog-block-audio-open') && editor.includes('blog-block-audio-selected') && editor.includes('preload = \'none\''), 'editor oferece core/audio, player administrativo local e integração com picker');
check(picker.includes('blogAudioModal') && picker.includes('audioListUrl') && picker.includes('blog-block-audio-selected') && !/uploadUrl|deleteUrl|type\s*=\s*['\"]file/i.test(picker), 'picker lista e seleciona; não implementa envio/exclusão');
const audioModal = template.slice(template.indexOf('id="blogAudioModal"'));
check(template.includes('blogAudioModal') && audioModal.includes('blogAudioFiles') && !/type="file"|Upload|Excluir/.test(audioModal), 'modal de áudio é separado e não inclui upload/exclusão');
check(!/public\s+function\s+audio(?:Upload|Delete)|function\s+(?:uploadAudio|deleteAudio)\b/i.test(admin + model + picker), 'não há endpoint/modelo de upload ou exclusão de áudio');
check(read('dev/catalog/view/audio/blog/index.html').includes('Audio assets'), 'raiz dedicada fica com index para evitar listagem de diretório');

['pt-br','en-us','es-eu'].forEach((locale) => {
  const language = read('dev/admin/language/' + locale + '/blog/blog.php');
  ['text_block_type_audio','text_block_audio_source','text_block_audio_choose','text_audio_library','text_audio_empty','text_audio_hint','button_audio_select','error_audio_generic'].forEach((key) => check(language.includes("$_['" + key + "']"), locale + ' contém ' + key));
});
check(!/https?:\/\//.test(picker), 'picker não declara origem ou dependência remota');
console.log('STATIC_ONLY: fixture, execução isolada do validador JS e varredura de fontes; sem PHP runtime, banco, navegador real, staging, deploy ou migrations.');
if (failed) process.exitCode = 1;
