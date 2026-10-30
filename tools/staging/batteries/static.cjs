'use strict';
/*
 * tools/staging — executor da bateria de checkers estáticos.
 *
 * Roda os checkers de contrato do repositório (tools/e2e/*.cjs e tests/check_*.js)
 * contra a raiz do clone. Eles são checagens de FONTE: não sobem PHP, banco,
 * navegador nem servidor.
 *
 * Convenção de saída dos checkers (uniformizada por leitura de cada um):
 *   exit 0 → PASS
 *   exit 2 → SKIP (falta argumento/ambiente — o próprio checker diz o uso)
 *   outro  → FAIL
 *
 * Muitos checkers são "checagens históricas de feature" e já falham no HEAD
 * atual por dívida. Por isso existe um BASELINE: falha registrada no baseline
 * vale WARN (dívida conhecida); falha NOVA vale FAIL (regressão).
 * Atualize o baseline com: node tools/staging/run-batteries.cjs --update-baseline
 */
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { STATUS, BatteryResult } = require('../lib/report.cjs');
const cfg = require('../lib/config.cjs');

const PER_CHECKER_TIMEOUT_MS = Number(process.env.STAGING_STATIC_TIMEOUT_MS || 90000);

function discover() {
  const files = [];
  const push = (dir, filter) => {
    if (!fs.existsSync(dir)) return;
    for (const name of fs.readdirSync(dir).sort()) {
      if (!filter(name)) continue;
      files.push(path.join(dir, name));
    }
  };
  push(cfg.TOOLS_E2E_DIR, n => n.endsWith('.cjs'));
  push(cfg.TESTS_DIR, n => /^check_.*\.js$/.test(n));
  return files;
}

function runChecker(file) {
  const args = [file, cfg.ROOT];
  const started = Date.now();
  const res = spawnSync(process.execPath, args, {
    cwd: cfg.ROOT,
    encoding: 'utf8',
    timeout: PER_CHECKER_TIMEOUT_MS,
    maxBuffer: 8 * 1024 * 1024,
  });
  const durationMs = Date.now() - started;
  const code = res.error && res.error.code === 'ETIMEDOUT' ? 'TIMEOUT' : res.status;
  const output = `${res.stdout || ''}${res.stderr || ''}`.trim();
  const lastLine = output.split(/\r?\n/).filter(Boolean).pop() || '';
  let status;
  if (code === 0) status = STATUS.PASS;
  else if (code === 2) status = STATUS.WARN; // falta argumento/ambiente — declarado
  else status = STATUS.FAIL;
  return { file: path.basename(file), status, code, durationMs, lastLine };
}

function loadBaseline() {
  if (!fs.existsSync(cfg.PATHS.staticBaseline)) return null;
  try {
    const data = JSON.parse(fs.readFileSync(cfg.PATHS.staticBaseline, 'utf8'));
    return new Set(Array.isArray(data.knownFailures) ? data.knownFailures : []);
  } catch (_) {
    return null;
  }
}

function saveBaseline(failures) {
  fs.mkdirSync(cfg.BASELINES_DIR, { recursive: true });
  const payload = {
    generatedAt: new Date().toISOString(),
    root: cfg.ROOT,
    note: 'Falhas de checkers estáticos já conhecidas na linha de base. Falha fora desta lista = regressão.',
    knownFailures: [...failures].sort(),
  };
  fs.writeFileSync(cfg.PATHS.staticBaseline, JSON.stringify(payload, null, 2), 'utf8');
}

async function run(ctx) {
  const battery = new BatteryResult('static', 'Checkers estáticos de contrato');
  const started = Date.now();
  const checkers = discover();
  if (!checkers.length) {
    battery.note('Nenhum checker encontrado em tools/e2e/*.cjs ou tests/check_*.js.');
    battery.durationMs = Date.now() - started;
    return battery.finalize();
  }

  battery.note(`${checkers.length} checkers descobertos. Executados contra ${cfg.ROOT}. Nenhum PHP/banco/navegador é iniciado.`);

  const results = checkers.map(runChecker);
  const failed = results.filter(r => r.status === STATUS.FAIL);
  const skipped = results.filter(r => r.status === STATUS.WARN);
  const passed = results.filter(r => r.status === STATUS.PASS);

  if (ctx.updateBaseline) {
    saveBaseline(failed.map(r => r.file));
    battery.note(`Baseline atualizado com ${failed.length} falha(s) conhecida(s) em ${path.relative(cfg.ROOT, cfg.PATHS.staticBaseline)}.`);
  }

  const baseline = loadBaseline();
  if (!baseline) {
    battery.note('Baseline ausente: cada falha é reportada como FAIL. Gere o baseline com --update-baseline.');
  } else {
    battery.note(`Baseline com ${baseline.size} falha(s) conhecida(s).`);
  }

  let newFailures = 0;
  let knownFailures = 0;
  for (const r of failed) {
    const known = baseline ? baseline.has(r.file) : false;
    if (known) {
      knownFailures += 1;
      battery.check(r.file, STATUS.WARN, `falha conhecida no baseline — ${r.lastLine}`);
    } else {
      newFailures += 1;
      battery.check(r.file, STATUS.FAIL, `${r.code === 'TIMEOUT' ? 'timeout' : 'exit ' + r.code} — ${r.lastLine}`);
    }
  }
  for (const r of skipped) {
    battery.check(r.file, STATUS.WARN, 'pulado: o checker exige argumento/ambiente próprio');
  }
  battery.check('resumo', newFailures > 0 ? STATUS.FAIL : STATUS.PASS,
    `${passed.length} PASS · ${knownFailures} falha(s) conhecida(s) · ${newFailures} regressão(ões) · ${skipped.length} pularam`);

  battery.note('Checagens históricas de feature: falha no baseline é dívida, não regressão desta bateria.');
  battery.durationMs = Date.now() - started;
  return battery.finalize();
}

module.exports = { run, discover };
