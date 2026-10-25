# BDREditor — Interface moderna

## Diretrizes aplicadas

A atualização usa uma linguagem de editor de blocos familiar: identidade discreta, barra de ferramentas, inserter pesquisável, outline/estrutura, canvas central e inspetor contextual. O visual é original e nativo do ReamurCMS: não há dependência de Gutenberg, código copiado, fonte remota, pacote de ícones ou CDN.

Ícones desenhados como SVG inline são decorativos. Nome acessível permanece no texto visível ou em `aria-label`; controles apenas visuais mantêm texto para leitores de tela via classe de ocultação visual. Estados de foco têm contorno de alto contraste; o layout adapta em breakpoints estreitos e respeita `prefers-reduced-motion` e `forced-colors`.

## Aplicação

Arquivos que mudam: o CSS do editor e o JS do editor. O arquivo de inicialização de formulários, schema, renderer, controladores e banco não são alterados por este incremento. Assets não usam URLs externas.

## Verificações

Os checkers estáticos anteriores foram executados contra a cópia atualizada do JS: code 11 PASS, callout 14 PASS, details 12 PASS, spacer 15 PASS, reordenação hierárquica 23 PASS; as E2E estáticas cumulativas anteriores também passaram. `tests/check_modern_ui.js` cobre 9 invariantes visuais/acessíveis.

Limite: teste visual interativo é fornecido por `preview/index.html`; não equivale a validar todos os navegadores ou a instalação final no admin. PHP, banco, GitHub write, staging e deploy não foram usados.
