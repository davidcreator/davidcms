# Condições por site (Frente L)

A frente J resolve **qual site** está ativo a partir de hosts allowlistados e a
frente K usa essa decisão para escolher o idioma. Faltava um elo: o motor de
condições declarativas da frente A **não enxergava o site**. Regras como
`language eq en-us` até podiam existir, mas não havia como escrever
"no site `shop`, use a cor laranja" sem duplicar condições por rota. A frente L
fecha esse elo com uma única dimensão nova: **`site`**.

## Contrato

`site` entra em `ConditionEngine::FIELDS` e em `spec()['fields']`. O valor só
pode ser um dos 8 IDs estáveis de `ThemeSites::IDS`:

```
primary, shop, blog, docs, landing, partner, legacy, staging
```

Operadores permitidos **apenas**: `eq`, `neq`, `in`, `not_in` (e os operadores
sem valor `exists`/`truthy`/`falsy`/`empty`). `contains`, `starts_with`,
`between`, comparações numéricas e qualquer outro operador são rejeitados com
`site_op_invalid`. Qualquer valor fora da allowlist é rejeitado com
`site_invalid` — inclusive dentro de listas `in`/`not_in`. O motor nunca vê o
`HTTP_HOST`: o ID vem exclusivamente de `ThemeSites::match()`.

Exemplo (aba JSON do construtor de condições):

```json
{
  "version": 1,
  "rules": [
    {
      "id": "shop-primary",
      "when": { "all": [ { "field": "site", "op": "eq", "value": "shop" } ] },
      "then": { "primary": "#b45309", "flags": ["shop"] }
    },
    {
      "id": "reading-sites",
      "when": { "all": [ { "field": "site", "op": "in", "value": ["blog", "docs"] } ] },
      "then": { "primary": "#0f766e" }
    }
  ]
}
```

## Onde o valor é produzido

| Cenário | Origem de `context['site']` |
|---|---|
| Catálogo | `startup/theme.php` → `ThemeSites::match()` sobre o host **comparado** |
| Prévia (publicado) | `ThemeSites::match()` sobre a política **publicada** |
| Prévia (rascunho) | `ThemeSites::match()` sobre a política **do rascunho** |
| Simulador (Admin) | `<select>` allowlistado do construtor; inválido ⇒ `primary` |

A prévia usa **um contexto por painel**: se o rascunho mudou o host de `shop`,
o painel do rascunho avalia as condições como `shop` e o painel publicado
continua com a sua própria decisão. A fita de contexto exibe o site do painel
publicado. A sonda de host continua sendo **apenas comparada**, nunca exibida.

## Interface

- O `<select>` de campos do construtor visual ganha a opção `site`
  automaticamente via `spec()['fields']` — nenhuma chave de idioma nova.
- Ao escolher `site` com operador escalar, o valor vira um `<select>` com os 8
  IDs (não é mais digitado livremente).
- O simulador ganha a linha **Site**, enviada em `context[site]`.
- O resumo do contrato, na aba JSON, lista os IDs permitidos — legível mesmo
  **sem JavaScript**.

## Compatibilidade e segurança

Sem regra que use `site`, o comportamento é **idêntico às frentes A–K**: o
contexto ganha uma chave allowlistada que nenhuma regra consulta. Sem `sites`
no config, `match()` devolve `primary`. Se `ThemeSites` não estiver disponível,
`siteIds()` fica vazio e a dimensão nunca valida nem casa (fail-closed).

Nenhuma query, migration, coluna, tabela, cookie, cron, arquivo, escrita no
banco, chave de tradução, CSS, schema, manifesto ou global Twig novo. Nenhum
`|raw`, `<script>` inline, `fetch`/`eval`/`innerHTML` novo, dado do visitante
em HTML, vhost/DNS ou upload. Os 14 `CHECKSUMS*.txt` permanecem intactos.

**Resolvido pela frente P:** `context['language']` agora é o idioma
**efetivo** (negociado uma única vez por `LanguageNegotiator` antes do
`ThemeSnapshot`), não o default do configfile. Regras `language eq en-us`
passam a casar com o idioma escolhido via `?language=`, sessão ou domínio.
A dimensão `site` continua disponível e a precedência da K foi preservada.

Aplicar os 6 arquivos completos deste pacote (o guia é novo) e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
