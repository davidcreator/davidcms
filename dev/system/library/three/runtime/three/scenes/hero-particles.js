/**
 * Cena Hero — partículas com parallax e, quando o controller publica um
 * `data-model`, o logo GLB da Cena 2 (Guia §6). O renderer e o ciclo de vida
 * continuam no SceneRunner; aqui só há conteúdo de cena.
 */
import { registerScene, THREE } from '../core/scene-runner.js';
import { createParticleField } from '../components/particle-field.js';
import { createStudioLighting } from '../components/studio-lighting.js';
import { intAttr, floatAttr } from '../core/dom.js';

/** Centraliza o modelo na origem e o ajusta a uma fração do viewport menor. */
function fitToViewport(object, camera, ratio) {
  const box = new THREE.Box3().setFromObject(object);
  const size = box.getSize(new THREE.Vector3());
  const center = box.getCenter(new THREE.Vector3());
  const visibleHeight = 2 * Math.tan((camera.fov * Math.PI) / 360) * camera.position.length();
  const available = Math.min(visibleHeight, visibleHeight * camera.aspect) * ratio;
  const scale = available / Math.max(size.x, size.y, size.z, 0.0001);
  object.scale.setScalar(scale);
  object.position.copy(center.multiplyScalar(-scale));
}

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

  let disposed = false;
  let model = null;
  let lighting = null;

  // Cena 2 — opcional por construção: sem data-model (ou em falha de rede,
  // parse ou WebGL) a cena continua sendo só o campo de partículas.
  if (config.model) {
    import('three/addons/loaders/GLTFLoader.js')
      .then(({ GLTFLoader }) => {
        if (disposed) return;
        new GLTFLoader().load(
          config.model,
          (gltf) => {
            if (disposed) return;
            lighting = createStudioLighting({ color: config.color ?? '#8a4fff' });
            scene.add(lighting.group);
            model = gltf.scene;
            fitToViewport(model, camera, 0.5);
            scene.add(model);
          },
          undefined,
          () => {
            // Degradação silenciosa: as partículas permanecem como única cena.
            lighting = null;
          }
        );
      })
      .catch(() => {});
  }

  return {
    animated: true,
    update({ time }) {
      field.points.rotation.y = time * speed;
      camera.position.x += (target.x - camera.position.x) * 0.05;
      camera.position.y += (-target.y - camera.position.y) * 0.05;
      camera.lookAt(scene.position);
    },
    dispose() {
      disposed = true;
      window.removeEventListener('pointermove', onPointer);
      if (lighting) lighting.dispose();
    },
  };
});
