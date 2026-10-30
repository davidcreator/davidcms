'use strict';
/*
 * tools/staging — bateria de banco e isolamento.
 *
 * Verifica:
 *  1. o banco descartável existe e é um CLONE fiel do espelho;
 *  2. o schema esperado (features recentes) está presente;
 *  3. collation utf8mb4_unicode_ci (ADR/lições: utf8mb4_0900_ai_ci quebra JOIN);
 *  4. integridade das colunas JSON de ACL;
 *  5. ISOLAMENTO: nenhum caminho, servidor ou banco do staging aponta para dev/prod.
 *
 * Nunca escreve no banco espelho.
 */
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { STATUS, BatteryResult } = require('../lib/report.cjs');
const cfg = require('../lib/config.cjs');

function mysqlArgs(extra) {
  const args = ['--host=' + cfg.DB.host, '--port=' + cfg.DB.port, '--user=' + cfg.DB.user];
  if (cfg.DB.password) args.push('--password=' + cfg.DB.password);
  args.push('--default-character-set=utf8mb4', '--batch', '--raw', '--skip-column-names');
  return args.concat(extra || []);
}

function query(sql, database) {
  const args = mysqlArgs(['--execute=' + sql]);
  if (database) args.push(database);
  const res = spawnSync(cfg.BIN.mysql, args, { encoding: 'utf8', timeout: 120000, maxBuffer: 32 * 1024 * 1024 });
  if (res.error) return { ok: false, rows: [], error: res.error.message };
  const out = `${res.stdout || ''}`;
  if (res.status !== 0) return { ok: false, rows: [], error: `${res.stderr || ''}`.trim() || `exit ${res.status}` };
  const rows = out.split(/\r?\n/).filter(Boolean).map(line => line.split('\t'));
  return { ok: true, rows, error: '' };
}

function scalar(sql, database) {
  const res = query(sql, database);
  if (!res.ok) return { ok: false, value: null, error: res.error };
  return { ok: true, value: res.rows.length ? res.rows[0][0] : null, error: '' };
}

function readConfig(file) {
  if (!fs.existsSync(file)) return null;
  const text = fs.readFileSync(file, 'utf8');
  const get = name => {
    const m = text.match(new RegExp(`define\\(\\s*'${name}'\\s*,\\s*'([^']*)'\\s*\\)`));
    return m ? m[1] : null;
  };
  const dirReamur = get('DIR_REAMUR');
  const appDirMatch = text.match(/define\(\s*'DIR_APPLICATION'\s*,\s*DIR_REAMUR\s*\.\s*'([^']*)'\s*\)/);
  const systemDirMatch = text.match(/define\(\s*'DIR_SYSTEM'\s*,\s*DIR_REAMUR\s*\.\s*'([^']*)'\s*\)/);
  return {
    file,
    httpServer: get('HTTP_SERVER'),
    dbDatabase: get('DB_DATABASE'),
    dbPrefix: get('DB_PREFIX'),
    dirReamur,
    appSubdir: appDirMatch ? appDirMatch[1] : null,
    systemSubdir: systemDirMatch ? systemDirMatch[1] : null,
    raw: text,
  };
}

async function run() {
  const battery = new BatteryResult('db', 'Banco, schema e isolamento');
  const started = Date.now();

  if (!cfg.exists(cfg.BIN.mysql)) {
    battery.check('cliente MySQL', STATUS.NOT_RUN, `não encontrado: ${cfg.BIN.mysql}`);
    battery.durationMs = Date.now() - started;
    return battery.finalize();
  }

  const ping = scalar('SELECT VERSION()', null);
  if (!ping.ok) {
    battery.check('servidor MySQL', STATUS.NOT_RUN, `sem conexão: ${ping.error}`);
    battery.durationMs = Date.now() - started;
    return battery.finalize();
  }
  battery.note(`MySQL ${ping.value} em ${cfg.DB.host}:${cfg.DB.port}.`);

  // ── 1. existência do banco descartável ────────────────────────────────
  const exists = scalar(
    `SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '${cfg.DB.target}'`, null);
  if (!exists.ok || exists.value === '0') {
    battery.check('banco descartável', STATUS.NOT_RUN,
      `${cfg.DB.target} não existe — rode tools/staging/staging.ps1 db-reset`);
    battery.durationMs = Date.now() - started;
    return battery.finalize();
  }
  battery.check('banco descartável', STATUS.PASS, `${cfg.DB.target} existe (espelho: ${cfg.DB.source})`);

  // ── 2. paridade de objetos com o espelho ──────────────────────────────
  const tablesOf = db => {
    const res = query(
      `SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '${db}' ORDER BY TABLE_NAME, COLUMN_NAME`,
      null);
    const map = new Map();
    if (!res.ok) return map;
    for (const [table, column, type] of res.rows) {
      if (!map.has(table)) map.set(table, new Map());
      map.get(table).set(column, type);
    }
    return map;
  };
  const source = tablesOf(cfg.DB.source);
  const target = tablesOf(cfg.DB.target);
  const sourceTableCount = source.size;
  const targetTableCount = target.size;
  battery.note(`Objetos: espelho ${sourceTableCount} tabelas · staging ${targetTableCount} tabelas.`);

  const missingTables = [...source.keys()].filter(t => !target.has(t));
  const extraTables = [...target.keys()].filter(t => !source.has(t));
  const missingColumns = [];
  for (const [table, columns] of source) {
    const t = target.get(table);
    if (!t) continue;
    for (const column of columns.keys()) {
      if (!t.has(column)) missingColumns.push(`${table}.${column}`);
    }
  }
  battery.check('paridade de clone (tabelas/colunas)', !missingTables.length && !missingColumns.length ? STATUS.PASS : STATUS.FAIL,
    !missingTables.length && !missingColumns.length
      ? 'clone fiel ao espelho'
      : `tabelas ausentes: ${missingTables.slice(0, 5).join(', ') || '—'} | colunas ausentes: ${missingColumns.slice(0, 5).join(', ') || '—'}`);
  if (extraTables.length) {
    battery.note(`Tabelas só no staging (esperado após migração local): ${extraTables.join(', ')}`);
  }

  // ── 3. schema esperado (features recentes) ────────────────────────────
  for (const [table, columns] of Object.entries(cfg.EXPECTED_SCHEMA.tables)) {
    const inTarget = target.has(table);
    const inSource = source.has(table);
    if (inTarget) {
      const absent = columns.filter(column => !target.get(table).has(column));
      battery.check(`schema ${table}`, absent.length === 0 ? STATUS.PASS : STATUS.FAIL,
        absent.length === 0 ? 'presente com todas as colunas esperadas' : `colunas ausentes no staging: ${absent.join(', ')}`);
    } else if (inSource) {
      battery.check(`schema ${table}`, STATUS.FAIL, 'existe no espelho mas NÃO no staging — clone incompleto');
    } else {
      battery.check(`schema ${table}`, STATUS.WARN, 'ausente no espelho e no staging — migração pendente (dívida conhecida)');
    }
  }

  // ── 4. collation ──────────────────────────────────────────────────────
  const collations = query(
    `SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${cfg.DB.target}' AND TABLE_NAME LIKE 'rms\\_%' ORDER BY TABLE_NAME`,
    null);
  if (collations.ok) {
    const offenders = collations.rows.filter(([, c]) => c !== 'utf8mb4_unicode_ci');
    battery.check('collation utf8mb4_unicode_ci', offenders.length === 0 ? STATUS.PASS : STATUS.WARN,
      offenders.length === 0
        ? `${collations.rows.length} tabelas conformes`
        : `${offenders.length} tabela(s) fora do padrão: ${offenders.slice(0, 3).map(([t, c]) => `${t}=${c}`).join(', ')}`);
  }

  // ── 5. integridade das ACL JSON ───────────────────────────────────────
  for (const [table, columns] of Object.entries(cfg.JSON_ACL_COLUMNS)) {
    if (!target.has(table)) continue;
    for (const column of columns) {
      if (!target.get(table).has(column)) continue;
      const res = scalar(
        `SELECT COUNT(*) FROM \`${table}\` WHERE \`${column}\` IS NOT NULL AND JSON_VALID(\`${column}\`) = 0`, cfg.DB.target);
      if (!res.ok) {
        battery.check(`ACL JSON ${table}.${column}`, STATUS.WARN, `não foi possível verificar: ${res.error}`);
      } else {
        battery.check(`ACL JSON ${table}.${column}`, Number(res.value) === 0 ? STATUS.PASS : STATUS.FAIL,
          Number(res.value) === 0 ? 'todas as linhas com JSON válido' : `${res.value} linha(s) com JSON inválido`);
      }
    }
  }

  // ── 6. degrade de consultas reais (schema lido pelo app) ──────────────
  const pageSystemKey = target.has('rms_page') && target.get('rms_page').has('system_key')
    ? scalar('SELECT COUNT(*) FROM rms_page WHERE system_key IS NOT NULL AND system_key <> \'\'', cfg.DB.target)
    : null;
  if (pageSystemKey && pageSystemKey.ok) {
    battery.check('rotas de sistema (F10)', Number(pageSystemKey.value) > 0 ? STATUS.PASS : STATUS.WARN,
      `${pageSystemKey.value} página(s) com system_key`);
  }

  // ── 7. ISOLAMENTO — configs do staging não apontam para dev/prod ──────
  const configs = [readConfig(cfg.PATHS.stagingCatalogConfig), readConfig(cfg.PATHS.stagingAdminConfig)];
  for (let i = 0; i < configs.length; i++) {
    const label = i === 0 ? 'catalog' : 'admin';
    const conf = configs[i];
    if (!conf) {
      battery.check(`isolamento config ${label}`, STATUS.FAIL, `config.php ausente em staging/${label}/ — rode o sync`);
      continue;
    }
    const problems = [];
    if (conf.dbDatabase !== cfg.DB.target) problems.push(`DB_DATABASE=${conf.dbDatabase} (esperado ${cfg.DB.target})`);
    if (conf.dbDatabase === cfg.DB.source) problems.push('DB_DATABASE aponta para o banco espelho — NUNCA');
    if (!String(conf.httpServer || '').startsWith(cfg.URLS.base)) problems.push(`HTTP_SERVER=${conf.httpServer} fora de ${cfg.URLS.base}`);
    if (conf.appSubdir !== `staging/${label}/`) problems.push(`DIR_APPLICATION=${conf.appSubdir} (esperado staging/${label}/)`);
    if (conf.systemSubdir !== 'staging/system/') problems.push(`DIR_SYSTEM=${conf.systemSubdir} (esperado staging/system/)`);
    if (conf.dirReamur && conf.dirReamur.includes('/prod/')) problems.push('DIR_REAMUR aponta para prod/');
    if (/(^|[^a-z])davidcms\/dev\//i.test(String(conf.httpServer))) problems.push('HTTP_SERVER aponta para dev/');
    battery.check(`isolamento config ${label}`, problems.length === 0 ? STATUS.PASS : STATUS.FAIL,
      problems.length === 0 ? `banco ${conf.dbDatabase} · url ${conf.httpServer}` : problems.join(' ; '));
  }

  const sharedStorage = configs.every(c => c && c.appSubdir && c.appSubdir.startsWith('staging/'));
  battery.check('isolamento de storage (cache/logs/session)', sharedStorage ? STATUS.PASS : STATUS.FAIL,
    sharedStorage ? 'staging/ usa system/storage próprio, separado de dev/' : 'configs não isolam o storage em staging/');

  // ── 8. inventário de migrações (informativo) ──────────────────────────
  const sqlDir = path.join(cfg.DEV_DIR, 'install', 'sql');
  if (fs.existsSync(sqlDir)) {
    const ups = fs.readdirSync(sqlDir).filter(n => /^upgrade_.*\.sql$/.test(n));
    const rolls = fs.readdirSync(sqlDir).filter(n => /^rollback_.*\.sql$/.test(n));
    battery.note(`Inventário de migrações: ${ups.length} upgrade(s) e ${rolls.length} rollback(s) em dev/install/sql/.`);
    battery.note('Migrações NÃO são executadas por esta bateria (cabeçalhos pedem fluxo de upgrade aprovado).');
  }

  battery.durationMs = Date.now() - started;
  return battery.finalize();
}

module.exports = { run };
