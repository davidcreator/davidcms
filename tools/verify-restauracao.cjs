/* Verificador da RESTAURACAO do CI — Frente AV.
   Node puro, sem dependencias, somente leitura.
   Uso: node tools/verify-restauracao.cjs [raiz] */
'use strict';
const fs = require('fs'), path = require('path'), crypto = require('crypto');
const ROOT = process.argv[2] || process.cwd();
let C = 0, F = 0;
function ok(l, c, x = '') { c ? C++ : F++; console.log((c ? 'OK   ' : 'FAIL ') + l + (x ? ' · ' + x : '')); }
const rd = p => fs.readFileSync(path.join(ROOT, p), 'utf8');
const ex = p => fs.existsSync(path.join(ROOT, p));
const sha = p => crypto.createHash('sha256').update(fs.readFileSync(path.join(ROOT, p))).digest('hex');

const LISTA = 'evidencia/lista-arquivos-removidos.txt';
const HASHES = 'evidencia/sha256-restaurados.txt';
ok('lista de removidos presente', ex(LISTA));
ok('lista de hashes presente', ex(HASHES));
const paths = rd(LISTA).split('\n').map(s => s.trim()).filter(Boolean);
ok('41 arquivos listados', paths.length === 41, paths.length);
const linhas = rd(HASHES).split('\n').map(s => s.trim()).filter(Boolean);
ok('41 hashes listados', linhas.length === 41, linhas.length);

let okHash = 0, faltando = [];
for (const l of linhas) {
  const [h, p] = l.split(/\s+/);
  if (!p) continue;
  if (!ex(p)) { faltando.push(p); continue; }
  if (sha(p) === h) okHash++; else faltando.push(p + ' (hash)');
}
ok('41 arquivos restaurados e integros', okHash === 41 && faltando.length === 0, okHash + '/41 · ' + faltando.slice(0, 3).join(','));
ok('nenhum arquivo de dev/ no restauro', !paths.some(p => p.startsWith('dev/')));

const cks = paths.filter(p => p.startsWith('CHECKSUMS'));
const f11 = paths.filter(p => p.includes('F11-') && p.endsWith('INDICE.md'));
const outros = paths.filter(p => !p.startsWith('CHECKSUMS') && !(p.includes('F11-') && p.endsWith('INDICE.md')));
ok('14 manifestos CHECKSUMS', cks.length === 14, cks.length);
ok('15 indices F11', f11.length === 15, f11.length);
ok('12 relatorios/indices/CONTRIBUTING', outros.length === 12, outros.length);
ok('CONTRIBUTING.md restaurado', paths.includes('CONTRIBUTING.md'));
ok('RELATORIO.md restaurado', paths.includes('RELATORIO.md'));
ok('CHECKSUMS.txt restaurado', paths.includes('CHECKSUMS.txt'));

/* Os 7 links mortos de docs/README.md voltam a resolver. */
const readme = ex('docs/README.md') ? rd('docs/README.md') : '';
const alvos = [...readme.matchAll(/\]\((\.\.\/F11-[^)#\s]+\.md)/g)].map(m => m[1]);
ok('docs/README.md presente', ex('docs/README.md'));
ok('7 links F11 no README', alvos.length === 7, alvos.length);
ok('todos os alvos do README existem', alvos.every(a => ex(path.normalize(path.join('docs', a)))), alvos.join(','));

/* Logs da simulacao antes/depois. */
const antes = ex('evidencia/guardas-antes.txt') ? rd('evidencia/guardas-antes.txt') : '';
const depois = ex('evidencia/guardas-depois.txt') ? rd('evidencia/guardas-depois.txt') : '';
ok('log ANTES registra REPROVADO', /REPROVADO/.test(antes) && /1 FAIL/.test(antes));
ok('log ANTES registra a causa', /nenhum CHECKSUMS/.test(antes));
ok('log DEPOIS registra COERENTE', /COERENTE/.test(depois) && /6 PASS \/ 0 FAIL/.test(depois));

/* Diagnostico e invariantes. */
const D = 'docs/Diagnostico-CI-Vermelho-accb6257.md';
ok('diagnostico presente', ex(D));
const d = ex(D) ? rd(D) : '';
ok('diagnostico cita o HEAD', d.includes('accb625768424e2deae4b6054721602a64097323'));
ok('diagnostico cita os 2 checks vermelhos', d.includes('docs-links') && d.includes('repo-guards'));
ok('diagnostico cita os 7 links mortos', /7 links/.test(d));
ok('diagnostico recomenda Via A sem relaxar guarda', /RECOMENDADA/.test(d) && /N[aã]o relaxa nenhum guarda/.test(d));
ok('diagnostico desaconselha Via B', /tolerar zero manifestos/.test(d) && /autoriza/.test(d));
ok('diagnostico nao afirma release', /n[aã]o declara release|significa release aprovada/i.test(d));
ok('diagnostico sem codigo PHP aplicavel', !d.includes('<?php'));
ok('veredito APROVADO preservado', /APROVADO/.test(d));

console.log('CJS AV: ' + C + ' OK / ' + F + ' FALHAS');
process.exit(F ? 1 : 0);
