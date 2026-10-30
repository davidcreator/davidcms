# F9-48 — Barra de ferramentas do espaço de trabalho

**Status:** implementação local, responsiva e localizada; verificações estáticas aprovadas. O editor ainda não foi validado em navegador real.

## Conceito

A captura enviada serviu de referência para uma navegação vertical compacta. No BDREditor, ela foi adaptada como uma **barra de ferramentas do espaço de trabalho**, exibida no modo de tela cheia. A faixa Office e suas guias continuam intactas e são a superfície principal; a barra oferece atalhos para os painéis e guias que já existem.

- **Elementos:** abre o inserter atual de blocos e padrões.
- **Navegador/Estrutura:** mostra o outline do documento.
- **Aparência:** abre/fecha o inspetor do bloco selecionado.
- **Texto, Mídia, Padrões, Revisar e Exibir:** ativam, respectivamente, as guias correspondentes da faixa Office.

No desktop, a barra aparece à esquerda do painel contextual, com ícone, rótulo e estado visual. Em viewports estreitos, ela se transforma em uma faixa horizontal rolável para não comprimir a área de edição. Os controles têm nomes acessíveis, estado pressionado/expandido coerente, relações `aria-controls` quando o destino está disponível e retorno de foco ao fechar o inserter.

## Decisões de escopo

A implementação usa funcionalidades existentes em vez de adicionar atalhos que aparentem funcionar sem backend. **IA, kits de marca, projetos, Apps, geração de logotipo e geração vetorial não foram adicionados**: não há, nesta tarefa, fluxo de serviço correspondente no BDREditor. Isso evita controles fictícios e deixa a expansão futura aberta, caso os serviços reais sejam especificados.

O fluxo de salvamento continua sendo o formulário existente. Páginas e Blog continuam independentes; não foi adicionado HTML arbitrário, Twig ou execução de `custom_js`.

## Arquivos completos incluídos no pacote

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `dev/admin/controller/blog/blog.php`
- `dev/admin/controller/cms/page.php`
- `dev/admin/language/{pt-br,en-us,es-eu}/blog/blog.php`
- `dev/admin/language/{pt-br,en-us,es-eu}/cms/page.php`

As traduções dos novos rótulos da barra estão alinhadas nos três idiomas e nos dois fluxos.

## Validação

- `node --check` no JavaScript do editor.
- Verificação de balanceamento de blocos CSS.
- Assertivas estáticas de localização e mapeamento dos controllers de Blog e Page.
- Passaram `e2e_bdre_keyboard_static.cjs`, `e2e_bdre_navigation_static.cjs`, `e2e_bdre_outline_keyboard_static.cjs`, `e2e_bdre_callout_static.cjs` (34), `e2e_bdre_details_static.cjs` (27), `e2e_bdre_preformatted_code_static.cjs` (29), `e2e_bdre_spacer_static.cjs` (36), `e2e_f919_static.cjs`, `e2e_f924_static.cjs`, `e2e_f926_static.cjs` (427 chaves alinhadas nas três localidades) e `check_single_page_ribbon.cjs` (12/12).

**Limites:** PHP CLI e executável de navegador não estavam disponíveis. Não houve teste em navegador real, runtime PHP, staging, banco, migrações, deploy ou escrita no GitHub. Os testes aprovados são estáticos e não confirmam dimensionamento visual em cada viewport.
