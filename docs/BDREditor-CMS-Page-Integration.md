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

## Contexto da página no modo imersivo

Quando o editor de blocos é usado em CMS > Páginas, a tela cheia exibe uma faixa de contexto com o título da página e o idioma da instância aberta. A faixa espelha o indicador de alterações não salvas do formulário e informa que a persistência ocorre ao voltar e usar **Salvar**. Os rótulos vêm das traduções do módulo `cms/page`; título/idioma são dados de apresentação já presentes no formulário.

Esse contexto é somente informativo: não cria campos de edição duplicados, não altera o JSON do BDREditor e não oferece autosave. O fluxo POST existente continua responsável por validar e salvar a página. O contexto é opcional no BDREditor e não é fornecido às instâncias do módulo Blog.

## Prévia privada no contexto público

CMS > Páginas envia o documento BDRE v2 e a ordem ainda não salva das seções por POST ao endpoint same-origin protegido por `modify cms/page` e CSRF. O servidor valida o documento, remove `custom_js` dos componentes Three.js de prévia e renderiza o template Catalog allowlisted da rota de sistema, com o shell atual de header/footer e a configuração publicada do tema. Pages comuns continuam usando `cms/page_info.twig`.

Os registros de Blog, Portfolio, Tools e demais módulos aparecem como amostras sintéticas claramente indicadas; a prévia não consulta nem copia dados desses módulos. Links e formulários ficam inertes. Nenhum draft é publicado, nenhum documento é salvo e não há token bearer, endpoint Catalog público ou migration.

A lista de seções usa somente a allowlist associada à `system_key` recuperada no servidor; a chave da rota não é confiada ao POST. Seleção vazia é válida, se o usuário desativar todas as seções. Os blocos editoriais continuam renderizados pelo `BlockRenderer` allowlisted, nunca pelo HTML arbitrário do cliente.

## Salvar no modo imersivo

Para quem tem permissão `modify` em `cms/page`, a faixa de contexto mostra um botão **Salvar** cujo tipo é `submit` dentro do formulário de página já existente. O envio passa pelos mesmos handlers de serialização e validação de todos os editores/idiomas, CSRF, validação do controller e POST normal. Sem permissão de modificação, não há botão e a faixa informa que o modo é somente consulta.

Não há endpoint, AJAX, autosave ou persistência paralela. Se a validação impedir o envio, o formulário e o estado não salvo continuam sob o fluxo existente; se passar, o backend mantém seu comportamento normal de resposta/redirect.

Se a validação HTML nativa bloquear o envio antes do evento `submit` (por exemplo, o título obrigatório de en-US estiver vazio), o handler específico de CMS > Páginas fecha a tela cheia, ativa a aba de idioma que contém o primeiro campo inválido e devolve o foco ao campo. O comportamento só atua quando há um BDREditor imersivo aberto; sem isso, a validação nativa segue sem interferência.

## Estado da verificação

A verificação é estática. Requer validação posterior num ambiente de desenvolvimento autorizado: editor visível, criar/editar, salvar, reabrir e confirmar renderização. Staging segue pausado; não foram executados acesso ao banco, migrations, GitHub write, deploy ou produção.
