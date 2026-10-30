'use strict';
/*
 * tools/staging — escritor de relatório das baterias.
 * Saída: staging/reports/batteries-<timestamp>.{json,md} + latest.{json,md}
 *
 * Status possíveis (nunca "aprovado por omissão"):
 *   PASS    — todas as checagens passaram
 *   WARN    — só falhas conhecidas/de dívida declarada (ex.: baseline, migração pendente)
 *   FAIL    — pelo menos uma checagem falhou e é NOVA (regressão)
 *   NOT_RUN — nada foi executado (pré-requisito ausente) — NÃO é aprovação
 */
const fs = require('node:fs');
const path = require('node:path');

const STATUS = { PASS: 'PASS', FAIL: 'FAIL', WARN: 'WARN', NOT_RUN: 'NOT_RUN' };
const SEVERITY = { FAIL: 3, WARN: 2, PASS: 1, NOT_RUN: 0 };

class BatteryResult {
  constructor(id, title) {
    this.id = id;
    this.title = title;
    this.status = STATUS.NOT_RUN;
    this.checks = [];
    this.notes = [];
    this.durationMs = 0;
  }

  check(name, status, detail) {
    this.checks.push({ name, status, detail: detail == null ? '' : String(detail) });
    return this;
  }

  note(text) {
    this.notes.push(String(text));
    return this;
  }

  get counts() {
    const c = { PASS: 0, FAIL: 0, WARN: 0, NOT_RUN: 0 };
    for (const ch of this.checks) c[ch.status] = (c[ch.status] || 0) + 1;
    return c;
  }

  finalize() {
    const c = this.counts;
    if (c.FAIL > 0) this.status = STATUS.FAIL;
    else if (c.WARN > 0) this.status = STATUS.WARN;
    else if (c.PASS > 0) this.status = STATUS.PASS;
    else this.status = STATUS.NOT_RUN;
    return this;
  }
}

function summarize(report) {
  const counts = { PASS: 0, FAIL: 0, WARN: 0, NOT_RUN: 0 };
  for (const b of report.batteries) counts[b.status] = (counts[b.status] || 0) + 1;
  const checks = { PASS: 0, FAIL: 0, WARN: 0, NOT_RUN: 0 };
  for (const b of report.batteries) {
    for (const c of b.checks) checks[c.status] = (checks[c.status] || 0) + 1;
  }
  let worst = STATUS.NOT_RUN;
  for (const b of report.batteries) {
    if (SEVERITY[b.status] > SEVERITY[worst]) worst = b.status;
  }
  return { batteries: counts, checks, overall: worst };
}

function markdown(report) {
  const s = summarize(report);
  const L = [];
  L.push('# Relatório de baterias — staging');
  L.push('');
  L.push(`- **Início:** ${report.startedAt}`);
  L.push(`- **Fim:** ${report.finishedAt}`);
  L.push(`- **Raiz:** \`${report.meta.root}\``);
  L.push(`- **Staging:** \`${report.meta.stagingDir}\``);
  L.push(`- **Banco:** \`${report.meta.dbSource}\` (espelho) → \`${report.meta.dbTarget}\` (descartável)`);
  L.push(`- **URL:** ${report.meta.urls.catalog}`);
  L.push('');
  L.push(`## Resultado global: ${s.overall}`);
  L.push('');
  L.push('| Bateria | Status | PASS | FAIL | WARN | NOT_RUN | Duração |');
  L.push('| --- | --- | --- | --- | --- | --- | --- |');
  for (const b of report.batteries) {
    const c = b.counts;
    L.push(`| ${b.id} — ${b.title} | **${b.status}** | ${c.PASS} | ${c.FAIL} | ${c.WARN} | ${c.NOT_RUN} | ${b.durationMs} ms |`);
  }
  L.push('');
  L.push('> `WARN` = falha conhecida/declarada (baseline, dívida). `NOT_RUN` **não** é aprovação.');
  L.push('');

  for (const b of report.batteries) {
    L.push(`## ${b.id} — ${b.title}`);
    L.push('');
    L.push(`Status: **${b.status}** (${b.durationMs} ms)`);
    L.push('');
    for (const n of b.notes) L.push(`- ${n}`);
    if (b.notes.length) L.push('');
    L.push('| Checagem | Status | Detalhe |');
    L.push('| --- | --- | --- |');
    for (const c of b.checks) {
      L.push(`| ${esc(c.name)} | ${c.status} | ${esc(c.detail)} |`);
    }
    L.push('');
  }

  const failures = [];
  for (const b of report.batteries) {
    for (const c of b.checks) {
      if (c.status === STATUS.FAIL) failures.push(`${b.id}: ${c.name} — ${c.detail}`);
    }
  }
  if (failures.length) {
    L.push('## Falhas a resolver');
    L.push('');
    for (const f of failures) L.push(`- ${f}`);
    L.push('');
  }

  return L.join('\n');
}

function esc(value) {
  return String(value).replace(/\|/g, '\\|').replace(/[\r\n]+/g, ' ');
}

function write(report, reportsDir) {
  fs.mkdirSync(reportsDir, { recursive: true });
  const stamp = report.startedAt.replace(/[:.]/g, '-');
  const json = JSON.stringify(report, null, 2);
  const md = markdown(report);
  const files = {
    json: path.join(reportsDir, `batteries-${stamp}.json`),
    md: path.join(reportsDir, `batteries-${stamp}.md`),
    latestJson: path.join(reportsDir, 'latest.json'),
    latestMd: path.join(reportsDir, 'latest.md'),
  };
  fs.writeFileSync(files.json, json, 'utf8');
  fs.writeFileSync(files.md, md, 'utf8');
  fs.writeFileSync(files.latestJson, json, 'utf8');
  fs.writeFileSync(files.latestMd, md, 'utf8');
  return files;
}

module.exports = { STATUS, BatteryResult, summarize, markdown, write };
