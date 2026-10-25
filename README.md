# BDREditor — navegação hierárquica e foco

Incremento cumulativo do BDREditor, editor de conteúdo exclusivo do ReamurCMS. Acrescenta breadcrumbs navegáveis e foco anunciado para blocos aninhados, mantendo os incrementos `core/file`, `core/audio`, edição por teclado e copiar/colar local. Gutenberg continua apenas referência conceitual; sem dependência, código ou assets Gutenberg/WordPress.

## Este incremento

- O inspetor mostra o caminho raiz → ancestrais → bloco selecionado em `nav`/lista ordenada acessível.
- Ancestrais são botões locais que selecionam e focam o bloco; o item atual é marcado `aria-current="location"`.
- Selecionar um item na árvore outline também move o foco para o bloco e anuncia a mudança na região `aria-live`.
- Usa rótulos personalizados de grupos; linhas/células de tabela recebem rótulos localizados. Conteúdo é inserido como texto, sem HTML interpretado.
- CSS responsive e estado `:focus-visible`; sem mudança no JSON ou persistência.

## Fora do escopo

Não adiciona movimentação/reordenação entre pais, seleção múltipla, atalhos que interceptem navegação do navegador, alterações de schema, endpoint, banco, migration, autosave, preview draft ou armazenamento compartilhado. O incremento anterior de cópia permanece local à instância.

## Base e verificações

Fontes pontuais comparados contra o commit `eca41ed5bfb87ff9ec9e801fd9bf745978ed9bdd` no `main`, que inclui `BDREditor-Block-Copy-Paste`; sem clone integral e sem GitHub write. Nenhuma numeração oficial foi atribuída.

Resultados estáticos: **24 PASS** na navegação, **29** copy/paste, **25** teclado, **49** áudio e **48** arquivo/download. Também passaram `node --check` dos scripts/JS, validação JSON das fixtures, verificação SHA-256 e integridade do ZIP. Checkers usam fixtures sintéticas e VM Node sem DOM/browser; não substituem runtime real, leitor de tela ou navegador.

Não foram executados PHP/runtime, navegador real, aplicação, banco, staging, deploy ou migrations. Staging permanece pausado.

## Arquivos de apoio

- Contrato: `docs/BDREditor-Hierarchical-Navigation.md`
- Escopo: `docs/BDREditor-Hierarchical-Navigation-Scope.md`
- Fixture: `tests/fixtures/navigation_tree.json`
- Checker novo: `tools/e2e/e2e_bdre_navigation_static.cjs`
- Checkers cumulativos: `tools/e2e/`
- Manifesto: `SHA256SUMS`
