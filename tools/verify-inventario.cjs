/* Verificador do PLANO / INVENTARIO — Frente AX.
   Node puro, somente leitura. Uso: node tools/verify-inventario.cjs [raiz] */
'use strict';
const fs = require('fs'), path = require('path');
const ROOT = process.argv[2] || process.cwd();
let C = 0, F = 0;
function ok(l, c, x = '') { c ? C++ : F++; console.log((c ? 'OK   ' : 'FAIL ') + l + (x ? ' · ' + x : '')); }
const rd = p => fs.readFileSync(path.join(ROOT, p), 'utf8');
const ex = p => fs.existsSync(path.join(ROOT, p));
const F7 = ['RELATORIO-FINAL-AX.md', 'INDICE-AX.md', 'Plano-Frente-AX.md',
            'Inventario-Rename-Global.md', 'Analise-Viabilidade-Ferramentas-Internas.md',
            'evidencia/inventario-rename.json', 'tools/verify-inventario.cjs'];
for (const p of F7) ok('presente: ' + p, ex(p));
const plano = rd('Plano-Frente-AX.md'), inv = rd('Inventario-Rename-Global.md');
const anal = rd('Analise-Viabilidade-Ferramentas-Internas.md');
const rel = rd('RELATORIO-FINAL-AX.md'), ind = rd('INDICE-AX.md');
const dados = JSON.parse(rd('evidencia/inventario-rename.json'));
const tudo = plano + inv + anal + rel + ind;

/* Inventario coerente. */
ok('HEAD no inventario', dados.head === '481b690209ec1a1caf4d9f334cef6012d4597653');
ok('179 arquivos', dados.arquivos === 179, dados.arquivos);
ok('762 ocorrencias', dados.ocorrencias === 762, dados.ocorrencias);
ok('itens batem com o total', dados.itens.length === dados.arquivos);
ok('soma das ocorrencias bate', dados.itens.reduce((a, x) => a + x.ocorrencias, 0) === dados.ocorrencias);
ok('nenhum item vazio', dados.itens.every(x => x.path && x.categoria));
ok('nao vaza node_modules/ckeditor/vendor', !dados.itens.some(x => /\/node_modules\/|\/ckeditor\/|\/vendor\//.test(x.path)));
ok('inventario MD cita 179/762', /\b179\b/.test(inv) && /\b762\b/.test(inv));
ok('inventario cita 13 de 14 manifestos', /13 de 14/.test(inv));
ok('inventario cita 0 de 3 contratos', /0 de 3|\*\*0 de 3\*\*/.test(inv));

/* Decisoes do usuario. */
ok('nome BDREditor-Content', /BDREditor-Content/.test(plano) && /BDREditor-Content/.test(anal));
ok('nome KAZEditor-Theme (corrigido)', /KAZEditor-Theme/.test(plano) && /KAZEditor-Theme/.test(anal));
ok('NAO usa KAZEditor-Thene como nome final', !/Nomes finais[^\n]*KAZEditor-Thene/.test(tudo));
ok('decisao R2 registrada', /R2 — global|R2 — rename global/.test(tudo));
ok('decisao V2 registrada', /JSON de metadados versionado/.test(tudo));
ok('decisao P2 registrada', /se[cç][aã]o "Ferramentas internas"/.test(tudo));
ok('4 decisoes na analise', /Grafia do nome/.test(anal) && /Alcance do rename/.test(anal));

/* Arquitetura V2/P2 sem migration. */
ok('JSON de metadados especificado', /tools\/internal-tools\.json/.test(plano));
ok('versao nao inventada', /PENDENTE_USUARIO/.test(plano) && /n[ãa]o foram inventados/.test(plano));
ok('fail-closed no JSON invalido', /fail-closed/.test(plano) && /sem quebrar a p[aá]gina/.test(plano));
ok('sem migration', /[Nn]enhuma migration|sem migration/.test(plano));
ok('sem |raw novo', /nenhum `\|raw` novo/.test(plano));
ok('sem CDN/fonte/rede nova', /nenhuma fonte\/CDN/.test(plano));
ok('catologo publico intacto', /nenhuma mudan[çc]a no cat[aá]logo p[uú]blico/.test(plano));
ok('fases B1..B4', ['B1','B2','B3','B4'].every(f => plano.includes(f)));
ok('B3 marcado como alto risco', /\*\*Alto\*\*/.test(plano));
ok('B3 nao assumido por inferencia', /N[aã]o vou assumir B3 por infer[eê]ncia/.test(plano));

/* Limites. */
ok('nenhuma escrita no GitHub', /Nenhuma escrita no GitHub|nenhuma escrita no GitHub/i.test(tudo));
ok('release_approved false', /release_approved:\s*false/.test(tudo));
ok('F9-27 preservado', /BLOCKED_NOT_RUN/.test(tudo));
ok('F9-18 preservado', /release_ready:\s*false/.test(tudo));
ok('10 PENDING_DECISION', /10 `PENDING_DECISION`/.test(tudo));
ok('nao significa release aprovada', /n[aã]o\s+significam?\s+release\s+aprovada/i.test(tudo));
ok('veredito APROVADO mantido', /APROVADO/.test(tudo));

/* Nao e codigo aplicavel. */
ok('sem PHP executavel no pacote', !tudo.includes('<?php'));
ok('sem tag HTML real', !/<script\s+src=|<!DOCTYPE|<html[\s>]/i.test(tudo));
ok('sem diff aplicavel', !tudo.includes('diff --git'));
ok('sem conversao em PASS', !/convertid[oa].*em `PASS`/i.test(tudo));

console.log('CJS AX: ' + C + ' OK / ' + F + ' FALHAS');
process.exit(F ? 1 : 0);
