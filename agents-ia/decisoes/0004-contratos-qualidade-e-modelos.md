# ADR 0004 — Contratos de fundação e infraestrutura de qualidade

| | |
| --- | --- |
| **Status** | ✅ aceita |
| **Data** | 2026-10-15 |
| **Proponente** | humano (David L. Almeida), via auditoria de fundação (L1–L10) |

## Contexto

A [Auditoria de Fundação](../../docs/Auditoria-Fundacao.md) identificou 10 lacunas (L1–L10). As 4 críticas são de *contrato* (bootstrap, schema, modelos de arquivo, decisões pendentes); as demais são de *infraestrutura de qualidade*. Sem elas, os agentes — mesmo disciplinados — divergiriam em entry points, colunas de banco e formato de arquivos.

## Decisão

Aprovar e versionar **todos** os itens de amarração:

1. **L1** — [`docs/Contrato-Bootstrap.md`](../../docs/Contrato-Bootstrap.md) + modelo `front-controller-index.php.tpl` (única fonte para `index.php` das apps);
2. **L2** — [`docs/Dicionario-de-Dados.md`](../../docs/Dicionario-de-Dados.md) como fonte da verdade do schema (mudança = ADR + migração);
3. **L3** — pasta [`agents-ia/modelos/`](../modelos/) com 7 esqueletos canônicos;
4. **L4** — pendências resolvidas pelos ADRs 0001 (idioma), 0002 (blog) e 0003 (CI/.gitignore);
5. **L5** — CI reforçado (ADR 0003): composer validado no diretório certo + `php -l` em `dev/**` + link check;
6. **L6** — `composer.json`: `require-dev` com **PHPStan** (`phpstan.neon`, paths `engine/library/helper`) e **PHPUnit**; phpDocumentor movido para `require-dev`; pasta **`dev/tests/`** autorizada (fica ativa na Fase 1);
7. **L7** — `.editorconfig` na raiz (espaço×tab unificados para código novo);
8. **L8** — `.github/pull_request_template.md` (DoD espelhado dos agentes) + `CONTRIBUTING.md` + `SECURITY.md` + `CHANGELOG.md`;
9. **L9** — patch na regra 03: **sem acesso ao arquivo, o agente deve pedir o conteúdo ao humano** (proibido assumir);
10. **L10** — quirks do OpenCart/vendor registrados como intencionais no `00-contexto-global.md` (não "normalizar" sem ADR).

## Alternativas consideradas

1. **Implementar só os críticos (L1–L4)** — rejeitada: os itens L5–L10 custam pouco agora e evitam retrabalho de revisão;
2. **Contratos só em texto, sem modelos** — rejeitada: esqueleto pronto é o que de fato elimina deriva de formato entre sessões.

## Consequências

- ✅ Fundação passa de ~80% para ~100% "amarrada": disciplina (agents-ia) **+** contrato (docs) **+** verificação automática (CI/PHPStan);
- ✅ `dev/tests/` é a primeira pasta de primeiro nível nova — fica **registrada aqui** como exceção aprovada (não abre precedente informal);
- ⚠️ PHPStan deve começar em nível baixo (0–2) e subir por fase, para não travar a entrega com dezenas de findings legados.

## Documentos/arquivos criados ou atualizados

`docs/Contrato-Bootstrap.md` · `docs/Dicionario-de-Dados.md` · `agents-ia/modelos/**` · `.github/workflows/php.yml` · `.github/pull_request_template.md` · `.gitignore` · `.editorconfig` · `CONTRIBUTING.md` · `SECURITY.md` · `CHANGELOG.md` · `dev/system/storage/composer.json` · `dev/system/storage/phpstan.neon` · `dev/tests/README.md` · `agents-ia/regras/00` e `03` (patches)
