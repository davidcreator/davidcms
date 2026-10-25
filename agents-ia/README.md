# Agentes IA — F9-26

## Regras

- Preservar o envelope do documento v1/v2 e deixar o servidor autoritativo.
- Priorizar HTML/DOM nativo, texto com `textContent`, controles semânticos e alternativa de teclado para drag.
- Nunca sobrescrever semântica de elementos nativos com ARIA incompatível; manter nome/estado/foco e mensagens live.
- Toda string deve existir em PT-BR, EN-US e ES-EU e ser distribuída pelo controller.
- Foco visível, contraste, responsividade/reduced-motion e limites de conteúdo são requisitos estáticos; sem alegar auditoria se não houver browser/leitor de tela.
- Otimizações não podem remover undo/redo nem enfraquecer validação/escape; snapshots devem ter limites de count e agregado.
- Proibido executar PHP, SQL, browser, app ou E2E runtime; não acessar DB/dump, não publicar GitHub ou deploy.
