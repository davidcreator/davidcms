# Guia — Reamur Reference (tema de referência em Twig)

**Arquivo:** `docs/temas/Reamur-Reference.reamurtheme.json`
**Formato:** `reamurcms-theme` v1 · **26.226 bytes** · **checksum SHA-256**
`07b6b2d294681e01ccec14cc0a02e3e50feeb72d8a2b0b0e4453e90853a444d4`.

## O que é

Um **tema completo e importável**, criado para servir de **referência** de
estrutura, segurança e personalização no KAZEditor-Theme. Não é um tema
instalado por padrão e **não é um release**: é um pacote de dados que você
importa quando quiser.

Foi gerado pelo próprio `ThemePackage::export()` do ReamurCMS e validado pelo
`ThemePackage::decode()` real — não é um JSON "parecido".

## Como usar

1. No Admin, abra **Temas do Catálog**.
2. Clique em **Importar tema** (o botão agora abre o gerenciador).
3. Escolha/arraste `docs/temas/Reamur-Reference.reamurtheme.json`.
4. Confira a prévia: **Conteúdo Twig = 25 fontes** e **Conteúdo ThreeJS =
   `hero-particles` / slot `hero`**, 5 camadas no `kaz_scene`.
5. Clique em **Importar como rascunho**. Nada é publicado automaticamente.
6. Revise, edite e **Ativar** quando quiser. Para remover: **Desativar** e
   depois **Excluir**.

Também é possível colar o conteúdo do arquivo na textarea, sem JS.

## O que o pacote contém

| Bloco | Conteúdo |
|---|---|
| `primary` / `surface` | `#1d4ed8` / `#ffffff` |
| `scene` | `hero-particles` |
| `three` | `enabled`, `scene=hero-particles`, `slot=hero`, `count=400`, `color=#1d4ed8`, `speed=0.05` |
| **`templates`** | **as 25 fontes Twig allowlistadas**, ~19,8 KB no total |
| `tokens` | os 22 tokens do `TokenRegistry` (contraste AA validado) |
| `conditions` | `version=1`, `rules=[]` (base limpa para você estender) |
| `kaz_scene` | `kaztheme-scene/v1`, canvas 960×540, 5 camadas (`rect`, `card`, `text`, `section`, `button`) |
| `parent` | vazio — tema base, pronto para ser pai de child-themes |

### As 25 fontes Twig

`common/home`, `common/footer`, `common/contact`, `partial/site_header`,
`partial/hero`, `partial/hero-3d`, `partial/about`, `partial/methodology`,
`partial/skills`, `partial/services`, `partial/testimonials`,
`partial/portfolio`, `partial/tools`, `partial/news`, `partial/clients`,
`partial/contact`, `cms/page_info`, `catalog/project_list`,
`catalog/project_info`, `blog/blog_list`, `blog/blog_info`,
`tool/tool_list`, `tool/tool_info`, `checkout/quote`, `error/not_found`.

Templates de rota começam com `{{ header }}`; `common/footer` fecha
`</body></html>`; os `partial/*` são fragmentos. Todos usam classes próprias
`ref-*`, `|default(...)` em variáveis e incluem os partials de cena ThreeJS
para não perder o 3D.

## Regras de segurança respeitadas

As fontes são **strings Twig dentro do JSON**, validadas por
`ThemePackage::validateConfig()` e pelo adaptador Twig (`ArrayLoader`).
**Nenhuma** contém `<script>`, `<iframe>`, `<object>`, `<embed>`,
`javascript:`, atributos `on…=`, `|raw`, `{% autoescape`, `srcdoc=` ou
`formaction=`. Sem CDN, fontes externas, `@import`, `!important`,
`--bs-*` ou `--rms-*`. Sem arquivos `.twig`/`.js` no servidor, sem upload
em disco, sem `eval`, sem código executável.

O **checksum** cobre o `config` canônico (chaves ordenadas recursivamente,
`JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE`). Um byte alterado invalida
o pacote com `checksum_invalid`.

## Como estender

- Edite as cores/tokens/cena no editor de temas e **Exportar rascunho** para
  gerar um novo pacote versionável.
- Use `parent: 'reamur_reference'` para criar um **child-theme** que herda
  tokens, templates e ThreeJS (cadeia limitada a 5 níveis, mesclada por chave).
- Para adicionar fontes Twig além das 25, é preciso alterar
  `ThemePackage::TEMPLATE_KEYS` — isso é uma decisão de allowlist, não deste
  tema.

## Verificação

```bash
node tools/verify-reference-theme.cjs <raiz>
```

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. CI verde, validação WASM e
guardas estáticos **não significam release aprovada**. Este tema **não é** uma
declaração de release, não altera migration/schema/contratos e não foi
escrito no GitHub pelo agente.
