# Agentes IA — F9-24

## Objetivo

Adicionar galeria, cover, controles responsivos de imagem, previews administrativos e padrões de layout locais ao editor Blog, como próxima etapa após F9-23. Base `main` `af3421cc1b3bd4636d8bb44d6d3d4e83e0fb5eb9`.

## Regras

- Validar e renderizar no servidor antes da UI que emite os novos tipos; manter versões 1/2 e `format_version` existente.
- Não persistir HTML/estilos arbitrários. Paths de mídia são locais allowlisted; classes são enums fixos; captions/títulos/alt são escaped plain text.
- Gallery aceita só filhos image; cover aceita só seus quatro tipos textuais e limites definidos. Failing closed: source inválida não deve virar conteúdo vazio.
- Patterns são inserções locais com IDs únicos, sem tabela/global persistence. Padrão cover precisa de imagem local antes de salvar.
- Admin embed preview não abre iframe externo; playback público mantém sandbox e host allowlist atual.
- Não mexer em SQL/migration, autosave, workflow editorial, GitHub, deploy ou banco.
- Sem clone integral; apenas arquivos pontuais e fixtures sintéticas.
- Não executar PHP, SQL, browser, app ou E2E runtime; staging exige autorização explícita.
