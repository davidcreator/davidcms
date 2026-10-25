# BDREditor `core/file` — contrato e implementação

## Forma do bloco

```json
{
  "id": "file1",
  "type": "core/file",
  "attributes": { "src": "view/download/blog/manual-<random>.pdf" },
  "children": [],
  "content": [{ "text": "Baixar manual", "marks": [], "href": null }]
}
```

- Exige documento `format_version: 2`.
- Atributos permitidos: somente `src`; children vazios.
- Conteúdo: runs v2 existentes. O texto combinado não pode ser vazio/só whitespace. Marcas inline fixas permitidas; href inline é rejeitado para evitar `<a>` aninhado.
- `src` deve ser um filename único sob `view/download/blog/`, sem host/esquema, query/fragment, percent-encoding, barras extras, `.`/`..`, controles ou symlink. Extensões: `.pdf`, `.docx`, `.xlsx`, `.pptx`, `.odt`, `.ods`, `.csv`, `.txt`.
- Conversão de documentos v1 não promove `core/file` legado: a versão v2 é obrigatória.

## Fluxo admin

1. `downloadList` (GET) exige ACL `modify` e lista apenas arquivos regulares cujo MIME detectado concorda com a allowlist; não cria diretório por efeito colateral de leitura.
2. `downloadUpload` (POST) exige ACL `modify` + CSRF; rejeita ausência, falha de upload, arquivo vazio, divergência de tamanho, acima de 2 MiB, extensão/MIME incompatível e formatos não permitidos.
3. Os destinos são gerados pelo servidor e não sobrescrevem arquivos. O nome enviado só é usado como base visual sanitizada, com sufixo aleatório; traversal não participa do caminho físico.
4. A pasta fixa é `catalog/view/download/blog/`; criação inicial ocorre no fluxo de upload, com verificação de symlinks nos ancestrais e `index.html` vazio contra listagem simples. Sem criação de subpastas ou exclusão de downloads.
5. A biblioteca de downloads possui UI/modal próprios e não altera a lista, upload ou exclusão de imagens existente.

## Renderização pública

`BlockDocument` rejeita tipo/atributos/caminhos não permitidos. Ao salvar, o controller também confirma que cada arquivo referenciado existe e passa a mesma verificação MIME no modelo de mídia. `BlockRenderer` recebe raiz física confiável do controller de catálogo, confirma caminho físico dentro da raiz, rejeita symlink e repete a validação MIME; emite somente:

```html
<p class="rms-block rms-block-file"><a href="..." download>rótulo escapado</a></p>
```

URL usa a base confiável do site e caminho local fixo. Rótulo é renderizado pelos runs allowlisted e escaping existente. Arquivo ausente/inválido resulta em saída vazia para esse bloco (fail-closed). Sem target blank, HTML arbitrário, classes controladas pelo documento ou links remotos.

## Upload e MIME

Allowlist do app, máximo 2 MiB, verificação via `finfo(FILEINFO_MIME_TYPE)`. DOCX/XLSX/PPTX/ODT/ODS podem ser identificados como `application/zip`; nesse caso, `ZipArchive` verifica a parte OOXML esperada e `[Content_Types].xml` (até 64 KiB, com o MIME principal correspondente), ou a entrada `mimetype` ODF (até 128 bytes e valor exato). Os arquivos extraídos não são materializados em disco. Se a extensão não estiver instalada ou o container não bater, o upload é rejeitado. Extensões macrohabilitadas, executáveis, HTML e arquivos compactados genéricos não são aceitos.

## Arquivos de código envolvidos

- `dev/system/library/blog/block_document.php` — allowlist e validação de `core/file`.
- `dev/system/library/blog/block_renderer.php` — link fixo e guard fail-closed de arquivo físico/MIME.
- `dev/catalog/controller/blog/blog.php` — passa raiz de download confiável ao renderer.
- `dev/admin/model/blog/media.php` — raiz dedicada, listagem, MIME, segurança de caminho e upload.
- `dev/admin/controller/blog/blog.php` — endpoints ACL/CSRF, valida referências ao salvar, strings e URLs de interface.
- `dev/admin/view/js/blog-block-editor.js` — tipo, validação cliente, bloco/inspector e escolha de documento.
- `dev/admin/view/js/blog-file-media.js` + `dev/admin/view/template/blog/blog_form.twig` — picker/upload separado e acessível.
- `dev/admin/language/{pt-br,en-us,es-eu}/blog/blog.php` — rótulos e mensagens.
- `dev/catalog/view/css/blog-blocks.css` — estilo público do bloco e foco visível do link; a folha admin existente não exigiu alteração.

## Cobertura do checker estático

- fixture válida e seis documentos inválidos: URL externa, traversal, extensão, label só whitespace, href inline e formato v1;
- allowlist de caminho no helper cliente e rejeição de URLs e caminhos malformados;
- presença de validação server-side, MIME, ACL/CSRF, renderer fail-closed, picker, traduções e ausência de endpoint de exclusão.

Não executa PHP, rede, upload real, browser nem aplicação; ver `tools/e2e/README.md`.
