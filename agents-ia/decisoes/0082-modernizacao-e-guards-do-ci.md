# ADR 0082 — Modernização e endurecimento do CI (`php.yml`)

| | |
| --- | --- |
| **Status** | ✅ aceita (F11-04, 2026-11-02) |
| **Data** | 2026-11-02 |
| **Contexto** | Após F11-01/0081 e F11-02, o `main` (`510640c68`) está com os 5 jobs verdes e sem dependências versionadas. Restavam 8 annotations por run, 2 por job: *"Node.js 20 is deprecated. The following actions target Node.js 20 but are being forced to run on Node.js 24"* e o notice de que `ubuntu-latest` migraria para Ubuntu 26. Além disso, os auditores `tools/verify-deps-layout.sh` (F11-02) e `tools/verify-checksums.sh` (F11-03) existiam, mas **não eram executados pelo CI**. |

## Decisão

1. **Atualizar as actions para as majors com runtime `node24`**, confirmadas nas releases oficiais em 2026-11-02 (API `repos/<repo>/releases/latest` + `action.yml` da tag):

   | Antes | Depois | Release | `runs.using` |
   | --- | --- | --- | --- |
   | `actions/checkout@v4` | `actions/checkout@v7` | v7.0.1 (2026-07-20) | `node24` |
   | `actions/setup-node@v4` | `actions/setup-node@v7` | v7.1.0 (2026-10-08) | `node24` |
   | `actions/cache@v4` | `actions/cache@v6` | v6.1.0 (2026-06-26) | `node24` |
   | `shivammathur/setup-php@v2` | **mantido** `@v2` | 2.40.0 (2026-10-08) | `node24` (a major flutuante já aponta para ela) |
   | `lycheeverse/lychee-action@v1` | `lycheeverse/lychee-action@v2` | v2.9.0 (2026-07-09) | `composite` |

   Inputs foram conferidos nos `action.yml` das majors: `path`/`key`/`restore-keys` (cache@v6), `node-version`/`cache-dependency-path`/`package-manager-cache` (setup-node@v7), `php-version`/`extensions` (setup-php@v2), `args` (lychee@v2). A v2 do lychee troca o `env: GITHUB_TOKEN` pelo input `token` (default `github.token`); passamos `token: ${{ secrets.GITHUB_TOKEN }}` explicitamente.

2. **Pinar `runs-on: ubuntu-24.04`** nos 6 jobs. O label `ubuntu-latest` está em migração gradual para Ubuntu 26.04 entre **19/10/2026 e 19/11/2026** — estamos dentro da janela — e a imagem 26.04 remove ferramentas pré-instaladas. Pinar congela o comportamento durante a transição; a migração deliberada para `ubuntu-26.04` fica agendada para após 19/11/2026, precedida de um run verde. `ubuntu-24.04` e `ubuntu-26.04` constam como labels disponíveis no `actions/runner-images` README.

3. **Adicionar `concurrency`** (`group: workflow + ref`, `cancel-in-progress: true`) e **`timeout-minutes`** em todos os jobs (10–20 min), evitando runs duplicados e jobs travados — relevante porque `docs-links`/lychee varre links externos.

4. **Novo job `repo-guards`** executando os três auditores read-only já existentes/novos, sem duplicar ferramentas: `verify-deps-layout.sh` (índice do Git + `.gitignore`), `verify-checksums.sh` (manifestos `CHECKSUMS*.txt`) e `verify-ci-workflow.sh` (contrato deste próprio workflow). O `verify-f11-01.sh` **não** foi acoplado ao CI porque seus checks já são cobertos por `composer`, `php-lint`, `static-analysis` e `node-deps`.

5. **Matrix `node-version: ['20', '22']`** com `fail-fast: false` no `node-deps`: mantém o mínimo declarado em `engines.node >= 20` e antecipa a validação no LTS 22. O cache npm passa a ser explícito via `actions/cache@v6`, com a versão do Node na chave, e o cache automático do `setup-node` é desligado (`package-manager-cache: false`) para não haver colisão entre as duas pernas da matrix.

6. **Sem `defaults.run` global**, `working-directory` apenas nos 2 jobs do Composer/PHPStan (invariante da ADR 0081 preservada). `permissions: contents: read` mantido.

## Alternativas consideradas

1. **Ignorar os avisos** — rejeitado: eram 8 annotations por run e indicavam actions executando em runtime forçado; o custo de atualizar é baixo e as majors foram verificadas.
2. **Migrar direto para `ubuntu-26.04`** — rejeitado neste momento: a janela de migração ainda está em curso e a imagem 26.04 remove diversas ferramentas; mudança sem evidência de run verde contraria o princípio de não alegar testes não executados. Fica como passo seguinte datado.
3. **Substituir `node-version: '20'` por `'24'` de uma vez** — rejeitado: o sandbox só dispõe de Node 20 e não há evidência local para Node 24 no projeto. A matrix 20+22 produz evidência real no CI sem abandonar o mínimo suportado.
4. **Rodar `verify-f11-01.sh` dentro do `repo-guards`** — rejeitado: duplicaria `npm ci`, `node --check` e as validações de Composer/PHPStan já feitas por outros jobs.
5. **Fixar actions por SHA completo** — considerado para supply-chain, mas adiado: exigiria um processo de renovação de pins que o repositório ainda não tem; as majors verificadas já eliminam os avisos. Registrado como melhoria futura.

## Consequências

- ✅ Fim dos avisos de actions Node 20 forçadas a Node 24 e do notice de `ubuntu-latest`.
- ✅ `vendor/`, `node_modules/` e a integridade dos manifests passam a ser verificados **em todo push/PR**, não apenas manualmente.
- ✅ O próprio workflow passa a ter um contrato auditável (`verify-ci-workflow.sh`), detectando regressões como o retorno de `defaults.run` global ou de `ubuntu-latest`.
- ⚠️ `docs-links` muda de `lychee-action@v1` para `@v2` (composite, input `token`): **primeira execução no CI real deve ser acompanhada** — não foi executada localmente (lychee é ação externa).
- ⚠️ `node-deps` passa a ter 2 pernas de matrix; aumento pequeno de minutos de CI.
- ⚠️ Após 19/11/2026, revisar o pin `ubuntu-24.04` e testar `ubuntu-26.04` deliberadamente; não reintroduzir `ubuntu-latest`.

## Fontes verificadas

- `https://api.github.com/repos/actions/checkout/releases/latest` · `.../actions/setup-node/releases/latest` · `.../actions/cache/releases/latest` · `.../shivammathur/setup-php/releases/latest` · `.../lycheeverse/lychee-action/releases/latest`
- `https://raw.githubusercontent.com/<repo>/<tag>/action.yml` (inputs e `runs.using`)
- `https://github.com/actions/runner-images` (README: labels `ubuntu-24.04` e `ubuntu-26.04`; anúncio de migração de 17/09/2026)
- Anúncio/documentação da migração: <https://creatorstoolbox.com/blog/github-ubuntu-26-generally-available-and-latest-migration> e <https://hidekazu-konishi.com/entry/github_hosted_runner_label_migrations.html>
