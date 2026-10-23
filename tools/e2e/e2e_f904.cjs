#!/usr/bin/env node
'use strict';

/**
 * F9-04 — gate estático de dry-run do legado davidalmeida.xyz.
 * Não executa SQL; valida contrato de fonte privada, inventário sanitizado e mapa.
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

function usage() {
  console.error('Uso: node e2e_f904.cjs --check [--inventory docs/F9-03-Inventario-Banco-DavidAlmeida.json] [--map docs/F9-04-Mapa-Migracao-DavidAlmeida.json] [--sql /caminho/privado/bd-davidalmeida.sql]');
  process.exit(2);
}
function fail(msg) { console.error(`FAIL F9-04: ${msg}`); process.exit(1); }
function ok(msg) { console.log(`PASS F9-04: ${msg}`); }

const args = process.argv.slice(2);
let check = false;
let inventoryPath = '';
let mapPath = '';
let sqlPath = '';
for (let i = 0; i < args.length; i++) {
  if (args[i] === '--check') check = true;
  else if (args[i] === '--inventory') inventoryPath = args[++i] || '';
  else if (args[i] === '--map') mapPath = args[++i] || '';
  else if (args[i] === '--sql') sqlPath = args[++i] || '';
  else usage();
}
if (!check) usage();

const root = path.resolve(__dirname, '..', '..');
if (!inventoryPath) inventoryPath = path.resolve(root, 'docs', 'F9-03-Inventario-Banco-DavidAlmeida.json');
else if (!path.isAbsolute(inventoryPath)) inventoryPath = path.resolve(root, inventoryPath);
if (!mapPath) mapPath = path.resolve(root, 'docs', 'F9-04-Mapa-Migracao-DavidAlmeida.json');
else if (!path.isAbsolute(mapPath)) mapPath = path.resolve(root, mapPath);
if (!fs.existsSync(inventoryPath)) fail(`inventário F9-03 ausente: ${path.relative(root, inventoryPath)}`);
if (!fs.existsSync(mapPath)) fail(`mapa F9-04 ausente: ${path.relative(root, mapPath)}`);

const inventory = JSON.parse(fs.readFileSync(inventoryPath, 'utf8'));
const map = JSON.parse(fs.readFileSync(mapPath, 'utf8'));
const publicDump = path.resolve(root, 'dev', 'install', 'sql', 'bd-davidalmeida.sql');
if (fs.existsSync(publicDump)) fail('dump real ainda está presente/versionado no repositório');

if (inventory.task !== 'F9-03') fail('inventário não pertence à F9-03');
if (map.task !== 'F9-04' || map.kind !== 'migration_dry_run_contract') fail('mapa F9-04 inválido');
if (map.scope?.included_site !== 'davidalmeida.xyz') fail('escopo incluído incorreto');
if (!Array.isArray(map.scope?.excluded_sites) || !map.scope.excluded_sites.includes('davidcreator.com')) fail('davidcreator.com precisa continuar fora do escopo');
if (map.scope?.blog_import_forbidden !== true) fail('proibição de importar blog externo ausente');
if (map.source_contract?.repository_policy !== 'never_commit_sql_dump') fail('política de nunca comitar dump ausente');
if (map.source_contract?.public_repository_absent_required !== true) fail('gate exige ausência pública do dump');
if (map.source_contract?.last_observed_sha256 !== inventory.source?.sha256) fail('hash de referência não confere com inventário sanitizado');
if (Number(map.source_contract?.last_observed_bytes) !== Number(inventory.source?.bytes)) fail('bytes de referência não conferem');
if (map.source_contract?.last_observed_inventory !== 'docs/F9-03-Inventario-Banco-DavidAlmeida.json') fail('referência de inventário F9-03 incorreta');

const requiredBooleans = ['no_sql_execution', 'dry_run_first', 'counts_only_logging', 'no_row_values_in_reports', 'credentials_must_be_rotated_before_import', 'backup_required_before_any_apply', 'requires_new_approval_for_new_data'];
for (const key of requiredBooleans) {
  if (map.global_rules?.[key] !== true) fail(`regra global obrigatória ausente: ${key}`);
}
if ((inventory.summary?.tables || 0) !== 18) fail('inventário F9-03 deveria registrar 18 tabelas');
if ((inventory.summary?.insert_statements || 0) !== 16 || (inventory.summary?.estimated_rows || 0) !== 909) fail('contagens F9-03 divergentes');

const inventoryNames = (inventory.tables || []).map(t => t.name);
const mapNames = (map.tables || []).map(t => t.source_table);
if (inventoryNames.length !== 18) fail('inventário não tem 18 entradas');
if (new Set(inventoryNames).size !== inventoryNames.length) fail('inventário tem tabela duplicada');
if (new Set(mapNames).size !== mapNames.length) fail('mapa tem tabela duplicada');
if (JSON.stringify([...mapNames].sort()) !== JSON.stringify([...inventoryNames].sort())) fail('mapa não cobre exatamente as 18 tabelas do inventário');

const safeDecisions = new Set(['archive_only', 'already_migrated_f3', 'excluded_prior_decision', 'out_of_scope_course_module', 'sensitive_out_of_scope', 'whitelist_future_review', 'no_rows_already_curated_f3', 'analytics_archive_only']);
for (const item of map.tables) {
  if (!safeDecisions.has(item.decision)) fail(`decisão insegura ou desconhecida para ${item.source_table}`);
  if (item.action_now !== 'none') fail(`ação não permitida nesta tarefa para ${item.source_table}`);
  const text = JSON.stringify(item);
  if (/import_now|direct_import|apply_to_production/i.test(text)) fail(`ação destrutiva/direta detectada para ${item.source_table}`);
}

if (sqlPath) {
  if (!path.isAbsolute(sqlPath)) sqlPath = path.resolve(root, sqlPath);
  if (!fs.existsSync(sqlPath)) fail('arquivo privado informado não existe');
  const sha = crypto.createHash('sha256').update(fs.readFileSync(sqlPath)).digest('hex');
  if (sha !== map.source_contract.last_observed_sha256) {
    fail('hash do dump privado difere do último inventário aprovado; nova auditoria F9-03 obrigatória');
  }
  ok('dump privado confere com o hash auditado; lembre-se de rotacionar credenciais');
}

ok(`gate aprovado; tabelas_mapeadas=${map.tables.length}; dump público ausente`);
