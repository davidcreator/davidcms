/* Frente BB — verificador do rename R2 / Fase B1.
   Node puro, somente leitura. Uso: node tools/verify-rename-b1.cjs [raiz] */
'use strict';
const fs=require('fs'),path=require('path');
const ROOT=process.argv[2]||process.cwd();
let C=0,F=0;
function ok(l,c,x=''){c?C++:F++;console.log((c?'OK   ':'FAIL ')+l+(x?' · '+x:''));}
const ex=p=>fs.existsSync(path.join(ROOT,p));
const rd=p=>fs.readFileSync(path.join(ROOT,p),'utf8');
const FILES=[
 'dev/admin/view/template/design/kaz_theme_editor.twig',
 'dev/admin/view/template/cms/page_form.twig',
 'dev/admin/view/template/cms/page_content_form.twig',
 'dev/admin/language/pt-br/cms/page.php','dev/admin/language/pt-br/blog/blog.php',
 'dev/admin/language/es-eu/cms/page.php','dev/admin/language/es-eu/blog/blog.php',
 'dev/admin/language/en-us/cms/page.php','dev/admin/language/en-us/blog/blog.php'];
const OLD=/BDREditor(?!-Content)|KAZTheme-Editor|KAZTheme/g;
const BASE=JSON.parse(rd('evidencia/invariantes.json'));

let totalNovos=0;
for(const p of FILES){
  ok('presente: '+p,ex(p));
  if(!ex(p))continue;
  const s=rd(p),b=BASE[p];
  const velho=[...s.matchAll(OLD)].length;
  ok('0 nome antigo em '+p.split('/').slice(-2).join('/'),velho===0,velho);
  const nb=(s.match(/BDREditor-Content/g)||[]).length+(s.match(/KAZEditor-Theme/g)||[]).length;
  ok('nomes novos em '+p.split('/').slice(-2).join('/'),nb>0,nb);
  totalNovos+=nb;
  if(b){
    ok('chaves $_[] intactas: '+p.split('/').pop(),(s.match(/\$_\['[^']+'\]/g)||[]).length===b.keys);
    ok('class intactas: '+p.split('/').pop(),(s.match(/class="[^"]*"/g)||[]).length===b.class);
    ok('id intactos: '+p.split('/').pop(),(s.match(/id="[^"]*"/g)||[]).length===b.id);
    ok('data-* intactos: '+p.split('/').pop(),(s.match(/data-[\w-]+=/g)||[]).length===b.data);
    ok('|raw intacto: '+p.split('/').pop(),(s.match(/\|raw/g)||[]).length===b.raw);
  }
  ok('sem grafia KAZEditor-Thene',!s.includes('KAZEditor-Thene'));
  ok('sem replace duplo',!s.includes('BDREditor-Content-Content')&&!s.includes('KAZEditor-Theme-Editor'));
}
ok('66 ocorrencias substituidas',totalNovos>=66,totalNovos);

/* Twig balanceado nos 3 templates. */
for(const p of FILES.filter(x=>x.endsWith('.twig'))){
  const s=rd(p);
  for(const tag of ['if','for','block']){
    const a=(s.match(new RegExp('{%\\s*'+tag+'\\b','g'))||[]).length;
    const b=(s.match(new RegExp('{%\\s*end'+tag+'\\s*%}','g'))||[]).length;
    ok('Twig '+tag+' balanceado: '+p.split('/').pop(),a===b,a+'/'+b);
  }
}

/* Preservacao: nenhum arquivo sensivel no pacote. */
const nomes=[];
function walk(d){for(const e of fs.readdirSync(path.join(ROOT,d),{withFileTypes:true})){
  const r=path.join(d,e.name);e.isDirectory()?walk(r):nomes.push(r);}}
walk('.');
ok('nenhum docs/ historico no pacote',!nomes.some(p=>p.startsWith('docs/')&&/\.md$/.test(p)));
ok('nenhum manifesto CHECKSUMS no pacote',!nomes.some(p=>/^CHECKSUMS/.test(p)));
ok('nenhum schema/workflow no pacote',!nomes.some(p=>p.startsWith('dev/install/')||p.startsWith('.github/')));
ok('nenhum contrato congelado no pacote',!nomes.some(p=>/BDREditor-Table-|F9-14-Contrato|F9-25-Contrato/.test(p)));
ok('nenhum .js/.css de runtime no pacote',!nomes.some(p=>p.endsWith('.js')||p.endsWith('.css')));
ok('nenhum arquivo de catalogo no pacote',!nomes.some(p=>p.startsWith('dev/catalog/')));

/* Documentacao e governanca. */
ok('relatorio presente',ex('RELATORIO-FINAL-BB.md'));ok('indice presente',ex('INDICE-BB.md'));
const docs=rd('RELATORIO-FINAL-BB.md')+rd('INDICE-BB.md');
ok('66 -> 0 registrado',/66 ocorr[eê]ncias|66 → 0/.test(docs));
ok('B2/B3 preservados',/B2 e B3 n[aã]o rodaram|B2\/B3 n[aã]o rodaram/.test(docs));
ok('aceite visual pendente',/pendente/.test(docs));
ok('nao afirma release',/n[aã]o\s+significam?\s+release\s+aprovada/i.test(docs));
ok('release_approved false',/release_approved:\s*false/.test(docs));
ok('F9-27 preservado',/BLOCKED_NOT_RUN/.test(docs));
ok('F9-18 preservado',/release_ready:\s*false/.test(docs));
ok('10 PENDING_DECISION',/10\s*`?PENDING_DECISION`?/.test(docs));
ok('sem escrita no GitHub',/escrita (do agente )?no GitHub/i.test(docs));

console.log('CJS BB: '+C+' OK / '+F+' FALHAS');
process.exit(F?1:0);
