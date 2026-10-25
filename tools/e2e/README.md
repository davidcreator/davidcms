# F9-26 — checker estático e plano de acessibilidade/runtime

## Checker atual

`node tools/e2e/e2e_f926_static.cjs [F9-26-root]` verifica marcadores de keyboard/ARIA/CSS/performance, igualdade dos conjuntos de chaves das 3 línguas, parsing e descritores sintéticos. Não executa os validadores JS/PHP do CMS, DOM real, browser, leitor de tela ou app.

## Auditoria futura (não executada; requer autorização)

1. Teclado-only em inserter, toolbar, canvas/outline, Media Library, undo/redo, link marks, drag alternative, multi-idioma e mobile; verificar foco retornado após cada ação.
2. NVDA + Firefox, NVDA + Chrome, VoiceOver + Safari; anunciar status, erros, nested outline e contenteditable, confirmar rotulagem e ordem.
3. Axe/WCAG com estados focus/hover/selected/disabled/error, light/dark themes se aplicável, contraste recalculado com estilos reais.
4. 200%, 400% zoom, reflow 320 CSS px, touch targets, landscape/tablet, reduced motion.
5. Performance em staging/browser: 10/100/500/1000 blocos, composição contínua, undo/redo, seleção e alterações estruturais; memory profiling do histórico.
6. Fixture bytes em PHP validator/serializer em instância descartável após autorização. Testar valores aceitos/rejeitados na fronteira sem dump/dados reais.
7. Confirmar submit explícito, payload hidden sincronizado no submit, `document_too_large` anunciado, postback de validação e formulário com múltiplos idiomas.

Nenhum passo de browser/PHP/app/SQL/runtime foi executado nesta tarefa; não declarar WCAG conformidade até auditoria real.
