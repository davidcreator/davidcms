/* Frente BE — verificador do CRUD de temas + Importar tema.
   Node puro, somente leitura. Uso: node tools/verify-theme-crud.cjs [raiz] */
'use strict';
const fs=require('fs'),path=require('path');
const ROOT=process.argv[2]||process.cwd();
let C=0,F=0;
function ok(l,c,x=''){c?C++:F++;console.log((c?'OK   ':'FAIL ')+l+(x?' · '+x:''));}
const ex=p=>fs.existsSync(path.join(ROOT,p));
const rd=p=>fs.readFileSync(path.join(ROOT,p),'utf8');
const TWIG='dev/admin/view/template/design/theme_list.twig';
const CTRL='dev/admin/controller/design/theme.php';
const MODEL='dev/admin/model/design/theme.php';
const LANGS=['en-us','pt-br','es-eu'].map(l=>'dev/admin/language/'+l+'/design/theme.php');
for(const p of [TWIG,CTRL,MODEL,...LANGS])ok('presente: '+p,ex(p));
const tw=rd(TWIG),ct=rd(CTRL),mo=rd(MODEL);

/* 1. Lista: acoes pedidas pelo usuario. */
ok('botao Novo tema preservado',/href="\{\{ add \}\}"[^>]*>\{\{ button_add \}\}/.test(tw));
ok('botao Importar tema ao lado',/href="#theme-import"[^>]*>\{\{ button_import \}\}/.test(tw));
ok('coluna Acoes',tw.includes('{{ column_actions }}'));
ok('Editar preservado',tw.includes('{{ theme.edit }}'));
ok('Ativar/Publicar preservado',tw.includes('{{ theme.activate }}'));
ok('Desativar para o ativo',tw.includes('{{ theme.deactivate }}')&&tw.includes('{{ button_deactivate }}'));
ok('Excluir para o inativo',tw.includes('{{ theme.delete }}')&&tw.includes('{{ button_delete_confirm }}'));
ok('Excluir desabilitado no ativo',/aria-disabled="true"/.test(tw)&&tw.includes('text_delete_blocked_active'));
ok('confirmacao em 2 etapas sem JS',/<details[^>]*>[\s\S]{0,400}<summary[^>]*>\{\{ button_delete \}\}/.test(tw));
ok('card de import na lista',tw.includes('id="theme-import"')&&tw.includes('data-theme-package-form'));
ok('import_url+CSRF no form',/action="\{\{ import_url \}\}"/.test(tw)&&/name="csrf_token"/.test(tw));
ok('textarea theme_package preservada',tw.includes('id="theme-package-json"')&&tw.includes('name="theme_package"'));
ok('seletores do theme-package.js presentes',['data-theme-package-drop','data-theme-package-preview',
  'data-theme-package-status','data-p-name','data-p-slug','data-p-version','data-p-parent',
  'data-p-file','data-p-size','id="theme-package-file"'].every(s=>tw.includes(s)));
ok('mensagens data-msg-* presentes',['size','type','json','format','ready','none'].every(k=>tw.includes('data-msg-'+k)));
ok('notices de sucesso/erro',tw.includes('{{ notice }}')&&tw.includes('{{ error }}'));
ok('sem <script>',!/<script/i.test(tw));
ok('sem JS inline (onclick/onchange)',!/\son(click|change|submit|load)\s*=/i.test(tw));
ok('|raw apenas no chrome (2)',(tw.match(/\|raw/g)||[]).length===2,(tw.match(/\|raw/g)||[]).length);
const forms=[...tw.matchAll(/<form[^>]*method="post"[^>]*>/g)].map(m=>m[0]);
ok('4 formularios POST',forms.length===4,String(forms.length));
ok('todos os POST tem action',forms.every(f=>f.includes('action=')));
const ifs=(tw.match(/{%\s*if\b/g)||[]).length,eifs=(tw.match(/{%\s*endif\s*%}/g)||[]).length;
const fors=(tw.match(/{%\s*for\b/g)||[]).length,efors=(tw.match(/{%\s*endfor\s*%}/g)||[]).length;
ok('Twig balanceado',ifs===eifs&&fors===efors,`if ${ifs}/${eifs} for ${fors}/${efors}`);

/* 2. Controller: rotas, ACL, CSRF, sem GET mutante. */
for(const m of ['activate','deactivate','delete','import','export','preview','restore'])
  ok('metodo '+m+'() presente',new RegExp('public function '+m+'\\(').test(ct));
for(const m of ['deactivate','delete']){
  const i=ct.indexOf('public function '+m+'(');const j=ct.indexOf('    }',i);
  const body=ct.slice(i,j);
  ok(m+'(): exige POST',/REQUEST_METHOD'\]!=='POST'/.test(body));
  ok(m+'(): ACL modify',/hasPermission\('modify','design\/theme'\)/.test(body));
  ok(m+'(): CSRF',/Csrf::check\(\$this->session/.test(body));
  ok(m+'(): sem GET mutante',!/REQUEST_METHOD\]==='GET'/.test(body));
}
ok('activate(): feedback ok=activate',/ok=activate/.test(ct));
ok('deactivate(): feedback ok=deactivate',/ok=deactivate/.test(ct));
ok('delete(): feedback ok=delete / error=delete',/ok=delete/.test(ct)&&/error=delete/.test(ct));
ok('import() id=0 volta para a lista',/\$back=\$id\?\$this->url->link\('design\/theme\.edit\|\'\.\$id\):\$this->url->link\('design\/theme'\)/.test(ct));
ok('import() id>0 preserva import_ok=1',/\$saved\?'import_ok=1':'import_error=save_failed'/.test(ct));
ok('import() continua ThemePackage::decode',/\$this->theme_package->decode/.test(ct));
ok('import() continua como rascunho (save)',/\$saved=\$this->model_design_theme->save/.test(ct));
ok('index(): injeta import_url',/\$data\['import_url'\]=/.test(ct));
ok('index(): URLs de deactivate/delete por linha',/\$theme\['deactivate'\]/.test(ct)&&/\$theme\['delete'\]/.test(ct));
ok('index(): allowlist de feedback',/\$codes=\['package_size_invalid'/.test(ct));
ok('index(): carrega theme-package.js',/addScriptDeferred\([\s\S]{0,300}theme-package\.js/.test(ct));
ok('index(): chrome() antes do view',ct.indexOf('$this->chrome($data); $this->response->setOutput($this->load->view(\'design/theme_list\'')>0);

/* 3. Model: fail-closed, sem migration. */
ok('model deactivate() presente',/public function deactivate\(int \$id, int \$userId\): bool/.test(mo));
ok('model delete() presente',/public function delete\(int \$id\): bool/.test(mo));
ok('delete() nunca remove ativo',/\(int\)\(\$theme\['status'\]\?\?0\)===1\) return false/.test(mo));
ok('delete() remove revisoes',/DELETE FROM `\$this->table\('design_theme_revision'\)/.test(mo)||/DELETE FROM `/.test(mo));
ok('deactivate() preserva payload',!/SET `config`=''|published_config`=''/.test(mo));
ok('sem ALTER/CREATE/DROP no model',!/ALTER\s+TABLE|CREATE\s+TABLE|DROP\s+TABLE/i.test(mo));
ok('activate() original intacto',/SET `status`='0'/.test(mo)&&/published_config`=`draft_config`/.test(mo));
ok('save() original intacto',/design_theme_revision/.test(mo)&&/`status`='0',`date_added`=NOW\(\)/.test(mo));

/* 4. Idiomas. */
const KEYS=['column_actions','button_deactivate','button_delete','button_delete_confirm','button_import',
 'text_import_title','text_activate_help','text_deactivate_help','text_delete_confirm',
 'text_delete_blocked_active','text_activate_ok','text_deactivate_ok','text_delete_ok','text_delete_error'];
for(const l of LANGS){
  const s=rd(l),tag=l.split('/')[3];
  ok('14 chaves em '+tag,KEYS.every(k=>s.includes("$_['"+k+"']")));
  ok('button_activate = Ativar/Activate/Activar em '+tag,
     /\$_\['button_activate'\] = '(Ativar|Activate|Activar)'/.test(s));
  ok(tag+' sem |raw',!s.includes('|raw'));
}

/* 5. Pacote e governanca. */
ok('relatorio presente',ex('RELATORIO-FINAL-BE.md'));ok('indice presente',ex('INDICE-BE.md'));
const docs=rd('RELATORIO-FINAL-BE.md')+rd('INDICE-BE.md');
ok('registra imagem-11',/image-11/.test(docs));
ok('sem migration',/[Nn]enhuma migration|sem migration/.test(docs));
ok('nao afirma release',/n[aã]o\s+significam?\s+release\s+aprovada/i.test(docs));
ok('release_approved false',/release_approved:\s*false/.test(docs));
ok('F9-27 preservado',/BLOCKED_NOT_RUN/.test(docs));
ok('F9-18 preservado',/release_ready:\s*false/.test(docs));
ok('10 PENDING_DECISION',/10\s*`?PENDING_DECISION`?/.test(docs));
ok('sem escrita no GitHub',/escrita (do agente )?no GitHub/i.test(docs));
ok('nenhum contrato congelado no pacote',!/^docs\/F9-14/m.test(docs));

console.log('CJS BE: '+C+' OK / '+F+' FALHAS');
process.exit(F?1:0);
