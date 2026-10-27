# ReamurCMS — Three.js System Engine (Cena 2 GLB)

Patch consolidado do runtime compartilhado e da Cena 2 do Hero, baseado no HEAD `3f161857c45be2f599b5dad8ea1d4fc40d014581` do `main`.

## Conteúdo desta atualização

- Modelo `logo-da.glb` armazenado em System e servido somente pela allowlist do AssetManifest no gateway Catalog (`model/gltf-binary`).
- GLTFLoader r186 e utilitários oficiais self-hosted no runtime System; download dinâmico apenas se a cena receber `data-model`.
- Cena Hero mantém partículas quando o modelo falta ou falha; loader, luz de estúdio, normalização visual e fallback estão integrados ao SceneRunner.
- Import map resolve `three/addons/`; `.htaccess` reconhece `.glb`; URL do modelo é derivada do manifesto, sem caminho fornecido pelo usuário.
- ADR 0075 registra que o GLB presente é um placeholder procedural válido a ser substituído pelo export final do Blender.
- Nenhuma tabela, setting novo ou migration.

## Aplicação

1. Faça backup e aplique os arquivos preservando os caminhos relativos.
2. Revise `DELETE_FILES.txt` antes de qualquer remoção; confirme as fontes System e allowlist primeiro.
3. Não exponha `system/` diretamente: use a rota allowlisted do Catalog.

## Verificações desta etapa

- `node --check` passou para Hero, studio lighting, GLTFLoader e utilitários.
- `tools/e2e/check_threejs_native_admin.cjs`: 10/10 verificações estáticas.
- `tools/e2e/check_threejs_thumb.cjs`: 22/22 verificações estáticas.
- GLB: cabeçalho glTF 2.0 e comprimento/chunks consistentes.
- Não foi executado browser E2E nem `php -l` (PHP CLI indisponível).

Nenhum push, deploy, teste mutável ou migration foi feito por esta sessão.
