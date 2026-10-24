#!/usr/bin/env node
'use strict';

/**
 * F9-05 — validação estática do Model de definições e ACL dormente.
 * Não executa PHP, SQL, CMS, navegador ou banco.
 */
const fs = require('fs');
const path = require('path');

function fail(msg) { console.error(`FAIL F9-05: ${msg}`); process.exit(1); }
function ok(msg) { console.log(`PASS F9-05: ${msg}`); }
function read(root, rel) {
  const file = path.join(root, rel);
  if (!fs.existsSync(file)) fail(`arquivo ausente: ${rel}`);
  return fs.readFileSync(file, 'utf8');
}
function functionBody(source, signature) {
  const start = source.indexOf(signature);
  if (start < 0) return '';
  const open = source.indexOf('{', start);
  if (open < 0) return '';
  let depth = 0;
  for (let i = open; i < source.length; i++) {
    if (source[i] === '{') depth++;
    if (source[i] === '}') {
      depth--;
      if (depth === 0) return source.slice(open + 1, i);
    }
  }
  return '';
}
function assert(cond, msg) { if (!cond) fail(msg); }

const root = path.resolve(__dirname, '..', '..');
const contract = JSON.parse(read(root, 'docs/F9-05-Contrato-Definicoes-Campos.json'));
const model = read(root, 'dev/admin/model/custom_field/definition.php');
const installer = read(root, 'dev/install/model/install/install.php');
const upgrade = read(root, 'dev/install/sql/upgrade_f9_05_custom_field_definition_grant.sql');
const rollback = read(root, 'dev/install/sql/rollback_f9_05_custom_field_definition_grant.sql');

assert(contract.task === 'F9-05', 'contrato da tarefa incorreto');
assert(contract.status === 'implementation_partial_backend_model_acl', 'status do contrato inesperado');
assert(contract.route.admin_route === 'custom_field/definition', 'rota ACL incorreta');
assert(contract.route.controller_available === false && contract.route.ui_available === false, 'F9-05 não deveria entregar controller/UI');
assert(contract.model.creates_values === false && contract.model.hard_deletes_values === false, 'model deveria ser somente definições');
assert(contract.public_exposure.allowed === false, 'exposição pública deve permanecer desligada');

assert(/declare\s*\(strict_types=1\)/.test(model) === false, 'model deve seguir padrão atual sem declare strict_types no topo');
assert(model.includes('namespace Reamur\\Admin\\Model\\Custom_field;'), 'namespace do model incorreto');
assert(model.includes('class Definition extends \\Reamur\\System\\Engine\\Model'), 'classe/herança do model incorreta');
assert(!model.includes('$this->load->language('), 'model não deve carregar language');
assert(!model.includes('Csrf::'), 'model não deve tratar CSRF');

const methods = [
  'getGroups', 'getTotalGroups', 'getGroup', 'getGroupDescriptions', 'groupExists', 'addGroup', 'editGroup', 'deleteGroup',
  'getFields', 'getTotalFields', 'getField', 'getFieldDescriptions', 'fieldExists', 'addField', 'editField', 'deleteField',
  'getAllowedEntities', 'getAllowedTypes', 'getAllowedVisibility', 'getLanguages', 'countGroupFields', 'countFieldValues'
];
for (const method of methods) assert(new RegExp(`public function ${method}\\(`).test(model), `método público ausente: ${method}`);

const entities = contract.domain.entity_types;
assert(JSON.stringify(entities) === JSON.stringify(['cms/page']), 'whitelist de entidade deve ser cms/page');
assert(model.includes("private const ALLOWED_ENTITIES = ['cms/page'];"), 'whitelist cms/page ausente no model');
const types = ['text', 'textarea', 'number', 'boolean', 'url', 'select'];
assert(JSON.stringify(contract.domain.field_types) === JSON.stringify(types), 'tipos do contrato divergem');
assert(model.includes(types.map(t => `'${t}'`).join(', ')), 'lista de tipos diverge no model');
assert(JSON.stringify(contract.domain.visibility) === JSON.stringify(['admin_only', 'public']), 'visibilidades divergem');
assert(contract.domain.default_visibility === 'admin_only', 'padrão deve continuar privado');
assert(JSON.stringify(contract.domain.immutable_after_create) === JSON.stringify(['entity_type', 'key', 'type', 'localized']), 'imutáveis divergem');

assert(!/INSERT\s+INTO\s+`" \+ \$this->t\('custom_field_value'\)/i.test(model), 'model não pode INSERT em custom_field_value');
assert(!/UPDATE\s+`" \+ \$this->t\('custom_field_value'\)/i.test(model), 'model não pode UPDATE em custom_field_value');
assert(!/DELETE\s+FROM\s+`" \+ \$this->t\('custom_field_value'\)/i.test(model), 'model não pode DELETE em custom_field_value');
assert(/SELECT\s+COUNT\(\*\).*custom_field_value/s.test(model), 'guarda de valores ausente');

const editField = functionBody(model, 'public function editField(');
assert(editField !== '', 'editField não encontrado');
for (const col of ['entity_type', 'key', 'type', 'localized']) {
  const updateRe = new RegExp('`' + col + '`\\s*=');
  assert(!updateRe.test(editField), `editField não pode alterar ${col}`);
}
assert(editField.includes('groupBelongsToEntity'), 'editField precisa validar grupo/entity');
assert(editField.includes('optionsToJson'), 'editField precisa normalizar options');

const deleteField = functionBody(model, 'public function deleteField(');
assert(deleteField.includes('countFieldValues'), 'deleteField precisa consultar valores antes');
assert(deleteField.includes('if ($this->countFieldValues($field_id) > 0)'), 'deleteField deve bloquear campo com valor');
const deleteGroup = functionBody(model, 'public function deleteGroup(');
assert(deleteGroup.includes('countGroupFields'), 'deleteGroup precisa consultar campos antes');
assert(deleteGroup.includes('if ($this->countGroupFields($group_id) > 0)'), 'deleteGroup deve bloquear grupo com campos');
assert(model.includes("optionsToJson(string $type, mixed $options): string"), 'serialização JSON de options ausente');
assert(model.includes("if ($type !== 'select')"), 'apenas select pode persistir options');

assert(upgrade.includes("custom_field/definition"), 'grant sem rota correta');
assert((upgrade.match(/JSON_ARRAY_APPEND/g) || []).length === 2, 'grant deve incluir access e modify');
assert((upgrade.match(/JSON_SEARCH/g) || []).length === 2, 'grant sem guarda idempotente');
assert(!/CREATE\s+TABLE|DROP\s+TABLE|TRUNCATE|DELETE\s+FROM/i.test(upgrade), 'grant de ACL não deve mexer em schema/dados');
assert(rollback.includes('JSON_REMOVE') && rollback.includes('JSON_SEARCH'), 'rollback da ACL sem remoção JSON');
assert(!/DROP\s+TABLE|TRUNCATE|CREATE\s+TABLE/i.test(rollback), 'rollback não deve tocar schema');

assert(installer.includes("'custom_field/definition'"), 'seed do wizard sem rota dormente');
assert((installer.match(/custom_field\/definition/g) || []).length === 1, 'rota dormente deve aparecer uma única vez no seed');
assert(!fs.existsSync(path.join(root, 'dev/install/sql/schema.sql')), 'F9-05 não deve entregar schema.sql');
// F9-05 was a backend-only delivery. Do not assert later F9-06 controller/UI
// paths are absent: this same gate is also part of the integrated F9-10 suite.
assert(contract.route.controller_available === false && contract.route.ui_available === false, 'contrato histórico F9-05 deve continuar parcial');

ok('model de definições e ACL dormente aprovados; nenhum valor/público/UI executado');
