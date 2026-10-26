/** Cena 404 — atualização de partículas; renderer/ciclo de vida ficam no SceneRunner. */
import { registerScene } from '../core/scene-runner.js';
import { createParticleField } from '../components/particle-field.js';
import { intAttr, floatAttr } from '../core/dom.js';

registerScene('not-found', ({ scene, container, config }) => {
  const spread = floatAttr(container, 'spread', 12);
  const count = intAttr(container, 'count', 120);
  const field = createParticleField({ count, color: config.color ?? '#8a4fff', spread });
  scene.add(field.points);
  const position = field.points.geometry.attributes.position;
  const velocities = new Float32Array(count);
  const drift = floatAttr(container, 'drift', 0.4);
  const half = spread / 2;

  for (let i = 0; i < count; i++) velocities[i] = (0.5 + Math.random()) * drift;
  const speed = floatAttr(container, 'speed', 0.05);

  return {
    animated: true,
    update({ delta }) {
      field.points.rotation.y += speed * delta;
      const values = position.array;
      for (let i = 0; i < count; i++) {
        values[i * 3 + 1] -= velocities[i] * delta;
        if (values[i * 3 + 1] < -half) values[i * 3 + 1] = half;
      }
      position.needsUpdate = true;
    },
    dispose() {},
  };
});
