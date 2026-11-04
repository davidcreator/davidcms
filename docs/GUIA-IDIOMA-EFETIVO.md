# Idioma efetivo unificado (Frente P)

Duas limitações declaradas conviviam desde as frentes K e L:

1. **K:** "`startup/language` roda **depois** de `startup/theme`" — o visitante
   que já tinha idioma na sessão (ex.: navegou antes noutro host com o mesmo
   cookie) carregava esse idioma para o novo site.
2. **L:** por causa da mesma ordem, `context['language']` usado pelo
   `ConditionEngine` era o default do configfile — uma regra
   `language eq en-us` nunca casava quando o idioma vinha do domínio ou da URL.

A frente P fecha as duas com **uma única fonte de verdade**.

## O que muda

`Reamur\System\Library\LanguageNegotiator` (novo, classe **pura**, sem IO)
centraliza a precedência da K:

```
?language=  >  sessão (do MESMO site)  >  idioma do site  >  default do painel
```

`startup/theme` chama `LanguageNegotiator::resolve()` **antes** de montar o
contexto e o `ThemeSnapshot`, publica `catalog_theme_language_effective`,
`catalog_theme_language_source`, `catalog_theme_language_site_changed`, grava
a sessão e injeta o código em `context['language']`. `startup/language`
passa a **aplicar** esse resultado (serviço `Language`,
`language_directory`, origem allowlistada) sem decidir de novo.

## Arquivos (4 completos + 2 guias)

| Arquivo | Tipo |
|---|---|
| `dev/system/library/language_negotiator.php` | **novo** — resolvedor puro |
| `dev/catalog/controller/startup/theme.php` | completo |
| `dev/catalog/controller/startup/language.php` | completo |
| `docs/GUIA-IDIOMA-EFETIVO.md` | **novo** |
| `docs/GUIA-CONDICOES-POR-SITE.md` | completo (limitação removida) |
| `docs/GUIA-IDIOMA-POR-SITE.md` | completo (limitação removida) |

## Compatibilidade e segurança

- **Ordem das pré-ações não muda**:
  `['startup/setting','startup/theme','startup/language']` em
  `dev/system/config/catalog.php` permanece intacto.
- **Precedência da K preservada** e `catalog_theme_language_source` continua
  em `{request, session, site, default}` — o badge do Admin (K) e o
  `language_directory` usado pelo `<head>`/`hreflang` (N) não mudam de forma.
- **Nenhuma query nova**: a leitura `getEnabledCodes()` migra de
  `startup/language` para `startup/theme`; continua sendo **1 SELECT**.
  Nenhuma escrita em banco, arquivo, cookie novo ou cron. A única escrita
  nova é a chave de sessão `language_site` (allowlistada, junto de `language`).
- **Fail-closed:** códigos fora de `pt-br|en-us|es-eu` ou não habilitados no
  painel são descartados; `startup/language` sem
  `catalog_theme_language_effective` cai no default do painel/configfile.
  Nenhum texto livre do `HTTP_HOST` entra na decisão: o site vem de
  `ThemeSites::match()` (J/O) e o idioma do site da política **publicada**.
- Sessões antigas sem `language_site` continuam valendo (zero invalidação
  retroativa). Trocar de site com a mesma sessão passa a usar o idioma do
  site; `?language=` continua soberano e é sempre gravado.
- Classe nova resolvida pelo **`Autoloader` real** (`Reamur\System` →
  `DIR_SYSTEM`), validada em harness. Sem `|raw`, `<script>`, `fetch`,
  `eval`, `innerHTML`, migration, schema, manifesto, contrato, global Twig,
  chave de tradução, CSS, JS, vhost/DNS ou escrita remota.

## Limitações honestas

**Resolvido pela frente S:** a negociação continua acontecendo **antes** da
decisão de rollout, com a política **publicada** (necessário para não criar
ciclo, mesmo critério da O). Depois da decisão, `LanguageNegotiator::alignSite()`
realinha o código ao idioma do **mesmo ID de site** no config efetivo — mas
**somente** quando a origem é `site`; `?language=`, sessão e default do painel
continuam soberanos. A frente R já havia alinhado o ID do site. Com isso
`language_directory`, `context['language']`, `<html lang>`, `og:locale` e
`catalog_theme_site_language` passam a descrever o mesmo config. Sem
divergência entre publicado e rascunho, nada muda.

Sem CMS completo, MySQL, sessão autenticada,
browser com rede, DNS/vhost, E2E, staging, deploy, PHP nativo, Composer,
PHPStan ou escrita no GitHub: validação com PHP 8.5 WASM, Twig 3.14 e
jsdom com mocks, que **não são um servidor**. CI remoto não verificado.
**CI verde / health verde ≠ release aprovada.**

Aplicar os 6 arquivos completos deste pacote e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
