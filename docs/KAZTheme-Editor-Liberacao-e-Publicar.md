# KAZTheme-Editor — Estado da liberação (Frente AH)

**Data:** 2026-11-05 · **Base:** `8cfce45dae00` (AG aplicada, auditoria 19/19
byte a byte no `main`) · **Status:** entregue para instalação, **não** aprovado
em produção.

## O que faltava

As frentes AC–AG fecharam layout, boot, cena e estado efetivo na prévia. Mas
existia um silêncio entre **Salvar** e **Publicar**:

- `activate()` (Admin) copia `draft_config` → `published_config` e `config`;
- `getEffectiveTheme()` (Catálogo) lê `published_config`;
- nenhum lugar da tela dizia **se havia rascunho pendente** ou se o que a
  prévia mostrava já era o publicado.

O `__kazHealth()` da AE resolveria parte da dúvida, mas exige abrir o DevTools
e colar um JSON — nunca foi colado. E um diagnóstico que depende de
JavaScript é justamente o que falha no cenário "nenhuma ferramenta responde e
não existe erro no console".

## O que a AH entrega

Um **`#kaz-release-status`** no topo do editor, renderizado **no servidor**:

| Veredito | Significado |
| --- | --- |
| `PUBLICADO` | `draft_config` e `published_config` idênticos; prévia e catálogo usam o mesmo tema |
| `RASCUNHO_PENDENTE` | há chaves alteradas; o painel lista até 16 delas e orienta a publicar |
| `SEM_TEMA_ATIVO` | nenhum tema com `status=1` |

Abaixo, métricas allowlistadas: quantas das **6 partes de CSS** da AG estão
presentes, bytes de CSS, camadas da cena e se a versão do asset é `-ah`.

`ThemeReleaseProbe::summarize()` é **pura**: sem IO, banco, sessão, request ou
`eval`. Devolve **somente escalares**, todos passam pelo autoescape do Twig.
Sem `|raw` novo, sem `name=` novo (o contrato de 53 campos permanece), sem
`data-kaz-module` novo, sem chave de tradução, sem rota, sem migration, sem
coluna. O bloco é **irmão** do `.kaz-shell`, não filho — a árvore medida pela
AC (`#kaz-theme-form > .kaz-*`) não muda.

## Prova do elo Publicar

`tools/kaz-theme-release-probe.php` (somente leitura):

1. constrói publicado A e rascunho B com `ThemeSnapshot::resolve()` real;
2. `summarize(B, A, …)` → `RASCUNHO_PENDENTE`, `pending_count > 0`;
3. simula `activate()` — `published = draft`, exatamente o que a SQL faz;
4. `summarize(B, B, …)` → `PUBLICADO`, `pending = 0`;
5. verifica estaticamente que `activate()` escreve `published_config` e
   `config` a partir de `draft_config`, e que `getEffectiveTheme()` lê
   `published_config`;
6. **prova central**: `ThemePreviewState::css(resolve(rascunho))` ===
   `ThemePreviewState::css(resolve(publishado após ativar))`. O que você viu
   na prévia é o que a home do catálogo vai renderizar.

`tools/verify-kaz-release.cjs` abre os dois estados no Chromium e confirma que
o painel aparece **com JavaScript ligado e desligado**, que os atributos
`data-kaz-release-*` batem com o veredito e que o painel não contém nenhum
campo de formulário.

## Como verificar no servidor

1. `php tools/kaz-theme-release-probe.php` → PROBE.
2. Sirva `/tmp/ah_site/*.html` e rode
   `node tools/verify-kaz-release.cjs <url-pendente> <url-publicado>`.
3. No Admin: abra um tema → o painel deve dizer **Rascunho pendente** após
   editar e **Publicado** depois de Ativar/Publicar. Recarregue a home do
   catálogo e confirme o mesmo resultado. **Se o painel não aparecer, o
   problema é de renderização do Admin, não de JavaScript.**

## Limites

Harnesses, PROBE e Chromium headless não substituem a sessão Admin
autenticada, o banco ou o servidor de produção. A simulação de `activate()`
reproduz o **contrato de dados** (`published = draft`), não executa SQL real
nem transação. Ainda não há nova avaliação visual do usuário desde
`image-7.png`, nem veredito `copy(__kazHealth())` colado do servidor.
`release_approved` continua `false`; F9-27 `BLOCKED_NOT_RUN`,
BDREditor/F9-103 pausado, P2/F9-18 não reaberto. **CI verde ou health local
não significam release aprovada.**
