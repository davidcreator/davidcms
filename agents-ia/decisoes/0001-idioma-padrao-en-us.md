# ADR 0001

## Adendo F2-3 (2026-10-16) — mecanismo de seleção de idioma

- **Decisão:** a escolha do idioma da requisição é da pré-ação **`startup/language`** (mesmo código nas 3 apps, por contrato). Ordem: `?language=codigo` validado **contra os diretórios que existem** em `{app}/language/` → persistido na sessão → senão a sessão → senão o padrão (`en-us`);
- **Fonte da verdade = filesystem**: nasceu o diretório `xx-yy` com os arquivos, o idioma entra no ar sem tocar em código. A tabela `rms_language` (seed presente) entra em cena quando a UI de gerenciamento de idiomas chegar ao admin — a pré-ação **não toca no banco** (install app tem `db_autostart=false` deliberado);
- **Fallback silencioso**: código inválido ou ausente nunca explode erro — cai para sessão/padrão;
- **Mecânica**: substitui o serviço `language` montado pelo framework e atualiza `config.language_directory` da requisição;
- **Conteúdo F2-3**: os 12 arquivos de language en-us (install ×6, catalog ×2, admin ×4) ganharam **pt-br e es-eu completos**, com validador de paridade tripla (mesmas chaves por rota em en-us/pt-br/es-eu + paridade das 404 entre apps);
- **Pendências declaradas:** atributo `<html lang>` dinâmico chega junto do header/footer compartilhado (F2 blocos comuns); switcher de idioma na UI (hoje a troca é via `?language=`); tradução dos textos da seed `rms_language` / campos dinâmicos. — Idioma padrão do sistema: `en-us`

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
