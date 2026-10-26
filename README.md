# ReamurCMS — Three.js System Engine (atualizado)

Patch incremental consolidado com a centralização anterior e o ciclo de vida compartilhado entre cenas nativas e thumbs declarativas. Base consultada: HEAD `11447edc9e09cfdf1c46c43cacafad34a140cdf6`.

## O que contém

- Runtime/vendor centralizados em `dev/system/library/three/runtime/`, expostos somente pela allowlist System e rota Catalog existente.
- `three/core/scene-runner.js` como única camada confiável de renderer, resize, lazy-init, visibilidade, reduced-motion e descarte para Hero/404 e thumbs declarativas.
- `three/thumbnail-runner.js` como adapter das thumbs declarativas sobre `mountScene()`.
- `custom_js` permanece fora do engine confiável e executa somente em iframe sandbox opaco.
- Documentação e ADR 0074 atualizadas. Sem migration, deploy ou alteração de conteúdo/settings.

## Aplicação

1. Faça backup da branch/instalação.
2. Copie os arquivos preservando os caminhos relativos para a raiz do DavidCMS.
3. Revise `DELETE_FILES.txt` antes de remover cópias antigas da fonte Catalog; não remova sem confirmar a presença dos novos módulos em System.
4. Preserve a negação de acesso HTTP direto a `system/`; use apenas a rota allowlisted do Catalog.

## Verificações desta atualização

- `node --check` passou nos cinco módulos JavaScript alterados.
- `tools/e2e/check_threejs_thumb.cjs`: 22/22 verificações estáticas.
- Não houve teste funcional/browser, PHP lint (CLI indisponível), push, deploy ou migration.

Não aplicado ao ambiente compartilhado e não enviado ao GitHub por esta sessão.
