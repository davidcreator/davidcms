# Passo 2 — Health observacional no Admin real

**HEAD:** `d35cfac1ce323ea7504041571df170de365e3b39` · **Script:** `tools/health-admin-ap.js` (já no GitHub, 38 linhas / 1.947 bytes, `node --check` OK).

> **Não é um pacote para aplicar.** É o roteiro para você executar a única
> parte que eu não consigo: ler o Admin autenticado. O script já está no
> repositório e foi conferido contra os controllers do HEAD atual.

## 1. O que o script faz (e o que não faz)

**SOMENTE LEITURA.** Lê `getComputedStyle`, atributos e presença de elementos.
**Não** chama `__kazHealth()` (função que nunca existiu no código — aparece só
em relatórios), não acessa rede, banco, sessão, token, conteúdo de post nem
grava nada. Termina com `copy(JSON.stringify(...))`, que usa o `copy()` do
DevTools para jogar o JSON na área de transferência.

**Verificação de sanidade já feita por mim:** os controllers
`dev/admin/controller/blog/blog.php` e `cms/page.php` registram

```
css/blog-block-editor-kaz.css?v=…-kaz-bdre-an
css/blog-block-editor-kaz-total.css?v=…-kaz-bdre-ap
```

Logo o filtro `h.includes('blog-block-editor-kaz')` pega os dois e os
marcadores `-kaz-bdre-ap` / `-kaz-bdre-ao` funcionam. **Nenhuma correção é
necessária no script.**

## 2. Passo a passo

1. Abra o **Admin autenticado** em produção/staging.
2. Entre em **Pages › Edit** (ou **Blog › Edit post**) — uma tela que monte o
   BDREditor.
3. `F12` → aba **Console**.
4. Abra `tools/health-admin-ap.js` no GitHub, copie **todo o conteúdo** e cole
   no console; `Enter`.
5. O JSON já ficou na área de transferência. **Cole aqui na conversa.**
6. Repita na **outra tela** (Pages e Blog) se quiser as duas evidências.

> Se o DevTools bloquear colagem, digite `allow pasting` primeiro (Chrome).
> Se `copy()` não existir (Firefox), troque a última linha por
> `console.log(JSON.stringify(..., null, 2))` — me avise que eu adapto.

## 3. Valores esperados (referência A/B/C da AP + HEAD atual)

| Campo | Esperado | O que indicaria problema |
|---|---|---|
| `path` | rota de edição de página/post | tela sem editor montado |
| **`marker_ap`** | **`true`** | `false` → AP não carregada / cache antigo |
| **`marker_ao`** | **`false`** | `true` → **AO reprovada ainda ativa** (grave) |
| `styles` | 2 entradas com `blog-block-editor-kaz` e `?v=…-kaz-bdre-an` / `-ap` | ausência de `-ap` |
| **`bs_primary`** | **`#0d6efd`** | `#31efb8` → vazamento da AO |
| `rms_accent` | `#0d6efd` | verde → vazamento da AO |
| **`menu_active_bg`** | **`rgb(13, 110, 253)`** (azul) | `rgb(49, 239, 184)` → **reprovação da AO** |
| `page_bg` | claro | `rgb(24, 24, 27)` → Admin escurecido (AO) |
| `host_present` | `true` | `false` → editor não montado nesta tela |
| `host_bg` | `rgb(24, 24, 27)` (escuro, **só no host**) | claro → identidade KAZ não aplicada |
| `editor_mounted` | `true` (`.rb-editor`) | `false` → JS do editor não rodou |
| `canvas_bg` | `rgb(255, 255, 255)` (branco) | escuro → canvas divergente |
| `data_bs_theme` | `light` (inalterado) | dark → alteração de página (revogada) |

**Divergência = achado a investigar, não erro automático.** O Admin real pode
usar skin/modo diferente da prévia. Me envie o JSON e eu interpreto antes de
qualquer conclusão.

## 4. O que eu faço com o seu JSON

- Comparo campo a campo com a tabela acima e com o A/B/C já medido
  (nativo/AP `rgb(13,110,253)`, AO `rgb(49,239,184)`, AP 0 divergências fora
  do editor, AO 54);
- aponto cada divergência com a causa provável e a ação correspondente;
- registro a evidência no protocolo, **sem** converter em `PASS`, **sem**
  tocar em contrato/hash e **sem** declarar release.

## 5. Limites

Health observacional, CI verde (7/7, com `static-analysis` em andamento neste
instante), PHP WASM, Chromium headless e guardas estáticos **não substituem o
veredito visual** e **não significam release aprovada**. O veredito **APROVADO**
da AP já foi dado; este health é a parte técnica que faltava do passo 2.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` ·
`runtime_tests_executed: false` · F9-18 `release_ready: false` ·
10 `PENDING_DECISION` / 0 `DECIDED` · 11 contratos congelados coerentes.

Sem sessão autenticada, MySQL, staging, migration ou deploy neste ambiente.
Nenhuma escrita do agente no GitHub.
