# Diagnóstico — CI vermelho no HEAD `accb6257…`

**Data:** 2026-11-06 · **Run:** `42232750836` · **Leitura e simulação local.**
Nenhuma escrita no GitHub, nenhuma alteração de guarda, nenhuma decisão de
release.

## 1. O que aconteceu

Entre o HEAD avaliado no veredito (`1b7724a1…`) e o HEAD atual houve **dois
commits**:

| Commit | Mensagem |
|---|---|
| `aeafbb042d4f26d19332b29f05827dc5724c0a10` | `docs: add AP front-end visual approval checklist` |
| **`accb625768424e2deae4b6054721602a64097323`** | `chore: archive legacy F11 deliverables and finalize AU approval` |

O segundo **aplicou a Frente AU** (+6 arquivos) e, no mesmo commit,
**removeu 41 arquivos** "legados F11":

| Categoria | Quantidade |
|---|---|
| `CHECKSUMS*.txt` (14 manifestos) | 14 |
| `F11-*-INDICE.md` | 15 |
| `RELATORIO*.md` / `INDICE-*.md` / `RELATORIO.md` / `CONTRIBUTING.md` | 12 |
| **Total** | **41** |

Árvore: 3.194 entradas / 2.611 blobs / `truncated: false`.

## 2. CI do HEAD atual

| Check | Resultado |
|---|---|
| `composer`, `php-lint`, `static-analysis`, `node-deps (20)`, `node-deps (22)` | ✅ success |
| **`docs-links`** | ❌ **failure** (exit 2) |
| **`repo-guards`** | ❌ **failure** (exit 1) |

## 3. Causa raiz (reproduzida localmente)

Réplica fiel do HEAD: **2.611 blobs / 42.833.718 bytes / 0 divergentes**.

### 3.1 `repo-guards`

`tools/verify-checksums.sh:54-55`:

```bash
if [ "$MANIFESTS" -eq 0 ]; then
  echo "  FAIL  nenhum CHECKSUMS*.txt encontrado"; exit 1
fi
```

Sem os 14 manifestos, o guarda falha imediatamente. `tools/repo-health.sh`
agrega **5 PASS / 1 FAIL → REPROVADO**.

Após restaurar **apenas** os 29 arquivos (14 manifestos + 15 índices F11),
ainda restaram **2 FAIL**:

```
FAIL  CHECKSUMS-F11-04.txt: arquivo ausente CONTRIBUTING.md
FAIL  CHECKSUMS.txt:        arquivo ausente CONTRIBUTING.md
resumo: 3 PASS / 2 FAIL | 14 manifesto(s), 140 arquivo(s) conferidos
```

Ou seja, `CONTRIBUTING.md` é referenciado por **dois** manifestos — restaurar
só os manifestos não basta.

### 3.2 `docs-links`

`docs/README.md` contém **7 links relativos mortos** para índices F11
removidos: `../F11-01-INDICE.md`, `F11-02`, `F11-05`, `F11-07`, `F11-08`,
`F11-09`, `F11-10`. O job roda `lycheeverse/lychee-action@v2` sobre
`./**/*.md` (`.github/workflows/php.yml:94-107`).

## 4. Correção verificada (simulação, não aplicada)

Restaurando **os 41 arquivos** exatamente como estavam no commit pai
`aeafbb04…`:

```
verify-checksums.sh : rc=0 · 14 manifesto(s), 142 arquivo(s) conferidos, 0 divergentes
repo-health.sh      : rc=0 · 6 PASS / 0 FAIL / 0 SKIP · COERENTE (guardas estaticos)
links Markdown      : 0 quebrados (374 .md varridos, excluindo node_modules/ckeditor de terceiros)
```

Os demais guardas (`verify-deps-layout`, `verify-ci-workflow`, `verify-f11-01`,
`verify-contract-freeze`, `release-readiness-report`) já estavam verdes e
permanecem verdes.

## 5. Duas vias — decisão do responsável

### Via A — **RECOMENDADA**: restaurar os 41 arquivos

É o que este pacote entrega, pronto para aplicar. **Não relaxa nenhum guarda**,
não altera `docs/README.md`, não altera `.github/workflows/php.yml`, não
reescreve hashes e não converte status. Os arquivos são cópias fiéis do pai
`aeafbb04…` (conferidas por SHA-256 em `evidencia/sha256-restaurados.txt`).
A intenção de "arquivar" os legados F11 pode ser preservada **fora do
repositório** (backup/tag), sem quebrar a integridade do CI.

### Via B — manter a limpeza (exige 3 mudanças e **não** é recomendada)

1. Remover os 7 links mortos de `docs/README.md`.
2. Remover `CONTRIBUTING.md` das entradas de `CHECKSUMS.txt` e
   `CHECKSUMS-F11-04.txt` (ou restaurar só esse arquivo).
3. Alterar `tools/verify-checksums.sh` para **tolerar zero manifestos** —
   isso **desliga uma verificação de integridade**. Pelas restrições vigentes
   ("não relaxar segurança"), esta via **só com autorização explícita do
   responsável** e não foi preparada por este pacote.

## 6. O que este diagnóstico NÃO faz

Não aplica nada no GitHub, não executa deploy, não altera contrato congelado,
não converte `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, não decide as
10 `PENDING_DECISION`, não reabre F9-18/F9-27 e não declara release.

**Simulação local ≠ CI remoto verde.** Depois de aplicar a Via A, conferir o
novo run em Actions. `release_approved: false` · F9-27 `BLOCKED_NOT_RUN` ·
F9-18 `release_ready: false` · 10 `PENDING_DECISION`.

O veredito **APROVADO** da AP continua válido: entre a AP e o HEAD atual
**nenhum arquivo de `dev/` foi modificado** — esta falha de CI é de
documentação/manifestos, não de comportamento visual.
