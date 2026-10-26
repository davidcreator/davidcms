/** Cena Hero — conteúdo de cena; renderer e ciclo de vida pertencem ao SceneRunner. */
import { registerScene } from '../core/scene-runner.js';
import { createParticleField } from '../components/particle-field.js';
import { intAttr, floatAttr } from '../core/dom.js';

registerScene('hero-particles', ({ scene, camera, container, config }) => {
  const field = createParticleField({
    count: intAttr(container, 'count', 400),
    color: config.color ?? '#8a4fff',
    spread: floatAttr(container, 'spread', 12),
  });
  scene.add(field.points);
  const speed = floatAttr(container, 'speed', 0.05);
  const target = { x: 0, y: 0 };
  const onPointer = (event) => {
    target.x = (event.clientX / window.innerWidth - 0.5) * 0.6;
    target.y = (event.clientY / window.innerHeight - 0.5) * 0.6;
  };
  window.addEventListener('pointermove', onPointer, { passive: true });

  return {
    animated: true,
    update({ time }) {
      field.points.rotation.y = time * speed;
      camera.position.x += (target.x - camera.position.x) * 0.05;
      camera.position.y += (-target.y - camera.position.y) * 0.05;
      camera.lookAt(scene.position);
    },
    dispose() {
      window.removeEventListener('pointermove', onPointer);
    },
  };
});
