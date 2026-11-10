# Thumbs 3D declarativas no BDREditor

O bloco `core/threejs_thumb` pode ser inserido em Páginas CMS e posts do Blog. O BDREditor armazena a configuração declarativa em `scene_json` dentro do envelope v2 existente; não requer migration nem gravação paralela. O cartão do bloco agora tem um botão **Editar thumb e script** que abre os controles no próprio canvas — não depende do Inspetor lateral estar aberto. O Inspetor também oferece os mesmos controles. É possível alterar o rótulo, adicionar/remover formas e luzes, editar seus atributos e usar o campo JSON avançado. O campo de JavaScript opcional tem ações explícitas para adicionar/editar ou excluir o script.

## Segurança e runtime

- Three.js 0.186.1 é servido localmente em `three.module.js` com a dependência obrigatória `three.core.js`; licença incluída e sem CDN.
- O renderizador público só registra JS/CSS do runtime quando o documento validado contém uma thumb. No Catalog, `common/header` transfere `Document::getScripts()` para o Twig e emite os scripts registrados com `defer`; os controllers de CMS/Blog só registram o runtime quando o renderer validado encontra uma thumb. O `.htaccess` encaminha `/three-runtime/*` ao controller comum, que entrega apenas assets da allowlist System.
- O backend valida a allowlist e limites da cena JSON antes da renderização. JavaScript opcional tem campo separado e executa somente no iframe isolado descrito abaixo; URLs e recursos externos continuam bloqueados.
- O runtime desenha WebGL ao entrar na área visível, pausa fora dela, reage a redimensionamento e respeita `prefers-reduced-motion`. Sem suporte a WebGL ou APIs de observação, o rótulo permanece como fallback.
- Os controles de cena atualizam o mesmo JSON validado. O cartão do bloco e o modo **Prévia** do editor Blog/CMS usam o runtime compartilhado para cenas declarativas. `custom_js` é executado apenas no iframe sandbox opaco, tanto na Prévia completa quanto na thumb pública; nunca no documento Admin/cartão do bloco.

## Formato v1 da cena

Campos de topo: `version` (1), `background` (`#RRGGBB`), `camera` (`fov` 20–80 e `position` `[x,y,z]`), `objects` (1–12), `lights` (1–4), `auto_rotate` (boolean) e `fog` opcional (`color`, `near`, `far`). Posições, escala, ângulos, intensidade, roughness e metalness possuem limites estritos no validador.

Primitivas: `box`, `sphere`, `torus`, `cone`, `cylinder`, `icosahedron`, `torusKnot`. Materiais permitem cor, roughness, metalness e wireframe. Luzes: `ambient`, `directional`, `point`. Animações de objeto: `none`, `spin`, `bob`, `pulse`.

Exemplo inicial (também usado ao inserir o bloco):

```json
{"version":1,"background":"#101827","camera":{"fov":42,"position":[3,2.4,4.5]},"objects":[{"type":"torusKnot","color":"#77aaff","position":[0,0,0],"rotation":[0,0,0],"scale":1,"animation":"spin","roughness":0.3,"metalness":0.55,"wireframe":false}],"lights":[{"type":"ambient","color":"#ffffff","intensity":1.4},{"type":"directional","color":"#ffffff","intensity":2.4,"position":[4,5,3]}],"auto_rotate":true}
```

## Verificações

`node tools/e2e/check_threejs_thumb.cjs` valida contratos estáticos do bloco, allowlist, integração pública/admin, textos de idioma e presença do runtime local. Os fluxos funcionais no CMS devem ainda ser confirmados em ambiente ReamurCMS com permissões adequadas; não foram executados nesta entrega local.

## JavaScript opcional isolado

O campo **JavaScript personalizado (sandbox)** aceita até 8 KiB por thumb. Cole JavaScript puro, sem envolver em tags `<script>...</script>`. O runtime informa na própria thumb quando está carregando, pronto ou com erro de inicialização/execução. O script só é executado dentro de um `iframe sandbox="allow-scripts"` com origem opaca; a CSP do frame bloqueia rede e recursos externos, e o script não pode acessar o DOM, cookies ou armazenamento da página CMS. A cena é montada antes de executar o script. A própria thumb mostra estado de carregamento/erro caso a dependência local ou o código não inicialize. API disponível: `THREE`, `scene`, `camera`, `renderer`, `canvas` e `onFrame(fn)`. `onFrame` recebe `{time, delta, THREE, scene, camera, renderer, canvas}`.

Exemplo:

```js
const mesh = new THREE.Mesh(
  new THREE.TorusGeometry(0.8, 0.2, 16, 48),
  new THREE.MeshStandardMaterial({ color: 0xff66aa })
);
scene.add(mesh);
onFrame(({ delta }) => { mesh.rotation.y += delta; });
```

A permissão de edição da página continua sendo o gate de autoria. O sandbox reduz o impacto de acesso ao contexto hospedeiro e de rede, mas não torna scripts arbitrários inofensivos: um loop infinito ainda pode travar a thumb/aba. Use código confiável; falhas mantêm a etiqueta acessível.
