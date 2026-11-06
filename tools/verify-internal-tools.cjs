/* Frente AY — verificador da secao 'Ferramentas internas' (Parte A).
   Node puro, sem dependencias, somente leitura.
   Uso: node tools/verify-internal-tools.cjs [raiz] */
'use strict';
const fs = require('fs'), path = require('path');
const ROOT = process.argv[2] || process.cwd();
let C = 0, F = 0;
function ok(l, c, x = '') { c ? C++ : F++; console.log((c ? 'OK   ' : 'FAIL ') + l + (x ? ' · ' + x : '')); }
const ex = p => fs.existsSync(path.join(ROOT, p));
const rd = p => fs.readFileSync(path.join(ROOT, p), 'utf8');

const CTRL = 'dev/admin/controller/tool/tool.php';
const TWIG = 'dev/admin/view/template/tool/tool_list.twig';
const CSS  = 'dev/admin/view/css/admin.css';
const JSONF= 'dev/admin/model/tool/internal-tools.json';
const LANGS= ['en-us','pt-br','es-eu'].map(l => 'dev/admin/language/' + l + '/tool/tool.php');
const ARQ  = [CTRL,TWIG,CSS,JSONF,...LANGS];
for (const p of ARQ) ok('presente: ' + p, ex(p));
const c=rd(CTRL), t=rd(TWIG), css=rd(CSS), meta=JSON.parse(rd(JSONF));

/* 1. Metadados: nomes finais, sem versao inventada. */
ok('schema 1', meta.schema === 1);
ok('2 ferramentas internas', Array.isArray(meta.tools) && meta.tools.length === 2);
const nomes = meta.tools.map(x => x.name);
ok('BDREditor-Content', nomes.includes('BDREditor-Content'));
ok('KAZEditor-Theme (grafia corrigida)', nomes.includes('KAZEditor-Theme'));
ok('nenhum KAZEditor-Thene', !JSON.stringify(meta).includes('Thene'));
ok('legacy_name documentado', meta.tools.every(x => typeof x.legacy_name === 'string' && x.legacy_name));
ok('versoes NAO inventadas (PENDENTE_USUARIO)', meta.tools.every(x => x.version === 'PENDENTE_USUARIO'));
ok('updated_at null', meta.tools.every(x => x.updated_at === null));
ok('notes vazias', meta.tools.every(x => Array.isArray(x.notes) && x.notes.length === 0));
ok('ids unicos e seguros', new Set(meta.tools.map(x => x.id)).size === 2 &&
   meta.tools.every(x => /^[a-z0-9][a-z0-9-]{0,63}$/.test(x.id)));

/* 2. Controller: injecao e fail-closed. */
ok('injeta internal_tools no index', /\$data\['internal_tools'\] = \$this->internalTools\(\);/.test(c));
ok('metodo internalTools declarado', /private function internalTools\(\): array/.test(c));
ok('path derivado de DIR_APPLICATION', /rtrim\(\(string\)DIR_APPLICATION/.test(c));
ok('JSON_THROW_ON_ERROR', c.includes('JSON_THROW_ON_ERROR'));
ok('catch Throwable fail-closed', /catch \(\\Throwable/.test(c));
ok('retorna [] em falha', (c.match(/return \[\];/g) || []).length >= 3);
ok('valida schema', /\$data\['schema'\] \?\? null\) !== 1/.test(c));
ok('valida id unico', /isset\(\$seen\[\$id\]\)/.test(c) && /preg_match\('~\^\[a-z0-9\]/.test(c));
ok('limita notas a 5', /count\(\$lines\) < 5/.test(c));
ok('PENDENTE_USUARIO vira vazio', /\$version === 'PENDENTE_USUARIO'/.test(c));
ok('valida data AAAA-MM-DD', /\\d\{4\}-\\d\{2\}-\\d\{2\}/.test(c));
ok('SEM SQL / migration', !/\b(INSERT INTO|UPDATE `|DELETE FROM|CREATE TABLE|ALTER TABLE)\b/i.test(c));
ok('SEM rota nova', !/\$this->url->link\('tool\/tool\.(?!add|edit|delete|upload)/.test(c));
ok('SEM |raw no controller', !c.includes('|raw'));
ok('SEM chamada de rede', !/\b(curl_|file_get_contents\('http|fopen\('http)/.test(c));
ok('SEM !important', !c.includes('!important'));

/* 3. Twig: secao separada, escape automatico. */
ok('secao internal-tools-panel', t.includes('<section class="internal-tools-panel"'));
ok('condicionada a internal_tools', /\{% if internal_tools %\}/.test(t));
ok('titulo e hint traduzidos', t.includes('{{ text_internal_tools }}') && t.includes('{{ text_internal_tools_hint }}'));
ok('renderiza nome/versao/vendor/legacy', ['tool.name','tool.version','tool.vendor','tool.legacy_name'].every(x => t.includes('{{ ' + x ) || t.includes('{{ tool.')));
ok('mostra nao informado', t.includes('text_internal_not_set'));
ok('lista atualizacoes', t.includes('{{ text_internal_notes }}') && t.includes('{{ note }}'));
ok('SEM |raw na secao nova', !t.slice(t.indexOf('<section class="internal-tools-panel"'), t.indexOf('{% endif %}', t.indexOf('<section class="internal-tools-panel"'))).includes('|raw'));
const ifs=(t.match(/{%\s*if\b/g)||[]).length, eifs=(t.match(/{%\s*endif\s*%}/g)||[]).length;
const fors=(t.match(/{%\s*for\b/g)||[]).length, efors=(t.match(/{%\s*endfor\s*%}/g)||[]).length;
ok('Twig balanceado (if/for)', ifs===eifs && fors===efors, `if ${ifs}/${eifs} for ${fors}/${efors}`);

/* 4. CSS: adendo prefixado, prefixo anterior intacto. */
ok('adendo AY presente', css.includes('/* ── Frente AY'));
const add = css.slice(css.indexOf('/* ── Frente AY'));
ok('so seletores .internal-tools-*', [...add.matchAll(/^([^{}\n]+)\{/gm)].every(m => /^@media|^\.internal-tools-/.test(m[1].trim())));
ok('SEM !important no adendo', !add.includes('!important'));
ok('SEM redefinir --bs-*', ![...add.matchAll(/--bs-[\w-]+\s*:/g)].length);
ok('SEM alvos globais no adendo', !/(^|\n)\s*(html|body|:root|\.sidebar|\.navbar|\.form-control|\.nav-tabs|\.alert|\.badge)\s*\{/m.test(add));
ok('usa acento Bootstrap primario', t.includes('badge text-bg-primary'));

/* 5. Idiomas: 8 chaves x 3. */
const KEYS=['text_internal_tools','text_internal_tools_hint','text_internal_version','text_internal_updated','text_internal_notes','text_internal_not_set','text_internal_no_updates','text_internal_legacy'];
for (const l of LANGS) {
  const s=rd(l);
  ok('8 chaves em '+l.split('/')[3], KEYS.every(k => s.includes("$_['"+k+"']")));
  ok(l.split('/')[3]+' sem |raw', !s.includes('|raw'));
}

/* 6. Invariantes de governanca. */
const REL='RELATORIO-FINAL-AY.md', IND='INDICE-AY.md';
ok('relatorio presente', ex(REL)); ok('indice presente', ex(IND));
const docs=rd(REL)+rd(IND);
ok('nao afirma release', /n[aã]o\s+significam?\s+release\s+aprovada|significa release aprovada/i.test(docs));
ok('release_approved false', /release_approved:\s*false/.test(docs));
ok('F9-27 preservado', /BLOCKED_NOT_RUN/.test(docs));
ok('F9-18 preservado', /release_ready:\s*false/.test(docs));
ok('10 PENDING_DECISION', /10\s*`?PENDING_DECISION`?/.test(docs));
ok('rename R2 nao aplicado', /R2.*(?:n[aã]o aplicado|aguard|pendente)|Parte B.*(?:n[aã]o|aguard)/i.test(docs));
ok('sem escrita no GitHub', /escrita (do agente )?no GitHub/i.test(docs));
ok('sem alteracao de contrato/hash', /nenhum.*(?:contrato|hash|manifesto)/i.test(docs));

console.log('CJS AY: ' + C + ' OK / ' + F + ' FALHAS');
process.exit(F ? 1 : 0);
