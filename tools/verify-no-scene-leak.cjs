/* Frente BK — verificador anti-vazamento do stage do KAZEditor-Theme.
   Uso: node tools/verify-no-scene-leak.cjs <raiz> [pacote.json ...]
   Falha se qualquer tema incluir partial/theme-scene-block.twig, se o patch
   nao fechar os 3 pontos do Catalogo, ou se o Admin perder a cena. */
'use strict';
const fs=require('fs'),path=require('path'),crypto=require('crypto');
const ROOT=process.argv[2]||process.cwd();
let C=0,F=0;
function ok(l,c,x=''){c?C++:F++;console.log((c?'OK   ':'FAIL ')+l+(x?' · '+x:''));}
const ex=p=>fs.existsSync(path.join(ROOT,p));
const rd=p=>fs.readFileSync(path.join(ROOT,p),'utf8');
function canon(v){if(Array.isArray(v))return v.map(canon);if(v&&typeof v==='object'){const o={};Object.keys(v).sort().forEach(k=>o[k]=canon(v[k]));return o;}return v;}

console.log('-- 1. Pacotes de tema corrigidos --');
const PKGS=['docs/temas/David-Creator.reamurtheme.json','docs/temas/Atlas-Studio.reamurtheme.json',
 'docs/temas/Reamur-Reference.reamurtheme.json','docs/temas/Reamur-ThreeJS-Reference.reamurtheme.json'];
const KEYS=25;
for(const rel of PKGS){
 ok('presente: '+rel,ex(rel));
 if(!ex(rel))continue;
 let p;try{p=JSON.parse(rd(rel));}catch(e){ok('JSON valido: '+rel,false,e.message);continue;}
 const t=p.config.templates,all=Object.values(t).join('\n');
 ok('  format/version',p.format==='reamurcms-theme'&&p.version===1);
 ok('  25 fontes',Object.keys(t).length===KEYS,String(Object.keys(t).length));
 ok('  checksum canonico confere',crypto.createHash('sha256')
   .update(JSON.stringify(canon(p.config)),'utf8').digest('hex')===p.checksum);
 ok('  SEM theme-scene-block.twig',!all.includes('theme-scene-block.twig'));
 ok('  theme-scene.twig declarativo mantido',all.includes('partial/theme-scene.twig'));
 ok('  kaz_scene preservado (dado de edicao)',p.config.kaz_scene.layers.length>=3,
    String(p.config.kaz_scene.layers.length));
 ok('  Twig balanceado',Object.values(t).every(v=>
   (v.match(/{%\s*if\b/g)||[]).length===(v.match(/{%\s*endif\s*%}/g)||[]).length&&
   (v.match(/{%\s*for\b/g)||[]).length===(v.match(/{%\s*endfor\s*%}/g)||[]).length));
}

console.log('-- 2. Patch de runtime (proposta) --');
ok('correcao-runtime.patch presente',ex('docs/correcao/correcao-runtime.patch'));
const patch=rd('docs/correcao/correcao-runtime.patch');
const ALVOS=['dev/catalog/view/template/common/home.twig','dev/catalog/controller/common/home.php',
 'dev/catalog/controller/startup/theme.php'];
ok('patch cobre os 3 arquivos',ALVOS.every(a=>patch.includes('a/'+a)&&patch.includes('b/'+a)));
ok('patch so REMOVE linhas',!/^\+[^+]/.test(patch.split('\n').filter(l=>l.startsWith('+')).join('\n')));
ok('remove o include do home.twig',/-\{% include 'partial\/theme-scene-block\.twig' %\}/.test(patch));
ok("remove theme_scene_view do home.php",/\-\s*\$data\['theme_scene_view'\]/.test(patch));
ok("remove o set do startup",/\-\s*\$this->config->set\('catalog_theme_scene_view'/.test(patch));
ok('patch NAO toca no Admin',!patch.includes('dev/admin/')&&!patch.includes('twig.php')
   &&!patch.includes('theme_compare_view.php'));
ok('patch NAO toca em manifests/contratos/schema',!patch.includes('CHECKSUMS')&&!patch.includes('schema')
   &&!patch.includes('.github/'));
const inv=JSON.parse(rd('docs/correcao/patch.json'));
ok('3 arquivos / 4 linhas removidas',inv.arquivos.length===3&&inv.linhas_removidas===4);
ok('Admin intacto inventariado',inv.admin_intacto.length===4);

console.log('-- 3. Prova visual e estrutural --');
const L=JSON.parse(rd('evidencia/layout-check.json'));
const antes=L.find(x=>x.caso==='antes'),depois=L.find(x=>x.caso==='depois');
ok('antes: 1 stage / 3 camadas',antes.rms_scene_stage===1&&antes.data_rms_layer===3);
ok('depois: 0 stage / 0 camadas',depois.rms_scene_stage===0&&depois.data_rms_layer===0);
ok('hero/CTA/footer preservados',[antes,depois].every(x=>x.hero_presente&&x.cta_presente&&x.footer_presente));
ok('mesmas secoes antes e depois',antes.secoes_dj===depois.secoes_dj,String(antes.secoes_dj));
ok('comparacao PNG presente',ex('evidencia/antes-depois.png'));
ok('diagnostico presente',ex('DIAGNOSTICO-BK.md'));

console.log('-- 4. Documentacao e governanca --');
const docs=['DIAGNOSTICO-BK.md','GUIA-CORRECAO-BK.md','RELATORIO-FINAL-BK.md','INDICE-BK.md']
 .filter(ex).map(rd).join('\n');
ok('documentacao presente',docs.length>0);
for(const s of ['theme_scene_view','catalog_theme_scene_view','ThemeSceneCss','kaz_scene',
 'não renderização no site público','release_approved: false','BLOCKED_NOT_RUN',
 'PENDING_DECISION','escrita do agente no GitHub']){
 ok('doc cita: '+s,docs.includes(s));
}
ok('doc registra 10 decisoes pendentes',/10\s*`?PENDING_DECISION`?/.test(docs));
ok('nao afirma release',/n[aã]o\s+significam?\s+release\s+aprovada/i.test(docs));
ok('nao converte PENDING em PASS',!/PENDING_DECISION.*PASS/.test(docs));
console.log('CJS BK: '+C+' OK / '+F+' FALHAS');
process.exit(F?1:0);
