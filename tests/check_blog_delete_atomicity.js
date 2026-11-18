#!/usr/bin/env node
/**
 * check_blog_delete_atomicity.js — KZ-48 / F918-04 (P2)
 * Garante que deletePost() no model admin do blog faz TUDO dentro de uma
 * única transação: o delete de bdre_revision não pode ficar depois do COMMIT
 * (ficaria órfão se falhasse). Falha se achar COMMIT antes do delete de
 * revisions dentro de deletePost.
 */
'use strict';
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const src = fs.readFileSync(path.join(root, 'dev/admin/model/blog/blog.php'), 'utf8');

let checks = 0;
function check(cond, label) {
  checks++;
  if (!cond) { console.error('FAIL  ' + label); process.exit(1); }
}

// extrai o corpo de deletePost
const m = src.match(/public function deletePost\(int \$post_id\): void \{([\s\S]*?)\n    \}/);
check(!!m, 'deletePost existe no model admin do blog');
const body = m ? m[1] : '';

check(body.includes('START TRANSACTION'), 'deletePost inicia transação');
check(body.includes('ROLLBACK'), 'deletePost tem rollback no catch');
const iCommit = body.indexOf("'COMMIT'");
const iRev = body.indexOf('bdre_revision');
check(iCommit !== -1, 'deletePost faz COMMIT');
check(iRev !== -1, 'deletePost apaga bdre_revision');
check(iRev !== -1 && iCommit !== -1 && iRev < iCommit, 'delete de bdre_revision acontece ANTES do COMMIT (dentro da transação)');

console.log('PASS  check_blog_delete_atomicity: ' + checks + ' verificacoes (KZ-48 / F918-04)');
