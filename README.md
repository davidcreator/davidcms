# BDREditor — Workbench visual inspirado no Elementor

Incremento visual baseado no `main` consultado em `4c71c46656bd7c4dd47d7c20d0d027bce53eb41c`.

A atualização muda a experiência **em tela cheia** para uma bancada familiar a editores visuais de páginas: barra superior escura, painel lateral de Elementos/Estrutura/Configurações e canvas de edição amplo à direita. O painel Elementos mostra busca e blocos em cartões; os blocos continuam inseridos e validados pelo BDREditor.

É uma referência visual/interacional, não uma cópia: sem código, assets, ícones, fontes, pacotes ou dependência do Elementor/WordPress. Os ícones SVG são inline e mantêm os nomes acessíveis.

## Aplicação

Copie os dois arquivos de `dev/` para os mesmos caminhos no checkout atualizado e revise o diff:

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`

O modo inline e o modelo de dados permanecem disponíveis; no modo tela cheia, o canvas passa a ser o foco e as ferramentas ficam numa barra lateral. O botão **Elementos** leva ao inserter; **Estrutura** abre o navegador do documento; **Configurações** abre o inspetor do bloco selecionado. O botão de tela cheia continua com `Esc` e **Voltar ao formulário**. Salvamento continua explícito no formulário, sem autosave.

## Preview e verificações

- `preview/index.html` mostra a interface interativa; sirva a pasta com um servidor HTTP estático para testar o modo fullscreen.
- `tests/check_elementor_workbench.js`: verificações estáticas do layout e acessibilidade.
- 19 checkers/suítes E2E estáticas do editor passaram contra os arquivos alterados.
- Não foi executado PHP, banco, GitHub write, staging ou produção. A prévia isolada não substitui uma verificação dentro do CMS.
