# 0130 — Artefato de estado deriva o número da fonte; quem transcreve, apodrece

| | |
| --- | --- |
| **Status** | ✅ aceita |
| **Data** | 2026-11-21 |
| **Frente** | KZ-64 · governança (F11-11 / F11-15 / F11-06) |
| **Decide** | como os artefatos de estado de `tools/` falam de decisão e de contrato congelado: lendo a matriz, nunca repetindo o número |
| **Não decide** | o conteúdo de nenhuma decisão, nem `release_approved`, nem `retified_in`, nem o `revalidated_by` da ADR 0128 |

## 1. A correção do meu próprio diagnóstico

Eu venho dizendo, desde a entrega do pkg29, que *"o `release-readiness-report.sh`
ainda imprime que a decision-matrix segue PENDING_DECISION"*. **Medido agora, não
é esse o arquivo.** O painel F11-06 não menciona a matriz em nenhum lugar — lê
`docs/F9-27-Status.json`, `F9-27-Preflight-Status.json`, `F9-18-Risk-Register.json`
e o handoff, e imprime o que cada um declara. A frase mora em
**`tools/verify-evidence-refs.py:63`**, dentro do `RESULTADO: COERENTE`:

```
Lembrando: recomendacoes NAO sao decisoes. decision-matrix.json segue PENDING_DECISION.
```

Repetir uma frase que li numa entrega anterior, sem reabrir o arquivo, produziu
exatamente o defeito que os guardas existem para pegar — em mim. Varri o
repositório pelo padrão e a podridão era **quatro sítios, não um**:

| Onde | O que dizia | Verdade medida em `82abf2f2` |
| --- | --- | --- |
| `tools/verify-evidence-refs.py` | *"segue PENDING_DECISION"* | 34 `DECIDED`, 0 `PENDING_DECISION` (desde 17/11) |
| `tools/decision-evidence.json` → `policy` | *"As 10 continuam PENDING_DECISION em decision-matrix.json"* | idem |
| `tools/contract-drift-audit.json` → `policy` | *"segue com 10 PENDING_DECISION; 11 contratos congelados"* | 0 pendentes; **30** contratos congelados |
| `docs/temas/GUIA-TEMA-{THREEJS-REFERENCIA,REFERENCIA,DAVID,ATLAS}.md` | *"10 `PENDING_DECISION`"* | idem |

Nenhum dos três em `tools/` era verificado contra a matriz, e os três são lidos
por guarda ou impressos no CI: `verify-evidence-refs.py` roda no `repo-guards`
no passo *Integridade de evidencia* e no `repo-health`. Ou seja: o log do CI
afirmava estado falso **enquanto verificava** a evidência das decisões.

## 2. A regra

**Artefato de estado em `tools/` não repete contagem: deriva.** A
`decision-matrix.json` é a fonte; quem transcreve o número congela o dia em que
ele foi verdade — e não existe aviso quando o dia passa.

Concretamente, em `tools/verify-evidence-refs.py`:

- a linha final passou a imprimir o que acabou de ler: `decision-matrix.json
  lido agora: 34 DECIDED / 0 PENDING_DECISION / 30 contratos congelados`. O
  número no log do CI fica confrontável com o arquivo, não com a memória de quem
  escreveu o script;
- entrou a checagem **`contagens_reescritas`**: sobre `tools/*.json` e o próprio
  script do guarda, todo par `<dígitos> PENDING_DECISION|DECIDED` e todo
  `<dígitos> contratos congelados` tem de bater com a matriz. **Errado é FAIL;
  ausente é o desejado** — o `policy` dos dois JSON passou a apontar para a
  fonte em vez de dizer número;
- o guarda ganhou argumento de **raiz candidata** (`python3
  tools/verify-evidence-refs.py [raiz]`), o mesmo convênio de
  `tools/verify-ci-workflow.sh` e, desde a ADR 0129, do `verify-docs-index.php`:
  sem isso o teste unitário só saberia aprovar o estado atual, que é o jeito
  elegante de não testar nada.

`docs/temas/GUIA-TEMA-*.md`: a contagem saiu e entrou a referência ao arquivo.
Não "corrigi 10 para 0" — isso seria plantar a próxima mentira do mesmo tipo.

## 3. Limite declarado, para não fingir cobertura

- **Documento `.md` fora de `tools/` não é auditado para contagem.** O
  `docs/CHANGELOG.md` é operacional pela classificação da ADR 0127 e diz *"a
  regra só bloqueava quando o dono estava `PENDING_DECISION`"* — frase sobre o
  passado, e correta. Distinguir "afirmação atual" de "registro histórico" por
  regex é fabricar falso positivo; a leitura pega, e foi ela que achou isto.
- **`Pauta-Decisoes-D01-D10.md`** (raiz) e `docs/historico/*` dizem
  `10 PENDING_DECISION / 0 DECIDED` e **ficam como estão**: `Pauta-*` e
  `docs/historico/` são registro pela regra da ADR 0127. Ali o número errado é
  a data do documento, não um bug.
- **`head`, `ci_status` e `retified_in` na matriz não foram tocados.** São
  registros datados — o `ci_status` diz expressamente *"pre-KZ-63 baseline"* ao
  citar o run, e `retified_in: F11-14` aponta para um pacote real cujo documento
  saiu da árvore (a fase `F11-14` nunca existiu: `docs/fases/f11/` vai até
  `F11-10`). Mexer nesses campos é reescrever histórico; se vier correção, é ato
  do responsável, não deste pacote.
- **`description` da matriz** fala em *"10 MARKER_OR_FINDING mantidos pelo
  F11-09"*: é a origem da matriz, não contagem de status — por isso a checagem
  nova não o pega, e não deveria.

- **O veredito do painel F11-06 continua uma linha escrita, e é de propósito.**
  `release-readiness-report.sh` fecha com `== Veredito INFORMATIVO: release NAO
  aprovada; gate BLOQUEADO ==` sem calcular nada. Diferente dos casos desta ADR:
  ali o número transcrito apontava para fora (a matriz mudou e a frase não
  acompanhou), enquanto o veredito é a afirmação *mais conservadora* possível —
  derivá-lo significaria permitir que o painel passasse a dizer "aprovada" se um
  JSON mudar de status por engano. O risco de manter é texto velho; o de derivar
  é gate aberto. Ficou, e está dito.

## 4. Por que isto é da frente de pagamento e não faxina

O painel F11-06 é o que diz se algo pode ir ao ar: `release_approved: false`,
F9-27 `BLOCKED_NOT_RUN`, F9-18 `release_ready: false`, 7 pré-requisitos, 10
`NOT_RUN` / 2 `BLOCKED` na matriz dele — nada disso foi alterado aqui, e **nada
deste pacote mexe em `dev/`**. Mas **D01** (R2/R3 — estorno pela tela) é uma das
decisões dessa matriz, e a frase falsa cobria justamente o estado delas: enquanto
o CI dissesse "segue PENDING_DECISION" para as 34 decisões já `DECIDED`, o número
a verificar e o número decidido seriam indistinguíveis na saída.

## 5. Verificação

Sob **PHP 8.2.36** (o piso do CI, ADR 0129), em `82abf2f2` + este pacote:

| Checagem | Antes | Depois |
| --- | --- | --- |
| `python3 tools/verify-evidence-refs.py` | COERENTE, com 3 afirmações falsas impressas/gravadas | COERENTE, 20/20 referências + **0 contagens reescritas**, números lidos da matriz |
| `bash tools/repo-health.sh` | 8 PASS / 0 FAIL / 0 SKIP | **8 PASS / 0 FAIL / 0 SKIP**, com o guarda verificando mais |
| Suíte | 1980 | **1982 testes**, os 2 novos em `StateArtifactsCoherenceTest` |
| `php -l` em `dev/` + `tests/` + `tools/` | 561 ok | **562 ok**, 0 reprovações em 8.2 |

**Teste de dente:** reintroduzir no `policy` do `contract-drift-audit.json` a
frase com `10 PENDING_DECISION` → o guarda imprime `DIVERGENTE
tools/contract-drift-audit.json: "10 PENDING_DECISION" — a matriz diz 0
PENDING_DECISION` e sai **exit 1**; o mesmo estado é exercitado pelo teste
unitário contra uma árvore candidata (`docs/` e `agents-ia/` por link), que
exige `EXIT:1`. E o `verify-contract-freeze.py` não apareceu na reprovação
porque nunca imprimiu contagem transcrita — desenho certo da entrega anterior,
que por isso deixou de fora o único arquivo que estava limpo.
