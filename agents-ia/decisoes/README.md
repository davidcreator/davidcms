# 🗂️ decisoes — Registro de Decisões Arquiteturais (ADR)

Toda decisão relevante do ReamurCMS fica registrada aqui, em um arquivo por decisão, com numeração sequencial. **Nenhuma decisão arquitetural vive só na memória ou no chat.**

## Quando criar um ADR

- Escolha entre alternativas arquiteturais (ex.: "blog dentro do `cms/` × módulo próprio");
- Exceção a uma regra de `agents-ia/regras/`;
- Renúncia de escopo com impacto técnico (ex.: "blog externo por ora");
- Mudança de convenção, de nome, de estrutura de pastas;
- Mudança de assinatura pública do núcleo (quebra de compatibilidade).

## Template

Copiar para o novo arquivo `NNNN-titulo-curto.md`:

```markdown
# ADR NNNN — Título da decisão

| | |
| --- | --- |
| **Status** | proposta · aceita · rejeitada · substituída por ADR XXXX |
| **Data** | AAAA-MM-DD |
| **Agente proponente** | system / install / admin / catalog / orquestrador / humano |
| **Contexto** | (qual problema forçou a decisão — 3-6 linhas) |

## Decisão

(A opção escolhida, em 1-3 linhas objetivas.)

## Alternativas consideradas

1. **Alternativa A** — por que não;
2. **Alternativa B** — por que não.

## Consequências

- ✅ Positivas...
- ⚠️ Negativas / cuidados...

## Documentos atualizados

- `docs/...` · `agents-ia/regras/...`
```

## Índice

| ADR | Título | Status | Data |
| --- | --- | --- | --- |
| — | (nenhum ADR registrado ainda) | — | — |
