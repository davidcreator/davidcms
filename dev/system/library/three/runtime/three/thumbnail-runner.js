/** Adapter declarativo de thumbs sobre o SceneRunner compartilhado. */
import { mountScene, THREE } from './core/scene-runner.js';

const geometries = {
  box: () => new THREE.BoxGeometry(1, 1, 1),
  sphere: () => new THREE.SphereGeometry(0.68, 24, 16),
  torus: () => new THREE.TorusGeometry(0.62, 0.22, 12, 36),
  cone: () => new THREE.ConeGeometry(0.62, 1.15, 24),
  cylinder: () => new THREE.CylinderGeometry(0.5, 0.5, 1, 24),
  icosahedron: () => new THREE.IcosahedronGeometry(0.68, 1),
  torusKnot: () => new THREE.TorusKnotGeometry(0.48, 0.16, 72, 8),
};

function validScene(data) {
  return data && data.version === 1 && Array.isArray(data.objects) && data.objects.length > 0 && data.objects.length <= 12 &&
    Array.isArray(data.lights) && data.lights.length > 0 && data.lights.length <= 4 && typeof data.background === 'string' &&
    /^#[0-9a-f]{6}$/i.test(data.background) && data.camera && Array.isArray(data.camera.position) && data.camera.position.length === 3;
}

export function mountThumbnail(element, config) {
  if (!validScene(config)) return { play() {}, pause() {}, resize() {}, dispose() {} };
  const fallback = element.querySelector('.rms-block-threejs-thumb__fallback');
  const animated = !!config.auto_rotate || config.objects.some((item) => item.animation !== 'none');

  return mountScene(element, ({ scene }) => {
    config.lights.forEach((item) => {
      let light;
      if (item.type === 'ambient') light = new THREE.AmbientLight(item.color, item.intensity);
      else if (item.type === 'point') light = new THREE.PointLight(item.color, item.intensity, 0, 2);
      else light = new THREE.DirectionalLight(item.color, item.intensity);
      if (item.position) light.position.set(item.position[0], item.position[1], item.position[2]);
      scene.add(light);
    });

    const group = new THREE.Group();
    scene.add(group);
    config.objects.forEach((item) => {
      const makeGeometry = geometries[item.type];
      if (!makeGeometry) return;
      const mesh = new THREE.Mesh(makeGeometry(), new THREE.MeshStandardMaterial({
        color: item.color,
        roughness: item.roughness === undefined ? 0.45 : item.roughness,
        metalness: item.metalness === undefined ? 0.1 : item.metalness,
        wireframe: !!item.wireframe,
      }));
      mesh.position.set(item.position[0], item.position[1], item.position[2]);
      mesh.rotation.set(item.rotation[0], item.rotation[1], item.rotation[2]);
      mesh.scale.setScalar(item.scale);
      mesh.userData.thumbAnimation = item.animation;
      mesh.userData.baseY = item.position[1];
      mesh.userData.baseScale = item.scale;
      group.add(mesh);
    });

    return {
      animated,
      update({ time, delta }) {
        if (config.auto_rotate) group.rotation.y += delta * 0.22;
        group.children.forEach((mesh) => {
          if (mesh.userData.thumbAnimation === 'spin') mesh.rotation.y += delta * 0.48;
          else if (mesh.userData.thumbAnimation === 'bob') mesh.position.y = mesh.userData.baseY + Math.sin(time * 1.5) * 0.12;
          else if (mesh.userData.thumbAnimation === 'pulse') mesh.scale.setScalar(mesh.userData.baseScale * (1 + Math.sin(time * 1.8) * 0.08));
        });
      },
      dispose() {},
    };
  }, config, {
    alpha: false,
    antialias: false,
    maxPixelRatio: 1.5,
    fov: config.camera.fov,
    cameraPosition: config.camera.position,
    rootMargin: '100px',
    canvasClass: 'rms-block-threejs-thumb__canvas',
    onReady() {
      if (fallback) fallback.hidden = true;
      element.dataset.threejsReady = 'true';
    },
    onError() {
      if (fallback) fallback.hidden = false;
      element.dataset.threejsReady = 'error';
    },
  });
}
