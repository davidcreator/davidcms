# Frente AI — Ciclo real de criação de tema · ReamurCMS KAZTheme-Editor

**Data:** 2026-11-05 · **Base:** `main` `39697000fd4057a3f5ab220d50c0fffc4d1d8df0`
(AH aplicada; auditoria 19/19 arquivos AH byte a byte; tree 3226 / 2643).

## Natureza desta entrega

**Esta frente NÃO altera nenhum arquivo `dev/`.** Não há o que aplicar no
produto: depois de A–AH corrigirem layout, boot, cena, estado efetivo e
liberação, faltava **executar** o fluxo que o usuário definiu como resultado
real — "config + CSS/templates reais que o catálogo renderiza. Salvar, prévia,
ativar e publicar têm efeito visível no site".

O pacote contém **2 ferramentas somente-leitura**, 15 documentos e 3 imagens
de evidência. `tools/` e `evidencia/` não precisam ir para produção.

## O que foi provado

`tools/kaz-theme-lifecycle-probe.php` executa o código **real** do `main`:
`buildDraftConfig()` (privado, via Reflection) com os 11 serviços reais do
`framework.php`; `ModelDesignTheme::save()`; `Controller::activate()` com ACL
e CSRF reais; `ModelDesignTheme::activate()`; `ModelCommonTheme::
getEffectiveTheme()`; `ThemeSnapshot::resolve()`; `ThemePreviewState::css()`;
e a tag `<body>` do `header.twig` real via Twig 3. As **SQL dos models são
reais**, interpretadas por um banco em memória — se a SQL mudar, o PROBE
falha. O POST vem dos campos `name=` do formulário real.

`tools/verify-kaz-lifecycle.cjs` mede no Chromium a home **antes** e **depois**
de Publicar: `rgb(37,99,235)` → `rgb(220,38,38)`, raio `12px` → `32px`,
fonte `16px` → `20px`. O painel da AH passa de `SEM_TEMA_ATIVO` /
`RASCUNHO_PENDENTE` para `PUBLICADO` com `pending=0`.

| Item | Resultado |
| --- | --- |
| HEAD / tree | `39697000fd40` · 3226 / 2643 · 19/19 AH byte a byte |
| Pacote | 17 arquivos (3 novos) · **0 em `dev/`** · réplica 2221 → 2224 (14 docs alterados + 3 novos + 2207 intactos) |
| Guards | 4/4 idênticos · 14 manifestos intocados |
| Sintaxe | 394 PHP / 617 JS-CJS, 0 erros |
| PROBE AI | 54/0 |
| Chromium AI | 22/0 (antes × depois, mobile 390px) |
| Regressões A–AH | 1245/0 asserções acumuladas |
| Build | idempotente 5/5 · CRC 22/22 · `sha256sum -c` 59/59 |
| ZIP | 22 entradas · `.sha256` externo |

## Como rodar

```bash
php tools/kaz-theme-lifecycle-probe.php   # PROBE, gera /tmp/ai_site/*.html
node tools/verify-kaz-lifecycle.cjs       # Chromium, gera PNGs
```

`AI_ROOT`, `AI_TWIG_AUTOLOAD`, `AI_OUT`, `AI_SHOTS` configuram os caminhos.
Requer PHP 8.1+, Twig 3.x, Node e Playwright. O CJS também aceite duas URLs.

## Limites — leia antes de considerar aprovado

O banco é **em memória, não MySQL**; `activate()` usa stubs mínimos de
`request`/`session`/`user`, **não** uma sessão Admin autenticada; a home é o
fragmento real do `header.twig` mais um elemento de medição, **não** a página
completa; não há deploy. Ainda **não** foi colado o `copy(__kazHealth())` do
servidor de produção, nem houve avaliação visual nova do usuário desde
`image-7.png`. `release_approved` continua `false`; F9-27
`BLOCKED_NOT_RUN`, BDREditor/F9-103 pausado, P2/F9-18 não reaberto.
**CI verde, PROBE e Chromium headless não significam release aprovada.**
Nenhuma escrita foi feita no GitHub; CI remoto não foi verificado.
