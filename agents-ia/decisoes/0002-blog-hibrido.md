# ADR 0002 — Blog: híbrido (externo no lançamento, interno depois)

| | |
| --- | --- |
| **Status** | ✅ aceita |
| **Data** | 2026-10-15 |
| **Proponente** | humano (David L. Almeida) |

## Contexto

O blog atual vive em WordPress, em domínio separado ([davidcreator.com](https://davidcreator.com)), e alimenta a seção "Latest News" do site. A [Análise](../../docs/Analise-DavidAlmeida.md) apontava duas opções: internalizar no ReamurCMS (`catalog/controller/cms/`) ganhando SEO no domínio principal, ou manter externo evitando trabalho de migração no curto prazo (lacuna L4 da Auditoria).

## Decisão

**Blog híbrido:**

1. **No lançamento (Fases 3–6):** o blog permanece em davidcreator.com; o novo site exibe os últimos posts via integração de leitura (feed/API do WordPress), como hoje;
2. **Fase posterior (pós-lançamento):** o blog migra para dentro do ReamurCMS como módulo do grupo `cms/`, com importação dos posts e redirects 301;
3. **Nenhum agente cria** módulo/tabela de blog antes da fase de migração interna.

## Alternativas consideradas

1. **Internalizar já no lançamento** — rejeitada: atrasa a Fase 3 (importação de posts + tematização de blog) sem ganho imediato de receita;
2. **Manter externo para sempre** — rejeitada: dilui autoridade SEO do domínio principal a longo prazo.

## Consequências

- ✅ Escopo do lançamento preservado (blog não entra no caminho crítico);
- ✅ SEO do legado mantido (nada quebra no domínio do blog);
- ⚠️ Renúncia conhecida: o benefício SEO do conteúdo no domínio principal só chega na migração posterior;
- ⚠️ Na migração: importador WordPress → tabelas `blog_post`/`blog_category` (ou reaproveitar `page`) via ADR novo + redirects 301.

## Documentos atualizados

- `docs/Analise-DavidAlmeida.md` (§2.2 notícias, §7 mapeamento blog, §8 Fase 3 decisão)
- `docs/Estrutura-de-Pastas.md` §3 (grupo `cms/` menciona blog apenas como etapa futura)
