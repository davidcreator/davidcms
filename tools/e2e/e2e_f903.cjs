#!/usr/bin/env node
'use strict';

/**
 * F9-03 — auditoria E2E estática do dump davidalmeida.xyz.
 * Lê SQL como texto; nunca conecta, executa ou imprime valores do dump.
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

function usage() {
  console.error('Uso: node e2e_f903.cjs --check [--sql <arquivo>] [--inventory <json>] [--fixture <sql>]');
  process.exit(2);
}
function fail(msg) { console.error(`FAIL F9-03: ${msg}`); process.exit(1); }
function ok(msg) { console.log(`PASS F9-03: ${msg}`); }

const args = process.argv.slice(2);
let check = false;
let sqlPath = '';
let inventoryPath = '';
let fixturePath = path.join(__dirname, 'fixtures', 'f903_minimal.sql');
for (let i = 0; i < args.length; i++) {
  if (args[i] === '--check') check = true;
  else if (args[i] === '--sql') sqlPath = args[++i] || '';
  else if (args[i] === '--inventory') inventoryPath = args[++i] || '';
  else if (args[i] === '--fixture') fixturePath = args[++i] || '';
  else usage();
}
if (!check) usage();
const root = path.resolve(__dirname, '..', '..');
if (!sqlPath) sqlPath = fixturePath;
if (!path.isAbsolute(sqlPath)) sqlPath = path.resolve(root, sqlPath);
if (!fs.existsSync(sqlPath)) fail(`arquivo SQL não encontrado: ${path.relative(root, sqlPath)}`);
const sql = fs.readFileSync(sqlPath, 'utf8');

function parseSql(text) {
  const tables = [];
  const createRe = /CREATE TABLE\s+`([^`]+)`\s*\(([\s\S]*?)\)\s*ENGINE=([^\s;]+)\s+DEFAULT CHARSET=([^\s;]+)(?:\s+COLLATE=([^\s;]+))?;/gi;
  let m;
  while ((m = createRe.exec(text))) {
    const columns = [];
    const columnTypes = {};
    for (const rawLine of m[2].split(/\r?\n/)) {
      const line = rawLine.trim().replace(/,$/, '');
      const q = line.match(/^`([^`]+)`\s+(.+)$/);
      if (!q) continue;
      const name = q[1];
      if (/^(PRIMARY|UNIQUE|KEY|CONSTRAINT|FOREIGN)$/i.test(name)) continue;
      columns.push(name);
      columnTypes[name] = q[2].replace(/\s+/g, ' ');
    }
    tables.push({ name: m[1], engine: m[3], charset: m[4], collation: m[5] || null, columns, columnTypes });
  }
  const insertStatements = {};
  const estimatedRows = {};
  const insRe = /INSERT INTO\s+`([^`]+)`[^\n]*VALUES\s*\n/gi;
  let firstInsertPosition = {};
  while ((m = insRe.exec(text))) {
    const name = m[1];
    insertStatements[name] = (insertStatements[name] || 0) + 1;
    if (!(name in firstInsertPosition)) firstInsertPosition[name] = m.index + m[0].length;
  }
  for (const [name, start] of Object.entries(firstInsertPosition)) {
    // phpMyAdmin pode quebrar uma tabela em mais de um INSERT sem comentário
    // entre eles; a seção da tabela termina no próximo bloco "--" separado.
    let end = text.indexOf('\n\n--', start);
    if (end < 0) end = text.length;
    estimatedRows[name] = ((text.slice(start, end).match(/^\(/gm) || []).length);
  }
  return {
    tables,
    insertStatements,
    estimatedRows,
    summary: {
      tables: tables.length,
      tablesWithInserts: Object.keys(insertStatements).length,
      insertStatements: Object.values(insertStatements).reduce((a, b) => a + b, 0),
      estimatedRows: Object.values(estimatedRows).reduce((a, b) => a + b, 0),
      engines: [...new Set(tables.map(t => t.engine))].sort(),
      charsets: [...new Set(tables.map(t => t.charset))].sort(),
      dropTable: (text.match(/\bDROP\s+TABLE\b/gi) || []).length,
      truncate: (text.match(/\bTRUNCATE\b/gi) || []).length,
      createDatabase: (text.match(/\bCREATE\s+DATABASE\b/gi) || []).length,
      useStatement: (text.match(/^\s*USE\s/gim) || []).length,
      customFieldReferences: (text.match(/custom_field/gi) || []).length,
      davidcreatorReferences: (text.match(/davidcreator\.com/gi) || []).length,
      rmsPrefixReferences: (text.match(/rms_/gi) || []).length
    }
  };
}

const parsed = parseSql(sql);
if (parsed.summary.tables < 1) fail('nenhuma CREATE TABLE encontrada');
if (parsed.summary.dropTable || parsed.summary.truncate || parsed.summary.createDatabase || parsed.summary.useStatement) {
  fail('SQL contém comando destrutivo ou seletor de banco fora do permitido');
}
if (!parsed.summary.engines.every(e => e.toLowerCase() === 'innodb')) fail('engine diferente de InnoDB');
if (!parsed.summary.charsets.every(c => c.toLowerCase() === 'utf8mb4')) fail('charset diferente de utf8mb4');
if (parsed.tables.some(t => t.columns.length < 1)) fail('tabela sem coluna identificada');

const isFixture = path.resolve(sqlPath) === path.resolve(fixturePath);
if (isFixture) {
  const names = parsed.tables.map(t => t.name);
  const expected = ['f903_safe_parent', 'f903_safe_child'];
  if (JSON.stringify(names) !== JSON.stringify(expected)) fail('fixture não corresponde ao contrato mínimo');
  if (parsed.summary.estimatedRows !== 1 || parsed.summary.insertStatements !== 1) fail('fixture com contagens inesperadas');
  ok(`fixture sanitizado aprovado; tabelas=${parsed.summary.tables}`);
  process.exit(0);
}

if (!inventoryPath) inventoryPath = path.resolve(root, 'docs', 'F9-03-Inventario-Banco-DavidAlmeida.json');
if (!fs.existsSync(inventoryPath)) fail(`inventário sanitizado ausente: ${path.relative(root, inventoryPath)}`);
const inv = JSON.parse(fs.readFileSync(inventoryPath, 'utf8'));
const sha = crypto.createHash('sha256').update(fs.readFileSync(sqlPath)).digest('hex');
if (sha !== inv.source.sha256) fail('hash do dump difere do inventário documentado');
if (parsed.summary.tables !== inv.summary.tables) fail('quantidade de tabelas difere do inventário');
if (parsed.summary.tablesWithInserts !== inv.summary.tables_with_inserts) fail('quantidade de tabelas com INSERT difere');
if (parsed.summary.insertStatements !== inv.summary.insert_statements) fail('quantidade de INSERT difere');
if (parsed.summary.estimatedRows !== inv.summary.estimated_rows) fail('estimativa de linhas difere');
if (parsed.summary.customFieldReferences !== 0) fail('dump tocou namespace custom_field');
if (parsed.summary.rmsPrefixReferences !== 0) fail('dump deveria ser legado sem prefixo rms_');
if (parsed.summary.davidcreatorReferences !== inv.summary.contains_davidcreator_references) fail('contagem de referências fora de escopo difere');

const expectedTables = inv.tables.map(t => t.name);
const parsedTables = parsed.tables.map(t => t.name);
if (JSON.stringify(parsedTables) !== JSON.stringify(expectedTables)) fail('ordem/nomes das tabelas diferem');
for (const exp of inv.tables) {
  const got = parsed.tables.find(t => t.name === exp.name);
  if (!got) fail(`tabela ausente: ${exp.name}`);
  if (got.engine !== exp.engine || got.charset !== exp.charset || got.collation !== exp.collation) fail(`metadados de tabela divergem: ${exp.name}`);
  if (JSON.stringify(got.columns) !== JSON.stringify(exp.columns)) fail(`colunas divergem: ${exp.name}`);
  if ((parsed.insertStatements[exp.name] || 0) !== exp.insert_statements) fail(`INSERT statements divergem: ${exp.name}`);
  if ((parsed.estimatedRows[exp.name] || 0) !== exp.estimated_rows) fail(`linhas estimadas divergem: ${exp.name}`);
}

ok(`dump real aprovado sem execução; tabelas=${parsed.summary.tables}; linhas_estimadas=${parsed.summary.estimatedRows}`);
