#!/usr/bin/env node
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const files = {
  upgrade: 'dev/install/sql/upgrade_f9_12_blog_block_content.sql',
  rollback: 'dev/install/sql/rollback_f9_12_blog_block_content.sql',
  contract: 'docs/F9-12-Contrato-Persistencia-Blog-Blocos.json',
  dictionary: 'docs/Dicionario-de-Dados.md',
  adr: 'agents-ia/decisoes/0068-f9-12-blog-block-content-sidecar.md',
};
const errors = [];
function read(key) {
  const p = path.join(root, files[key]);
  if (!fs.existsSync(p)) { errors.push(`arquivo ausente: ${files[key]}`); return ''; }
  return fs.readFileSync(p, 'utf8');
}
function need(ok, msg) { if (!ok) errors.push(msg); }
const up = read('upgrade');
const down = read('rollback');
const dict = read('dictionary');
const adr = read('adr');
let contract;
try { contract = JSON.parse(read('contract')); } catch (e) { errors.push(`JSON inválido: ${e.message}`); }

need(/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`rms_blog_post_content`/i.test(up), 'upgrade precisa ser CREATE TABLE IF NOT EXISTS para o sidecar correto');
for (const col of ['post_id','language_id','format_version','content_json','source_hash','legacy_html_backup','date_added','date_modified'])
  need(new RegExp('`' + col + '`', 'i').test(up), `coluna ausente na migration: ${col}`);
need(/PRIMARY\s+KEY\s*\(\s*`post_id`\s*,\s*`language_id`\s*\)/i.test(up), 'chave primária composta post_id + language_id ausente');
need(/MEDIUMTEXT\s+NOT\s+NULL/i.test(up), 'content_json precisa MEDIUMTEXT NOT NULL');
need(/CHAR\(64\)/i.test(up), 'source_hash deve ter 64 caracteres');
need(/ENGINE\s*=\s*InnoDB/i.test(up) && /utf8mb4/i.test(up), 'engine/charset esperados: InnoDB + utf8mb4');
need(!/^\s*(ALTER|DROP|TRUNCATE|DELETE|UPDATE|INSERT)\b/im.test(up), 'upgrade não pode alterar nem migrar conteúdo existente');
need(!/^\s*DROP\s+TABLE\b/im.test(down), 'rollback padrão não pode descartar a tabela');
need(/description continua/i.test(down) || /HTML reader/i.test(down), 'rollback deve documentar fallback HTML');
need(/blog_post_description\.description/i.test(dict) && /blog_post_content/i.test(dict), 'dicionário deve documentar as tabelas Blog e a compatibilidade');
need(/rollback/i.test(adr) && /legacy_html_backup/i.test(adr), 'ADR deve registrar rollback e snapshot legado');
need(contract?.task === 'F9-12', 'contrato precisa identificar F9-12');
need(contract?.status === 'migration_prepared_not_executed', 'status deve deixar claro que SQL não foi executado');
need(contract?.legacy_compatibility?.altered === false, 'campo HTML legado precisa permanecer inalterado');
need(contract?.migration?.database_execution_authorized === false, 'contrato não pode autorizar execução no banco');
need(contract?.rollback_policy?.data_loss_by_default === false, 'rollback não pode causar perda por padrão');

if (errors.length) {
  console.error('F9-12 FAIL');
  for (const e of errors) console.error(` - ${e}`);
  process.exit(1);
}
console.log('F9-12 PASS — migrations e contrato validados estaticamente; nenhum SQL/banco executado.');
