/* Frente BF — verificador do gerenciador de temas + correcao do botao.
   Node puro, somente leitura. Uso: node tools/verify-theme-manager.cjs [raiz] */
'use strict';
const fs=require('fs'),path=require('path');
const ROOT=process.argv[2]||process.cwd();
let C=0,F=0;
function ok(l,c,x=''){c?C++:F++;console.log((c?'OK   ':'FAIL ')+l+(x?' · '+x:''));}
const ex=p=>fs.existsSync(path.join(ROOT,p));
const rd=p=>fs.readFileSync(path.join(ROOT,p),'utf8');
const TWIG='dev/admin/view/template/design/theme_list.twig';
const JS='dev/admin/view/js/theme-package.js';
const CTRL='dev/admin/controller/design/theme.php';
const LANGS=['en-us','pt-br','es-eu'].map(l=>'dev/admin/language/'+l+'/design/theme.php');
for(const p of [TWIG,JS,CTRL,...LANGS])ok('presente: '+p,ex(p));
const tw=rd(TWIG),js=rd(JS),ct=rd(CTRL);
const CODE=js.replace(/\/\*[\s\S]*?\*\//g,'').replace(/^\s*\/\/.*$/gm,'');

/* 1. CORRECAO DO BUG: evento real, registrado antes do early-return. */
ok('botao tem data-theme-import-open',/href="#theme-import"[^>]*data-theme-import-open/.test(tw));
ok('painel #theme-import presente',/id="theme-import"/.test(tw));
ok('handler do clique presente',/data-theme-import-open/.test(CODE)&&/\.open\s*=\s*true/.test(CODE));
ok('handler antes do early-return',CODE.indexOf('data-theme-import-open')<CODE.indexOf('if (!input'));
ok('preventDefault no clique',/e\.preventDefault\(\)/.test(CODE));
ok('foco na textarea',/area\.focus\(\)/.test(CODE));
ok('fallback sem JS (href) preservado',/href="#theme-import"/.test(tw));

/* 2. Gerenciador: capacidades Twig + ThreeJS. */
ok('titulo do gerenciador',/text_manager_title/.test(tw));
ok('cartao Pacotes Twig',/text_package_twig/.test(tw)&&/template_count/.test(tw));
ok('cartao Pacotes ThreeJS',/text_package_three/.test(tw)&&/scene_count/.test(tw)&&/preset_count/.test(tw));
ok('allowlist Twig injetada',/data-template-keys="\{\{ template_keys_list \}\}"/.test(tw));
ok('allowlist de cenas injetada',/data-scenes="\{\{ scene_list \}\}"/.test(tw));
ok('preview Twig',['twig-count','twig-status','twig-list'].every(k=>tw.includes('data-p-'+k)));
ok('preview ThreeJS',['three-scene','three-slot','three-status','scene-layers','scene-kinds'].every(k=>tw.includes('data-p-'+k)));
ok('preview demais blocos',tw.includes('data-p-blocks'));
ok('14 data-msg-* traduziveis',['size','type','json','format','ready','none','fonts','absent',
  'twig-ok','twig-bad','three-ok','three-bad','blocks-none'].every(k=>tw.includes('data-msg-'+k)));
ok('form import + CSRF',/action="\{\{ import_url \}\}"/.test(tw)&&/name="csrf_token"/.test(tw));
ok('textarea theme_package',tw.includes('id="theme-package-json"')&&tw.includes('name="theme_package"'));
ok('CRUD da BE preservado',/\{\{ theme\.activate \}\}/.test(tw)&&/\{\{ theme\.deactivate \}\}/.test(tw)&&/\{\{ theme\.delete \}\}/.test(tw));
ok('tabela com Acoes',tw.includes('{{ column_actions }}'));
ok('sem <script>',!/<script/i.test(tw));
ok('sem JS inline',!/\son(click|change|submit|load)\s*=/i.test(tw));
ok('|raw apenas chrome (2)',(tw.match(/\|raw/g)||[]).length===2);
const ifs=(tw.match(/{%\s*if\b/g)||[]).length,eifs=(tw.match(/{%\s*endif\s*%}/g)||[]).length;
const fors=(tw.match(/{%\s*for\b/g)||[]).length,efors=(tw.match(/{%\s*endfor\s*%}/g)||[]).length;
ok('Twig balanceado',ifs===eifs&&fors===efors,`if ${ifs}/${eifs} for ${fors}/${efors}`);

/* 3. JS: seguranca e inspecao. */
for(const [n,r] of [['fetch/XHR',/\bfetch\s*\(|XMLHttpRequest/],['eval/Function',/\beval\s*\(|new Function|document\.write/],
  ['innerHTML/outerHTML',/innerHTML|outerHTML/],['storage',/localStorage|sessionStorage/],
  ['import/require',/\bimport\s*\(|\brequire\s*\(/]])ok('JS sem '+n,!r.test(CODE));
ok('JS usa textContent',/\.textContent\s*=/.test(CODE));
ok('JS usa createElement',/document\.createElement/.test(CODE));
ok('JS inspeciona templates',/cfg\.templates/.test(CODE)&&/ALLOW_TPL/.test(CODE));
ok('JS inspeciona three/kaz_scene',/cfg\.three/.test(CODE)&&/kaz_scene/.test(CODE)&&/ALLOW_SCENE/.test(CODE));
ok('JS nao calcula checksum',!/hash_equals|createHash|crypto/.test(CODE));
ok('JS mantem fail-closed (512 KiB, formato, slug, checksum)',
  /MAX\s*=\s*524288/.test(CODE)&&/reamurcms-theme/.test(CODE)&&/\[a-z0-9_-\]\{1,48\}/.test(CODE)&&/\[0-9a-f\]\{64\}/.test(CODE));
ok('JS mantem POST theme_package',/area\.value\s*=\s*raw/.test(CODE));
ok('JS drag-and-drop',/dataTransfer/.test(CODE));

/* 4. Controller: inventario allowlistado, sem mudanca de backend de import. */
ok('template_keys_list injetado',/\$data\['template_keys_list'\]=implode\(',',.*ThemePackage::TEMPLATE_KEYS\)/.test(ct));
ok('scene_list injetado',/\$data\['scene_list'\]=implode\(',',\$this->three_scene_preset->scenes\(\)\)/.test(ct));
ok('contagens injetadas',/\$data\['template_count'\]/.test(ct)&&/\$data\['scene_count'\]/.test(ct)&&/\$data\['preset_count'\]/.test(ct));
ok('import() intacto',/\$this->theme_package->decode/.test(ct)&&/\$saved=\$this->model_design_theme->save/.test(ct));
ok('ACL/CSRF das rotas preservados',(ct.match(/hasPermission\('modify','design\/theme'\)/g)||[]).length>=3);
ok('nenhum ALTER/CREATE/DROP',!/ALTER\s+TABLE|CREATE\s+TABLE|DROP\s+TABLE/i.test(ct));
ok('apenas 1 comentario Frente BE',(ct.match(/Frente BE: feedback estavel/g)||[]).length===1);

/* 5. Idiomas. */
const KEYS=['text_manager_title','text_manager_intro','text_package_capacity','text_package_twig',
 'text_package_twig_help','text_package_three','text_package_three_help','text_template_fonts',
 'text_scene_units','text_preset_units','text_preview_content','text_preview_twig',
 'text_preview_twig_count','text_preview_three','text_preview_three_scene','text_preview_three_slot',
 'text_preview_scene_layers','text_preview_scene_kinds','text_preview_blocks','text_preview_blocks_none',
 'status_absent','status_twig_ok','status_twig_bad','status_three_ok','status_three_bad'];
for(const l of LANGS){
  const s=rd(l),tag=l.split('/')[3];
  ok('25 chaves em '+tag,KEYS.every(k=>s.includes("$_['"+k+"']")));
  ok(tag+' sem |raw',!s.includes('|raw'));
}

/* 6. Governanca. */
ok('relatorio presente',ex('RELATORIO-FINAL-BF.md'));ok('indice presente',ex('INDICE-BF.md'));
const docs=rd('RELATORIO-FINAL-BF.md')+rd('INDICE-BF.md');
ok('registra o bug do clique',/nenhum evento|n[aã]o abria|bug/i.test(docs));
ok('Twig/ThreeJS como dados',/dados validados|sem c[oó]digo execut[aá]vel/.test(docs));
ok('sem migration',/[Nn]enhuma migration|sem migration/.test(docs));
ok('nao afirma release',/n[aã]o\s+significam?\s+release\s+aprovada/i.test(docs));
ok('release_approved false',/release_approved:\s*false/.test(docs));
ok('F9-27 preservado',/BLOCKED_NOT_RUN/.test(docs));
ok('F9-18 preservado',/release_ready:\s*false/.test(docs));
ok('10 PENDING_DECISION',/10\s*`?PENDING_DECISION`?/.test(docs));
ok('sem escrita no GitHub',/escrita (do agente )?no GitHub/i.test(docs));
console.log('CJS BF: '+C+' OK / '+F+' FALHAS');
process.exit(F?1:0);
