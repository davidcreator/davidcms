# ADR 0022: Páginas públicas das ferramentas (Fluxa, Quotia, Vertex, Solis) com keywords seo_url tool_id=* e grade temática

- **Status:** Aceita
- **Data:** 2026-10-19
- **Autor:** Cascade (Coevolve AI)
- **Tarefa:** Fase 3 — Tarefa 04 (F3-04)
- **Regras relacionadas:** agents-ia/regras/00-contexto-global.md, 03-protocolo-anti-alucinacao.md; ADR 0016 (ErrorException), 0017 (publicação catalogo, seo_url), 0019 (tema F3-01 + seed real + tools.url=''), 0021 (SEO efetivo — meta description em todas as páginas)

## Contexto

O checklist do roadmap (Análise §8, Fase 3) lista **"Páginas das ferramentas
(Fluxa, Quotia, Vertex, Solis)"** como item público pendente. A pasta
`catalog/controller/tool/` existia mas estava vazia (F2-13 entregou apenas o
CRUD admin + ACL grant; F2-17/F3-01 exibiram os 4 cards na seção da home mas
sem página própria). As ferramentas têm SEO relevante (nomes próprios fortes:
Fluxa=gestão financeira, Quotia=orçamentos, Vertex=currículos, Solis=conteúdo
social), então cada uma precisa de um endpoint público dedicado — mesmo padrão
já validado do portfólio (`catalog/project.info|N` + keywords `project_id=*`).

Restrições herdadas:

- A URL **externa** (`rms_tool.url`) está vazia no seed da F3-01 — em produção
  o site atual resolve via `/catalog/tools/open?id=N` **sem expor** o destino
  no HTML; rastrear direto os links externos seria inventar conteúdo
  (proibido). Declarado desde a ADR 0019: `url=''` até o admin preencher.
- Zero binários de imagem (monograma do nome no card — ADR 0019/0020),
  textos 100% na Language ×3, escopo cirúrgico, sem tocar `system/`.

## Decisão

### 1 — Controller público `catalog/controller/tool/tool.php`

- `index` → grade pública (título/lead do lang, cards com monograma do nome,
  vendor, description e dois CTAs: **página dedicada** — link primário — e
  **Open ↗** externo só quando `t.url !== ''`).
- `info|N` → página dedicada com `<title>` `Nome — Vendor`, descrição como
  meta description, tabela de fatos (`entry_vendor` + `entry_external_url`
  somente quando preenchida) e dois caminhos de CTA:
  - `url` preenchida → `btn primary` externo (mesmo padrão dos cards externos
    do portfólio/Hyperion — ADR 0019);
  - **`url` vazia** → `text_open_hint` (idioma-dependente: "The public link
    for this tool is being prepared. Get in touch for early access.") + CTA
    para `/contato` — sem ar links mortos, sem inventar URL de produção.
- Erro honesto: keyword inexistente ou `tool_id` fora do ar → dispatch para
  `error/not_found` (mesmo snippet já usado em `catalog/project.php`).

### 2 — Model: `getTool(int $tool_id)` com coalesce pt/es→en

Mesmo SQL-coalesce usado por `getProjects()`/`getProject()` (etapa
`currentLanguageId()` do config de idioma ativo, com fallback estático →
`language_id=1`):

```sql
SELECT t.*, COALESCE(NULLIF(td.name,''),td_en.name,'') AS name,
            COALESCE(NULLIF(td.description,''),td_en.description,'') AS description
  FROM rms_tool t LEFT JOIN rms_tool_description td  (+lang)
                  LEFT JOIN rms_tool_description td_en (1, en-us)
 WHERE t.tool_id = ? AND t.status = 1 LIMIT 1
```

Retorna array cheio ou `[]` (nunca exceção — a rota decide o 404).

### 3 — Keywords seo_url `tool_id=*` no seed F3-01 (aditivo, idempotente)

Seed da F3-01 é o ponto de contato único do conteúdo real — mantive isso e
adicionei (com `DELETE` das âncoras antes, como já era o padrão):

```sql
('tool_id=1','fluxa',1,0), ('tool_id=2','quotia',1,0),
('tool_id=3','vertex',1,0), ('tool_id=4','solis',1,0);
```

**Decisão deliberada:** keywords identicas nas 3 línguas (nomes próprios),
seedadas só em `language_id=1` (convenção ADR 0017/0019 — coalesce do router
`resolveSeo` já cobre pt/es através da busca em dois passos no
`getQueryByKeyword`). Assert do e2e_f301 atualizado de 12→16 keywords.

### 4 — Router SEO: `tool_id` entra nos 2 pontos tocados — nada mais

- `SEO_ROUTES` ganha `'tool_id' => 'tool/tool.info|'` (ao lado de
  `project_id`/`page_id` — mesma gramática).
- `rewrite()` ganha o 3º branch no regex:
  `catalog\/project|cms\/page|tool\/tool` + mapa
  `['catalog/project' => 'project_id=', 'cms/page' => 'page_id=', 'tool/tool' => 'tool_id=']`.
- **`ALIASES` ganha `'tools' => 'tool/tool'`** (espelha `/contato`, `/quote`,
  `/portfolio` — a grade tem URL limpa sem `route=`).

**Não** houve alteração no pipeline do router (resolveSeo/readConfig) — a
gramática `xxx.info|N` já é nativa do dispatcher (explode em `|`).

### 5 — Partial da home faz o nome do card linkar p/ dentro (`t.href`)

- `home.php` passa a hidratar `href = url->link('tool/tool.info|id')`;
  o router reescreve para a keyword (`/fluxa`).
- `partial/tools.twig`: `<strong>{{ t.name }}` virou
  `<a class="tool-link" href="{{ t.href }}">{{ t.name }}</a>` (Tema:
  `.tool-link` herda cor/sob-hover accent; CTAs externos continuam
  separados e só quando `t.url` preenchida).
- **Sem** efeito colateral nas categorias/projetos (outros caminhos de
  hidratação inalterados).

### 6 — Templates `tool/tool_list.twig` + `tool/tool_info.twig` nas classes do tema

Mesmos elementos do padrão `/portfolio` (F3-03): `.container`, `.page-head`,
`.back-link`, `.lead`, `.tool-card`/`.tool-icon` (monograma), `.fact-table`,
`.muted`. **Zero `style=` inline** (check 6 do e2e_f304 asserta).

### 7 — Langs ×3 en/pt-br/es-eu (14 chaves · 3 languages)

`catalog/language/{en-us,pt-br,es-eu}/tool/tool.php` — `heading_title`,
`text_lead` (mesma do partial da home — consolidação futura), `text_no_results`,
`text_open_hint` (traduzida), `entry_vendor`, `entry_external_url`,
`button_view`/`button_back`/`button_back_home`/`button_contact`, `text_open`.
pt-br e es-eu traduzidas pela mesma rotina das demais (convenção do projeto).

## Assunções declaradas

1. Keywords `/fluxa`… são os nomes públicos canônicos (moeda da produção) —
   seo_url em todas as línguas é a mesma (nomes próprios). Se o mantendor
   quiser slugs localizadas (`/ferramentas-quotia`), é UPDATE idempotente no
   seed + roseed.
2. `tools.url=''` até o admin preencher (ADR 0019) — quando preenchida, o
   hint some e o CTA externo aparece automaticamente (mesma tela, sem deploy).
3. Não implementei o redirect `/tools/open?id=N` do site atual (mecanismo
   interno de produção; destino final não consta no HTML público) — quando as
   URLs externas forem preenchidas, uma tarefa posterior pode mapear aquela
   rota legada com 301.
4. A descrição da página dedicada sai de `td.description` (1–2 frases
   traduzidas) — não inventei descrição longa; quando o mantenedor quiser
   rica, basta editar o campo no admin (já CRUDável na F2-13).

## Consequências

### Positivas
- 4 endpoints públicos novos com SEO real (meta description da metodologia
  F3-03 herda automaticamente — confirmado: check 3 do e2e_f304 asserta
  `<meta name="description" content="…tool.">`).
- Hub interno `/tools` coerente com o padrão one-page (mesma família de
  classes do tema).
- Nome do card da home vira o link canônico interno — resolve o "aonde
  clicar" sem envolver a URL externa.
- Nenhum arquivo de system/, banco de dados (exceto seed aditivo) ou engine
  tocado.

### Negativas / débitos
- A página dedicada hoje é magra (vendor + descrição + CTA) — depende de
  conteúdo rico por ferramenta (campo `description` curto; screenshots/blog
  posts anexados são trabalho de conteúdo, não de código).
- Redirect `open?id=N` legado fica para quando as URLs forem preenchidas
  (associado à tarefa de migração 301 da Fase 6 — redirects do site atual).

## Validação executada (não declarada)

- **`tools/e2e/e2e_f304.sh` (10 checks, 10/10):**
  1. `/tools` 200 com as 4 reais (Fluxa/Quotia/Vertex/Solis + vendor);
  2. 4 keywords reais todas 200 (`/fluxa /quotia /vertex /solis`);
  3. meta description em `/fluxa` com texto REAL do seed (`Financial
     management and indicator tracking tool.`);
  4. `<title>Fluxa — DavidCreator` (nome + vendor do seed);
  5. hint "public link…/early access" aparece com url='' e CTA externo
     _blank **não** aparece;
  6. layout do tema sem estilo inline (page-head + fact-table);
  7. keyword inexistente → HTTP 404 + fog (Cena 4 da F3-03);
  8. card da home linka `class="tool-link"` para keyword `/fluxa`;
  9. rewrite limpo: nenhum `index.php?route=tool/tool.info` no HTML da home;
  10. `/tools` em pt-br/es-eu traduzida; hint traduzido aparece em
      `/fluxa?language=pt-br`; keywords seguem legíveis (nomes próprios).
- **Suíte completa:** regressao(10) + f212..f218 (14+14+15+15+17+17+16=108)
  + f301 (13, assert ajustado 12→16 keywords) + f302 (10) + f303 (10) +
  f304 (10) = **161 asserts, 0 FAIL**.
- **PHPStan:** `[OK] No errors`.
- **php -l:** nos 5 arquivos PHP tocados/criados (controller tool, model
  tool +`getTool`, router, home.php, langs ×3).
- **Rerun smoke da f301** após o ajuste no assert de keywords: 13/13.
