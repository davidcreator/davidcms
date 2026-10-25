# BDREditor — bloco arquivo/download

Incremento local do editor nativo do ReamurCMS. O Gutenberg continua sendo apenas referência conceitual: não há dependência, código ou assets Gutenberg/WordPress.

## O que foi criado

- Novo bloco `core/file` no contrato v2, com referência local de arquivo e rótulo rich-text editável/acessível.
- Seletor e upload dedicados à raiz `catalog/view/download/blog/`; não mistura documentos com a biblioteca de imagens.
- Listagem e upload protegidos por ACL `modify` e CSRF; não há endpoint de exclusão para downloads.
- Allowlist inicial: PDF, DOCX, XLSX, PPTX, ODT, ODS, CSV e TXT. Upload limitado a 2 MiB, MIME detectado e compatibilidade de arquivo compactado Office/ODF verificada quando necessária.
- Nome de armazenamento criado pelo servidor com sufixo aleatório; o nome do usuário não é usado como caminho.
- Validação client/server de caminho, extensão e rótulo. Renderer público só emite link fixo `<a download>` e não renderiza arquivo ausente, symlink ou com MIME inválido.
- Strings PT-BR, EN-US e ES-EU; sem schema, migration, tabela ou persistência nova.

## Instalação/revisão

Os caminhos em `dev/` espelham os do ReamurCMS. Revisar e aplicar somente os arquivos do pacote; ele não contém o projeto inteiro. O pacote foi montado a partir do estado `main` em `829ed0e7af6ff622e321c2528ff20f3ee6d34fab` e dos fontes locais da tarefa `core/table` já publicados pelo usuário. Não envia código ao GitHub.

## Verificações executadas

```sh
node --check dev/admin/view/js/blog-block-editor.js
node --check dev/admin/view/js/blog-file-media.js
node --check tools/e2e/e2e_bdre_file_static.cjs
node tools/e2e/e2e_bdre_file_static.cjs
python3 -m json.tool tests/fixtures/bdre_file.json >/dev/null
```

O checker é estático: executa apenas o validador JavaScript em VM sem DOM/browser contra fixture sintética e verifica fontes/strings/endpoints por presença de contrato. Ele não prova integração PHP, comportamento do upload real, MIME no servidor, rendering, segurança de servidor web, leitor de tela nem E2E real.

**Não executados por instrução:** PHP, SQL, browser, aplicação, testes runtime, staging, deploy, migrations ou banco. Staging permanece pausado.

## Limites conhecidos antes de release

- O ambiente não tem `upload_max_filesize`/`post_max_size` versionados. O limite do app foi mantido em 2 MiB (o mesmo teto do fluxo de imagens) e precisa caber nos limites PHP do servidor mais o overhead multipart.
- Office/ODF cujo `finfo` identifica como `application/zip` exige a extensão PHP `ZipArchive`; sem ela, o upload falha fechado.
- A pasta é pública por decisão de produto: não usar para documentos privados/sensíveis. O incremento não promete DRM, antivírus, DLP nem controle por usuário.
- A validação MIME com `finfo` reduz extensão/conteúdo incompatíveis, mas não é antivírus nem garante ausência de conteúdo malicioso dentro de documentos complexos.
- Nenhuma nova numeração oficial foi atribuída à tarefa.

## Arquivos de apoio

- Contrato e critérios: `docs/BDREditor-File-Download.md`
- Escopo/decisões: `docs/BDREditor-File-Download-Scope.md`
- Fixture: `tests/fixtures/bdre_file.json`
- Checker: `tools/e2e/e2e_bdre_file_static.cjs`
