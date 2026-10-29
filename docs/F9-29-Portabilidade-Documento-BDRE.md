# F9-29 — Importação e exportação portátil do documento BDRE

## Objetivo

Permitir cópia de segurança e transporte explícito de um documento BDRE entre editores, sem incluir dados de página/post, credenciais ou operações de servidor.

## Comportamento

- O botão **Exportar documento BDRE** baixa o estado canônico validado como `bdre-document-AAAA-MM-DD.bdre.json`.
- A importação aceita esse formato JSON nativo. O documento é validado pelo editor para a versão atual e para o contexto de mídia de Pages ou Blog.
- Ao importar um `.bdre.json`, o editor pede confirmação antes de substituir o documento inteiro; ao importar DOCX/XLSX/PPTX/HTML/IMSCC o fluxo anterior de append permanece.
- IDs de blocos são regenerados na importação para impedir colisões. A importação preserva o limite de 1.000 blocos e 4 MiB.
- JSON inválido, UTF-8 inválido, versão/estrutura/tipos fora do contrato ou incompatibilidade contextual são recusados. A importação permanece local no navegador; não há upload.
- PT-BR, EN-US e ES-EU recebem rótulos de exportação e confirmação localizados.

## Dados e segurança

O arquivo contém somente o documento BDRE serializado, não a página/post completo. Nenhum HTML/Twig arbitrário é executado. A saída e entrada passam pelo contrato allowlist já utilizado pelo editor. A escolha de importação substitui apenas o documento em memória; o conteúdo no site continua inalterado até o usuário salvar o formulário explicitamente.

## Escopo/estado

Entrega funcional local F9-29. Não altera schema, banco, endpoint, ACL ou fluxo de salvamento. A bateria abrangente permanece adiada para o marco de conclusão do BDREditor.
