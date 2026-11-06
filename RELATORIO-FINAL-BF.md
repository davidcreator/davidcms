# Relatório Final — Frente BF (correção do botão + Gerenciador de Temas)

**Data:** 2026-11-06 · **HEAD auditado:** `6ee263724af50008277d5af56d6d0f6fa4b7d551`
**CI remoto:** ✅ 7/7 success · **Gatilho:** *"Ao clicar no botão para importar nenhum evento ocorre"*.

## 1. O bug — causa raiz (meu erro na BE)

```twig
<a href="#theme-import" …>{{ button_import }}</a>       <!-- linha 7 -->
<details id="theme-import" {% if import_open %}open{% endif %}>  <!-- linha 15 -->
```

O botão era **apenas uma âncora** para um `<details>` **fechado** (só aberto
quando `?import=1`). Clicar rolava a página até um painel que continuava
recolhido — **nenhum evento visível**. Eu havia antecipado essa redundância e
escolhi mal. O usuário estava certo.

**Correção:** `data-theme-import-open` no botão + handler real no
`theme-package.js` (já carregado pela lista): `preventDefault()`,
`panel.open = true`, `scrollIntoView()` e `focus()` na textarea. O handler é
registrado **antes** do `early-return` do `FileReader`, então o botão funciona
mesmo sem suporte a `FileReader`. O `href` permanece como fallback sem JS.

## 2. Gerenciador de Temas (Twig + ThreeJS)

O pedido "importar os pacotes necessário tanto para twig quanto ThreeJS" foi
implementado na **interpretação segura** já decidida por você na frente BC
(**dados validados, sem código executável**): um **único pacote**
`reamurcms-theme` transporta **os dois tipos de conteúdo** como dados, e o
gerenciador agora **mostra e valida cada um**.

| Bloco do painel | O que exibe |
|---|---|
| **Pacotes Twig** | 25 fontes allowlistadas (`TEMPLATE_KEYS`), como strings validadas dentro do JSON |
| **Pacotes ThreeJS** | 5 cenas + 6 presets allowlistados, `three`/`kaz_scene` como dados versionados |
| **Importar** | textarea, escolha/arraste de `.json`, CSRF, mesmo POST `design/theme.import` |
| **Prévia · Conteúdo Twig** | nº de fontes, lista das chaves (até 25), status "Todas allowlistadas" / "Chave fora da allowlist" / "Ausente" |
| **Prévia · Conteúdo ThreeJS** | cena, slot, status "Cena allowlistada" / "Cena fora da allowlist" / "Ausente", nº de camadas e versão `kaz_scene`, tipos de camada |
| **Prévia · Demais blocos** | tokens, condições, seções, componentes, schedule, mídia, sites |

As allowlists são **injetadas pelo servidor** (`template_keys_list`,
`scene_list`, `template_count`, `scene_count`, `preset_count` via
`ThemePackage::TEMPLATE_KEYS` e `ScenePresetRegistry`) em atributos `data-*`.
O cliente **sinaliza** divergências, mas **não decide**: `decode()` com
`hash_equals` e `validateConfig()` continua a autoridade.

A tabela de temas com Ativar/Desativar/Excluir (BE) foi preservada.

## 3. Arquivos (6)

| Arquivo | Ação |
|---|---|
| `dev/admin/view/js/theme-package.js` | handler do botão + inspeção Twig/ThreeJS; mantém toda a BC |
| `dev/admin/view/template/design/theme_list.twig` | botão com `data-*`, cartões de capacidade, prévia enriquecida |
| `dev/admin/controller/design/theme.php` | `index()` injeta as allowlists/contagens + 25 chaves de idioma |
| `dev/admin/language/{en-us,pt-br,es-eu}/design/theme.php` | 25 rótulos/status por idioma |

**Nenhuma rota, model, `ThemePackage`, `TEMPLATE_KEYS`, migration, schema,
contrato, hash, manifesto ou workflow foi alterado.** Nenhum dos 6 alvos está
em `CHECKSUMS*.txt`.

### Segurança

Sem `fetch`, `XMLHttpRequest`, `eval`, `Function`, `innerHTML`, `outerHTML`,
`document.write`, storage, `import()` ou `require()`. Listas são construídas
com `createElement` + `textContent`. Sem `|raw` novo (só os 2 do chrome), sem
`<script>`, sem JS inline, sem upload em disco, sem `ALTER`/`CREATE`/`DROP`.
Import continua **sempre como rascunho**, nunca publica.

## 4. Validação

| Verificação | Resultado |
|---|---|
| `node --check` no JS | ✅ |
| `php -l` (**PHP 8.5.12** WASM): controller + 3 idiomas | ✅ **4/4** |
| **Harness jsdom** | ✅ **47 OK / 0 FALHAS** — **reprodução do bug** (painel fechado) → **clique abre**, foco, funciona sem `FileReader`; pacote completo Twig+ThreeJS; chave/cena fora da allowlist; pacote sem nenhum dos dois; >512 KiB; extensão/JSON/checksum inválidos; drag-and-drop; 10 checagens de superfície; 7 checagens estruturais do Twig |
| `tools/verify-theme-manager.cjs` | ✅ 0 FALHAS |
| `verify-checksums.sh` / `repo-health.sh` na réplica fiel | ✅ 142 arquivos / 0 divergentes · **6 PASS / 0 FAIL / COERENTE** |
| `verify-contract-freeze.py` | ✅ COERENTE |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

## 5. O que NÃO foi feito — decisão sua

**Não** foram criados pacotes separados contendo **arquivos `.twig` ou `.js`
ThreeJS executáveis**, nem upload de ZIP, nem escrita em disco, nem sandbox
Twig. Isso **reverteria** a decisão "dados validados, sem código executável" e
exigiria ACL dedicada (F9-25), revisão de migration e análise de SSTI/XSS.
Hoje está **vetado**; se quiser abrir essa frente, diga explicitamente.

## 6. Limites

Nenhuma escrita do agente no GitHub, nenhuma migration, contrato congelado,
hash, `schema.sql`, workflow ou biblioteca alterado. Nenhuma conversão de
`PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, nenhuma decisão das
**10 `PENDING_DECISION`**, nenhuma mudança em `PROPOSAL_PENDING` /
`DESIGN_GATE_PENDING`, nenhuma declaração de release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false`. CI verde, PHP WASM, jsdom e guardas estáticos **não
significam release aprovada** e não substituem o aceite no Admin real.

**Ainda pendentes:** JSON do **health** do Admin real (guia aplicado, não
colado) e aceites visuais (Ferramentas internas, nomes, lista de temas,
gerenciador).

## 7. Próximos passos

1. **Aceite visual** no Admin real: clicar em "Importar tema" e confirmar que
   o painel abre, e carregar um pacote para ver a prévia Twig/ThreeJS.
2. Decisão sobre **arquivos executáveis** (ver §5), se desejar.
3. **Recorte B** — ampliar `TEMPLATE_KEYS` (diga quais templates).
4. Passos do roteiro: health (2), D01–D10 com **AR** (3), F918 com **AS** (4).
