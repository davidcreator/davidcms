# Integração do BDREditor em CMS > Páginas

## Contrato de armazenamento

O conteúdo novo é guardado em `page_description.description`, coluna `MEDIUMTEXT` já existente. O formato persistido é o prefixo literal `REAMURCMS_BDRE_JSON_V2:` seguido do JSON canônico do documento com `format_version: 2`. O campo title/SEO e a estrutura de idiomas existentes permanecem inalterados.

A camada administrativa não confia no cliente: exige que o editor tenha inicializado, confere o prefixo, decodifica e valida o schema no servidor e somente então serializa novamente. O renderer público exige versão de documento 2 e passa a árvore validada por `BlockRenderer`; não usa o envelope como HTML cru.

## Conteúdo legado

- Valores sem marcador seguem no caminho legado do catálogo.
- No formulário, o conversor legado restrito é usado apenas para montar uma representação editável; a conversão não é persistida até o salvamento explícito.
- Se a conversão não for segura (inclusive limite, UTF-8/DOM ou marcação fora da allowlist), mantém-se o texto original em textarea.
- Se um envelope já marcado estiver inválido, o conteúdo original fica preservado em campo oculto e a página não o envia ao Twig como HTML.

## Mídia em Páginas

O editor reutilizado preserva os blocos e os campos locais de imagem/arquivo/áudio, sujeitos ao schema e às raízes já implementadas pelo renderer. Os botões dos seletores Blog ficam desativados nesta tela porque os diálogos/rotas têm ACL e contexto próprios do Blog; duplicar esses endpoints seria uma mudança de escopo. Não há upload, exclusão ou escrita de arquivos por esta integração.

## Estado da verificação

A verificação é estática. Requer validação posterior num ambiente de desenvolvimento autorizado: editor visível, criar/editar, salvar, reabrir e confirmar renderização. Staging segue pausado; não foram executados acesso ao banco, migrations, GitHub write, deploy ou produção.
