#!/usr/bin/env node
'use strict';
/*
 * verify-docs-index.js — ADR 0127: a MESMA tabela para o guarda e para os testes.
 *
 *   node tools/verify-docs-index.js --json   # classifica todo .md versionado
 *
 * Por que existe (ADR 0127): a ADR 0126 pôs no guarda uma lista de documentos
 * operacionais escrita à mão. Em duas semanas ela já estava incompleta —
 * `docs/PROJECT-HANDOFF.md` e vinte guias de tema ficaram fora sem ninguém
 * notar, exatamente o defeito que o guarda existe para pegar. E o teste
 * unitário repetia a lista, de modo que o descompasso era invisível dos dois
 * lados: o guarda cobria menos do que dizia, e o teste aprovava.
 *
 * Aqui a classificação é DERIVADA, nunca enumerada:
 *
 *   histórico  = agents-ia/decisoes/**, docs/historico/**, docs/fases/**,
 *                e os relatórios datados da raiz (INDICE-*, RELATORIO-*,
 *                DIAGNOSTICO-*, Pauta-*)
 *   operacional = todo o resto
 *
 * Histórico é registro: ali um caminho que existiu e foi removido está correto.
 * Operacional é instrução: o leitor vai executar, então o caminho tem de existir.
 *
 * Arquivo novo em qualquer pasta cai em uma das duas classes sozinho. Não há
 * lista para manter — é o ponto.
 *
 * Somente leitura: não escreve, não acessa rede nem banco.
 */
const { execFileSync } = require('node:child_process');

const RASTREIO = '(?:^|/)';

/** Prefixos em que um caminho citado é REGISTRO, não instrução. */
const PASTAS_HISTORICAS = [
  'agents-ia/decisoes/',
  'docs/historico/',
  'docs/fases/',
];

/** Relatórios datados da raiz: registro de uma sessão, congelado por natureza. */
const RAIZ_HISTORICA = /^(INDICE|RELATORIO|DIAGNOSTICO|Pauta)/;

/**
 * Caminhos que o Git não versiona: existem só depois de `composer install`,
 * do gerador de documentação ou da build de produção. Citá-los é correto.
 */
const CAMINHOS_GERADOS = [
  'dev/system/storage/vendor/',
  'dev/system/storage/node_modules/',
  'dev/system/storage/cache/',
  'dev/system/storage/logs/',
  'docs/api/',
  'prod/',
];

function versionados() {
  const saida = execFileSync('git', ['ls-files', '-z', '*.md'], {
    encoding: 'utf8',
    maxBuffer: 32 * 1024 * 1024,
  });

  return saida.split('\0').filter(Boolean).sort();
}

function classe(caminho) {
  if (PASTAS_HISTORICAS.some((p) => caminho.startsWith(p))) {
    return 'historico';
  }

  const raiz = caminho.split('/').length === 1;

  if (raiz && RAIZ_HISTORICA.test(caminho)) {
    return 'historico';
  }

  return 'operacional';
}

/** @returns {{operacionais: string[], historicos: string[], caminhos_gerados: string[]}} */
function escopo() {
  const operacionais = [];
  const historicos = [];

  for (const caminho of versionados()) {
    if (classe(caminho) === 'historico') {
      historicos.push(caminho);
    } else {
      operacionais.push(caminho);
    }
  }

  return { operacionais, historicos, caminhos_gerados: CAMINHOS_GERADOS };
}

if (require.main === module) {
  const argv = process.argv.slice(2);

  if (argv.includes('--help') || argv.includes('-h')) {
    process.stdout.write(
      'uso: node tools/verify-docs-index.js --json\n' +
      '  Classifica todo .md versionado em operacional ou histórico.\n' +
      '  A tabela é derivada das pastas — não existe lista de arquivos.\n'
    );
    process.exit(0);
  }

  if (!argv.includes('--json')) {
    process.stderr.write('uso: node tools/verify-docs-index.js --json\n');
    process.exit(2);
  }

  process.stdout.write(JSON.stringify(escopo(), null, 0) + '\n');
}

module.exports = { escopo, classe, CAMINHOS_GERADOS, PASTAS_HISTORICAS, RASTREIO };
