# BDREditor — Integração em CMS > Páginas

Pacote incremental baseado no `main` do ReamurCMS no commit `5ced14b6c1ca412a7a39f6b3ee73bf22c8b9b1f5`.

## O que muda

- Monta o BDREditor no campo Descrição de cada idioma em CMS > Páginas e carrega os assets do editor.
- Armazena documentos **v2** no campo existente `page_description.description`, identificados pelo prefixo `REAMURCMS_BDRE_JSON_V2:`. Não cria coluna, tabela ou migration.
- Valida o prefixo, a inicialização do editor, o JSON e o schema v2 no servidor; canoniza o documento validado antes de salvá-lo.
- No catálogo, valida/renderiza o envelope via `BlockRenderer` seguro antes de passá-lo ao Twig. Envelopes inválidos falham fechados; o marcador/JSON não é enviado como HTML.
- Mantém o caminho legado: conteúdo HTML legado que o conversor suporta só é convertido no editor e persistido depois de um salvamento explícito; conteúdo que não pode ser convertido com segurança continua no textarea original. Envelopes marcados inválidos são preservados byte a byte ao salvar outros campos.
- Usa as ferramentas e blocos já existentes no BDREditor. Na tela CMS > Páginas, os seletores da biblioteca de mídia do Blog não são carregados; os campos de caminho local continuam editáveis manualmente. Não há upload nem exclusão de mídia nesta integração.

## Aplicação

Copie os arquivos do diretório `dev/` para a mesma raiz `dev/` do projeto, preservando os caminhos. O ZIP inclui os arquivos do editor já entregues anteriormente e os pontos de integração de Páginas. Revise o diff no seu checkout antes de qualquer instalação. Não contém alterações de banco, GitHub, staging, produção ou deploy.

## Validação executada

- `tests/check_cms_page_integration.js`: 8 verificações estáticas PASS.
- Checkers anteriores do editor: 11 (code), 14 (callout), 12 (details), 15 (spacer), 23 (reordenação hierárquica) PASS.
- Suítes E2E estáticas anteriores executadas; sem browser real, PHP runtime, banco ou app conectado.
- PHP não estava instalado neste ambiente; `php -l`, teste server-side e a confirmação de aparecer/salvar/reabrir numa instância real não foram executados. Portanto, este pacote não representa comprovação de integração runtime.

## Limitações / próximos testes autorizados

1. Em ambiente local autorizado, abrir CMS > Páginas, confirmar a presença da barra/inserter, salvar e reabrir uma página de teste.
2. Validar renderização pública de documento v2 e fallback de HTML legado.
3. Avaliar integração de seletores de mídia para Páginas separadamente, respeitando permissões e bibliotecas já existentes.

Não execute testes em staging ou produção sem nova autorização explícita.
