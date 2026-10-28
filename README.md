# CMS > Páginas — biblioteca própria de imagens (V1)

Implementação local preparada sobre o HEAD remoto `dc4caa2460ddffdcba362292fe50a69a1d1b38b5` (`docs(bdreditor/cms): add CMS Pages gap audit doc`).

## Escopo

- Seletor próprio de imagens para os blocos `core/image` e `core/cover` em CMS > Páginas.
- Biblioteca física `catalog/view/image/cms/page/`; lista com busca e paginação, seleção e upload.
- JPEG, PNG, WebP e GIF; limite de 2 MiB; nomes gerados pelo `MediaUpload` compartilhado após preflight do diretório e dos symlinks.
- Rotas próprias `cms/page.mediaList` (GET) e `cms/page.mediaUpload` (POST), ambas ACL `modify cms/page`; o POST valida CSRF.
- Não há pastas, exclusão, campo de imagem/capa na entidade Página, schema, migration ou escrita no GitHub.
- O seletor/rota/diretório de imagens de Pages não reutiliza os fluxos de mídia do Blog. O motor de blocos já compartilhado é mantido; somente a allowlist de caminhos compartilhada foi ampliada para reconhecer o prefixo aprovado.

## Arquivos

- `CMS-Pages-Image-Library.patch` — patch unificado para aplicar a partir da raiz do repositório no HEAD indicado.
- `implementation/` — cópia dos arquivos modificados e adicionados, preservando os caminhos `dev/...`.

Aplicação: `git apply CMS-Pages-Image-Library.patch` (execute da raiz do repositório). O patch é local; não foi enviado ao GitHub.

## Validação feita

- `git diff --check`: passou.
- `node --check` nos três scripts JS modificados/adicionados: passou.
- Checagens estáticas de ACL, CSRF, tipos/tamanho, strings nos três idiomas, ausência de SQL/DDL no novo model e isolamento dos elementos da UI: passaram.
- `php -l` não pôde ser executado: PHP CLI não está instalado neste ambiente. Não houve teste de upload real, execução do CMS, alteração de banco, staging, deploy ou migration.
