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
| [0001](0001-idioma-padrao-en-us.md) | Idioma padrão do sistema: `en-us` | ✅ aceita | 2026-10-15 |
| [0002](0002-blog-hibrido.md) | Blog híbrido (externo no lançamento, interno depois) | ✅ aceita | 2026-10-15 |
| [0003](0003-ci-e-gitignore.md) | Correção do CI e do `.gitignore` | ✅ aceita | 2026-10-15 |
| [0004](0004-contratos-qualidade-e-modelos.md) | Contratos de fundação e infra de qualidade (L1–L10) | ✅ aceita | 2026-10-15 |
| [0048](0048-f8-04-sale-marketing-filtros-paginacao-csrf.md) | F8-04: sale/marketing, filtros, paginação e CSRF | ✅ aceita | 2026-10-22 |
| [0049](0049-f8-05-blog-media-library.md) | F8-05: biblioteca de mídia para capas do Blog | ✅ aceita | 2026-10-22 |
| [0072](0072-threejs-scenes-admin-system.md) | Configuração declarativa das cenas Three.js nativas via System e Admin | ✅ aceita | 2026-10-26 |
| [0073](0073-threejs-runtime-system-asset-gateway.md) | Runtime Three.js canônico no System com entrega allowlisted pelo Catalog | 🟡 aprovada para implementação incremental | 2026-10-26 |
| [0074](0074-threejs-shared-scene-runner.md) | Ciclo de vida compartilhado para cenas Three.js e thumbs declarativas | 🟡 aceita para implementação incremental | 2026-10-26 |
| [0075](0075-cena-2-hero-logo-glb-placeholder-procedural.md) | Cena 2 do Hero: logo GLB servido pela allowlist do runtime | 🟡 aceita para implementação incremental | 2026-10-26 |
| [0076](0076-cena-3-etapa-2-galeria-3d-do-portfolio.md) | Cena 3 etapa 2 do Portfólio: galeria 3D opcional sobre a grade | 🟡 proposta (especificação pós-lançamento) | 2026-10-27 |
