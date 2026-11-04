# KAZTheme-Editor — Biblioteca de Blocos (Frente X)

**Data:** 4 de novembro de 2026 (America/Sao_Paulo)  
**Base:** `main` @ `cd92573c507b6a5a4189d7b1b574cb73ec17ad59` (frente W aplicada 4/4).  
**Estado:** pacote instalável entregue para aplicação. **Não foi aplicado no GitHub por nós**; não é release aprovada.

## 1. O que a frente X acrescenta

Duas funcionalidades **visíveis no Admin**, dentro do card KAZTheme-Editor:

1. **Biblioteca de blocos** — 6 presets declarativos que inserem conjuntos de
   camadas reais no canvas. Os blocos são editáveis como qualquer camada
   (selecionar, arrastar, redimensionar, duplicar, ocultar, bloquear, desfazer)
   e são **persistidos na `kaz_scene`** ao salvar o tema, porque o editor
   sincroniza o campo `kaz_scene_json` a cada mutação.
2. **Selo "Alterações não salvas"** — compara a cena atual com o `baseline`
   carregado do servidor. Não aparece ao abrir, desaparece no `submit` e some
   ao desfazer até o estado inicial.

| ID allowlistado | Camadas | Conteúdo |
|---|---|---|
| `hero` | 3 | painel 560×200, título 480×80, botão 160×56 |
| `card` | 2 | cartão 280×176, texto 240×64 |
| `banner` | 2 | faixa 720×96, chamada 640×48 |
| `titulo` | 2 | título 480×64, linha de destaque 480×8 |
| `galeria` | 3 | três molduras 160×120 lado a lado |
| `cta` | 2 | seção 480×120, botão 160×56 |

## 2. Contrato e segurança

Cada template gera uma camada com exatamente as **14 chaves** de
`kaztheme-scene/v1`: `id` único (`kaz-layer-N`), `kind` entre
`rect|text|button|card|section`, `role='none'`, `bindId=''`, `visible=true`,
`locked=false`, `fill` em `#[0-9a-fA-F]{6}`, texto estático de no máximo 80
caracteres, `radius` 0..64, `fontSize` 8..72, `w` 8..960, `h` 8..540 e `x`/`y`
com snap na grade de 8 px. A origem é calculada a partir da maior borda
inferior das camadas existentes, com módulo sobre a altura útil do canvas, para
que o bloco fique visível sem sair dos limites.

**Fail-closed:** ID de bloco desconhecido é rejeitado sem alterar nada; se
`camadas atuais + camadas do bloco > 48`, a inserção é recusada integralmente.
Os presets são **constantes no JavaScript** — nenhum texto do usuário, request,
sessão ou arquivo entra na definição deles. `validScene()` continua validando
toda a cena após a inserção e `ThemePackage::normalizeScene()` revalida no
servidor antes de gravar.

**Nenhum binding automático:** inserir um bloco não chama `applyBindings()` e
não altera `primary`, `surface`, `sections` ou `components`. O usuário continua
precisando clicar em **Aplicar ao tema**, como na U/V.

Continua sem `innerHTML`, `outerHTML`, `document.write`, `eval`, `Function`,
`fetch`, `XMLHttpRequest`, jQuery AJAX, `srcdoc`, tag `script` inline,
atributo `on*`, `localStorage`, `|raw` novo, `custom_js`, WebGL, Three.js,
upload, migration, coluna, `schema.sql`, chave de tradução nova ou escrita
remota. Os rótulos dos botões são literais estáticos no Twig (padrão da U).
O cache key dos assets passou de `-kaz-theme-editor-v` para
`-kaz-theme-editor-x` para evitar que o navegador sirva o JS antigo.

## 3. O que NÃO mudou

`CONFIG_KEYS` (14 chaves), `MAP_KEYS`, `validateScene()`, `normalizeScene()`,
`validateConfig()`, `decode()`, `export()`, `resolveChain()`, os 14 manifestos,
os 11 artefatos congelados por hash, `ThemeSnapshot`, `ConditionEngine`,
`startup/*`, o runtime do Catalog, as 418 chaves de tradução ×3 idiomas e o
comportamento de save/prévia das frentes V e W. **Não há renderização da cena
no site público** — a cena continua sendo dado de edição e versionamento.
Child-themes continuam substituindo a cena integralmente.

## 4. Instalação

Extrair os 7 arquivos completos sobre a raiz do repositório (6 substituem,
1 é novo). Não há patches nem manifestos a atualizar — os 7 paths estão
**LIVRES** nos 14 `CHECKSUMS*.txt` (90 paths cobertos). Depois:
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.

## 5. Limitações

PHP 8.5 WASM, Twig 3.14 e jsdom 29.1.1 com mocks **não são um servidor**. Não
houve PHP nativo, Composer, PHPStan, MySQL, sessão Admin autenticada,
navegador real, E2E, staging, deploy ou CI remoto verificado. **Nenhuma escrita
no GitHub foi feita por nós.** F9-27 segue `BLOCKED_NOT_RUN` com
`release_approved:false`; BDREditor/F9-103 pausado; P2/F9-18 não reabertos; a
limitação de hreflang da frente N permanece. **CI verde/health ≠ release aprovada.**
