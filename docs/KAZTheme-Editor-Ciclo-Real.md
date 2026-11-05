# KAZTheme-Editor — Ciclo real: formulário → banco → Publicar → home (Frente AI)

**Data:** 2026-11-05 · **Base:** `main` `39697000fd4057a3f5ab220d50c0fffc4d1d8df0`
(AH aplicada, 19/19 arquivos AH byte a byte) · **Status:** evidência
executável entregue, **não** aprovado em produção.

## Por que esta frente existe

A reprovação original (`image-7.png`) dizia que **nenhuma das funcionalidades
presentes tinha resultado para criação de temas**. As frentes AC–AH fecharam
responsividade, boot silencioso, diagnóstico, paridade de cena, estado efetivo
completo e o painel server-side de liberação. Mas todos os números vinham de
harnesses que **inspecionavam** ou **simulavam** o fluxo:

| Frente | O que provava | O que ficava de fora |
| --- | --- | --- |
| AH | `published = draft` por atribuição de array; CSS da prévia == CSS do publicado | nenhuma SQL executada, nenhum controller chamado |
| AF/AG | prévia isolada e painéis publicado × rascunho | a home real do catálogo |
| A–AE | formulário, layout, boot, persistência da cena | o POST completo indo para o banco |

A AI fecha exatamente esse vazio: **executa o código do `main`** de ponta a
ponta e mostra o resultado no navegador.

## O que é executado

```
POST (campos name= reais do Twig)
  → Theme\Controller::buildDraftConfig()        [Reflection, método privado]
  → Registry com 11 serviços reais do framework.php
  → ModelDesignTheme::save()                    [INSERT real]
  → getDraftConfig() / getPublishedConfig()
  → ThemeReleaseProbe::summarize()              [AH: SEM_TEMA_ATIVO]
  → Controller::activate()                      [ACL + CSRF reais]
  → ModelDesignTheme::activate()                [2 UPDATE + revisão reais]
  → ModelCommonTheme::getEffectiveTheme()       [SELECT real, status=1]
  → ThemeSnapshot::resolve() + ThemePreviewState::css()
  → header.twig real (Twig 3, autoescape)       [<body style="--rms-*">]
  → Chromium: getComputedStyle() antes × depois
```

O `FakeDb` **interpreta as strings SQL que os models realmente emitem** —
`INSERT`, `UPDATE … SET published_config=draft_config`, `SELECT … WHERE
status='1'` — sobre uma tabela em memória. Não é um mock que devolve
respostas prontas: se a SQL mudar, o PROBE falha. As 4 escritas reais são
contadas e conferidas.

## Resultado medido

| Medida | Antes de Publicar | Depois de Publicar |
| --- | --- | --- |
| `getComputedStyle(#kaz-probe).backgroundColor` | `rgb(37, 99, 235)` | `rgb(220, 38, 38)` |
| `border-radius` | `12px` | `32px` |
| `font-size` | `16px` | `20px` |
| `--theme-primary` no `<body>` | `#2563eb` | `#dc2626` |
| painel da AH | `SEM_TEMA_ATIVO` / `RASCUNHO_PENDENTE` | `PUBLICADO`, `pending=0` |

`ThemePreviewState::css(resolve(rascunho))` === `css(resolve(publicado))`
(1001 bytes). Evidências em `evidencia/antes.png`, `evidencia/depois.png` e
`evidencia/depois_mobile.png` (390 px).

**Observação registrada, não corrigida:** num tema recém-criado,
`published_config` é `'{}'` e `ModelDesignTheme::getPublishedConfig()` usa o
fallback legado `config` (F13-C), que no INSERT recebe o próprio rascunho. O
painel da AH, por sua vez, lê `published_config` direto e com `status=0`
reporta `SEM_TEMA_ATIVO`. Os dois comportamentos são distintos e
**permanece como está**: não há pedido de mudança, e a AH já foi aplicada.

## Como rodar

```bash
php tools/kaz-theme-lifecycle-probe.php          # PROBE 54/0, gera HTMLs
node tools/verify-kaz-lifecycle.cjs              # Chromium 22/0, gera PNGs
```

Variáveis: `AI_ROOT` (raiz do projeto, padrão dois níveis acima de `tools/`),
`AI_TWIG_AUTOLOAD` (padrão `vendor/autoload.php`), `AI_OUT` (HTMLs, padrão
`/tmp/ai_site`), `AI_SHOTS` (PNGs, padrão `/tmp/ai_shots`). O CJS aceita duas
URLs como argumentos; por padrão abre os HTMLs via `file://`. Requer PHP 8.1+,
Twig 3.x, Node e Playwright.

## Escopo e limites

**Nenhum arquivo `dev/` é alterado por esta frente.** Não há migration,
coluna, rota Admin, chave de tradução, `|raw` novo, asset, manifesto, hash
congelado, `PENDING_DECISION` ou `release_approved` mexido. O pacote contém
apenas 2 tools, documentação e evidências.

Limites honestos: o banco é em memória, **não** MySQL; `activate()` é chamado
com stubs mínimos de `request`/`session`/`user`/ACL, **não** com uma sessão
Admin autenticada; a home é o fragmento real do `header.twig` mais um elemento
de medição, **não** a página completa com todos os controllers; não há deploy.
Ainda não foi colado o `copy(__kazHealth())` do servidor de produção nem houve
nova avaliação visual do usuário desde `image-7.png`. `release_approved`
continua `false`; F9-27 `BLOCKED_NOT_RUN`, BDREditor/F9-103 pausado,
P2/F9-18 não reaberto. **CI verde, PROBE e Chromium headless não significam
release aprovada.** Nenhuma escrita foi feita no GitHub; CI remoto não foi
verificado.
