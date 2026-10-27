/**
 * TEMPLATE: cena Three.js no padrão SceneModule.
 * Destino: dev/system/library/three/runtime/three/scenes/{{scene-name}}.js
 * REGRAS (docs/Implementacao-ThreeJS.md §4, ADR 0074):
 *  - a factory recebe { scene, camera, container, config } e devolve
 *    { animated, update({ time, delta }), dispose() }
 *  - renderer, câmera, resize, lazy-init, pausa e descarte ficam no
 *    core/scene-runner.js — não crie requestAnimationFrame próprio
 *  - registrar o arquivo no AssetManifest e importá-lo em three/main.js
 *  - container declarado no Twig com data-scene="{{scene-name}}"
 */
import { registerScene } from '../core/scene-runner.js';
import { intAttr } from '../core/dom.js';

registerScene('{{scene-name}}', ({ scene, container, config }) => {
  // --- CONTEÚDO DA CENA (trocar pelo específico) ---
  const count = intAttr(container, 'count', 200);
  // const mesh = ...; scene.add(mesh);

  // --- listeners próprios (lembre de remover no dispose) ---
  // const onPointer = (event) => { ... };
  // window.addEventListener('pointermove', onPointer, { passive: true });

  return {
    animated: true,
    update({ time, delta }) {
      // mesh.rotation.y = time * 0.2;
    },
    dispose() {
      // window.removeEventListener('pointermove', onPointer);
    },
  };
});
