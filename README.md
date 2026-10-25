# BDREditor — edição unificada de CMS > Páginas com faixa estilo Office

Pacote incremental sobre `main` do ReamurCMS, HEAD verificado em 25/10/2026: `9fc5d3cacfe04aab4b6005b26df2cd2d7ac85ffb`.

## Resultado desta etapa

- A criação e a edição passam a acontecer em **uma tela CMS única**: dados gerais, SEO, status, campos customizados e conteúdo BDREditor no mesmo formulário.
- O conteúdo deixa de abrir uma rota/página separada. A rota antiga `cms/page.content` redireciona para `cms/page.edit` como compatibilidade para links existentes.
- A faixa superior organiza comandos em guias **Início**, **Inserir** e **Exibir**, com grupos para transferência, formatação de texto e alinhamento.
- A formatação inline e o alinhamento de parágrafo ficam na faixa superior; o toolbar flutuante repetido dentro de cada bloco foi removido.
- Estrutura, inserir e propriedades ficam fechados por padrão e abrem sob demanda como painel lateral recolhível.
- Salvar continua manual no formulário original. O campo `description`, o envelope `REAMURCMS_BDRE_JSON_V2:`, validação, status, metadados e custom fields são mantidos; sem autosave, migration ou novo campo no banco.
- Botões de ação permanecem icon-only onde possível, com `aria-label`, `title` e suporte a teclado.

## Instalação do pacote

Copie os arquivos sob `dev/` para os mesmos caminhos do projeto. A rota antiga e seu Twig podem permanecer no repositório, mas ficam sem uso porque `content()` redireciona para a edição unificada.

## Validação disponível

```sh
node tools/e2e/check_single_page_ribbon.cjs
node --check dev/admin/view/js/blog-block-editor.js
node --check dev/admin/view/js/blog-block-editor-init.js
```

O checker é estático/contratual, não um navegador real. A prévia local está em `preview/index.html` e em execução no preview da conversa; ela demonstra o editor e não salva em nenhum backend. PHP não está instalado neste ambiente, portanto ainda falta lint PHP, renderização Twig no CMS e round-trip de salvamento em ambiente de desenvolvimento autorizado.

Nenhuma escrita GitHub, banco, staging, produção, migration ou deploy foi feita.
