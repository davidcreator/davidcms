# BDREditor — navegação de outline por teclado

Incremento cumulativo do BDREditor exclusivo do ReamurCMS. Estende a outline hierárquica com navegação por teclas direcionais quando o foco está em um botão da outline. Mantém `core/file`, `core/audio`, atalhos do editor, copiar/colar local e breadcrumbs. Gutenberg é apenas referência; sem código, assets ou dependência.

## Teclas na outline

- **↑ / ↓**: bloco anterior / próximo na ordem hierárquica visível.
- **→**: primeiro filho visível.
- **←**: bloco pai.
- **Home / End**: primeiro / último bloco visível.
- Em limite ou alvo inexistente, mantém foco/seleção e suprime rolagem acidental da página.
- O foco permanece na outline; `aria-current` acompanha a seleção e uma região `aria-live` anuncia a mudança.
- O hint localizado é exibido junto à outline.

Essas teclas só são tratadas quando o foco está em `.rb-outline-button`. Setas em conteúdo editável, campos, breadcrumbs e demais controles mantêm comportamento nativo. Reordenação continua pelos controles existentes; este incremento navega, não move blocos.

## Verificações

Foram adicionados fixture e checker estático para ordem de leitura, pais/filhos, Home/End e limites. Checkers cumulativos de breadcrumbs, copy/paste, atalhos, áudio e arquivo/download continuam no pacote. Resultado: **24 PASS** outline por teclado, **24** breadcrumbs, **29** copy/paste, **25** atalhos, **49** áudio e **48** arquivo/download. `node --check`, parsing JSON das seis fixtures, checksums e ZIP também passaram. Os helpers são testados em VM Node sem DOM/browser; não é teste de leitor de tela ou navegador real.

HEAD de base consultado por commit: `7b4ee32a3218efc418da5a3fd75634910d9f0760` (Hierarchical-Navigation). Pacote local cumulativo, sem clone integral e sem GitHub write. Não foram executados PHP/runtime, browser/app, banco, staging, deploy ou migrations. Nenhuma numeração oficial foi atribuída.

- Escopo: `docs/BDREditor-Outline-Keyboard-Navigation-Scope.md`
- Contrato: `docs/BDREditor-Outline-Keyboard-Navigation.md`
- Fixture: `tests/fixtures/outline_navigation.json`
- Checker: `tools/e2e/e2e_bdre_outline_keyboard_static.cjs`
- Checkers anteriores: `tools/e2e/`
- SHA-256: `SHA256SUMS`
