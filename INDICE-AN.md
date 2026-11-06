# ÍNDICE — Frente AN · Padrão visual BDREditor ↔ KAZTheme-Editor

**Pacote:** `ReamurCMS-Frente-AN-Padrao-Visual-BDREditor.zip` · **2026-11-05** · **Não é release**

## Instalação

Copiar `dev/` e `docs/` sobre a instalação. `tools/`, `evidencia/` e os `.md` da raiz são apenas validação. **Limpar cache de CDN/proxy** (novo marcador `-kaz-bdre-an`). Abrir uma edição de Post (Blog) ou Página (CMS).

## Arquivos do pacote

| Caminho | Tipo | Papel |
| --- | --- | --- |
| `dev/admin/controller/blog/blog.php` | alterado | +3 linhas: registra CSS/JS aditivos |
| `dev/admin/controller/cms/page.php` | alterado | +3 linhas: registra CSS/JS aditivos |
| `dev/admin/view/css/blog-block-editor-kaz.css` | novo | tema escuro/verde, scrollbars, escala, dobras, foco |
| `dev/admin/view/js/blog-block-editor-kaz.js` | novo | barra de controles, `MutationObserver`, `__kazBdre()` |
| `docs/KAZ-BDREditor-Padrao-Visual.md` | novo | decisões, arquitetura, limites |
| `tools/kaz-bdre-visual-probe.php` | novo | PROBE somente leitura, 57 verificações |
| `tools/verify-kaz-bdre-visual.cjs` | novo | A/B Chromium, 51 verificações |
| `evidencia/controle_sem_an.png` | imagem | antes — desktop |
| `evidencia/controle_mobile.png` | imagem | antes — 390 px |
| `evidencia/an_normal.png` | imagem | depois — desktop |
| `evidencia/an_amplo.png` | imagem | escala "Amplo" |
| `evidencia/an_recolhido.png` | imagem | ferramentas recolhidas |
| `evidencia/an_expandido.png` | imagem | ferramentas expandidas |
| `evidencia/an_mobile.png` | imagem | depois — 390 px |
| `RELATORIO-FINAL-AN.md` | doc | resultados medidos e limites |
| `INDICE-AN.md` | doc | este arquivo |

## Verificação

```bash
php tools/kaz-bdre-visual-probe.php
node tools/verify-kaz-bdre-visual.cjs
```

## Uso na tela

- **ELEMENTOS** — Compacto / Normal / Confortável / Amplo (escala todo o chrome).
- **Ícones / Abas / Status** — recolhem e reexibem cada ferramenta.
- **Recolher tudo / Expandir tudo** — alterna as três de uma vez. A barra de controles permanece sempre visível.

Preferências são estado local descartável. O **canvas de conteúdo continua branco** — ele é o que será publicado.

## O que NÃO mudou

`blog-block-editor.css` (119 KB), `blog-block-editor.js` (600 KB), `blog-block-editor-init.js`, `blog-block-import.js`, `bdre-concurrency.js`, os 4 Twigs de host, o catálogo público, `schema.sql`, traduções, `theme_form.twig`, manifestos, artefatos congelados e contratos F9-14 / F9-25 / **F9-26** / F9-18 / F9-27.

Foco segue `outline: 3px solid #174ea6` com `outline-offset: 2px` (F9-26).

## Limites

Camada de apresentação, sem sessão Admin autenticada, MySQL, deploy ou CI remoto. As capturas são da **prévia local**, não do Admin em produção; seguem pendentes as 2 capturas reais desktop + 390 px, `copy(__kazHealth())` de produção, CI remoto e o veredito visual. `release_approved:false` · F9-27 `BLOCKED_NOT_RUN` · F9-18 `release_ready:false`. **CI verde, PROBE e Chromium headless não significam release aprovada.**
