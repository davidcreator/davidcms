# Rollout de rascunho por site (Frente O)

As frentes **H** (agendamento/rollout declarativo) e **J** (multisite) não se
enxergavam: `ThemeSchedule` só conhecia os escopos `all`, `logged_in` e
`anonymous`. Um rascunho em rollout aparecia nos **8 sites ou em nenhum**.
A frente O fecha essa lacuna sem alterar a janela, o percentual, o bucket, a
semente do visitante, a decisão pura, a política de sites da J, as condições
da L, a prévia da M ou a matriz hreflang da N.

## Como usar

No card **Agendamento e rollout** do Admin, o campo **Sites** (multi-select,
8 IDs allowlistados de `ThemeSites::IDS`) limita o rollout:

- **Nenhum site selecionado** ⇒ comportamento idêntico às frentes A–N
  (todos os sites). Compatibilidade total com configs já gravados.
- **Um ou mais sites** (`shop`, `blog`, …) ⇒ só visitantes cujo site ativo
  está na lista entram na amostra do rascunho; os demais veem o publicado.
- A sonda **Simular janela e rollout** ganha o seletor de site, para reproduzir
  `reason='site'` sem esperar a janela.
- A prévia (C/M) mostra `Sites: shop, blog · primary` na fita do agendamento.

## Regras de segurança

- Os IDs vêm **somente** de `ThemeSites::IDS`; qualquer valor fora da
  allowlist faz `normalize()` devolver `null` ⇒ `error_schedule` no POST
  (fail-closed). Nada do `HTTP_HOST` é publicado: o host continua sendo apenas
  **comparado** por `ThemeSites::match()`.
- **Sem ciclo de decisão:** o site usado no `decide()` é resolvido pela
  política de sites **publicada**, antes da escolha do config. A política do
  config efetivo (J/L) continua sendo usada para o `context['site']` e para o
  tema por site — exatamente como antes. `match()` é puro: **zero query nova**.
- O 5º argumento de `decide()`/`state()` é **opcional** (`''` ⇒ `primary`),
  então todos os chamadores A–N seguem válidos.
- `sites=[]` ⇒ nenhuma restrição. Novo `reason='site'` (allowlistado em
  `REASONS`), novo inteiro `--rms-sched-sites` no CSS e dois globais Twig
  planos (`theme_schedule_site`, `theme_schedule_site_count`).
- Nenhuma chave de tradução nova: o formulário reaproveita `text_sites` e
  `text_sites_help` (418 chaves ×3 intactas). Sem migration, schema, coluna,
  escrita, cron, cookie, sessão, `custom_js`, `<script>` inline, `|raw`,
  `fetch`/`eval`/`innerHTML`, CSS além da variável inteira, JS novo (só DOM),
  manifesto, contrato, vhost/DNS, upload ou global Twig aninhado.
- Os 14 `CHECKSUMS*.txt` permanecem intactos; nenhum arquivo deste pacote é
  coberto por eles.

## Compatibilidade e limites

`ThemeSchedule::KEYS` ganha `sites` e `ThemePackage::validateConfig()` já
valida o bloco `schedule` integralmente por `normalize()`, então pacotes
exportados/importados (E) continuam funcionando — os sem `sites` caem em `[]`.
`ThemeSchedule::SCOPES` **não muda**: `scope` continua sendo o público
(`all|logged_in|anonymous`) e `sites` é uma dimensão independente.

**Limitação honesta:** se o **rascunho** alterar o host de um site, o site da
decisão vem da política publicada (necessário para evitar o ciclo), enquanto o
`context['site']` das condições (L) vem da política efetiva. Em configs com
hosts divergentes entre publicado e rascunho, use o seletor de site da prévia
(M) para comparar pelo ID. A sonda do Admin sempre usa um ID allowlistado.

Aplicar os 7 arquivos completos deste pacote (o guia é novo) e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
