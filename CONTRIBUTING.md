# Contribuindo com o ReamurCMS

Obrigado pelo interesse! Este guia vale para contribuidores humanos **e** para agentes de IA — todos seguem o mesmo padrão.

## Antes de codar

1. Leia o [README](README.md) e o índice de [`docs/`](docs/README.md);
2. Entenda a arquitetura: [MVCL](docs/Arquitetura-MVCL.md) e [Estrutura de Pastas](docs/Estrutura-de-Pastas.md);
3. Regras invioláveis: [`agents-ia/regras/`](agents-ia/regras/) — contexto global, convenções, padrões de código e protocolo anti-alucinação;
4. Schema de banco é contrato: [`docs/Dicionario-de-Dados.md`](docs/Dicionario-de-Dados.md) — mudança exige ADR + migração;
5. Pontos de entrada são contrato: [`docs/Contrato-Bootstrap.md`](docs/Contrato-Bootstrap.md).

## Fluxo de trabalho

- Branches: trabalho novo sai de `feature/*` → PR para `staging` → merge em `main` após validação;
- Commits em **pt-BR, Conventional Commits** (`feat(catalog): ...`, `fix(system): ...`, `docs: ...`);
- Todo PR usa o [template](.github/pull_request_template.md) — o checklist é obrigatório;
- CI precisa estar verde: `composer validate`, `php -l` e link check;
- Decisões arquiteturais: propor ADR em [`agents-ia/decisoes/`](agents-ia/decisoes/) antes de implementar.

## Arquivos novos

- Nunca gere arquivo "de cabeça": copie o esqueleto correspondente em [`agents-ia/modelos/`](agents-ia/modelos/);
- Textos de interface: somente camada **Language**; idioma padrão **`en-us`** (ADR 0001), com pt-BR/es-EU quando possível;
- Nenhuma pasta de primeiro nível nova sem ADR (a única exceção aprovada é `dev/tests/`).

## Reportar bugs

Abra uma issue descrevendo: comportamento esperado × observado, aplicação afetada (`catalog`/`admin`/`install`), passos para reproduzir e logs de `dev/system/storage/logs/`. Para **segurança**, veja [SECURITY.md](SECURITY.md).
