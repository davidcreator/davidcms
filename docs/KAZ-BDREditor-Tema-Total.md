# Frente AO — Tema TOTAL do BDREditor ↔ KAZTheme-Editor

**Data:** 2026-11-06 · **Base remota:** `1b6ffd97e418272eebccfd573cccbe61c15ca386` · **Não é release**

## Pedido e decisões

> "ReamurCMS-Frente-AN-Padrao-Visual-BDREditor aplicado com sucesso github atualizado. Podemos prosseguir com o próximo passo deixe todo tema do BDREditor semelhante ao KAZTheme-Editor"

Aplicação da AN confirmada no remoto: **14 adições** (os 5 arquivos da AN + 7 evidências + `RELATORIO-FINAL-AN.md` e `INDICE-AN.md`), **2 modificações** (`blog/blog.php`, `cms/page.php`), **0 remoções**; os 7 arquivos da AN conferem byte a byte.

Escopo confirmado para a AO: `alcance=pagina_e_estados` e `modo_tema=sempre_escuro`.

- **Página Admin inteira + todos os estados** nas telas que hospedam o BDREditor.
- **Sempre no escuro KAZ**, independente de `theme_mode`, `data-bs-theme` ou skin.
- Continua **somente Admin** (o catálogo público não é tocado), **sem clonar layout** (screenbar/3 colunas/divisórias do KAZ), **sem alterar DOM**, o JS de 600 KB ou o documento de blocos, e com o **foco `#174ea6` congelado (F9-26)**.

## O que faltava depois da AN

A AN era escopada em `[data-blog-block-editor]` e por isso cobria apenas o chrome do editor. Ficavam de fora:

1. **O Admin ao redor** — `reamurcms.css` (tema global, claro: `#f4f6f9`, `#fff`, `#f8fafc`) com navbar, breadcrumb, `.page-titlebar`, `.card`, `.form-control`, `.nav-tabs`, `.input-group-text`, `.alert`, `.btn`, `.badge` e a sidebar de publicação.
2. **Os estados sob demanda** — o `blog-block-editor.js` faz 3× `document.body.appendChild` (overlays/dialogs **fora do host**, portanto fora do escopo da AN) e existem ~100 classes de command palette, biblioteca de padrões, inserter, prévias, tabelas, agrupamento, fullscreen, arraste e seleção; a AN só tinha 6 regras que mencionavam esses termos.
3. **O `<html>`** — superfície visível quando o editor entra em fullscreen/workspace.

## Implementação

**Uma folha aditiva**, `blog-block-editor-kaz-total.css`, carregada **depois** da AN nos 2 controllers, com marcador próprio `-kaz-bdre-ao`. Nenhum JS novo, nenhuma alteração no `blog-block-editor-kaz.js` da AN, nenhuma mudança de Twig ou de `Document`.

**Escopo sem tocar no servidor:** `:is(body:has([data-blog-block-editor]), html[data-kaz-bdre-tema="1"] body)`. O `:has()` é reavaliado dinamicamente quando o init monta o host, e cobre inclusive os overlays criados depois em `document.body`. `html[data-kaz-bdre-tema="1"]` é o fallback para navegadores sem `:has()`; nesse caso a **AN continua intacta** — degradação graciosa, nada quebra. Compatibilidade: Chrome 105+, Safari 15.4+, Firefox 121+; `:has()` é Baseline desde 2023.

**"Sempre escuro"** foi obtido sobrescrevendo os tokens que o Admin realmente usa (`--bs-body-bg`, `--bs-body-color`, `--bs-card-bg`, `--bs-border-color`, `--bs-primary`, `--bs-secondary-bg`, `--bs-tertiary-bg`, `--bs-gray-*`, `--bs-anchor`, `--bs-link-*`, `--bs-form-control-*`) **no `body` das telas com editor**, mais regras explícitas com `!important` para as cores literais de `reamurcms.css`/Bootstrap. A preferência `data-bs-theme="light"` do usuário **não é alterada** — a paleta KAZ apenas vence naquelas telas.

**Canvas preservado:** regra de restauração final mantém `.rb-canvas` e os blocos de conteúdo em `#fff` com texto `#172033`, exatamente como na AN e como o board branco do KAZ.

**Foco F9-26:** `outline: 3px solid #174ea6` / `outline-offset: 2px` reafirmados no escopo da página, com o mesmo halo claro da AN. Nenhuma troca por verde.

## Arquivos

| Arquivo | Papel |
| --- | --- |
| `dev/admin/controller/blog/blog.php` | +4 linhas (comentário, variável, `addStyle` AO) |
| `dev/admin/controller/cms/page.php` | +4 linhas (idem) |
| `dev/admin/view/css/blog-block-editor-kaz-total.css` | **novo** · tokens, superfícies Admin, estados, `<html>`, scrollbars, foco, canvas |
| `docs/KAZ-BDREditor-Tema-Total.md` | este documento |
| `tools/kaz-bdre-total-probe.php` | PROBE somente leitura |
| `tools/verify-kaz-bdre-total.cjs` | A/B Chromium |

**Inalterados:** `blog-block-editor.css` (119 KB), `blog-block-editor.js` (600 KB), `blog-block-editor-init.js`, `blog-block-import.js`, `bdre-concurrency.js`, `blog-block-editor-kaz.css`, `blog-block-editor-kaz.js`, os 4 Twigs de host, `reamurcms.css`, `bootstrap.css`, as skins, `admin.css`, `dev/catalog/view/css/blog-blocks.css`, `schema.sql`, traduções, `theme_form.twig`, os 14 manifestos, os 11 artefatos congelados, `decision-matrix.json`, F9-14/F9-25/F9-26/F9-18/F9-27.

## Instalação e verificação

Copiar `dev/` e `docs/` sobre a instalação; `tools/`, `evidencia/` e os `.md` da raiz são só validação. **Limpar cache de CDN/proxy** (novo marcador `-kaz-bdre-ao`). Abrir uma edição de Post ou Página: a página inteira deve estar escura com accent verde, o canvas branco, e a barra `ELEMENTOS` da AN no início do editor.

```bash
php tools/kaz-bdre-total-probe.php
node tools/verify-kaz-bdre-total.cjs
```

Não é preciso abrir o console; preferências de escala/dobras continuam descartáveis e locais.

## Limites — isto NÃO é uma aprovação

A prévia usa uma reconstrução estática dos formulários Admin com as **classes reais** de `reamurcms.css`/Bootstrap, mas não é uma sessão Admin autenticada, não há MySQL, deploy nem CI remoto. As capturas não substituem as **2 capturas desktop + 390 px do Admin real**, `copy(__kazHealth())` em produção, o CI remoto (403 `Must have admin rights`) e o veredito visual do usuário — todos ainda pendentes. `image-7.png` e `image-8.png` seguem como critério histórico.

O `:has()` cobre Chrome/Safari/Firefox atuais; sem suporte, a página volta ao tema do Admin e **a AN continua funcionando** (sem perda funcional). Corrigir isso em navegadores legados exigiria marcar o `<html>` via JS — fora do escopo desta frente.

`release_approved` continua `false`, F9-27 `BLOCKED_NOT_RUN`, F9-18 `release_ready:false` com achados P1 em aberto. Backend, banco, migration, ciclo criar/editar/salvar/Publicar, P2/F9-18 e F9-103 **não foram reabertos**. **CI verde, PROBE, WASM e Chromium headless não significam release aprovada.** Nenhuma escrita nossa foi feita no GitHub; a aplicação do pacote é feita pelo usuário.
