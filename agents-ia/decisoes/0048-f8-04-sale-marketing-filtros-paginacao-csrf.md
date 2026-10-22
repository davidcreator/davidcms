# ADR 0048 — F8-04: sale/order e marketing no shell admin, com filtros, paginação e CSRF

| | |
| --- | --- |
| **Status** | aceita |
| **Data** | 2026-10-22 |
| **Agente proponente** | admin / orquestrador |
| **Contexto** | F8-04 migra `sale/order`, `marketing/testimonial` e `marketing/client_logo` do visual legado para o shell Bootstrap estabelecido em F8-01/F8-02. A migração deve preservar contratos F2/F5 existentes e proteger todas as mutações, incluindo uploads multipart. O usuário excluiu Appearance e a tela Localization; autorizou somente as novas chaves de interface necessárias nos arquivos de idioma dos três módulos. |

## Decisão

1. Usar o shell Bootstrap responsivo do painel para as três listas e os três formulários, mantendo rotas, actions, `name`/`id` de campos existentes, upload via `MediaUpload`, e o comportamento de domínio dos CRUDs.
2. Implementar filtros e paginação server-side de 20 itens por página, com contagem correspondente, preservação dos filtros nos links e correção de página fora do intervalo. Os métodos de listagem dos modelos aceitam parâmetros opcionais; chamadas legadas sem parâmetros continuam retornando todas as linhas.
3. Exigir CSRF em add/edit/delete de todos os módulos e em `testimonial.upload` e `client_logo.upload`. Os formulários e as requisições multipart incluem o token da sessão. A ação paylink de `sale/order` mantém o token que já havia sido adicionado em F8-03.
4. Não alterar schema, SQL de instalação, Appearance ou telas de Localization. Adicionar apenas strings necessárias de filtros, contagem, estado vazio e erro CSRF aos idiomas existentes `en-us`, `pt-br` e `es-eu` dos três módulos, conforme autorização limitada do usuário.
5. Preservar o texto de depoimento como textarea simples; não introduzir editor rico ou mudar a semântica de conteúdo/catalog.

## Alternativas consideradas

1. **Paginação client-side** — rejeitada: carrega todas as linhas e não escala; os lotes F8 anteriores adotam filtros server-side.
2. **Reutilizar rótulos de idioma de outro módulo** — rejeitada: criaria dependência de apresentação cruzada entre módulos e cópia inadequada em alguns idiomas.
3. **Alterar tabelas/schema ou rotas para acomodar a migração visual** — rejeitada: o trabalho não requer novo dado nem mudança de contrato.

## Consequências

- ✅ As listas passam a exibir dados por página, filtros ativos e contagens; continuam acessíveis em telas menores e em temas claros/escuros do shell.
- ✅ POSTs válidos continuam preservando os fluxos F2/F5; POST sem CSRF não persiste nem remove dados e upload sem token não grava arquivo.
- ⚠️ Clientes externos ou scripts que invocavam os endpoints administrativos de upload/mutação sem CSRF precisam enviar o token da sessão; as baterias E2E existentes foram atualizadas.
- ⚠️ A verificação E2E com MariaDB/Apache depende do ambiente de teste do projeto; nesta execução local, a validação ética/estática é registrada no README da entrega.

## Documentos atualizados

- `dev/tests/e2e/e2e_f212.sh`, `dev/tests/e2e/e2e_f214.sh`, `dev/tests/e2e/e2e_regressao.sh` — passam a enviar CSRF nos fluxos afetados; F214/F212 incluem rejeição de POST sem token.
- Nenhuma alteração em `docs/Guia-Twig.md`, `docs/Estrutura-de-Pastas.md` ou schema: o padrão Twig/Bootstrap existente é reutilizado, sem novo bloco/rota/pasta.
