# ADR 0023: "Latest News" do blog externo na home com cache compartilhado e degradação silenciosa

- **Status:** Aceita
- **Data:** 2026-10-19
- **Autor:** Cascade (Coevolve AI)
- **Tarefa:** Fase 3 — Tarefa 05 (F3-05)
- **Regras relacionadas:** agents-ia/regras/00-contexto-global.md, 03-protocolo-anti-alucinacao.md; ADR 0002 (blog externo no lançamento), 0019 (tema F3-01 + links externos _blank), 0021 (SEO meta description)

## Contexto

O checklist do roadmap (Análise §8, Fase 3) pede a seção **"Latest News" lendo o
feed do davidcreator.com — blog externo no lançamento (ADR 0002)**. A versão
anterior da seção existia na produção (`davidalmeida.xyz`) como carrossel
`owl-carousel id=blog` imediatamente após a galeria do portfólio, com 3 cards
de data + título + resumo + "Read More", consumindo o RSS real e abrindo os
posts em `target=_blank`.

Restrições herdadas:

- **Zero SQL**: nenhuma tabela no schema para "notícias" (o blog é e permanece
  externo). Portanto o provider é o próprio RSS da produção, sem intermediário
  no banco.
- **Engine lean**: o fetch usa cURL nativo (sem composer), parse via
  `SimpleXML` (extensão sempre ativa no PHP 8).
- **Prefere-se a ausência à quebra**: uma falha no blog não pode deixar a home
  parada, acinzentada ou com 500.
- **Textos da UI continuam 100% nas Languages ×3**; títulos/resumos/datas dos
  posts vêm do feed no idioma do blog (pt) — **não traduzo o conteúdo de
  terceiro** (isso é decisão do ADR 0002: blog externo).

## Decisão

### 1 — Model `catalog/model/feed/news.php` (feed reader, zero banco)

Novo namespace `Feed\` para concentrar provedores externos. `getLatestNews(int $limit): array`:

1. Checa cache (`$this->cache->get('news_feed')`) — o driver `file` nativo do
   config é usado (TTL default 1h = `cache_expire`). Se válido, devolve fatia
   de `data.items`.
2. Miss → `httpGet()` via cURL (timeout connect 3s/total 6s, SSL verify, max 3
   redirects, User-Agent identificado). Timeout curto porque ninguém deve
   esperar blog para ver a home.
3. Parse `simplexml_load_string` com `libxml_use_internal_errors(true)`.
   Itera `$rss->channel->item` e normaliza cada item para
   `{title,url,date,excerpt,source}`. `clean()` faz strip_tags + decode de
   entidades + colapsa espaços; `excerpt()` corta com reticências em
   `mb_strimwidth` (160 chars). Data RFC-2822 do feed → `d/m/Y`.
4. **Se QUALQUER coisa falhar — rede, HTTP não-2xx, XML malformado, data
   inválida — retorna `[]` sem throw** (contrato da classe: nunca lança).
5. **Cacheia o resultado mesmo vazio**. Se o blog cair, a home paga 1 hit por
   TTL (1h), nunca 1 por pageview. Isso é a degradação silenciosa efetiva: a
   seção some, o resto da página fica.

**Hard cap** de 6 itens do feed (não carrega toda a sequência) com `MAX_ITEMS_HARD_CAP`,
`LIMIT=3` default passado pelo controller.

### 2 — Controller `common/home.php` monta `news_items` + chaves de idioma

A home continua com 1 query p/ seção existente e adiciona **1 hit de feed por
TTL** (típico 1/dia hora se cache quente). Novas chaves:
`text_news_title`, `text_news_kicker`, `text_news_read_more`, `text_news_source`.

### 3 — Partial `partial/news.twig` entre `tools` e `clients`

Posição seguindo a da produção (após a seção de portfólio/ferramentas, antes
da seção comercial final). Guard `{% if news_items %}` — se o feed estiver
vazio (falhou ou ainda não populou), a `<section id="news">` sequer aparece
no HTML. Cartões reutilizam `grid-3` + `.news-card`/`.news-meta`/`.news-cta`/
`.news-source` — criadas no `theme.css` (grep anti-duplicata rodado antes,
confirmado que não existiam).

Link externo com `target="_blank" rel="noopener"` (padrão ADR 0019).
Classe CSS usa apenas variáveis `--card/--line/--radius/--accent/--muted` —
zero `style=` inline (check 4 do e2e testa).

### 4 — Bundle e2e_f305 (10 checks) + armadilha SimpleXML documentada

Checklist cobre: seção presente com heading real; conteúdo REAL do feed HTML
↔ RSS batendo (extrai `<title>` do 1º `<item>` do feed e valida na home);
metadados (data d/m/Y, source, CTA externo ↗); zero `style=`; cache criado e
2º hit sem re-write (mtime estável); **feed vazio artificial → home 200 sem
seção, resto da página íntegro; i18n ×3 no container mas títulos do feed
idênticos**; sem side effects em storage (nada de rss/feed fora do cache) nem
banco (contagem 10 projetos + 4 tools + 3 depoimentos); payload < 15 KB de
delta; logs limpos.

**A regressão escondida da F3-05 (documentada no próprio código):**

```php
foreach ($rss->channel->item as $i => $item) {   // ← armadilha!
    if ($i >= 6) break;                          // $i é a STRING "item"!
}
```

Em SimpleXML, a chave do `foreach` sobre o nó repete o **nome do elemento**
(`"item"`, `"item"`, ...), não o índice inteiro. Comparar `"item" >= 6` força
coerção lexicográfica e sempre `true` — loop quebrado na 1ª iteração. Cache
ficava perpetuamente vazio, página silenciosamente sem seção, nenhum erro em
nenhum log. Detectado somente após instrumentação com `error_log` — reforça o
porquê do protocolo de "validar com ferramentas".

Correção: contador manual (`$count++`). PHPStan + manual review não
agarraram o tipo da chave do foreach sobre SimpleXML — vale o registro aqui
para o próximo fetch de XML no projeto.

## Assunções declaradas

1. URL do blog (`https://davidcreator.com/feed/`) é estável e permanece
   pública — `GET` anônimo sem sessão.
2. Conteúdo do feed (títulos/textos) permanece em **pt** independente do
   idioma da página — isso é decisão de conteúdo do ADR 0002 (blog externo),
   não erro de i18n. O entorno (kicker/título da seção/CTA/source) traduz
   pelos idiomas do site.
3. Data formatada em `d/m/Y` para coerência entre os 3 idiomas —
   preferi isso a usar `locale` PHP (imprevisível no shared host).
4. Trechos podem conter entidades de HTML do feed — `clean()` decodifica + strip.
5. **TTL de 1 hora** é o do `cache_expire` do config — a troca para um TTL
   longo/dinâmico pode vir com a gestão de config da Fase 4 (não agora).
6. Seção com 3 cartões (entre `tools` e `clients`) — a posição segue o fluxo
   da produção (<galeria> → blog). Pode mudar quando o admin puder reordenar
   seções (Fase 4+).

## Consequências

### Positivas
- Seção "Latest News" da home **finalmente viva**, com dados reais do blog
  (nada foi hardcoded no tema — se o blog mudar, o feed já reflete).
- Blog pode cair, parar ou trocar de domínio: a home nunca vai junto.
- Zero SQL adicionado — alinhado à decisão arquitetural de blog externo.

### Negativas / débitos
- A cada TTL (1h) a primeira visita paga *< 7s* de fetch — transparente hoje,
  mas se a página for agressiva em tempo real, deve migrar para um worker
  cron/webhook de atualização.
- 3 cartões fixos — a grade do tema permite ajustar sem tocar código (a
  classe `grid-3` cuida do wrap; para 4+ basta mudar LIMIT no controller).

## Validação executada (não declarada)

- **`tools/e2e/e2e_f305.sh` (10 checks, 10/10):** seção presente com kicker
  real; conteúdo REAL do feed batendo; data + source + CTA externo; zero
  inline; cache+mtime; degradação silenciosa; i18n ×3 com títulos do feed
  intocados; sem side effects (storage/banco); payload +2.148 bytes; logs limpos.
- **Suíte completa:** regressao(10) + f212..f218 (108) + f301(13) + f302(10)
  + f303(10) + f304(10) + f305(10) = **171 asserts, 0 FAIL**.
- **PHPStan:** `[OK] No errors`.
- **php -l:** model/feed/news.php + controller/common/home.php + langs ×3.
- **Rerun smoke** de f301/f303/f304 após inserção no section entre parciais:
  tudo verde (ordem dos parciais preservada).
