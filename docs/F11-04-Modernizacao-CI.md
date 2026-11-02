# F11-04 — Modernização e endurecimento do CI (ADR 0082)

**Data:** 2026-11-02 · **Base:** `main` @ `510640c68` (5/5 jobs verdes) · **Tipo:** CI + governança. **Nenhuma escrita no GitHub.**

---

## 1. Diagnóstico

| Achado | Evidência |
| --- | --- |
| 8 annotations informativas por run (2 por job) | `Node.js 20 is deprecated. The following actions target Node.js 20 but are being forced to run on Node.js 24: actions/cache@v4, actions/checkout@v4, ...` + notice de `ubuntu-latest` → Ubuntu 26 |
| `ubuntu-latest` em janela de migração ativa | O label migra gradualmente de Ubuntu 24.04 para 26.04 entre **19/10/2026 e 19/11/2026**; hoje (02/11) estamos dentro da janela, então o mesmo workflow pode rodar em imagens diferentes |
| Auditores não acoplados ao CI | `tools/verify-deps-layout.sh` (F11-02) e `tools/verify-checksums.sh` (F11-03) só rodavam manualmente |
| Sem proteção contra jobs travados/duplicados | Sem `concurrency` nem `timeout-minutes`; `docs-links` (lychee)_varre links externos e já ficou `in_progress` por vários minutos |

**Não havia erro de código**: o CI estava 100% verde. Esta frente é de modernização e prevenção de regressão.

---

## 2. Mudanças

| Item | Antes | Depois |
| --- | --- | --- |
| `actions/checkout` | `@v4` | `@v7` (v7.0.1, `node24`) |
| `actions/setup-node` | `@v4` | `@v7` (v7.1.0, `node24`) |
| `actions/cache` | `@v4` (2 jobs) | `@v6` (v6.1.0, `node24`) + 1 cache npm explícito |
| `shivammathur/setup-php` | `@v2` | **mantido** — a major já resolve para 2.40.0 / `node24` |
| `lycheeverse/lychee-action` | `@v1` + `env: GITHUB_TOKEN` | `@v2` (v2.9.0, composite) + input `token` |
| `runs-on` | `ubuntu-latest` × 5 | `ubuntu-24.04` × **6** (pinado) |
| Jobs | 5 | **6** (novo `repo-guards`) |
| Node do projeto | `20` | matrix `['20','22']`, `fail-fast: false` |
| Determinismo | — | `concurrency` + `timeout-minutes` em todos os jobs |
| Guardas | manuais | `verify-deps-layout.sh`, `verify-checksums.sh`, `verify-ci-workflow.sh` no CI |

Todas as versões foram obtidas das **releases oficiais** via API do GitHub e os inputs conferidos nos `action.yml` das respectivas majors — nenhuma tag foi inventada. `permissions: contents: read`, a ausência de `defaults.run` global e os 2 `working-directory` (Composer/PHPStan) foram preservados da ADR 0081.

### Por que `ubuntu-24.04` e não `ubuntu-26.04`
A imagem 26.04 já está disponível, mas a migração do label `ubuntu-latest` é gradual e a imagem nova remove ferramentas pré-instaladas. Pinar 24.04 congela o comportamento conhecido durante a janela; a passagem para 26.04 deve ser **deliberada e precedida de um run verde**, após 19/11/2026. Não reintroduzir `ubuntu-latest`.

### Por que matrix 20 + 22
O `package.json` declara `engines.node >= 20` e o sandbox de validação só tem Node 20. A matrix mantém o mínimo suportado e faz o próprio CI produzir evidência para o LTS 22, sem declarar compatibilidade com Node 24 que não foi testada.

---

## 3. Novo auditor: `tools/verify-ci-workflow.sh`

Read-only, bash puro (sem dependência de PyYAML), aceita um caminho alternativo como argumento para auditar candidatos. Verifica: `permissions`, `concurrency`, ausência de `defaults` global, `timeout-minutes` em todos os jobs, runner pinado, majors das actions, presença dos 3 guardas no `repo-guards`, matrix 20+22 e — quando disponível — `yaml.safe_load`.

---

## 4. Validações executadas localmente

| Verificação | Resultado |
| --- | --- |
| `yaml.safe_load` do workflow novo | válido; 6 jobs; `concurrency` presente; sem `defaults` global |
| `bash -n` no novo auditor | OK |
| `bash tools/verify-ci-workflow.sh` sobre o **workflow novo** | **19 PASS / 0 FAIL**, `exit 0` |
| Mesmo auditor sobre o **workflow antigo** (`510640c68`) | **5 PASS / 14 FAIL**, `exit 1` (ubuntu-latest, majors v4/v1, sem guards/timeouts/concurrency) — prova A/B de que o contrato detecta a regressão |
| `yaml.safe_load` do YAML novo | 6 jobs, todos `ubuntu-24.04`, `concurrency`, sem `defaults` global |
| `bash tools/verify-checksums.sh` em réplica com os arquivos reais do GitHub | 37 OK / 0 divergentes |
| `verify-deps-layout.sh` (script **inalterado** nesta frente) | 18 PASS / 0 FAIL / 1 WARN no repositório Git de prova do F11-02 (índice limpo) |
| `bash -n` em `tools/verify-ci-workflow.sh` | OK (nenhum arquivo `.js`/`.cjs` foi alterado nesta frente) |
| `tools/*smoke.js` (30) e `f9-57-static-battery.py` (335 checks) | **não reexecutados aqui**: esta frente não toca em código PHP/JS, e a réplica temporária usada nas frentes anteriores não persistiu entre sessões. Os resultados verdes das frentes F11-01/F11-02 permanecem válidos para o código inalterado. |

### ⚠️ Não executado / limitações
- **`lychee-action@v2` não rodou em CI real aqui** (ação externa, sem execução local). A primeira execução no GitHub deve ser acompanhada; o input `args` foi preservado e apenas o mecanismo de token mudou (`env` → input `token`).
- **Node 22 e 24 não estão disponíveis no sandbox** ( apenas Node 20.20.2 ); a compatibilidade da perna 22 da matrix será comprovada pelo próprio CI após a aplicação.
- PHP, Composer, PHPStan, `php -l`, browser, banco, staging, produção e deploy **não** foram executados localmente (ferramentas ausentes); os resultados verdes citados do CI atual vêm dos check-runs reais do GitHub.
- **Nenhuma escrita no GitHub**: sem commit, push, PR, PR template remoto, migration ou deploy. A aplicação é feita pelo responsável, como nas frentes anteriores.
- O projeto segue pausado em F9-103; F9-27 permanece `BLOCKED_NOT_RUN`; os 36 checkers `tools/e2e` com saída não-zero seguem pré-existentes e fora deste escopo.

---

## 5. Documentação atualizada no mesmo pacote

`agents-ia/decisoes/0082-*.md` (nova ADR) · `agents-ia/decisoes/README.md` · `docs/CHANGELOG.md` · `docs/Guia-Composer.md` §5 · `README.md` · `CONTRIBUTING.md` · `.github/pull_request_template.md` · `docs/F11-04-Modernizacao-CI.md` · `F11-04-INDICE.md`.

## 6. Aplicação

Extrair o ZIP na raiz do clone e confirmar:

```bash
bash tools/verify-ci-workflow.sh                 # contrato do workflow
bash tools/verify-checksums.sh                  # integridade dos manifests
bash tools/verify-deps-layout.sh                # indice do Git
git diff --stat
```

Após o push, acompanhar o primeiro run: 6 jobs (sendo `node-deps` em 2 pernas), 0 annotations de Node 20 / `ubuntu-latest`, e `docs-links` verde com a v2 do lychee.

**Passo seguinte datado:** após 19/11/2026, testar `runs-on: ubuntu-26.04` em um run e só então promover o pin — com evidência de CI verde.
