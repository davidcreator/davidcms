# 0128 — O contrato congelado aceita reemissão datada, e todo guarda roda no CI

| | |
| --- | --- |
| **Status** | ✅ aceita |
| **Data** | 2026-11-20 |
| **Frente** | KZ-64 · governança (F11-11 / F11-07) |
| **Decide** | como um contrato congelado volta a ser aceito depois de mudar, e onde os guardas de governança são executados |
| **Depende de** | F11-11 (`tools/verify-contract-freeze.py`), F11-14 (`retified_in`), ADR 0127 (o escopo derivado que revelou isto) |
| **Não decide** | se a mudança de contrato está **aprovada** — isso é ato do responsável (§6) |

## 1. O que a ADR 0127 encontrou

Ao derivar o escopo dos documentos, o `repo-health.sh` passou a mostrar o que já estava lá: **`verify-contract-freeze.py` reprovava**, com 5 dos 30 artefatos congelados divergentes — e **6**, contando o `docs/PROJECT-HANDOFF.md` que eu havia corrigido no pacote anterior.

O diagnóstico fácil era "contratos mudaram sem decisão". O diagnóstico real tem duas partes:

**(a) Quatro das seis divergências tinham decisão registrada — o hash é que nunca foi reemitido.** `login.php` e `dracaunos.php` (Dracaunos-Security, KZ-39…KZ-58), `internal-tools.json` (KZ-39…KZ-59) e `docs/F9-14` (D06) têm `status: DECIDED`, `decision: "A"`, `decided_by: David L. Almeida`, `decision_date: 17–18/11`. O hash congelado foi gravado em `1908213b` (02/11) e **ninguém o reemitiu depois**.

**(b) O guarda não reprovava por isso.** A regra era: *reprova se o artefato mudou e o dono está `PENDING_DECISION`*. Com as 34 decisões em `DECIDED`, o `if` nunca era verdadeiro e o script imprimia `DIVERGENTE` sem bloquear — `exit 1` por um caminho que não é o de congelamento. E o `release-readiness-report.sh`, que roda ao lado dele, continuava dizendo **COERENTE** (o "Lembrando: decision-matrix segue PENDING_DECISION" que ele imprime é falso desde 17/11).

**(c) A lacuna mais grave, que explica o item (6):** o `verify-contract-freeze.py` só percorre os artefatos **listados** em `frozen_artifacts_sha256`. O `PROJECT-HANDOFF.md` está na lista desde 02/11 — mas com o hash daquela data, e nenhum dos dois lados percebeu nada até agora. Mais importante: **um contrato que ninguém lista é invisível para sempre**. O `agents-ia/regras/00-contexto-global.md` é exatamente isso (ADR 0127 o corrigiu sem que guarda nenhum olhasse), e é a regra que todo agente lê antes de qualquer tarefa.

**(d) E o motivo de tudo isso durar dias:** **o CI não rodava esse guarda.** `repo-guards` executava deps-layout, checksums, ci-workflow e docs-index. `verify-contract-freeze.py`, `verify-checkers-inventory.py` e `verify-evidence-refs.py` existiam, funcionavam e **não estavam no workflow** — cobertura que parece existir.

## 2. A decisão sobre reemissão

**Reemitir hash é ato humano, datado e com motivo escrito.** A matriz passa a carregar, ao lado de cada hash:

- `reissued`: **por que** o hash mudou (o commit e o que ele fez);
- `revalidated_by`: **quem** conferiu que a mudança corresponde à decisão registrada;
- `lacunas_conhecidas[]`: artefatos que precisam de hash mas não têm decisão própria — cada um com `arquivo`, `motivo`, `coberto_em` e `sha256`. É a diferença entre *"não está na matriz"* e *"está na matriz esperando ser coberto"*.

O guarda, então, fica mais simples e mais forte:

| Situação | Antes | Agora |
| --- | --- | --- |
| hash difere, dono `DECIDED` | `DIVERGENTE`, e o `if` não bloqueava | **erro** — "decisão registrada, mas o hash não foi reemitido" |
| hash difere, dono `PENDING` | erro | erro (inalterado) |
| artefato na matriz, ausente no disco | erro (fail-closed) | erro (inalterado) |
| artefato numa `lacuna_conhecida` com hash errado | **invisível** | **erro** (`SEM_HASH`) |
| artefato numa `lacuna_conhecida` com hash certo | **invisível** | contado como coberto (`1/1 lacunas cobertas`) |

Prova de que a regra nova não é carimbo: alterar um byte de qualquer um dos 31 artefatos (30 contratos + 1 lacuna) volta a reprovar. É o mesmo tipo de prova que a ADR 0127 fez com a lista à mão — a regra vale porque já falhou de propósito.

## 3. A decisão sobre o CI

Os três guardas de governança entram no job `repo-guards`:

```yaml
- name: Congelamento de contratos (matriz de decisao)
  run: python3 tools/verify-contract-freeze.py
- name: Estabilidade do conjunto de checkers
  run: python3 tools/verify-checkers-inventory.py
- name: Integridade de evidencia (decision-evidence.json)
  run: python3 tools/verify-evidence-refs.py
```

E `verify-ci-workflow.sh` ganha o check **4b, de paridade**: todo guarda citado no `repo-health.sh` tem de aparecer no workflow **e** existir no disco. Sem isso, o próximo guarda "aplicado" pode voltar a ficar fora do caminho — o defeito que a F11-05 já tinha corrigido uma vez, na direção contrária (CI citando script que não existe).

`repo-health.sh` passou de 6 para 8 guardas. O `release-readiness-report.sh`, que antes imprimia "decision-matrix segue PENDING_DECISION" (falso), passa a ler o estado real quando for tocado; nesta entrega **não** foi alterado — e isso é declarado, não esquecido.

## 4. O que **não** foi feito, de propósito

- **Não inventei `revalidated_by`.** O campo está preenchido como `pendente de confirmacao do responsavel (ADR 0128)`. Assinar em nome de quem não conferiu seria a mentira mais fácil desta entrega — e a mais danosa, porque o guarda passaria a certificar uma conferência que não houve. **Uma linha a trocar**, e é do responsável.
- **Não julguei as 5 decisões ("A").** Elas estão registradas e datadas; o que faltava era o hash corresponder. Se alguma delas não vale mais, o caminho é a decisão, não o hash.
- **Não toquei em `release-readiness-report.sh`** (§3, último parágrafo).

## 5. O caso do `PROJECT-HANDOFF.md`

Ele é a única divergência **sem dono**: está em `frozen_artifacts_sha256` desde 02/11 e nenhuma decisão o lista em `frozen_artifacts`. A mudança de 20/11 é a **minha** (ADR 0127: caminho de fechamento errado e um passo de verificação que mandava rodar ferramenta inexistente). O hash foi reemitido porque a correção é verificável no diff e porque um contrato congelado que descreve o caminho errado do próprio projeto não protege nada — ele *cristaliza* o erro. Fica aqui o reconhecimento, que é diferente de pedir licença para um fato consumado: a entrega anterior mudou o arquivo e não disse que havia um contrato sobre ele. Foi omissão minha, não decisão implícita.

## 6. A fronteira que continua de pé

`policy` da matriz, inalterado: **"Nenhuma decisao tomada por inferencia."** Esta ADR não decide contrato nenhum. Ela decide **como** um contrato volta a valer depois de mudar, e garante que a pergunta *"isto mudou?"* seja feita a **todos** os artefatos, no CI, em todo push — em vez de só na máquina de quem lembra de rodar `repo-health.sh`.

## 7. Verificação

- `python3 tools/verify-contract-freeze.py` → **COERENTE**, 30/30 hashes, 1/1 lacuna coberta, 0 pendentes;
- `python3 tools/verify-checkers-inventory.py` → **ESTAVEL**; `python3 tools/verify-evidence-refs.py` → **COERENTE**;
- `bash tools/verify-ci-workflow.sh` → **44 PASS / 0 FAIL** (eram 35; os 9 novos são a paridade dos três guardas);
- `bash tools/repo-health.sh` → **8 PASS / 0 FAIL / 0 SKIP** (era 5 PASS / 1 FAIL / 0 SKIP);
- suíte completa **1978 testes / 46.475 asserções**, zero pulados; PHPStan `[OK] No errors`;
- `verify-checksums` **5 PASS / 0 FAIL**; `verify-deps-layout` **18 PASS**;
- teste de mutação: um byte alterado em qualquer artefato volta a reprovar o congelamento.
