# Guia — Agendamento e Rollout (Frente H)

A **H** permite que um **rascunho** de tema passe a valer para uma janela de
tempo e uma parcela dos visitantes — **sem cron, sem migration, sem coluna nova,
sem escrita no banco e sem promoção automática**. O tema publicado permanece
intacto; **Ativar** continua sendo um ato explícito no Admin.

Não é o BDREditor (F9-25/F9-103), não é runtime WebGL e não é agendamento de
conteúdo. É uma decisão **pura**, tomada a cada requisição pelo
`startup/theme.php` a partir de uma chave JSON do próprio tema.

## O que o rascunho declara

`schedule` é uma chave do config (como `sections` ou `components`), com 5 campos
allowlistados:

| Campo | Tipo | Regra |
|---|---|---|
| `enabled` | bool | desligado ⇒ sempre o publicado |
| `from` | `YYYY-MM-DDTHH:MM` | **UTC**, formato validado por `DateTimeImmutable` |
| `until` | idem ou `''` | `''` = janela em aberto; precisa ser ≥ `from` |
| `percent` | int 0–100 | parcela dos visitantes que recebe o rascunho |
| `scope` | enum | `all`, `logged_in`, `anonymous` |

`ThemeSchedule::normalize()` é **fail-closed**: chave extra, aninhamento, data
inválida, `until` anterior a `from`, percentual fora da faixa ou `scope`
desconhecido ⇒ `null` ⇒ nenhum rollout.

## Como a decisão é tomada

1. `getEffectiveTheme()` (model do Catalog) lê **publicado + rascunho** do tema
   ativo numa **única query**, somente leitura.
2. `ThemeSchedule::decide()` compara `now()` (UTC) com a janela, verifica o
   `scope` contra `logged_in` do contexto allowlistado e calcula o **bucket**.
3. O bucket é `hexdec(substr(hash('sha256', session_id), 0, 8)) % 100` — estável
   por visitante, determinístico, **nunca renderizado nem persistido**. Não há
   cookie novo, nem dado sensível na saída.
4. Se `bucket < percent`, o snapshot é resolvido sobre o **rascunho** e a cadeia
   child-theme usa `getThemeMap('draft')`; caso contrário, sobre o **publicado**.
5. `common/header.php` acrescenta a classe `rms-theme-rollout` ao `<body>` e
   concatena variáveis planas `--rms-sched-*` ao `style` já existente (B + G).
   Nenhum `<style>`, `<script>` ou `|raw` novo.

Motivos estáveis: `disabled`, `pending`, `expired`, `scope`, `zero`,
`outside_bucket`, `active`.

## Admin

- Card **Agendamento e rollout**: checkbox, início/fim (`datetime-local`),
  percentual, público e uma **sonda determinística** (data/hora, identificador
  de teste, visitante autenticado). O botão **Simular** usa `formaction` +
  `formtarget="theme-preview"` — **sem JavaScript e sem `fetch`** — e reabre a
  prévia C com a decisão calculada.
- `buildDraftConfig()` valida e grava `schedule`; `build` inválido ⇒
  `error_schedule`, nada é salvo. `import` ⇒ `schedule_invalid`, `export`
  inclui a chave no **checksum SHA-256** da frente E.
- `theme-schedule.js` é DOM puro: habilita/desabilita campos e mostra o
  percentual. Sem o JS, o formulário continua funcionando.

## Prévia C

Cada painel ganha a fita **Agendamento**: estado (publicado × rascunho em
rollout), motivo, janela, percentual/bucket, público e `now`. O diff estruturado
passa a incluir `schedule.percent`, `schedule.from`, `schedule.scope`, etc.

## Herança E e pacotes

`schedule` **não** é mesclado por chave na cadeia child-theme: é substituído
integralmente, como `conditions`. Um filho **sem** `schedule` herda a regra do
pai **por inteiro**; um filho que declara `schedule` substitui **todos os 5
campos** de uma vez — nunca sobra metade de uma janela. Um pacote exportado
pela E transporta a chave e é revalidado no `decode()`.

## Compatibilidade e limites

Config sem `schedule` ⇒ comportamento **idêntico à G** (`effective=published`,
variáveis `--rms-sched-*` com valores neutros, globais planos vazios). Nenhum
`CHECKSUMS*.txt` é reemitido. Intocados: `schema.sql`, `twig.php`,
`autoloader.php`, `home.twig`, `PageLayout`, `cms/slot.php`, `ConditionEngine`,
`TokenRegistry`, `SceneConfig`, runtime Three.js, BDREditor, contratos
congelados e `release_approved`.

A decisão é por requisição: **não** existe tarefa agendada que promova o
rascunho. Se o servidor reiniciar ou o rascunho mudar, a regra continua a mesma
enquanto o JSON não for alterado. A validação local usa PHP 8.5 WASM, Twig
3.14 e jsdom com mocks — **não** houve MySQL real, sessão autenticada, browser
com rede, cron, E2E, staging ou deploy. **CI verde ≠ release aprovada.**
