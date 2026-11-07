# Guia — BN (David Universe 3D)

## O que é

O **novo tema oficial** do seu site, seguindo a amostra online
`https://davidalmeida.xyz/threejs/?lang=pt`. Arquivo:
`docs/temas/David-Universe-3D.reamurtheme.json` (88.864 bytes, 25 fontes Twig,
`validateConfig = []`, `decode ok=true`, checksum
`e081f0d26759034b15f1e200e2248199bbee43dea30765fb8977704c3f29a1d4`).

Site completo com as 9 fases da amostra, HUD de jogo, 9 projetos no catálogo,
inventário, serviços, depoimento, Instagram, formulário de contato e rodapé.
Paleta medida: `#121516`, `#5cc6d0`, `#dfdfdf`, `#a7b3b5`, `#eb767e`,
`#ffdf00`.

## Instalar (a ordem importa)

1. Admin → **Temas do Catálog** → abra **o tema que está ATIVO**.
2. **Importar tema** → `David-Universe-3D.reamurtheme.json`.
3. Conferir a prévia: 25 fontes, 20 camadas em `kaz_scene`, `three.enabled`.
4. **ATIVAR** ⚠️ — sem isso, `save()`/`import()` gravam só `draft_config` e o
   Catálogo continua no `published_config` antigo (causa raiz da BL).
5. Limpar `storage/cache/template/` e conferir em aba anônima.

Se quiser um registro separado, importe pela lista (cria tema **inativo**) e
depois Ative o novo e desative o anterior.

## Cenas por rota

| Rota | Cena | Slot | Altura |
|---|---|---|---|
| Início | `hero-particles` | `hero` | 320px (210px no mobile) |
| Catálogo / projeto | `portfolio-3d` | `home_top` | 230px |
| Ferramentas / blog | `tools-3d` | `home_top` | 230px |
| 404 | `not-found` | `footer_top` | 170px |

## Três diferenças conscientes em relação à amostra

1. **Sem Google Fonts.** A amostra carrega Poiret One / Open Sans / Lato de
   `fonts.googleapis.com`; fontes remotas/CDN são proibidas no Recorte A. O
   CSS usa `--du-display` com stack local (`"Poiret One", "Century Gothic",
   "Trebuchet MS", system-ui`) — usa a fonte se existir, sem rede.
2. **Sem `.webp` remotas.** As 9 miniaturas do catálogo são **SVG inline**
   determinísticos com as cores do tema.
3. **Sem JS no tema.** O WebGL/scroll-driven da amostra é feito pelo
   `scene-runner.js` self-hosted do Catálogo lendo os `data-scene` do
   `partial/theme-scene.twig`. O tema escolhe cena, slot, contagem, cor e
   velocidade — nunca escreve código. As capturas mostram o **fallback
   declarativo**; a animação aparece no runtime publicado.

## Verificação local

```bash
node tools/verify-bn.cjs .
```

- `evidencia/export-bn.txt` — **14 OK / 0 FALHAS** com as classes PHP reais.
- `evidencia/layout-bn.json` — 5 capturas Chromium 141, 0 overflow em
  390/360/320px, 0 pageerrors, **0 scripts**, 1 slot por página.
- `evidencia/ref-*.png` / `ref.json` — capturas e medições da amostra online.
- `BN-Amostra-vs-Tema.png` — comparação lado a lado do hero.
- `preview/*.html` — prévias estáticas fiéis das 4 cenas.

## Pendências que este pacote NÃO resolve

- Os patches **A/B da BL** continuam não aplicados (HEAD `ad7643de…` tem
  `dev/` intacto); o `common/home.twig` nativo ainda inclui
  `theme-scene-block.twig`.
- `release_approved: false`, F9-27 `BLOCKED_NOT_RUN`, F9-18
  `release_ready: false`, 10 `PENDING_DECISION`.
- Aceite visual da BN e health no Admin real pendentes.

Nenhuma escrita do agente no GitHub, nenhum deploy/runtime/migration executado
por mim. CI verde, PHP WASM, Chromium e guardas **não significam release
aprovada**.
