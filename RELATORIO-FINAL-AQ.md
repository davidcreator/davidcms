# Relatório Final — Frente AQ (Roteiro dos próximos passos)

**Data:** 2026-11-06 · **HEAD:** `179269fea36584076f9ef41b95ae94638dd08f45`
**Tipo:** pacote de **planejamento/leitura**, não de código.

## Objetivo

Converter as sugestões pós-AP num roteiro verificável, ancorado nos contratos
**reais** do HEAD, sem executar runtime, sem alterar contrato, sem escrever no
GitHub e sem declarar release.

## Estado confirmado

- AP aplicada de forma fiel: +9 / −0 / ~6 vs AO; **6/6 arquivos substituídos
  byte a byte idênticos**; tree 3.218 entradas / 2.635 blobs / `truncated:false`.
- **Veredito visual ainda pendente** — `image-9.png` continua como reprovação
  vigente. Aplicar ≠ aprovar (AA e AO também foram aplicadas e reprovadas).
- `PENDING_DECISION: 10` / `DECIDED: 0`; F9-27 `BLOCKED_NOT_RUN`;
  F9-18 `release_ready:false` com 2 P1 + 2 P2 abertos;
  F9-25 `PROPOSAL_PENDING` / `DESIGN_GATE_PENDING`;
  F9-26 `wcag_conformance_claimed:false`; F9-14 `not_integrated`.

## Correção de inconsistência

`copy(__kazHealth())` foi citado como pendência em 11 relatórios desde a AN,
mas **não existe no código**: varredura de 464 JS/PHP/Twig e 460
`.md`/`.json`/`.txt` do HEAD encontrou **0 ocorrências em código**. Era
formulação de relatório. Este pacote entrega `tools/health-admin-ap.js` —
health **observacional, somente leitura**, com `node --check` válido — no
lugar da instrução inexistente.

Outra ressalva descoberta: `ci_status: "7/7 success"` em
`decision-matrix.json` refere-se a runs **anteriores**
(`41483802295`/`41504039830`), não ao CI de `179269fe…`.

## Conteúdo do roteiro

1. **Passo 1 (bloqueante):** veredito visual, checklist de 4 pontos, critério
   de saída e desdobramentos aprovado/reprovado.
2. **Passo 2:** health observacional + CI remoto (6 jobs do `php.yml`;
   `repo-guards` chama `repo-health.sh`; `SKIP` ≠ `PASS`).
3. **Passo 3:** pauta das 10 `PENDING_DECISION` agrupadas por risco, política
   literal de "nenhuma decisão por inferência", e o estado real de
   F9-27 / F9-18 / F9-25 / F9-26 / F9-14.
4. Dependências, passos 4 e 5 (fora do roteiro imediato) e o que não fazer.

## Validação deste pacote

- Leitura de 13 arquivos de contrato/CI do remoto, sem cache.
- Varredura de 924 arquivos para localizar `kazHealth`.
- `node --check` do snippet de health: **OK**.
- Extrato de contratos transcrito, sem interpretação.
- ZIP com CRC verificado e `.sha256`; `sha256sum -c` conferido.

## Limites

PHP WASM, Chromium headless, CI verde, health local e este roteiro **não
significam release aprovada**. CI remoto não verificado (`403`), sem
`copy(__kazHealth())` de produção (função inexistente), sem sessão
autenticada, MySQL, staging, backend/DB/migration ou ciclo
salvar–Publicar. `release_approved:false`, F9-27 `BLOCKED_NOT_RUN`,
F9-18 `release_ready:false`.

**O passo 1 depende do usuário.** Sem ele, nenhum dos passos seguintes
autoriza conclusão de release.
