# Índice — Frente BG (tema de referência em Twig)

Pacote: `ReamurCMS-Frente-BG-Tema-Referencia-Twig.zip` · HEAD `11b98aac…` · CI 7/7 ✅
**1 pacote importável + documentação e verificador** · **nenhum código alterado** · **sem migration**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-BG.md` | Construção, validação real, segurança, limites |
| 2 | `INDICE-BG.md` | Este índice |
| 3 | **`docs/temas/Reamur-Reference.reamurtheme.json`** | **O tema**: 25 fontes Twig + ThreeJS, 26.226 bytes |
| 4 | `docs/temas/GUIA-TEMA-REFERENCIA.md` | Como importar, o que contém, como estender |
| 5 | `tools/verify-reference-theme.cjs` | Verificador Node puro (checksum independente) |
| 6 | `evidencia/theme-package-decode.txt` | `validateConfig()`/`decode()` reais em PHP WASM |
| 7 | `evidencia/validacao.txt` | Guardas na réplica |

## O tema

| Campo | Valor |
|---|---|
| `name` / `slug` | `Reamur Reference` / `reamur_reference` |
| `format` / `version` | `reamurcms-theme` / `1` |
| `checksum` | `07b6b2d294681e01ccec14cc0a02e3e50feeb72d8a2b0b0e4453e90853a444d4` |
| `templates` | **25/25** allowlistadas, ~19,8 KB |
| `three` | `hero-particles` / `hero` / 400 / `#1d4ed8` / 0.05 |
| `kaz_scene` | v1, 960×540, 5 camadas |
| `tokens` | 22 tokens AA |
| `parent` | vazio (tema base / child-themes) |

## Como usar

Admin → **Temas do Catálog** → **Importar tema** → escolher o `.json` →
conferir a prévia Twig/ThreeJS → **Importar como rascunho** → revisar →
**Ativar**. Nada é publicado automaticamente.

## Validação medida

`validateConfig()` **0 erros** · **`decode()` ok=true** com as classes reais
com `export()` (PHP 8.5.12 WASM) · **checksum idêntico** em Python e Node ·
25 fontes sem conteúdo proibido / `|raw` / `<script>` / CDN · Twig
balanceado 25/25 · verificador 0 FALHAS · `verify-checksums` 142/0 ·
`repo-health` **6 PASS / 0 FAIL** · `verify-contract-freeze` **COERENTE**.

## O que NÃO faz

Não instala o tema automaticamente, não cria arquivos `.twig`/`.js`, não faz
upload em disco, não altera `TEMPLATE_KEYS`, controller, model, schema,
contrato, hash, manifesto ou workflow, não decide D01–D10, não escreve no
GitHub pelo agente e **não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Health e aceites visuais
ainda pendentes.**
