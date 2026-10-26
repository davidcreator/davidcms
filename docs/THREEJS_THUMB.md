# Thumbs 3D declarativas no BDREditor

O bloco `core/threejs_thumb` pode ser inserido em Páginas CMS e posts do Blog. O BDREditor armazena a configuração declarativa em `scene_json` dentro do envelope v2 existente; não requer migration nem gravação paralela. A área Inspetor permite alterar um rótulo acessível e a configuração JSON.

## Segurança e runtime

- Three.js 0.186.1 é servido de `dev/catalog/view/js/three.module.js`, com licença incluída; não há dependência de CDN.
- O renderizador público só inclui JS/CSS do runtime quando o documento validado contém uma thumb.
- O backend valida a allowlist e limites de cena antes da renderização. Não são aceitos scripts, URLs, texturas, modelos externos ou JavaScript arbitrário.
- O runtime desenha WebGL ao entrar na área visível, pausa fora dela, reage a redimensionamento e respeita `prefers-reduced-motion`. Sem suporte a WebGL ou APIs de observação, o rótulo permanece como fallback.
- A edição do JSON no inspetor não executa código nem tenta carregar uma prévia WebGL administrativa.

## Formato v1 da cena

Campos de topo: `version` (1), `background` (`#RRGGBB`), `camera` (`fov` 20–80 e `position` `[x,y,z]`), `objects` (1–12), `lights` (1–4), `auto_rotate` (boolean) e `fog` opcional (`color`, `near`, `far`). Posições, escala, ângulos, intensidade, roughness e metalness possuem limites estritos no validador.

Primitivas: `box`, `sphere`, `torus`, `cone`, `cylinder`, `icosahedron`, `torusKnot`. Materiais permitem cor, roughness, metalness e wireframe. Luzes: `ambient`, `directional`, `point`. Animações de objeto: `none`, `spin`, `bob`, `pulse`.

Exemplo inicial (também usado ao inserir o bloco):

```json
{"version":1,"background":"#101827","camera":{"fov":42,"position":[3,2.4,4.5]},"objects":[{"type":"torusKnot","color":"#77aaff","position":[0,0,0],"rotation":[0,0,0],"scale":1,"animation":"spin","roughness":0.3,"metalness":0.55,"wireframe":false}],"lights":[{"type":"ambient","color":"#ffffff","intensity":1.4},{"type":"directional","color":"#ffffff","intensity":2.4,"position":[4,5,3]}],"auto_rotate":true}
```

## Verificações

`node tools/e2e/check_threejs_thumb.cjs` valida contratos estáticos do bloco, allowlist, integração pública/admin, textos de idioma e presença do runtime local. Os fluxos funcionais no CMS devem ainda ser confirmados em ambiente ReamurCMS com permissões adequadas; não foram executados nesta entrega local.
