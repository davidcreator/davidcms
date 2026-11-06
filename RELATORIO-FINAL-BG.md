# Relatório Final — Frente BG (tema completo em Twig de referência)

**Data:** 2026-11-06 · **HEAD auditado:** `11b98aace6c4478747d8782c86afc38f9f06f930`
**CI remoto:** ✅ 7/7 success · **Pedido:** *"criar um tema completo em twig para ser uma opção de tema de referencia"*.

## 1. O que foi entregue

**`docs/temas/Reamur-Reference.reamurtheme.json`** — um pacote
`reamurcms-theme` v1 **importável pelo botão "Importar tema"** (corrigido na
BF), com **as 25 fontes Twig allowlistadas preenchidas**, ThreeJS, tokens,
condições e cena de editor. **26.226 bytes**, checksum
`07b6b2d294681e01ccec14cc0a02e3e50feeb72d8a2b0b0e4453e90853a444d4`.

Acompanham: `docs/temas/GUIA-TEMA-REFERENCIA.md`,
`tools/verify-reference-theme.cjs` e `evidencia/theme-package-decode.txt`.

| Bloco | Valor |
|---|---|
| `name` / `slug` / `parent` | `Reamur Reference` / `reamur_reference` / vazio (tema base) |
| `primary` / `surface` | `#1d4ed8` / `#ffffff` |
| `scene` / `three` | `hero-particles`; `enabled`, `slot=hero`, `count=400`, `color=#1d4ed8`, `speed=0.05` |
| **`templates`** | **25/25 chaves `TEMPLATE_KEYS`**, ~19.823 bytes de Twig |
| `tokens` | 22 tokens do `TokenRegistry` (contraste AA validado) |
| `conditions` | `version=1`, `rules=[]` |
| `kaz_scene` | `kaztheme-scene/v1`, canvas 960×540, 5 camadas |

## 2. Como foi construído (e por que é confiável)

Não é um JSON "parecido" com o formato: foi gerado pelo **próprio
`ThemePackage::export()`** e validado pelo **`ThemePackage::decode()` real**,
executado em PHP **8.5.12 WASM** com as **classes reais** do ReamurCMS
(`ConditionEngine`, `TokenRegistry`, `SectionRegistry`, `ComponentRegistry`,
`ThemeSchedule`, `ThemeMedia`, `ThemeSites`, `PageLayout`).

| Verificação com as classes reais | Resultado |
|---|---|
| `validateConfig($config)` | **`[]` — zero erros** |
| `decode($json)` | **`ok = true`, `errors = []`** |
| `export()` → `checksum` | SHA-256 do config canônico |
| `decode()` → 25 templates, cena, nome/slug | conferidos |
| `resolveChain()` sem pai | `#1d4ed8` |
| **Checksum recalculado de forma independente em Python** | **bate exatamente** |

O checksum também é recalculado de forma independente em **Node** pelo
verificador (ordenação recursiva de chaves + `JSON.stringify`), garantindo que
o pacote não foi adulterado.

## 3. Regras que o tema obedece

As fontes são **strings Twig dentro do JSON** — o modelo de **dados
validados, sem código executável** que você confirmou na frente BF. Cada uma
passa na regex de `validateConfig()` e no adaptador `ArrayLoader`: sem
`<script|iframe|object|embed`, `javascript:`, `on…=`, `|raw`,
`{% autoescape`, `srcdoc=`, `formaction=`. Sem CDN, fontes externas,
`@import`, `!important`, `--bs-*` ou `--rms-*`.

**Um detalhe encontrado e corrigido durante a construção:** meu comentário
inicial de cabeçalho dizia *"Sem scripts, |raw, eventos ou autoescape"* — a
regex varre a string inteira, **inclusive comentários**, e recusaria 11
fontes por causa da palavra `|raw` no comentário. O texto foi trocado e a
varredura passou a **0 fontes proibidas**.

Templates de rota usam `{{ header }}`, `common/footer` fecha o documento, e
os includes de `partial/theme-scene*` preservam a cena ThreeJS. Todas as
variáveis usam `|default(...)` para não quebrar em rotas com contexto
diferente.

## 4. Onde fica e o que NÃO mexe

`docs/temas/` é um diretório novo. Testei na réplica fiel: o arquivo novo
**não** entra em nenhum `CHECKSUMS*.txt` e **não** altera os guardas —
`verify-checksums` 142 arquivos / 0 divergentes, `repo-health`
**6 PASS / 0 FAIL**, `verify-contract-freeze` **COERENTE**.

**Nenhuma** migration, `schema.sql`, contrato congelado, hash, manifesto,
workflow, `ThemePackage`, `TEMPLATE_KEYS`, controller, model, idioma ou
template de arquivo foi alterado. **Nenhum tema foi instalado
automaticamente**: para aparecer na lista é preciso importar o pacote — e o
import continua **sempre como rascunho**, nunca publicando.

## 5. Validação

| Verificação | Resultado |
|---|---|
| `validateConfig()` / `decode()` reais (PHP 8.5.12 WASM) | ✅ **0 erros / ok=true** |
| Checksum independente (Python + Node) | ✅ idêntico |
| 25 fontes: allowlist, tamanho, conteúdo proibido, `\|raw`, `<script>`, CDN, `!important` | ✅ 0 violações |
| Twig `if/endif` e `for/endfor` balanceados por fonte | ✅ 25/25 |
| `kaz_scene`: canvas, kinds/roles, ids, cores, textos | ✅ |
| `tools/verify-reference-theme.cjs` | ✅ 0 FALHAS |
| `verify-checksums` / `repo-health` / `verify-contract-freeze` na réplica | ✅ 142/0 · 6 PASS/0 FAIL · COERENTE |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

## 6. Limites

Nenhuma escrita do agente no GitHub, nenhum runtime/MySQL/staging/deploy,
nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, nenhuma
decisão das **10 `PENDING_DECISION`**, nenhuma mudança em
`PROPOSAL_PENDING` / `DESIGN_GATE_PENDING`, nenhuma declaração de release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false`. CI verde, PHP WASM e guardas estáticos **não
significam release aprovada** e não substituem o aceite no Admin real.

**Ainda pendentes:** JSON do **health** do Admin real e os aceites visuais
(Ferramentas internas, nomes, lista de temas, gerenciador e agora o tema de
referência importado).

## 7. Próximos passos

1. **Importar** o pacote no Admin real, conferir a prévia (25 Twig +
   ThreeJS) e dar o aceite visual.
2. **Recorte B** — ampliar `TEMPLATE_KEYS` se quiser mais fontes (diga quais).
3. Passos do roteiro: health (2), D01–D10 com **AR** (3), F918 com **AS** (4).
