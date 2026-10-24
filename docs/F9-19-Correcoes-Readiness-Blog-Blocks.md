# Fase 9 · Tarefa 19 — Correções de prontidão do Blog Blocks

**HEAD-base lido:** `b4ef8cc2f6cc398eea6e9a80f8ea81c7d3b0f1f6` (`docs(f9-18): add f9-18 static review deliverables`).  
**Escopo:** correções locais das quatro findings da F9-18; pacote pontual de arquivos, sem escrita GitHub.

## Correções propostas no pacote

1. **F918-01 (P1), detecção do sidecar:** tabela ausente só é reconhecida quando a query a `information_schema` retorna com sucesso zero linhas. Exceção/non-row resultado vira `query_error` para o controller, que usa o ramo bloqueado; não libera o HTML legado `|raw`.
2. **F918-02 (P1), URL de imagem:** renderer recebe base pública confiável do controller e resolve paths locais allowlisted contra essa base. Sem base, normaliza para path root-relative. Base com esquema/host inválidos não é utilizada.
3. **F918-03 (P2), seleção de idioma:** o catálogo consulta primeiro o sidecar do idioma ativo (incluindo documento válido vazio) e só tenta o idioma de fallback depois de ausência confirmada da linha ativa. Erro de consulta não inicia fallback.
4. **F918-04 (P2), exclusão:** remoção do sidecar, post, descrições e keywords SEO passa a usar uma transação com rollback em falha.

## Arquivos no pacote

Os arquivos de código sob `dev/` são cópias pontuais para aplicar/revisar contra o HEAD; não são commit, não foram enviados ao GitHub e não devem ser copiados sem revisão do delta. `tests/fixtures/f918_regressions.json` contém dados sintéticos para os casos de regressão. O verificador em `tools/e2e` é leitura estática.

## Prontidão e limites

A correção de fonte não equivale a confirmação de runtime. **Não executar PHP, SQL, migration, browser ou aplicação nesta entrega.** A migration F9-12 não foi aplicada nem presumida. Antes de merge/deploy, revisar o diff e obter autorização para validar num ambiente isolado, particularmente transações, metadata permission e URLs em subdiretório.
