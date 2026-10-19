/**
 * Cena 4 — Página 404 ("fog": partículas com deriva suave por partícula).
 * Base: docs/Implementacao-ThreeJS.md §8 — reutiliza components/particle-field.js.
 * Interface SceneModule: { play, pause, resize, dispose }.
 *
 * Config via data-* (controller → Twig → container):
 *  data-count (120) · data-color (#8a4fff) · data-speed (0.05 rotação) · data-drift (0.4 deriva)
 */
import * as THREE from 'three';
import { registerScene } from '../core/scene-runner.js';
import { createParticleField } from '../components/particle-field.js';
import { intAttr, floatAttr } from '../core/dom.js';

export default registerScene('not-found', (container, cfg) => {
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
  const spread = floatAttr(container, 'spread', 12);
  const count = intAttr(container, 'count', 120);
  const field = createParticleField({
    count,
    color: cfg.color ?? '#8a4fff',
    spread,
  });
  scene.add(field.points);

  // Drift ("fog"): deriva vertical independente por partícula (Guia §8)
  const position = field.points.geometry.attributes.position;
  const velocities = new Float32Array(count);
  const drift = floatAttr(container, 'drift', 0.4);
  const half = spread / 2;

  for (let i = 0; i < count; i++) {
    velocities[i] = (0.5 + Math.random()) * drift;
  }

  const speed = floatAttr(container, 'speed', 0.05);

  let rafId = null;
  let last = performance.now();

  function frame(now) {
    const dt = Math.min(((now ?? performance.now()) - last) / 1000, 0.05); // anti-salto pós-pausa
    last = now ?? performance.now();

    field.points.rotation.y += speed * dt;

    const arr = position.array;

    for (let i = 0; i < count; i++) {
      arr[i * 3 + 1] -= velocities[i] * dt;         // deriva para baixo
      if (arr[i * 3 + 1] < -half) arr[i * 3 + 1] = half; // wrap: volta ao topo
    }

    position.needsUpdate = true;

    renderer.render(scene, camera);
    rafId = requestAnimationFrame(frame);
  }

  const handle = {
    play() {
      if (!rafId) {
        last = performance.now();
        rafId = requestAnimationFrame(frame);
      }
    },
    pause() { cancelAnimationFrame(rafId); rafId = null; },
    resize() {
      camera.aspect = container.clientWidth / Math.max(container.clientHeight, 1);
      camera.updateProjectionMatrix();
      renderer.setSize(container.clientWidth, container.clientHeight);
    },
    dispose() {
      handle.pause();
      field.dispose();
      renderer.dispose();
      renderer.domElement.remove();
    },
  };

  window.addEventListener('resize', handle.resize);
  handle.play();

  return handle;
});
