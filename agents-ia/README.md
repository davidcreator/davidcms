# Agentes IA — F9-21

## Escopo

F9-21 entrega roadmap e contrato proposto do editor avançado. Nenhum código de produção foi alterado. A primeira feature sugerida para F9-22 é edição inline rich-text com estrutura JSON v2, mantendo os documentos v1 compatíveis.

## Fonte

- Repositório `https://github.com/davidcreator/davidcms`, branch `main`.
- HEAD consultado: `81bccd80470b7f8e26eee90c43dc475fb8dafe90`.
- O editor MVP atual usa `<textarea>` para conteúdo, tem ações básicas de bloco e já salva via fluxo F9-16.

## Guardrails obrigatórios

- Não introduzir HTML bruto ou `contenteditable.innerHTML` persistido.
- Schema v1 preservado; v2 deve ser validado e renderizado no servidor antes de ativar UI v2.
- Converter v1 em memória; abertura da tela não modifica o banco.
- Marks e links têm allowlist do servidor; limites de tamanho/complexidade são obrigatórios.
- Persistência continua no POST explícito; sem autosave nesta etapa sem política aprovada.
- Não baixar/clonar o projeto inteiro; trabalhar somente com arquivos necessários.
- Não executar PHP, SQL, migration, browser, app ou acessar banco. Não presumir F9-12 aplicada.
- Sem GitHub write/deploy, dados reais, dump privado, credenciais ou logs runtime.

Leia `docs/F9-21-Roadmap-Editor-Avancado.md`, `docs/F9-21-Contrato-Rich-Text-v2.json` e `tools/e2e/README.md` antes de iniciar F9-22.
