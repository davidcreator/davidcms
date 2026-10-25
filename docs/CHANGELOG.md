# Changelog

## Fluxo de conteúdo separado — 2026-10-25

- Adicionada rota `cms/page.content` e template dedicado para conteúdo.
- Formulário geral de CMS > Páginas enxugado para resumo por idioma, mantendo os campos administrativos e de SEO.
- Fluxo de criação encaminha ao editor depois do primeiro salvamento geral; salvar conteúdo volta ao formulário geral.
- Salvamento do conteúdo mantém status, sort order e metadados/descriptions não editados.
- Botões do editor e ações principais do formulário usam ícones com nomes acessíveis.
- Preservados o campo existente e o formato BDREditor v2, sem migrations.
