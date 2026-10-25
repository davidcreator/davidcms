# Instruções para agentes — BDREditor arquivo/download

- Produto-alvo: editor nativo exclusivo ReamurCMS chamado BDREditor. Gutenberg é referência apenas; não importar pacote, código ou assets.
- Escopo deste pacote: bloco `core/file` e biblioteca Blog em `catalog/view/download/blog/`; tipos PDF, DOCX, XLSX, PPTX, ODT, ODS, CSV e TXT; limite 2 MiB; sem exclusão de documentos.
- Preservar validação/renderer server-side. Referências locais estritas; nada de URL remota, traversal, HTML arbitrary ou atributos livres.
- Não modificar o helper global de imagem. Manter ACL `modify`, CSRF e comportamento dos uploads existentes.
- Nenhum banco/schema/migration, upload para produção, operação GitHub, deploy ou staging nesta etapa.
- Não executar PHP/SQL/browser/app/runtime; usar apenas checker estático `node tools/e2e/e2e_bdre_file_static.cjs` e verificações source-only autorizadas.
- Qualquer alteração de limite, novos tipos, exclusão, área não pública ou novos endpoints deve ser proposta como novo escopo, não inferida.
