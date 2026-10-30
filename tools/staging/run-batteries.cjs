#!/usr/bin/env node
'use strict';
/*
 * tools/staging — orquestrador das baterias de teste do ambiente descartável.
 *
 * Uso:
 *   node tools/staging/run-batteries.cjs                 # roda todas
 *   node tools/staging/run-batteries.cjs --only=static   # roda uma (ou lista com vírgula)
 *   node tools/staging/run-batteries.cjs --update-baseline
 *   node tools/staging/run-batteries.cjs --list
 *
 * Saída: staging/reports/batteries-<timestamp>.{json,md} + latest.{json,md}
 * Código de saída: 0 = nenhuma bateria FAIL · 1 = houve FAIL
 */
const fs = require('node:fs');
const path = require('node:path');
const cfg = require('./lib/config.cjs');
const report = require('./lib/report.cjs');

const BATTERIES = [
  { id: 'static', title: 'Checkers estáticos de contrato', module: './batteries/static.cjs' },
  { id: 'php', title: 'PHP lint + PHPStan', module: './batteries/php.cjs' },
  { id: 'db', title: 'Banco, schema e isolamento', module: './batteries/db.cjs' },
  { id: 'http', title: 'Servidor de staging e isolamento público', module: './batteries/http.cjs' },
  { id: 'e2e', title: 'E2E em navegador real (Playwright)', module: './batteries/e2e.cjs' },
];

function parseArgs(argv) {
  const options = { only: null, updateBaseline: false, list: false };
  for (const arg of argv.slice(2)) {
    if (arg === '--update-baseline') options.updateBaseline = true;
    else if (arg === '--list') options.list = true;
    else if (arg.startsWith('--only=')) {
      options.only = arg.slice('--only='.length).split(',').map(s => s.trim()).filter(Boolean);
    } else if (arg === '--help' || arg === '-h') {
      console.log('Uso: node tools/staging/run-batteries.cjs [--only=a,b] [--update-baseline] [--list]');
      process.exit(0);
    } else {
      console.error(`Argumento desconhecido: ${arg}`);
      process.exit(2);
    }
  }
  return options;
}

function preflight() {
  const problems = [];
  if (!fs.existsSync(cfg.ROOT)) problems.push(`raiz do repositório não encontrada em ${cfg.ROOT}`);
  if (!fs.existsSync(cfg.STAGING_DIR)) problems.push('staging/ não existe — rode: powershell -File tools/staging/staging.ps1 sync');
  if (!fs.existsSync(cfg.PATHS.stagingCatalogConfig)) problems.push('staging/catalog/config.php ausente — rode o sync');
  if (!fs.existsSync(cfg.PATHS.stagingAdminConfig)) problems.push('staging/admin/config.php ausente — rode o sync');
  for (const tool of ['php', 'mysql']) {
    if (!fs.existsSync(cfg.BIN[tool])) problems.push(`${tool} não encontrado em ${cfg.BIN[tool]}`);
  }
  return problems;
}

async function main() {
  const options = parseArgs(process.argv);

  if (options.list) {
    for (const b of BATTERIES) console.log(`${b.id.padEnd(8)} ${b.title}`);
    return 0;
  }

  const problems = preflight();
  if (problems.length) {
    console.error('Pré-requisitos pendentes:');
    for (const p of problems) console.error(` - ${p}`);
    console.error('\nRode primeiro:  powershell -ExecutionPolicy Bypass -File tools/staging/staging.ps1 all');
    return 2;
  }

  const selected = options.only ? BATTERIES.filter(b => options.only.includes(b.id)) : BATTERIES;
  if (!selected.length) {
    console.error(`Nenhuma bateria corresponde a --only=${options.only.join(',')}. Use --list.`);
    return 2;
  }

  const result = {
    meta: {
      root: cfg.ROOT,
      stagingDir: cfg.STAGING_DIR,
      dbSource: cfg.DB.source,
      dbTarget: cfg.DB.target,
      urls: { base: cfg.URLS.base, catalog: cfg.URLS.catalog, admin: cfg.URLS.admin },
      node: process.version,
      selected: selected.map(b => b.id),
    },
    startedAt: new Date().toISOString(),
    finishedAt: null,
    batteries: [],
  };

  const ctx = { updateBaseline: options.updateBaseline, config: cfg };

  console.log(`\n=== Baterias de staging (${selected.map(b => b.id).join(', ')}) ===`);
  for (const meta of selected) {
    const started = Date.now();
    process.stdout.write(`→ ${meta.id.padEnd(8)} … `);
    let battery;
    try {
      // eslint-disable-next-line global-require
      const mod = require(meta.module);
      battery = await mod.run(ctx);
    } catch (error) {
      battery = new report.BatteryResult(meta.id, meta.title);
      battery.check('execução da bateria', report.STATUS.FAIL, `${error.message}`);
      battery.durationMs = Date.now() - started;
      battery.finalize();
    }
    console.log(battery.status);
    result.batteries.push(battery);
  }

  result.finishedAt = new Date().toISOString();
  const files = report.write(result, cfg.REPORTS_DIR);
  const summary = report.summarize(result);

  console.log('\n--- Resumo ---');
  for (const b of result.batteries) {
    const c = b.counts;
    console.log(`${b.status.padEnd(8)} ${b.id.padEnd(8)} PASS=${c.PASS} FAIL=${c.FAIL} WARN=${c.WARN} NOT_RUN=${c.NOT_RUN} (${b.durationMs} ms)`);
  }
  console.log(`\nGlobal: ${summary.overall}`);
  console.log(`Relatório: ${path.relative(cfg.ROOT, files.md)}`);
  console.log(`JSON:      ${path.relative(cfg.ROOT, files.json)}`);

  const failures = [];
  for (const b of result.batteries) for (const c of b.checks) if (c.status === report.STATUS.FAIL) failures.push(`${b.id}/${c.name}: ${c.detail}`);
  if (failures.length) {
    console.log('\nFalhas:');
    for (const f of failures) console.log(` - ${f}`);
  }

  return summary.batteries.FAIL > 0 ? 1 : 0;
}

main()
  .then(code => process.exit(code))
  .catch(error => { console.error(error.stack || error.message); process.exit(2); });
