# BDREditor — escopo confirmado: arquivo/download

## Estado

Escopo aprovado e implementado em pacote local de revisão. O documento de escopo foi publicado pelo usuário no commit `829ed0e7af6ff622e321c2528ff20f3ee6d34fab`; este pacote de código não foi enviado ao GitHub. Sem alterações em staging, produção ou banco.

## Base

- `main` verificada em `829ed0e7af6ff622e321c2528ff20f3ee6d34fab` (commit que publicou este escopo), pai `a67b14dc4cb553a800729146dd8490b48643f39f` (tarefa 01, `core/table`).
- A biblioteca original do Blog só listava imagens em `catalog/view/image/blog/`; o novo seletor usa uma área dedicada.

## Decisões aprovadas

- Bloco nativo `core/file` do BDREditor; Gutenberg não é dependência nem código/assets incorporados.
- Expandir a biblioteca do Blog com seletor/upload de documentos em `catalog/view/download/blog/`.
- Tipos iniciais: PDF, DOCX, XLSX, PPTX, ODT, ODS, CSV e TXT. Sem ZIP genérico, HTML ou scripts; não há exclusão de documentos.
- Referência local validada no cliente e servidor, rótulo rich-text v2 acessível, renderer server-side com link fixo e fail-closed para arquivo ausente/inválido.
- Sem schema/migration, tabela ou persistência nova. Upload dedicado ao modelo de mídia Blog; o helper compartilhado de imagens não foi alterado.
- Limite aplicado: 2 MiB por arquivo, alinhado ao fluxo existente de mídia. Configuração PHP `upload_max_filesize`/`post_max_size` do ambiente não está versionada; não foi executado runtime.
- Guardrails mantidos: sem PHP, SQL, browser/app, testes runtime, staging, deploy, migrations, banco ou escrita no GitHub. Staging pausado.
- Sem número oficial novo de tarefa.

## Entrega

Implementação e evidências estáticas: `../README.md`, `BDREditor-File-Download.md`, `tests/fixtures/bdre_file.json` e `tools/e2e/e2e_bdre_file_static.cjs`. O checker valida fixtures sintéticas em VM JS sem DOM e varre contratos; não é E2E runtime nem certificação da integração PHP/web.

## Pendências para release futuro

- Confirmar que o servidor operacional permite uploads de 2 MiB mais o overhead multipart; nenhum valor de PHP/web do ambiente foi consultado por runtime.
- Validar em ambiente de staging autorizado a integração PHP, MIME/ZipArchive, ACL/CSRF, renderização, resposta HTTP e acessibilidade real. Não executado neste incremento; staging permanece pausado.
