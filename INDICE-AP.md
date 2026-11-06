# Índice — Frente AP (correção de escopo da AO)

Pacote: `ReamurCMS-Frente-AP-Escopo-Corrigido-BDREditor.zip`  
Base: `9c809050ff908b66b6f3bf97c1fc5a1aed9b3160` · 2026-11-06

| # | Entrada no ZIP | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-AP.md` | Diagnóstico, A/B/C, validação, limites e aceitação |
| 2 | `INDICE-AP.md` | Este índice |
| 3 | `dev/admin/controller/blog/blog.php` | Marcador `-kaz-bdre-ap` (substitui o da AO) |
| 4 | `dev/admin/controller/cms/page.php` | Marcador `-kaz-bdre-ap` (substitui o da AO) |
| 5 | `dev/admin/view/css/blog-block-editor-kaz-total.css` | **CSS recolhido**: somente `[data-blog-block-editor]` + overlays `rb-*` |
| 6 | `docs/KAZ-BDREditor-Tema-Total.md` | Documentação corrigida (causa `--bs-primary`, escopo, aplicação) |
| 7 | `docs/Registro-Visual-AP.md` | Critério visual: `image-9` reprovação, `image-10` referência |
| 8 | `tools/kaz-bdre-total-probe.php` | PROBE de **não-vazamento** (53 asserções) |
| 9 | `tools/verify-kaz-bdre-total.cjs` | Verificador A/B/C (41 asserções, Chromium) |
| 10 | `evidencia/ap_reprovacao_image-9.png` | Admin real após a AO — **reprovação** |
| 11 | `evidencia/ap_referencia_image-10.png` | KAZTheme-Editor — **referência** |
| 12 | `evidencia/ap_admin_nativo.png` | A/B — Admin nativo + AN (sidebar azul) |
| 13 | `evidencia/ap_admin_ao.png` | A/B — AO reprovada (sidebar verde, página escura) |
| 14 | `evidencia/ap_admin_ap.png` | A/B — AP corrigida (Admin azul, editor escuro) |
| 15 | `evidencia/ap_mobile.png` | AP a 390 px (sem overflow) |

## Como aplicar

Copiar os 9 arquivos de código/docs/evidência do pacote **sobre** os caminhos
correspondentes do repositório (6 substituições + 3 novos). Não é necessário
remover nenhum arquivo: os caminhos da AO são reutilizados. O marcador
`-kaz-bdre-ap` dos dois controllers invalida o cache do navegador.

## Como validar localmente

```bash
AP_ROOT=<raiz> AP_OUT=/tmp/ap_site \
  php-wasm-cli tools/kaz-bdre-total-probe.php
AP_URL=http://127.0.0.1:8123/ap/ AP_SHOTS=/tmp/ap_shots \
  node tools/verify-kaz-bdre-total.cjs
```

O verificador espera a prévia A/B/C (`admin_nativo.html`, `admin_ao.html`,
`admin_ap.html`) servida com `bootstrap.css`, `reamurcms.css`,
`themes/light.css`, a AN e o markup real de `#menu > li.active > a`. O estado
com a AO antiga existe para provar que a asserção **detecta** o defeito.

## Aceitação

Veredito visual do usuário no Admin real. PROBE / Chromium headless / CI /
health local **não significam release aprovada**. `image-9.png` é a reprovação
vigente; `image-10.png` é a referência.
