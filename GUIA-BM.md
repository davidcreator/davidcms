# Guia — BM (Atlas Studio 3D)

## O que é

Um **tema completo** no mesmo padrão do Atlas Studio aprovado na BI, agora com
**ThreeJS declarativo em várias seções**. Arquivo:
`docs/temas/Atlas-Studio-3D.reamurtheme.json` (76.637 bytes, 25 fontes Twig,
`validateConfig = []`, `decode ok=true`).

## Como instalar (a ordem importa)

1. Admin → **Temas do Catálog** → abra o **tema que está ATIVO**.
2. **Importar tema** → selecione `Atlas-Studio-3D.reamurtheme.json`.
3. Conferir a prévia: 25 fontes, 16 camadas em `kaz_scene`, `three.enabled`.
4. **ATIVAR.** ⚠️ Sem este clique, `save()`/`import()` gravam apenas
   `draft_config` e o Catálogo continua no `published_config` antigo — foi
   exatamente isso que manteve o vazamento na frente BL.
5. Limpar `storage/cache/template/` e conferir em aba anônima.

> Se preferir um registro separado, importe pela lista (cria tema **inativo**)
> e depois **Ative** esse novo e desative o anterior.

## O que você deve ver

| Rota | Cena | Slot | Visual |
|---|---|---|---|
| Início | `hero-particles` | `hero` | campo de partículas 340px no topo |
| Projetos | `portfolio-3d` | `home_top` | 240px, padrão diagonal |
| Ferramentas | `tools-3d` | `home_top` | 240px, padrão de oficina |
| 404 | `not-found` | `footer_top` | 180px, mais discreto |
| Mobile | `hero-particles` + `atlas-scene-lite` | `hero` | 220px, 180 partículas |

O conteúdo do site (hero, serviços, sobre, método, projetos, depoimentos,
blog, contato, CTA, rodapé) é **o mesmo do Atlas aprovado** — 13 fontes ficaram
byte-a-byte idênticos.

## O que o tema NÃO faz

- Não contém JavaScript. O WebGL é montado pelo `scene-runner.js` **do próprio
  Catálogo**, que lê os atributos `data-scene`/`data-count`/`data-color`/
  `data-speed` emitidos por `partial/theme-scene.twig`.
- Não adiciona `<script>`, `|raw`, handlers inline, fontes remotas nem CDN.
- Não cria coluna, tabela ou migration.
- Não depende de outro tema: `parent` está vazio para o editor não cair nos
  `.twig` do disco (defeito da BL).

## Verificação local

```bash
node tools/verify-bm.cjs .
```

- `evidencia/export-bm.txt` — 14 OK / 0 FALHAS com as classes PHP reais.
- `evidencia/layout-bm.json` — 4 capturas Chromium 141, 0 overflow,
  0 pageerrors, **0 scripts**, 1 slot por página.
- `BM-Preview-Cenas.png` — prancha visual das 3 cenas + mobile.
- `preview/home.html`, `preview/projetos.html`, `preview/not-found.html` —
  prévias estáticas fiéis (slot renderizado como o Twig emitiria).

## Pendências que este pacote NÃO resolve

- Os patches **A** e **B** da BL ainda não foram aplicados: o HEAD
  `ad7643de…` tem **`dev/` intacto**. Enquanto isso, o `common/home.twig`
  nativo continua incluindo `theme-scene-block.twig`.
- `release_approved: false`, F9-27 `BLOCKED_NOT_RUN`, F9-18
  `release_ready: false`, 10 `PENDING_DECISION`.
- Aceite visual da BM e health no Admin real pendentes.

Nenhuma escrita do agente no GitHub, nenhum deploy/runtime/migration
executado por mim. CI verde, PHP WASM, Chromium e guardas **não significam
release aprovada**.
