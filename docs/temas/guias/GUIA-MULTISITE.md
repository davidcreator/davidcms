# Guia — Multisite Declarativo (Frente J)

A **J** fecha a série A→J permitindo que **hosts diferentes usem temas
diferentes** — sem vhost, DNS, tabela, coluna, migration, cron, cookie novo,
escrita no banco ou consulta SQL adicional.

> **Não é** hospedagem multiloja, não cria banco por site, não mexe em
> `schema.sql`, não é a biblioteca de mídia do Blog (ADR 0049) nem o BDREditor.
> É uma **política declarativa no JSON do tema**, resolvida em cada requisição.

## Como funciona

O config do tema ativo ganha a chave `sites`: até **8 IDs allowlistados**
(`primary`, `shop`, `blog`, `docs`, `landing`, `partner`, `legacy`, `staging`),
cada um com `host`, `slug` (tema), `language` (`pt-br`/`en-us`/`es-eu`) e
`enabled`.

1. `startup/theme.php` lê `HTTP_HOST` e chama `ThemeSites::match()`. O host é
   **normalizado e apenas comparado** (minúsculas, porta removida, regex
   ancorada). **Nunca** aparece em HTML, global Twig, CSS, log ou resposta.
2. A frente H já carregava **todos** os temas com `getThemeMap($source)` para
   resolver a herança E. A J reaproveita **esse mesmo SELECT**: o `slug` do site
   é procurado no mapa e seu config vira a raiz da cadeia de child-themes.
   **Continuam exatamente 2 SELECTs somente leitura** (nenhuma query nova).
3. O rollout H continua decidindo `published` × `draft` no tema ativo; o site
   escolhe **qual tema** daquele mapa é renderizado. Slug ausente/inválido cai
   no tema ativo (fail-closed), sem erro e sem escrita.
4. Host desconhecido ⇒ primeiro site habilitado na ordem allowlistada. Sem
   `sites` no config ⇒ comportamento **idêntico à I**.

## Segurança

`HTTP_HOST` é controlado pelo cliente. `normalize()` é **fail-closed**: ID fora
da allowlist, chave extra, aninhamento, host com scheme/porta/caminho/`..`,
slug fora de `^[a-z0-9_-]{1,48}$`, idioma desconhecido ou mais de 8 sites ⇒
`null` ⇒ nenhum site. `hash_equals()` compara hosts. Os globais Twig são 3
mapas **planos e curtos** (`theme_site`, `theme_sites`, `theme_site_enabled`),
sem nenhum host do request; `--rms-site-*` contém **apenas inteiros**. A classe
`rms-site-<id>` usa um ID allowlistado. Zero `|raw`, `<script>`, `fetch`, `eval`
ou `innerHTML` novo.

## Pacotes, prévia e Admin

`sites` entra em `CONFIG_KEYS`, `spec()`, `validateConfig()`
(**`sites_invalid`**), checksum SHA-256 e `decode()`. Na herança é **substituído
integralmente** (política global, como `schedule` e `conditions`) — um filho
nunca herda metade do mapa. `ThemeSnapshot` publica `sites`, `site`, `site_css`,
`site_bars`, `site_language` e os globais planos; `diff()` inclui
`sites.<id>.host`. `ThemeCompareView::siteStrip()` mostra a fita nos dois
painéis, com o site ativo destacado.

No Admin, o card **Sites e domínios** tem 8 linhas (checkbox, host, slug dos
temas existentes, idioma) e uma **sonda**: `theme-sites.js` simula o host
**localmente**, sem rede, e mostra `id · slug · idioma · motivo` em
`textContent`. Só **Salvar rascunho** persiste. `buildDraftConfig()` valida;
`import` recusa JSON com `sites_invalid`. **36 novas chaves ×3 idiomas**
(382 → 418). O `common/header.php` concatena `--rms-site-*` no `style` do
`<body>`, reaproveitando B, G, H e I.

### Correção da frente W — linhas em branco não são política

O formulário sempre publica os 8 blocos allowlistados. Até a V, o servidor
montava as 8 entradas e chamava `ThemeSites::normalize()`, que exige host
válido em **todas**: com 7 linhas em branco o `save()` era bloqueado por
`error_sites` — *"Mapa de sites inválido: revise host, slug e idioma."* — mesmo
sem o usuário ter tocado no card (e a cena persistente da V também não salvava).
A **W** descarta, antes de `normalize()`, apenas a linha que está **sem host,
sem slug e desmarcada**. Qualquer conteúdo continua **fail-closed**: host com
scheme, porta, underline, caminho, `..`, slug fora de `^[a-z0-9_-]{1,48}$`,
idioma desconhecido ou mais de 8 sites ainda retornam `null` e nenhuma
gravação. Quando há erro real, a mensagem existente ganha o **ID allowlistado**
do bloco (`(shop)`), sem chave de tradução nova e sem refletir texto livre.

No cliente, `theme-sites.js` libera no `submit` os campos das linhas **com host
preenchido**, mesmo desabilitadas, para que a política de um site temporariamente
desligado não seja apagada ao salvar; linhas vazias continuam `disabled`. O
cache key passou de `-theme-sites-t` para `-theme-sites-w`. Servidor e cliente
são independentes: com JS antigo ou desligado o servidor já resolve o bloqueio.

## Compatibilidade e limites

Config sem `sites` ⇒ nenhuma classe `rms-site-*`, `site_css` vazio, globais nos
defaults e 8 linhas desabilitadas. Preservados: 6 presets D, 12 seções F,
10 componentes G, 5 campos/7 motivos H, 12 mídias/6 modelos I, o hotfix, os
14 manifestos e os contratos congelados. Validação local: PHP 8.5 WASM,
Twig 3.14 e jsdom com mocks — **não** houve MySQL real, sessão Admin
autenticada, browser com rede, DNS, vhost, WebGL, E2E, staging ou deploy.
Cabe ao servidor web apontar os domínios para a mesma instalação; a J apenas
**decide o tema** de cada host. **CI verde ≠ release aprovada.**
