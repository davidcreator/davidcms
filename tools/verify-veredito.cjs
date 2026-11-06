/* Verificador do REGISTRO DE VEREDITO — Frente AU.
   Node puro, sem dependencias, somente leitura.
   Uso: node tools/verify-veredito.cjs [raiz]
   Confere que a AP foi aprovada E que nenhum status foi promovido indevidamente. */
'use strict';
const fs = require('fs'), path = require('path');
const ROOT = process.argv[2] || process.cwd();
let C = 0, F = 0;
function ok(l, c, x = '') { c ? C++ : F++; console.log((c ? 'OK   ' : 'FAIL ') + l + (x ? ' · ' + x : '')); }
const rd = p => fs.readFileSync(path.join(ROOT, p), 'utf8');
const ex = p => fs.existsSync(path.join(ROOT, p));
const F7 = ['Registro-Veredito-Visual-AP.md', 'Protocolo-Aceitacao-Visual-AP.md',
            'Roteiro-Proximos-Passos-AP.md', 'Pauta-Decisoes-D01-D10.md',
            'RELATORIO-FINAL-AU.md', 'INDICE-AU.md', 'tools/verify-veredito.cjs'];
for (const p of F7) ok('presente: ' + p, ex(p));
const reg = rd('Registro-Veredito-Visual-AP.md');
const pro = rd('Protocolo-Aceitacao-Visual-AP.md');
const rot = rd('Roteiro-Proximos-Passos-AP.md');
const pau = rd('Pauta-Decisoes-D01-D10.md');
const todos = reg + pro + rot + pau + rd('RELATORIO-FINAL-AU.md') + rd('INDICE-AU.md');

/* 1. O veredito esta registrado corretamente. */
ok('decisao APROVADO explicita', /\*\*APROVADO\*\*/.test(reg) && /APROVADO/.test(pro));
ok('decidido pelo usuario', /usu[aá]rio|David Almeida/.test(reg));
ok('data 2026-11-06', /2026-11-06/.test(reg));
ok('HEAD do estado avaliado', reg.includes('1b7724a10314cf5b5fee479a328651b21843d06a'));
ok('HEAD da aplicacao da AP', reg.includes('179269fea36584076f9ef41b95ae94638dd08f45'));
ok('image-9 superada', /superad/i.test(reg) && /superad/i.test(pro));
ok('image-10 referencia atendida', /atendida/i.test(reg) && /ATENDIDA/.test(pro));
ok('passo 1 concluido', /CONCLU[iÍ]DO/.test(rot) && /CONCLU[iÍ]DO/.test(pro));
ok('escopo fechado e congelado', /fechado e congelado/i.test(reg));

/* 2. O que NAO pode ter mudado — o coracao deste verificador. */
ok('release_approved continua FALSE',
   /release_approved[`'\"\s:|*]*false/i.test(reg) && /release_approved[`'\"\s:|*]*false/i.test(pro));
ok('nenhum release_approved true', !/release_approved["']?\s*[:=]\s*true/i.test(todos));
ok('nenhum release_ready true', !/release_ready["']?\s*[:=]\s*true/i.test(todos));
ok('F9-27 BLOCKED_NOT_RUN preservado', /BLOCKED_NOT_RUN/.test(reg) && /BLOCKED_NOT_RUN/.test(pro));
ok('F9-18 release_ready false preservado', /release_ready:\s*false/.test(reg));
ok('10 PENDING_DECISION preservados', /10\s*`?PENDING_DECISION`?/.test(todos) && /0\s*`?DECIDED`?/.test(todos));
ok('nenhum status DECIDED aplicado', !/"status"\s*:\s*"DECIDED"/.test(todos) && !/status:\s*DECIDED\s*$/m.test(pau));
ok('nenhuma conversao em PASS', !/convertid[oa].*em\s*`?PASS`?\s*(?:com|sem sucesso)?\s*$/im.test(todos));
ok('F9-26 sem conformidade WCAG', /wcag_conformance_claimed:\s*false/.test(todos));
ok('F9-25 PROPOSAL_PENDING', /PROPOSAL_PENDING/.test(todos));
ok('F918-01..04 continuam abertos', /F918-01.{0,10}04/.test(reg) && /abertos/i.test(reg));
ok('nenhuma escrita do agente no GitHub', /Nenhuma escrita do agente no GitHub|escrita do agente no GitHub/i.test(reg));
ok('nao significa release aprovada', /n[aã]o\s+significam?\s+release\s+aprovada|significam release aprovada/i.test(todos));

/* 3. Pauta D01-D10 fiel e sem decisoes pre-preenchidas. */
ok('pauta cita a policy literal', /Nenhuma decisao tomada por inferencia/.test(pau));
ok('pauta lista D01..D10', Array.from({ length: 10 }, (_, i) => 'D' + String(i + 1).padStart(2, '0')).every(id => pau.includes(id)));
ok('pauta declara que o agente nao decide', /agente n[aã]o decide/i.test(pau));
ok('pauta sem decisao pre-preenchida', !/"decision"\s*:/.test(pau) && !/^\s*-\s*decis[aã]o:/im.test(pau));
ok('pauta aponta para o pacote AR', /\*\*AR\*\*|pacote AR/.test(pau));

/* 4. Invariantes do pacote. */
ok('registro sem codigo PHP aplicavel', !reg.includes('<?php'));
ok('registro sem diff/git apply', !reg.includes('diff --git') && !reg.includes('git apply'));

console.log('CJS AU: ' + C + ' OK / ' + F + ' FALHAS');
process.exit(F ? 1 : 0);
