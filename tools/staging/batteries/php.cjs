'use strict';
/*
 * tools/staging — bateria PHP: lint sintático + análise estática (PHPStan).
 *
 * Alvo: o CONTEÚDO COPIADO em staging/ (é o que o servidor de staging executa).
 * Não sobe o servidor web. Só usa os binários locais do WAMP.
 */
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { STATUS, BatteryResult } = require('../lib/report.cjs');
const cfg = require('../lib/config.cjs');

const SKIP_DIRS = new Set(['vendor', 'node_modules', '.git']);

function collectPhpFiles(dir, acc) {
  let entries;
  try { entries = fs.readdirSync(dir, { withFileTypes: true }); } catch (_) { return acc; }
  for (const entry of entries) {
    if (entry.isDirectory()) {
      if (SKIP_DIRS.has(entry.name)) continue;
      collectPhpFiles(path.join(dir, entry.name), acc);
    } else if (entry.isFile() && entry.name.endsWith('.php')) {
      acc.push(path.join(dir, entry.name));
    }
  }
  return acc;
}

function lint(files) {
  const errors = [];
  for (const file of files) {
    const res = spawnSync(cfg.BIN.php, ['-l', file], { encoding: 'utf8', timeout: 30000 });
    if (res.error) errors.push({ file, message: `falha ao invocar PHP: ${res.error.message}` });
    else if (res.status !== 0) errors.push({ file, message: `${res.stdout || ''}${res.stderr || ''}`.trim().split(/\r?\n/)[0] });
  }
  return errors;
}

function phpstan() {
  if (!cfg.exists(cfg.PATHS.phpstanPhar)) return { status: STATUS.NOT_RUN, detail: `phpstan.phar ausente em ${cfg.PATHS.phpstanPhar}` };
  if (!cfg.exists(cfg.PATHS.phpstanConfig)) return { status: STATUS.NOT_RUN, detail: `phpstan.neon ausente em ${cfg.PATHS.phpstanConfig}` };
  const res = spawnSync(cfg.BIN.php, [
    cfg.PATHS.phpstanPhar,
    'analyse',
    '--configuration', cfg.PATHS.phpstanConfig,
    '--no-progress',
    '--error-format=json',
    '--memory-limit=1G',
  ], {
    cwd: path.dirname(cfg.PATHS.phpstanConfig),
    encoding: 'utf8',
    timeout: Number(process.env.STAGING_PHPSTAN_TIMEOUT_MS || 600000),
    maxBuffer: 64 * 1024 * 1024,
  });
  const raw = `${res.stdout || ''}${res.stderr || ''}`.trim();
  let parsed = null;
  const jsonStart = raw.indexOf('{');
  if (jsonStart >= 0) {
    try { parsed = JSON.parse(raw.slice(jsonStart)); } catch (_) { parsed = null; }
  }
  if (res.error && res.error.code === 'ETIMEDOUT') return { status: STATUS.NOT_RUN, detail: 'phpstan excedeu o tempo limite' };
  if (!parsed) {
    return res.status === 0
      ? { status: STATUS.PASS, detail: 'sem erros (saída sem JSON)' }
      : { status: STATUS.FAIL, detail: `phpstan saiu com ${res.status}: ${raw.split(/\r?\n/).filter(Boolean).slice(-3).join(' ')}` };
  }
  const total = Number(parsed.totals && parsed.totals.file_errors || 0);
  const firstMessages = (parsed.files && Object.values(parsed.files) || [])
    .flatMap(f => (f.messages || []).map(m => `${path.relative(cfg.STAGING_DIR, f.file || '')}:${m.line} ${m.message}`))
    .slice(0, 3);
  return {
    status: total === 0 ? STATUS.PASS : STATUS.FAIL,
    detail: total === 0
      ? 'nenhum erro'
      : `${total} arquivo(s) com erro. ${firstMessages.join(' | ')}`,
  };
}

async function run() {
  const battery = new BatteryResult('php', 'PHP lint + PHPStan');
  const started = Date.now();

  if (!cfg.exists(cfg.BIN.php)) {
    battery.check('binário PHP', STATUS.NOT_RUN, `não encontrado: ${cfg.BIN.php}`);
    battery.durationMs = Date.now() - started;
    return battery.finalize();
  }
  if (!cfg.exists(cfg.STAGING_DIR)) {
    battery.check('ambiente staging', STATUS.NOT_RUN, 'staging/ não existe — rode tools/staging/staging.ps1 sync');
    battery.durationMs = Date.now() - started;
    return battery.finalize();
  }

  const files = collectPhpFiles(cfg.STAGING_DIR, []);
  battery.note(`${files.length} arquivos PHP analisados em staging/ (exclui vendor/, node_modules/).`);

  const errors = lint(files);
  battery.check('php -l (lint sintático)', errors.length === 0 ? STATUS.PASS : STATUS.FAIL,
    errors.length === 0
      ? `${files.length} arquivo(s) sem erro de sintaxe`
      : `${errors.length} arquivo(s) com erro: ${errors.slice(0, 3).map(e => `${path.relative(cfg.STAGING_DIR, e.file)} → ${e.message}`).join(' | ')}`);

  const analysis = phpstan();
  battery.check('phpstan (nível 2, config do projeto)', analysis.status, analysis.detail);

  battery.durationMs = Date.now() - started;
  return battery.finalize();
}

module.exports = { run, collectPhpFiles };
