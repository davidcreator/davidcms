/**
 * Cena 1 — Hero (partículas + parallax suave de câmera).
 * Base: docs/Implementacao-ThreeJS.md §5 + modelo agents-ia/modelos/scene-threejs.js.tpl.
 * Interface SceneModule: { play, pause, resize, dispose }.
 *
 * Config via data-* (controller → Twig → container):
 *  data-count (400) · data-color (#8a4fff) · data-speed (0.05) · data-spread (12)
 */
import * as THREE from 'three';
import { registerScene } from '../core/scene-runner.js';
import { createParticleField } from '../components/particle-field.js';
import { intAttr, floatAttr } from '../core/dom.js';

export default registerScene('hero-particles', (container, cfg) => {
  const camera = new THREE.PerspectiveCamera(
    50,
    container.clientWidth / Math.max(container.clientHeight, 1),
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

  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2)); // budget §10: DPR ≤ 2
  renderer.setSize(container.clientWidth, container.clientHeight);
  container.appendChild(renderer.domElement);

  const scene = new THREE.Scene();
  const field = createParticleField({
    count: intAttr(container, 'count', 400),
    color: cfg.color ?? '#8a4fff',
    spread: floatAttr(container, 'spread', 12),
  });
  scene.add(field.points);

  // Parallax de câmera (alvo suavizado por pointer)
  const speed = floatAttr(container, 'speed', 0.05);
  const target = { x: 0, y: 0 };
  const onPointer = (e) => {
    target.x = (e.clientX / window.innerWidth - 0.5) * 0.6;
    target.y = (e.clientY / window.innerHeight - 0.5) * 0.6;
  };
  window.addEventListener('pointermove', onPointer, { passive: true });

  let rafId = null;
  const clock = new THREE.Clock();

  function frame() {
    const t = clock.getElapsedTime();
    field.points.rotation.y = t * speed;
    camera.position.x += (target.x - camera.position.x) * 0.05;
    camera.position.y += (-target.y - camera.position.y) * 0.05;
    camera.lookAt(scene.position);
    renderer.render(scene, camera);
    rafId = requestAnimationFrame(frame);
  }

  const handle = {
    play() { if (!rafId) rafId = requestAnimationFrame(frame); },
    pause() { cancelAnimationFrame(rafId); rafId = null; },
    resize() {
      camera.aspect = container.clientWidth / Math.max(container.clientHeight, 1);
      camera.updateProjectionMatrix();
      renderer.setSize(container.clientWidth, container.clientHeight);
    },
    dispose() {
      handle.pause();
      window.removeEventListener('pointermove', onPointer);
      field.dispose();
      renderer.dispose();
      renderer.domElement.remove();
    },
  };

  window.addEventListener('resize', handle.resize);
  handle.play(); // nasce tocando; watchVisibility pausa quando sair da viewport

  return handle;
});
