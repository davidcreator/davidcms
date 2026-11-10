# Fase 9 · Tarefa 15 — Renderer público allowlisted do Blog

**Data:** 2026-10-24  
**Repositório verificado:** `https://github.com/davidcreator/davidcms`  
**HEAD base de `main`:** `0162822d3bf98ce100780b17cf0c88ff22ee832f` (`feat(f9-14): add standalone blog block editor mvp`).  
**F9-14:** assets e contrato conferidos por SHA-256 no repositório correto.  
**Estado:** renderer + leitura catalog condicional preparados; sem runtime/browser/PHP/DB tests.

## Entrega

- `dev/system/library/blog/block_renderer.php`: converte JSON validado em HTML fixo, sem bloco HTML/custom markup.
- `dev/system/library/blog/block_document.php`: o validador F9-13 foi ampliado para os tipos/layouts definidos na UI F9-14.
- Model Blog detecta a tabela sidecar por nome exato via `information_schema` antes de consultá-la; usa o idioma de conteúdo escolhido pelo `getPost`.
- Controller invoca renderer somente quando há linha do sidecar; template recebe HTML resultante dentro de branch explicitamente confiável.
- CSS público responsivo para grupos, imagens, botões, vídeo e colunas 2/3; no mobile as colunas empilham.
- Texto de indisponibilidade localizado nas três línguas do Blog.

## Allowlist e escaping

Todos os campos de texto passam por `htmlspecialchars` com `ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5`. `<br>` é o único elemento gerado a partir de quebras de linha. Classes e tags são fixas, não configuráveis pelo documento. Atributos extras ou block types não conhecidos rejeitam o documento inteiro e geram `html=''`.

- Parágrafo, heading nível 1–6, lista e item, citação, separador, grupos e colunas com schemas restritos.
- Imagem: caminho local em `image/` ou `/image/`, caracteres ASCII allowlisted, sem esquema/host, query, fragment ou segmentos `.` / `..`; `src`, `alt`, `title` são contextualizados ao gerar atributos.
- Botão: apenas HTTP(S) ou caminho raiz local; barra protocol-relative, URL com credenciais e protocolos `javascript:`/`data:` rejeitados.
- Embed: extrai apenas ID de formato aceito em YouTube/Vimeo e reconstrói `src` em hosts fixos. Nunca reutiliza uma URL arbitrária em `iframe`. Sandbox restringe forms, popups e navegação superior.
- `core/group`, `core/columns`, `core/column`: filhos e contagens validados; nenhum estilo CSS livre.

## Compatibilidade / fallback

- **Sem migration/tabela sidecar ou sem linha daquele idioma:** o catálogo mantém o caminho legado atual para `description` exatamente como antes. A descrição/snapshot segue alimentando o excerpt da lista.
- **Linha sidecar válida:** conteúdo visível usa exclusivamente o HTML produzido pelo renderer.
- **Linha presente inválida, versão divergente, ou erro ao ler a linha:** template não publica o fallback HTML dessa linha; mostra mensagem localizada de revisão. Isto evita bypass silencioso do fail-closed e não descarta a fonte armazenada.
- O antigo `description|raw` continua somente no branch legado para compatibilidade histórica; esta tarefa não o sanitiza, não o amplia e não converte posts.

A consulta à sidecar é protegida por uma detecção da tabela primeiro. Nenhuma migration é aplicada; o código funciona antes do upgrade F9-12 e degrada ao HTML legado. O acesso a `information_schema` e a query pública ainda precisam de verificação no ambiente do projeto antes de deploy.

## Fixtures e validação

`tests/fixtures/renderer.json` e `tests/run_f915_fixtures.php` cobrem texto/script escaping, newline, atributos de imagem, embeds fixos YouTube/Vimeo, containers responsivos, URL/image inseguras, block type/atributo desconhecidos, layout inválido e versão divergente. Runner funcional não foi executado por restrição ativa. `tools/e2e/e2e_f915.sh --check` valida fontes/contratos e fixtures estaticamente; não prova sintaxe/runtime do PHP, query ou template.

**Não houve execução de SQL; testes PHP/browser/app não foram executados. Nenhum banco foi acessado ou alterado, não houve deploy e o GitHub não foi modificado.**
