# Cena 5 — ícones 3D de Ferramentas (ADR 0077)

Overlay de implementação sobre o HEAD `22240e45711125722f5c11921ac602a19ea203cf` do `main`.

## O que entra
- Cena decorativa opcional no bloco `#tools`, com até quatro ícones flutuantes e reação a foco/ponteiro nos cards existentes.
- Toggle Admin `three_tools_enabled`, default `0`, com validação central e rótulos/ajuda nos idiomas `en-us`, `pt-br` e `es-eu`; sem migration.
- Imagens same-origin dos registros são usadas quando disponíveis; quando ausentes/falham, o canvas mostra uma inicial. Não há logos ou binários inventados.
- SceneRunner e rota allowlisted existentes; cards, texto e links HTML permanecem como conteúdo e fallback.
- O `logo-da.glb` atual não é alterado: a Cena 2 continua usando o asset já presente no HEAD.

## Aplicação
Extraia os arquivos na raiz do repositório no HEAD indicado. Este ZIP é um overlay, não uma cópia integral; não exclua arquivos não listados.

## Verificação realizada
- `node --check` no novo módulo `tools-3d.js` e no entrypoint `main.js`.
- Sem QA visual/browser, suíte de testes, PHP lint ou alteração GitHub/deploy. A cena fica desligada por padrão.
