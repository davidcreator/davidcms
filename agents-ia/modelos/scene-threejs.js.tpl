/**
 * TEMPLATE: cena Three.js no padrão SceneModule.
 * Destino: dev/catalog/view/js/three/scenes/{{scene-name}}.js
 * REGRAS (docs/Implementacao-ThreeJS.md §4, §10):
 *  - interface { play, pause, resize, dispose } obrigatória
 *  - DPR ≤ 2 · respeitar prefers-reduced-motion (o boot já filtra) · fallback preservado até o 1º frame
 *  - container declarado no Twig com data-scene="{{scene-name}}"
 */
import * as THREE from 'three';
import { registerScene } from '../core/scene-runner.js';
import { clearFallback, intAttr } from '../core/dom.js';

export default registerScene('{{scene-name}}', (container, cfg) => {
  const camera = new THREE.PerspectiveCamera(
    50,
    container.clientWidth / container.clientHeight,
    0.1,
    100,
  );
  camera.position.z = 4;

  let renderer;

  try {
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  } catch {
    return null; // WebGL indisponível → o fallback do HTML permanece
  }

  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(container.clientWidth, container.clientHeight);

  clearFallback(container);
  container.appendChild(renderer.domElement);

  const scene = new THREE.Scene();

  // --- CONTEÚDO DA CENA (trocar pelo específico) ---
  // const mesh = ...; scene.add(mesh);
  const count = intAttr(container, 'count', 200);

  // --- listeners próprios (lembre de remover no dispose) ---
  // const onPointer = (e) => { ... };
  // window.addEventListener('pointermove', onPointer, { passive: true });

  let rafId = null;
  const clock = new THREE.Clock();
  let firstFrame = true;

  function frame() {
    renderer.render(scene, camera);

    if (firstFrame) { firstFrame = false; } // espaço p/ hook pós-1º frame
    rafId = requestAnimationFrame(frame);
  }

  const handle = {
    play() { if (!rafId) rafId = requestAnimationFrame(frame); },
    pause() { cancelAnimationFrame(rafId); rafId = null; },
    resize() {
      camera.aspect = container.clientWidth / container.clientHeight;
      camera.updateProjectionMatrix();
      renderer.setSize(container.clientWidth, container.clientHeight);
    },
    dispose() {
      handle.pause();
      // window.removeEventListener('pointermove', onPointer);
      // geometry.dispose(); material.dispose();
      renderer.dispose();
      renderer.domElement.remove();
    },
  };

  window.addEventListener('resize', handle.resize);

  return handle;
});
