/* Verificador da evidencia de CI remoto — Frente AT.
   Node puro, sem dependencias, somente leitura.
   Uso: node tools/verify-ci-evidencia.cjs [raiz] */
'use strict';
const fs = require('fs'), path = require('path');
const ROOT = process.argv[2] || process.cwd();
let C = 0, F = 0;
function ok(l, c, x = '') { c ? C++ : F++; console.log((c ? 'OK   ' : 'FAIL ') + l + (x ? ' · ' + x : '')); }
const rd = p => fs.readFileSync(path.join(ROOT, p), 'utf8');
const ex = p => fs.existsSync(path.join(ROOT, p));
const EV = 'evidencia/ci-remoto-bfe9eec7.json';
const HEAD = 'bfe9eec7aae82dc72c7054bcb46dbd901307d7c5';

ok('evidencia JSON presente', ex(EV));
const e = JSON.parse(rd(EV));
ok('repositorio correto', e.repository === 'davidcreator/davidcms', e.repository);
ok('HEAD correto', e.head === HEAD, e.head);
ok('endpoints de leitura publica', e.endpoints.length === 2 && e.endpoints.some(x => x.includes('actions/runs')) && e.endpoints.some(x => x.includes('check-runs')));
ok('observacao sobre o 403', /403/.test(e.auth) && /outro endpoint/.test(e.auth));

/* Run do HEAD atual. */
ok('run do HEAD e 42217429359', e.head_run && e.head_run.id === 42217429359, e.head_run && e.head_run.id);
ok('run do HEAD completed/success', e.head_run.status === 'completed' && e.head_run.conclusion === 'success');
ok('run aponta para o HEAD', e.head_run.head_sha === HEAD);
ok('run e do workflow PHP Composer', e.head_run.name === 'PHP Composer', e.head_run.name);
ok('url do run presente', /actions\/runs\/42217429359/.test(e.head_run.html_url || ''));

/* 7 check-runs, todos success, com os nomes esperados. */
ok('7 check-runs no HEAD', e.head_total_count === 7 && e.head_check_runs.length === 7, e.head_total_count);
ok('todos os check-runs success', e.head_check_runs.every(c => c.status === 'completed' && c.conclusion === 'success'));
ok('nenhum check-run pendente/falho', !e.head_check_runs.some(c => ['failure', 'pending', 'skipped', 'cancelled'].includes(c.conclusion)));
for (const n of ['php-lint', 'static-analysis', 'repo-guards', 'docs-links', 'composer'])
  ok('check presente: ' + n, e.head_check_runs.some(c => c.name === n));
ok('node-deps em 2 versoes', e.head_check_runs.filter(c => c.name.startsWith('node-deps')).length === 2);

/* Commit da AP tambem verde (contexto, nao conclusao de release). */
ok('run da AP e 42212306218', e.previous_ap_run && e.previous_ap_run.id === 42212306218);
ok('AP 7/7 success', e.previous_ap_total_count === 7 && e.previous_ap_check_runs.every(c => c.conclusion === 'success'));

/* Ressalva do contrato antigo preservada. */
ok('contrato antigo citado como runs antigos', /41483802295/.test(e.ci_status_registrado_no_contrato) && /41504039830/.test(e.ci_status_registrado_no_contrato) && /antigos/.test(e.ci_status_registrado_no_contrato));

/* Limites explicitos. */
ok('3 limites registrados', Array.isArray(e.limits) && e.limits.length >= 3, e.limits && e.limits.length);
ok('limite: nao substitui veredito visual', e.limits.some(x => /veredito visual/.test(x)));
ok('limite: nao converte NOT_RUN em PASS', e.limits.some(x => /NOT_RUN/.test(x) && /PASS/.test(x)));
ok('limite: nao significa release aprovada', e.limits.some(x => /release aprovada/.test(x)));
ok('evidencia nao contem release_approved true', !/release_approved["']?\s*[:=]\s*true/.test(rd(EV)));

/* Documentos atualizados no pacote. */
const ROT = 'Roteiro-Proximos-Passos-AP.md', PROT = 'Protocolo-Aceitacao-Visual-AP.md';
ok('roteiro presente', ex(ROT)); ok('protocolo presente', ex(PROT));
const r = rd(ROT), p = rd(PROT);
ok('roteiro cita o HEAD novo', r.includes(HEAD));
ok('roteiro cita o run 42217429359', r.includes('42217429359'));
ok('roteiro registra 7/7 success', /7\/7 success/.test(r));
ok('roteiro mantem passo 1 bloqueante', /bloqueante/.test(r) && /veredito visual/.test(r));
ok('protocolo cita a aplicacao da AQ', p.includes('AQ') && p.includes('10/10'));
ok('protocolo registra CI verificado', p.includes('42217429359'));
ok('protocolo mantem veredito pendente', /aguardando veredito visual/i.test(p));
ok('protocolo nao afirma release', /n[ãa]o\s+significam?\s+release\s+aprovada/i.test(p));
ok('registro CI presente', ex('docs/Registro-CI-Remoto-HEAD-bfe9eec7.md'));
ok('registro CI sem codigo aplicavel', !rd('docs/Registro-CI-Remoto-HEAD-bfe9eec7.md').includes('<?php'));

console.log('CJS AT: ' + C + ' OK / ' + F + ' FALHAS');
process.exit(F ? 1 : 0);
