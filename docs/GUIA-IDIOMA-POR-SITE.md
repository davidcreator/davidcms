# Guia — Idioma por Site (Frente K)

A **K** fecha uma lacuna da frente J: `startup/theme.php` já publicava
`catalog_theme_site_language` (o idioma configurado para cada host), mas o
`startup/language.php` decidia o idioma apenas por `?language=`, sessão e
default do painel — **o idioma do site nunca chegava a valer**.

> Não é uma frente do roadmap A→J (concluído). É a continuação funcional da J,
> no mesmo desenho: sem migration, sem coluna, sem tabela, sem query nova, sem
> cookie novo, sem cron e sem escrita no banco. Não toca em `schema.sql`, no
> BDREditor, no runtime WebGL nem em `custom_js`.

## Precedência

`startup/theme` roda **antes** de `startup/language` na lista de pre-ações
(`setting`, `theme`, `language`), então o idioma do site já está disponível.

| Ordem | Origem | Mudou na K? |
|---|---|---|
| 1 | `?language=codigo` validado contra idiomas ativos | Não — continua soberano |
| 2 | Sessão com idioma ainda ativo | Não — legado F2-3/F4-02 intacto |
| 3 | **Idioma do site** (host resolvido pela J) | **Sim — novo** |
| 4 | `config_language_catalog` / `language_directory` | Não |
| 5 | Primeiro idioma ativo, ou `en-us` | Não |

O `catalog_theme_language_source` publica apenas a origem allowlistada
(`request`, `session`, `site`, `default`) — útil para diagnóstico, sem nenhum
dado do visitante.

## Fail-closed

O idioma do site só é aceito se for **minúsculo**, estiver na allowlist
`pt-br` / `en-us` / `es-eu` **e** constar em `getEnabledCodes()`
(`status=1` no painel **e** diretório real). Qualquer divergência — inclusive
`catalog_theme_site_language` ausente, vazio ou de um host sem site ativo —
cai no default anterior. Nenhum valor do request é usado aqui: o host já foi
comparado e descartado pela J.

A única escrita é a já existente `$session->data['language']` do legado; a K
não cria cookie, sessão, coluna nem arquivo.

## Efeito visível

Com uma sessão nova, `loja.exemplo.com` abre no idioma configurado para aquele
site e `exemplo.com` no seu, sem `?language=` e sem intervenção do usuário. No
Admin, o card **Sites e domínios** mostra um badge com o idioma efetivo do site
ativo e o ID allowlistado. `?language=` continua permitindo trocar a qualquer
momento.

## Limitação honesta — resolvida pela frente P

A sessão de idioma agora registra também o **site** de origem
(`language_site`). Se o visitante troca de site com o mesmo cookie, o idioma
herdado do outro host é descartado e vale o idioma do site atual. Dentro do
**mesmo** site a sessão continua prevalecendo, e a troca explícita via
`?language=` continua soberana. Sessões antigas (sem `language_site`)
mantêm o comportamento da K: nenhuma invalidação retroativa.
Não houve MySQL real, sessão autenticada, browser com rede, DNS/vhost, E2E,
staging ou deploy: validação com PHP 8.5 WASM, Twig 3.14 e mocks.
**CI verde ≠ release aprovada.**
