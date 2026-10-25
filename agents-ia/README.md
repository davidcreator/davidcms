# Agentes IA — F9-23

## Objetivo

Implementar a próxima etapa do roadmap F9-21 após F9-22: inserter pesquisável/contextual, comando `/`, ações estruturais, outline e reordenação nested acessível. Base `main` `184bff0c52b57bfbdbd43922093d21240f3fc836`.

## Regras de integração

- Trabalhar apenas com arquivos pontuais necessários; sem clonar/baixar o projeto inteiro.
- Manter o contrato v2/v1 e renderer allowlisted F9-22; não mexer em persistência, SQL, migrations ou backend de publicação sem necessidade e autorização.
- Nenhuma ação silenciosa pode perder conteúdo. Transformações são restritas a tipos textuais compatíveis; tipos de mídia/layout incompatíveis não podem converter destrutivamente.
- Duplicação deve regenerar IDs recursivamente; reordenação só rearranja irmãos; validação estrutural continua autoritativa.
- Operações de UI continuam no estado JSON em memória; sem `innerHTML`, autosave ou externalização de HTML.
- Manter PT-BR/EN-US/ES-EU e alternativas de teclado/foco, mensagens ARIA e limite de histórico.
- Não executar PHP, SQL, browser, app, E2E runtime, deploy ou GitHub write. Sem banco/dump/logs/credenciais/dados reais.

## Verificação

São permitidos apenas parsing estático de JS e checker de fonte/fixtures. Resultado PASS não equivale a validação browser/runtime; staging E2E continua pendente e exige autorização explícita.
