# Matriz hreflang multilíngue (Frente N)

O `common/sitemap` (F4-07 / ADR 0031) documentava explicitamente: *"matriz
hreflang é tarefa futura, renúncia na ADR"*. A frente N implementa essa tarefa
pendente, aproveitando que a K tornou o idioma por domínio efetivo e que o
switcher F2-3/F4-02 já lista os idiomas **ativos**.

## O que é entregue

**No `<head>` do Catálogo** (`common/header.php` + `common/header.twig`):

```html
<link rel="canonical" href="https://exemplo.com/contato">
<link rel="alternate" hreflang="pt-br" href="...?language=pt-br">
<link rel="alternate" hreflang="en-us" href="...?language=en-us">
<link rel="alternate" hreflang="es-eu" href="...?language=es-eu">
<link rel="alternate" hreflang="x-default" href="https://exemplo.com/contato">
<meta property="og:locale:alternate" content="pt_br">  (uma por idioma)
```

**No `sitemap.xml`**: `<urlset>` ganha `xmlns:xhtml` e cada `<url>` recebe um
`<xhtml:link rel="alternate" hreflang="...">` por idioma ativo mais o
`x-default`. O `&` das URLs é escapado como `&amp;` pelo autoescape do Twig.

## Regras de segurança

- A origem das URLs vem de **`site_url`** (config do painel) via
  `requestOrigin()` — **nunca** de `HTTP_HOST`. O canonical honesto (F4-07)
  continua sendo a única base e só existe em **caminho limpo**: com rota suja
  (`index.php`), `hreflangs` fica vazio e **nenhum** alternate é emitido
  (fail-closed). `x-default` aponta para o próprio canonical.
- Só entram na matriz os códigos allowlistados `pt-br` / `en-us` / `es-eu`
  **e** presentes em `getEnabledCodes()` (`status=1` + diretório real). Nenhum
  parâmetro do request é copiado para a URL — apenas `language=<codigo>`.
- No `<head>` a lista é a **mesma** do switcher: **zero query nova**. No
  sitemap há **um SELECT de leitura** adicional (`getEnabledCodes()`); nenhuma
  escrita, migration, coluna, tabela, cookie, cron, sessão ou arquivo.
- Tudo é autoescapado: nenhum `|raw`, `<script>`, `fetch`, `eval`, `innerHTML`,
  CSS, JS, schema, manifesto, global Twig ou chave de tradução novo.

## Compatibilidade e limites

Sem canonical, o HTML é **idêntico** ao das frentes A–M. O `sitemap.xml`
mantém as mesmas `<loc>`, `lastmod`, `changefreq`, `priority`, o dedup por
`language_id=1` e o filtro de entidade ativa — só ganha os elementos
`xhtml:link`. Os 14 `CHECKSUMS*.txt` permanecem intactos.

**Limitação de arquitetura:** o CMS usa `?language=` (Adendo F2-3 do ADR
0001), então as variantes são URLs com query, não paths/hosts distintos. Isso é
honesto para o desenho atual; `hreflang` entre domínios exigiria DNS/vhost,
fora de escopo. Idiomas extras eventualmente habilitados no painel aparecem
no switcher, mas não entram na matriz (allowlist dos 3 códigos do core).
O `x-default` aponta para a URL sem `language`, que resolve pelo default do
painel ou pelo idioma do site (frente K). Os valores de `og:locale` e
`og:locale:alternate` seguem o `str_replace('-', '_')` já usado pelo F4-07
(`pt_br`), sem alterar o metadado legado.

Aplicar os 5 arquivos completos deste pacote (o guia é novo) e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
