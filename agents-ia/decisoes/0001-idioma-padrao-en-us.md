# ADR 0001 — Idioma padrão do sistema: `en-us`

| | |
| --- | --- |
| **Status** | ✅ aceita |
| **Data** | 2026-10-15 |
| **Proponente** | humano (David L. Almeida) |

## Contexto

`system/config/default.php` declarava `language_code = 'en-gb'`, porém a estrutura de pastas criada no repositório é `en-us`, `es-eu` e `pt-br` — divergência que faria cada agente decidir de um jeito (lacuna L4 da [Auditoria](../../docs/Auditoria-Fundacao.md)). O site atual é majoritariamente em inglês, com público internacional e brasileiro.

## Decisão

1. **Idioma padrão do sistema e do novo site: `en-us`** (secundários: `pt-br`, `es-eu`);
2. Padronizar `default.php` para `language_code`/`language_directory` = `en-us` (não `en-gb`) na Fase 1;
3. **Regra de entrega de conteúdo:** arquivo de language obrigatório em **`en-us`** (completo); `pt-br` e `es-eu` podem entrar como pendência anotada no mesmo commit, mas nunca o contrário.

## Alternativas consideradas

1. **pt-BR padrão** — rejeitada: site atual já é em inglês e o alvo é público internacional; pt-BR segue como secundário prioritário;
2. **Manter `en-gb` e criar pastas en-gb** — rejeitada: multiplicaria pastas sem ganho; `en-us` já existe na estrutura e é o padrão do OpenCart moderno.

## Consequências

- ✅ Divergência `en-gb` × `en-us` eliminada por decisão única;
- ✅ Templates de language do `agents-ia/modelos/` já nascem em inglês (coerente);
- ⚠️ Textos do novo site precisarão de tradução pt-BR/es-EU antes do go-live nacional (Fase 3 checklist);
- ⚠️ URLs localizadas (Fase 6) usarão `/en/...` como canônico.

## Documentos atualizados

- `agents-ia/regras/00-contexto-global.md` (fatos + pendências)
- `agents-ia/modelos/language.php.tpl` (regra en-us primeiro)
- `agentes/agente-admin.md`, `agentes/agente-catalog.md` (prioridade de idioma)
- `docs/Arquitetura-MVCL.md` §9 · `docs/Guia-CMS.md` §6 · `docs/Guia-Composer.md` §6.2 · `docs/Analise-DavidAlmeida.md`
