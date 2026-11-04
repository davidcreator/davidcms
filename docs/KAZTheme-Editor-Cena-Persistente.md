# KAZTheme-Editor — cena persistente (Frente V)

**Objetivo:** fechar a limitação declarada na frente U. A cena `kaztheme-scene/v1` deixa de ser apenas um artefato do textarea local e passa a fazer parte do **config do tema**, com validação no servidor, round-trip no pacote e herança coerente.

## 1. O que mudou

| Antes (U) | Depois (V) |
| --- | --- |
| Cena derivada do config a cada abertura | Cena salva no config é reaberta; derivação fica como fallback para temas antigos |
| Textarea apenas para copiar/colar | Textarea é `name="kaz_scene_json"`, submetido e sincronizado no `submit` |
| Nenhuma validação no servidor | `ThemePackage::validateScene()` / `normalizeScene()` fail-closed |
| `CONFIG_KEYS` com 13 chaves | 14 chaves, com `kaz_scene` |
| Cena não viajava no pacote | `export()`/`decode()` incluem e revalidam a cena |
| — | Child-theme substitui a cena integralmente |

## 2. Migração zero

Não há migration, schema, coluna, job de conversão ou backfill. um tema A–U simplesmente **não tem** a chave `kaz_scene`; ao abri-lo, `edit()` detecta `normalizeScene(null) === null` e usa `kazScene($config)` — exatamente a mesma cena derivada da U. O `data-kaz-saved="0"` e a nota "Cena derivada do config" avisam que é preciso salvar uma vez para fixar a cena. Pacotes exportados antes da V continuam importando: `validateConfig()` só valida `kaz_scene` quando a chave existe.

Nenhum dos 14 manifestos `CHECKSUMS*.txt` cobre `theme_package.php`, `theme.php`, o Twig ou o JS do editor. `theme_package.php` também **não** está entre os 11 artefatos congelados por SHA-256 em `tools/decision-matrix.json`; portanto nenhum hash do release gate foi reescrito.

## 3. Contrato servidor

`ThemePackage` expõe:

- `SCENE_VERSION = 1`, `SCENE_KINDS`, `SCENE_ROLES`, `SCENE_MAX_LAYERS = 48`, `SCENE_MAX_BYTES = 65536`.
- `validateScene(mixed $scene): array` — códigos estáveis `kaz_scene_type_invalid`, `kaz_scene_version_invalid`, `kaz_scene_canvas_invalid`, `kaz_scene_layers_invalid`, `kaz_scene_size_invalid`, `kaz_scene_layer_invalid`, `kaz_scene_id_invalid`, `kaz_scene_enum_invalid`, `kaz_scene_number_invalid`, `kaz_scene_color_invalid`, `kaz_scene_text_invalid`, `kaz_scene_flag_invalid`, `kaz_scene_bind_invalid`.
- `normalizeScene(mixed $scene): ?array` — reconstrói a cena com apenas as chaves allowlistadas e tipos garantidos, ou `null`.

`validateConfig()` acrescenta `kaz_scene_invalid` quando a chave existe e é inválida. `sanitizeConfig()` descarta `kaz_scene` com tipo errado. `spec()` publica os limites. `MAP_KEYS` **não** inclui `kaz_scene`, garantindo substituição integral na cadeia de child-themes (mesmo tratamento de `conditions` e `schedule`).

O texto das camadas é rejeitado se contiver tag `script`/`iframe`/`object`/`embed`, `javascript:`, atributo `on*`, `|raw`, `{% autoescape`, `srcdoc`, `formaction`, `{{` ou `{%`. No cliente, o mesmo texto vai para `textContent`, nunca para markup.

## 4. Fluxo de gravação

```
textarea[name=kaz_scene_json]  --submit-->  edit() / compare()
        -> buildDraftConfig($post)
        -> json_decode -> ThemePackage::normalizeScene()
        -> null  => error_validation (nada é gravado)
        -> array => $out['kaz_scene'] -> model_design_theme->save()
```

A chave de erro é `error_validation`, já presente nos três idiomas (`en-us`, `pt-br`, `es-eu`) — **nenhuma chave de tradução nova**. `buildDraftConfig()` é o mesmo construtor usado pela prévia e pelo save desde a frente C, então não há duas rotas de validação divergentes.

`decode()` continua calculando `checksum($config)` sobre o config canônico antes de `validateConfig()`. Como a cena entra no config, ela passa a ser coberta pelo checksum do pacote.

## 5. O que ainda não é feito

- A cena **não** é renderizada no Catalog. `ThemeSnapshot::resolve()` continua devolvendo apenas os globais de runtime; a cena é dado de edição/versionamento. O efeito visual no site continua vindo de `primary`, `surface`, `sections`, `components` e tokens.
- Condições e rollout não podem alterar a cena: o `then` do `ConditionEngine` permanece allowlistado e não a conhece.
- Não há upload, servidor de arquivos, `localStorage`, colaboração em tempo real, histórico persistido no banco ou geração automática de Twig a partir da cena.

Cada um desses pontos exigiria uma frente própria com decisão explícita.

## 6. Segurança e estado

Sem `innerHTML`, `eval`, `fetch`, tag `script` inline, `|raw` novo, `custom_js`, WebGL, Three.js, migration, schema, coluna, escrita remota ou deploy. F9-27 segue `BLOCKED_NOT_RUN` com `release_approved:false`; BDREditor/F9-103 pausado e intocado; P2/F9-18 não reaberto; limitação de hreflang da frente N mantida. CI verde/health coerente não é release aprovada.
