# Documentação — ReamurCMS

Índice da pasta `docs/`. O `README.md` da raiz e `Estrutura-de-Pastas.md` apontam para este arquivo; ele foi criado em F11-10 para fechar os dois links relativos quebrados detectados pelo `lychee` no CI.

## Comece aqui

- [PROJECT-HANDOFF.md](PROJECT-HANDOFF.md) — estado do projeto, pausa do BDREditor em F9-103 e restrições permanentes.
- [CHANGELOG.md](CHANGELOG.md) — histórico de entregas.
- [Estrutura-de-Pastas.md](Estrutura-de-Pastas.md) — layout do repositório.
- [Arquitetura-MVCL.md](Arquitetura-MVCL.md) · [Contrato-Bootstrap.md](Contrato-Bootstrap.md) · [Dicionario-de-Dados.md](Dicionario-de-Dados.md) · [Auditoria-Fundacao.md](Auditoria-Fundacao.md).

## Guias

[Guia-CMS.md](Guia-CMS.md) · [Guia-Composer.md](Guia-Composer.md) · [Guia-PHP8.md](Guia-PHP8.md) · [Guia-Twig.md](Guia-Twig.md) · [Guia-ThreeJS.md](Guia-ThreeJS.md) · [Implementacao-ThreeJS.md](Implementacao-ThreeJS.md) · [IMPORT_FORMATS.md](IMPORT_FORMATS.md).

## CI, dependências e governança (frentes F11)

- [F11-01 — CI e dependências em `dev/system/storage/`](F11-01-CI-e-Dependencias.md)
- [F11-02 — Verificação de dependências](F11-02-Verificacao-Dependencias.md)
- [F11-04 — Modernização do CI](F11-04-Modernizacao-CI.md)
- [F11-05 — Hotfix do `repo-guards`](F11-05-Hotfix-Repo-Guards.md)
- [F11-07 — Inventário dos checkers](F11-07-Inventario-Checkers.md)
- [F11-08 — Hotfix de performance do `docs-links`](F11-08-Hotfix-Docs-Links.md)
- [F11-09 — Triagem dos marcadores](F11-09-Triagem-Marcadores.md)
- [F11-10 — Links quebrados no `docs-links`](F11-10-Links-Quebrados.md)

Índices de entrega na raiz: [`F11-01-INDICE.md`](../F11-01-INDICE.md) · [`F11-02-INDICE.md`](../F11-02-INDICE.md) · [`F11-05-INDICE.md`](../F11-05-INDICE.md) · [`F11-07-INDICE.md`](../F11-07-INDICE.md) · [`F11-08-INDICE.md`](../F11-08-INDICE.md) · [`F11-09-INDICE.md`](../F11-09-INDICE.md) · [`F11-10-INDICE.md`](../F11-10-INDICE.md).

## Fase 9, BDREditor e release

O projeto do BDREditor está **pausado em F9-103** por determinação do responsável; consulte o handoff antes de retomar.

- [F9-27 — Plano E2E de staging e release gate](F9-27-Release-Gate.md) · [F9-27 — Preflight](F9-27-Preflight-Execucao.md)
- [F9-18 — Relatório de revisão estática](F9-18-Relatorio-Revisao-Estatica.md) · [F9-47 — Auditoria funcional do BDREditor](F9-47-Auditoria-Funcional-BDREditor.md)
- [F9-103 — Auditoria de acessibilidade de mídia](F9-103-Media-Accessibility-Audit.md) · [Referência completa e encerramento](BDREditor-Complete-Reference-and-Closure.md)

## Ferramentas de verificação

- [`tools/verify-deps-layout.sh`](../tools/verify-deps-layout.sh) — layout de `vendor/` e `node_modules/`.
- [`tools/verify-checksums.sh`](../tools/verify-checksums.sh) — integridade dos manifestos `CHECKSUMS*.txt`.
- [`tools/verify-ci-workflow.sh`](../tools/verify-ci-workflow.sh) — contrato do `.github/workflows/php.yml`.
- [`tools/verify-checkers-inventory.py`](../tools/verify-checkers-inventory.py) — estabilidade do conjunto de checkers.

> O CI verde **não** significa release aprovada: F9-27 segue `BLOCKED_NOT_RUN` e `release_approved: false`.
