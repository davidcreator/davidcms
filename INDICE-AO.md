# ÍNDICE — Frente AO · Tema TOTAL do BDREditor ↔ KAZTheme-Editor

**Pacote:** `ReamurCMS-Frente-AO-Tema-Total-BDREditor.zip` · **2026-11-06** · **Não é release**

## Instalação

A AN precisa estar aplicada (está no HEAD `1b6ffd97…`). Copiar `dev/` e `docs/` sobre a instalação; `tools/`, `evidencia/` e os `.md` da raiz são apenas validação. **Limpar cache de CDN/proxy** (novo marcador `-kaz-bdre-ao`). Abrir uma edição de Post (Blog) ou Página (CMS): a página inteira deve escurecer, mantendo o canvas branco e a barra `ELEMENTOS` da AN.

## Arquivos do pacote

| Caminho | Tipo | Papel |
| --- | --- | --- |
| `dev/admin/controller/blog/blog.php` | alterado | +4 linhas: `addStyle` do CSS AO depois da AN |
| `dev/admin/controller/cms/page.php` | alterado | +4 linhas: idem |
| `dev/admin/view/css/blog-block-editor-kaz-total.css` | **novo** | tokens `--bs-*`/`--kaz-*`, superfícies Admin, estados sob demanda, `<html>`, scrollbars, foco, canvas |
| `docs/KAZ-BDREditor-Tema-Total.md` | novo | diagnóstico, arquitetura, limites |
| `tools/kaz-bdre-total-probe.php` | novo | PROBE somente leitura, 52 verificações |
| `tools/verify-kaz-bdre-total.cjs` | novo | A/B Chromium, 46 verificações |
| `evidencia/controle_sem_ao.png` | imagem | antes — Admin com AN, desktop |
| `evidencia/ao_admin.png` | imagem | depois — desktop |
| `evidencia/ao_amplo.png` | imagem | escala "Amplo" (não-regressão AN) |
| `evidencia/ao_recolhido.png` | imagem | dobras (não-regressão AN) |
| `evidencia/ao_mobile.png` | imagem | depois — 390 px |
| `RELATORIO-FINAL-AO.md` | doc | resultados medidos |
| `INDICE-AO.md` | doc | este arquivo |

## Verificação

```bash
php tools/kaz-bdre-total-probe.php
node tools/verify-kaz-bdre-total.cjs
```

## O que a AO cobre

- **Página Admin inteira** (navbar, breadcrumb, título, alertas, cards, abas de idioma, campos, botões, badges, sidebar de publicação) **nas telas que hospedam o BDREditor**.
- **Todos os estados sob demanda**: command palette e overlays criados em `document.body`, biblioteca de padrões, inserter, prévias, tabelas, agrupamento, fullscreen, arraste, seleção, sidebar/rail/inspector do editor.
- **Sempre escuro KAZ**, mesmo com `data-bs-theme="light"` e skin clara — a preferência do usuário não é alterada.
- O **canvas de conteúdo continua branco** (é o que será publicado).

## O que NÃO mudou

Nenhum JavaScript novo ou alterado; nenhum DOM, Twig, `Document`, renderer, documento de blocos, catálogo público, `schema.sql`, tradução, manifesto, artefato congelado ou contrato. `blog-block-editor.css` (119 KB), `blog-block-editor.js` (600 KB), o CSS e o JS da AN, `reamurcms.css`, `bootstrap.css`, as skins e `admin.css` seguem intactos. Foco F9-26: `outline: 3px solid #174ea6`, `outline-offset: 2px`.

## Compatibilidade

`:is(body:has(...), html[data-kaz-bdre-tema="1"] body)`. `:has()` é reavaliado quando o init monta o host e cobre overlays posteriores; Baseline 2023 (Chrome 105+, Safari 15.4+, Firefox 121+). Sem `:has()`, **a AN continua funcionando** e a página volta ao tema do Admin — sem perda funcional.

## Limites

Prévia local sem sessão autenticada, MySQL, deploy ou CI remoto. Seguem pendentes as 2 capturas reais desktop + 390 px, `copy(__kazHealth())` de produção, CI remoto e o veredito visual. `release_approved:false` · F9-27 `BLOCKED_NOT_RUN` · F9-18 `release_ready:false`. **CI verde, PROBE e Chromium headless não significam release aprovada.**
