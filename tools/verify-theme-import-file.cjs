/* Frente BC — verificador do import por arquivo (recorte A).
   Node puro, somente leitura. Uso: node tools/verify-theme-import-file.cjs [raiz] */
'use strict';
const fs=require('fs'),path=require('path');
const ROOT=process.argv[2]||process.cwd();
let C=0,F=0;
function ok(l,c,x=''){c?C++:F++;console.log((c?'OK   ':'FAIL ')+l+(x?' · '+x:''));}
const ex=p=>fs.existsSync(path.join(ROOT,p));
const rd=p=>fs.readFileSync(path.join(ROOT,p),'utf8');
const JS='dev/admin/view/js/theme-package.js';
const TWIG='dev/admin/view/template/design/kaz_theme_editor.twig';
const CTRL='dev/admin/controller/design/theme.php';
const LANGS=['en-us','pt-br','es-eu'].map(l=>'dev/admin/language/'+l+'/design/theme.php');
for(const p of [JS,TWIG,CTRL,...LANGS])ok('presente: '+p,ex(p));
const js=rd(JS),tw=rd(TWIG),ct=rd(CTRL);
const CODE=js.replace(/\/\*[\s\S]*?\*\//g,'').replace(/^\s*\/\/.*$/gm,'');

/* 1. Seguranca: nada executa, nada sai do cliente. */
ok('sem fetch/XMLHttpRequest',!/\bfetch\s*\(|XMLHttpRequest/.test(CODE));
ok('sem eval/Function/document.write',!/\beval\s*\(|new Function|document\.write/.test(CODE));
ok('sem innerHTML/outerHTML',!/innerHTML|outerHTML/.test(CODE));
ok('sem storage/rede',!/localStorage|sessionStorage|navigator\.sendBeacon/.test(CODE));
ok('preview usa textContent',/\.textContent\s*=/.test(CODE));
ok('FileReader + readAsText',/\bFileReader\b/.test(CODE)&&/readAsText/.test(CODE));
ok('drag-and-drop',/dragenter|dragover/.test(CODE)&&/dataTransfer/.test(CODE));
ok('limite 512 KiB coerente',/MAX\s*=\s*524288/.test(CODE));
ok('formato e versao coerentes',/reamurcms-theme/.test(CODE)&&/VER\s*=\s*1/.test(CODE));
ok('valida checksum 64 hex no client',/\[0-9a-f\]\{64\}/.test(CODE));
ok('valida slug no client',/\[a-z0-9_-\]\{1,48\}/.test(CODE));
ok('nao calcula/substitui o checksum do servidor',!/hash_equals|createHash|crypto/.test(CODE));
ok('POST continua theme_package (string)',/area\.value\s*=\s*raw/.test(CODE));

/* 2. Twig: card, sem superficie nova. */
ok('zona de drop presente',tw.includes('data-theme-package-drop'));
ok('preview presente e oculto por padrao',/data-theme-package-preview[^>]*hidden/.test(tw));
ok('status aria-live presente',/data-theme-package-status[^>]*aria-live="polite"/.test(tw));
ok('input de arquivo preservado',tw.includes('id="theme-package-file"'));
ok('textarea preservada',tw.includes('id="theme-package-json"')&&tw.includes('name="theme_package"'));
ok('accept inclui .reamurtheme.json',/\.reamurtheme\.json/.test(tw));
ok('csrf_token preservado',tw.includes('name="csrf_token"'));
ok('sem <script> no template',!/<script/i.test(tw));
const ifs=(tw.match(/{%\s*if\b/g)||[]).length,eifs=(tw.match(/{%\s*endif\s*%}/g)||[]).length;
const fors=(tw.match(/{%\s*for\b/g)||[]).length,efors=(tw.match(/{%\s*endfor\s*%}/g)||[]).length;
ok('Twig balanceado (if/for)',ifs===eifs&&fors===efors,`if ${ifs}/${eifs} for ${fors}/${efors}`);

/* 3. Controller: apenas as chaves de idioma foram adicionadas. */
ok('import() intacto',/public function import\(string \$theme_id='0'\): void/.test(ct)&&
   /\$decoded=\$this->theme_package->decode\(\(string\)\(\$this->request->post\['theme_package'\]\?\?''\)\)/.test(ct));
ok('CSRF e ACL do import intactos',/Csrf::check\(\$this->session/.test(ct)&&/hasPermission\('modify','design\/theme'\)/.test(ct));
ok('import nunca publica',/import_ok=1/.test(ct)&&/save\(\$id/.test(ct));
ok('14 chaves novas injetadas no data',[
 'text_package_drop','text_package_preview','text_package_preview_name',
 'text_package_preview_slug','text_package_preview_file','text_package_ready',
 'error_package_size','error_package_type','error_package_json','error_package_format'
].every(k=>ct.includes("'"+k+"'")));
ok('ThemePackage::decode continua a autoridade',/\$this->theme_package->decode/.test(ct));

/* 4. Idiomas: 10 chaves x 3. */
const KEYS=['text_package_drop','text_package_preview','text_package_preview_name',
 'text_package_preview_slug','text_package_preview_file','text_package_ready',
 'error_package_size','error_package_type','error_package_json','error_package_format'];
for(const l of LANGS){
  const s=rd(l);
  ok('10 chaves em '+l.split('/')[3],KEYS.every(k=>s.includes("$_['"+k+"']")));
  ok(l.split('/')[3]+' sem |raw',!s.includes('|raw'));
}

/* 5. Pacote e governanca. */
ok('relatorio presente',ex('RELATORIO-FINAL-BC.md'));ok('indice presente',ex('INDICE-BC.md'));
const docs=rd('RELATORIO-FINAL-BC.md')+rd('INDICE-BC.md');
ok('recorte A e dados validados',/recorte A|Recorte A/.test(docs)&&/sem c[oó]digo execut[aá]vel/.test(docs));
ok('Twig/ThreeJS continuam dados',/allowlist/.test(docs));
ok('sem migration',/[Nn]enhuma migration|sem migration/.test(docs));
ok('nao afirma release',/n[aã]o\s+significam?\s+release\s+aprovada/i.test(docs));
ok('release_approved false',/release_approved:\s*false/.test(docs));
ok('F9-27 preservado',/BLOCKED_NOT_RUN/.test(docs));
ok('F9-18 preservado',/release_ready:\s*false/.test(docs));
ok('10 PENDING_DECISION',/10\s*`?PENDING_DECISION`?/.test(docs));
ok('sem escrita no GitHub',/escrita (do agente )?no GitHub/i.test(docs));
ok('nenhum contrato congelado no pacote',!/^docs\/F9-14/m.test(docs));

console.log('CJS BC: '+C+' OK / '+F+' FALHAS');
process.exit(F?1:0);
