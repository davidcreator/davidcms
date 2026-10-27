/**
 * Cena 3, etapa 2 — quadros decorativos sobre a grade HTML do portfólio.
 * A grade continua sendo a navegação acessível; renderer e ciclo de vida
 * pertencem exclusivamente ao SceneRunner (ADR 0074/0076).
 */
import { registerScene, THREE } from '../core/scene-runner.js';

const MAX_FRAMES = 12;
const TILE_WIDTH = 256;
const TILE_HEIGHT = 192;

function sameOriginUrl(value) {
  if (typeof value !== 'string' || value === '') return '';
  try {
    const url = new URL(value, window.location.href);
    return url.origin === window.location.origin ? url.href : '';
  } catch (_) {
    return '';
  }
}

function readImageUrls(value, limit) {
  let images;
  try {
    images = JSON.parse(value || '[]');
  } catch (_) {
    return [];
  }
  if (!Array.isArray(images)) return [];
  return images.slice(0, limit).map(sameOriginUrl);
}

function drawCover(context, image, x, y, width, height) {
  const sourceWidth = image.naturalWidth || image.width;
  const sourceHeight = image.naturalHeight || image.height;
  if (!sourceWidth || !sourceHeight) return;
  const scale = Math.max(width / sourceWidth, height / sourceHeight);
  const cropWidth = width / scale;
  const cropHeight = height / scale;
  const sourceX = (sourceWidth - cropWidth) / 2;
  const sourceY = (sourceHeight - cropHeight) / 2;
  context.drawImage(image, sourceX, sourceY, cropWidth, cropHeight, x, y, width, height);
}

function createGeometry(count, columns, rows, camera, container) {
  const aspect = Math.max(0.45, container.clientWidth / Math.max(container.clientHeight, 1));
  const visibleHeight = 2 * Math.tan((camera.fov * Math.PI) / 360) * camera.position.length();
  const visibleWidth = visibleHeight * aspect;
  let panelWidth = Math.min(1.15, (visibleWidth / columns) * 0.82);
  let panelHeight = panelWidth * (TILE_HEIGHT / TILE_WIDTH);
  let gapX = Math.min(0.2, panelWidth * 0.16);
  let gapY = Math.min(0.22, panelHeight * 0.22);
  const totalHeight = rows * panelHeight + Math.max(0, rows - 1) * gapY;
  if (totalHeight > visibleHeight * 0.82) {
    const scale = (visibleHeight * 0.82) / totalHeight;
    panelWidth *= scale;
    panelHeight *= scale;
    gapX *= scale;
    gapY *= scale;
  }

  const positions = [];
  const uvs = [];
  const indices = [];
  const centers = [];

  for (let index = 0; index < count; index++) {
    const column = index % columns;
    const row = Math.floor(index / columns);
    const centerX = (column - (columns - 1) / 2) * (panelWidth + gapX);
    const centerY = ((rows - 1) / 2 - row) * (panelHeight + gapY);
    const depth = -Math.abs(column - (columns - 1) / 2) * 0.08 - row * 0.025;
    const yaw = (column - (columns - 1) / 2) * 0.065;
    const cosine = Math.cos(yaw);
    const sine = Math.sin(yaw);
    const halfWidth = panelWidth / 2;
    const halfHeight = panelHeight / 2;
    const corners = [
      [-halfWidth, -halfHeight],
      [halfWidth, -halfHeight],
      [halfWidth, halfHeight],
      [-halfWidth, halfHeight],
    ];
    const baseVertex = positions.length / 3;

    corners.forEach(([x, y]) => {
      positions.push(centerX + x * cosine, centerY + y, depth - x * sine);
    });

    const tileColumn = index % columns;
    const tileRow = Math.floor(index / columns);
    const u0 = tileColumn / columns;
    const u1 = (tileColumn + 1) / columns;
    const v0 = 1 - (tileRow + 1) / rows;
    const v1 = 1 - tileRow / rows;
    uvs.push(u0, v0, u1, v0, u1, v1, u0, v1);
    indices.push(baseVertex, baseVertex + 1, baseVertex + 2, baseVertex, baseVertex + 2, baseVertex + 3);
    centers.push({ x: centerX, y: centerY });
  }

  const geometry = new THREE.BufferGeometry();
  geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
  geometry.setAttribute('uv', new THREE.Float32BufferAttribute(uvs, 2));
  geometry.setIndex(indices);
  geometry.computeVertexNormals();
  return { geometry, centers, aspect };
}

registerScene('portfolio-3d', ({ scene, camera, container, config }) => {
  const requestedLimit = Number.parseInt(config.frameLimit, 10);
  const frameLimit = Number.isFinite(requestedLimit) ? Math.min(MAX_FRAMES, Math.max(1, requestedLimit)) : MAX_FRAMES;
  const images = readImageUrls(config.images, frameLimit);
  if (!images.length) return { animated: false, update() {}, dispose() {} };

  const count = images.length;
  const aspect = container.clientWidth / Math.max(container.clientHeight, 1);
  const columns = Math.min(count, aspect < 0.85 ? 2 : 4);
  const rows = Math.ceil(count / columns);
  const atlas = document.createElement('canvas');
  atlas.width = columns * TILE_WIDTH;
  atlas.height = rows * TILE_HEIGHT;
  const context = atlas.getContext('2d');
  if (!context) return { animated: false, update() {}, dispose() {} };
  context.imageSmoothingEnabled = true;
  context.imageSmoothingQuality = 'high';

  const colors = ['#5b21b6', '#4338ca', '#7c3aed', '#312e81'];
  for (let index = 0; index < count; index++) {
    const x = (index % columns) * TILE_WIDTH;
    const y = Math.floor(index / columns) * TILE_HEIGHT;
    const gradient = context.createLinearGradient(x, y, x + TILE_WIDTH, y + TILE_HEIGHT);
    gradient.addColorStop(0, colors[index % colors.length]);
    gradient.addColorStop(1, '#111827');
    context.fillStyle = gradient;
    context.fillRect(x, y, TILE_WIDTH, TILE_HEIGHT);
  }

  const atlasTexture = new THREE.CanvasTexture(atlas);
  atlasTexture.colorSpace = THREE.SRGBColorSpace;
  const initialGeometry = createGeometry(count, columns, rows, camera, container);
  const mesh = new THREE.Mesh(initialGeometry.geometry, new THREE.MeshStandardMaterial({
    map: atlasTexture,
    roughness: 0.48,
    metalness: 0.12,
    side: THREE.DoubleSide,
    transparent: true,
    opacity: 0.92,
  }));
  mesh.frustumCulled = false;
  const group = new THREE.Group();
  group.add(mesh);
  scene.add(group);

  const ambient = new THREE.AmbientLight('#ffffff', 1.5);
  const key = new THREE.DirectionalLight('#ffffff', 1.2);
  key.position.set(-2, 3, 5);
  scene.add(ambient, key);

  const section = container.closest('#portfolio');
  const cards = section ? Array.from(section.querySelectorAll('.pcard')).slice(0, count) : [];
  const pointer = { x: 0, y: 0 };
  let activeIndex = -1;
  let disposed = false;
  let layoutAspect = initialGeometry.aspect;
  let centers = initialGeometry.centers;

  const onPointerMove = (event) => {
    if (!section) return;
    const rect = section.getBoundingClientRect();
    pointer.x = ((event.clientX - rect.left) / Math.max(rect.width, 1) - 0.5) * 2;
    pointer.y = (0.5 - (event.clientY - rect.top) / Math.max(rect.height, 1)) * 2;
  };
  const onPointerLeave = () => { pointer.x = 0; pointer.y = 0; };
  const onCardFocus = (event) => {
    const card = event.target.closest('.pcard');
    activeIndex = card ? cards.indexOf(card) : -1;
  };
  const onCardEnter = (event) => { activeIndex = cards.indexOf(event.currentTarget); };

  if (section) {
    section.addEventListener('pointermove', onPointerMove, { passive: true });
    section.addEventListener('pointerleave', onPointerLeave, { passive: true });
    section.addEventListener('focusin', onCardFocus);
    cards.forEach((card) => card.addEventListener('mouseenter', onCardEnter, { passive: true }));
  }

  const loader = new THREE.TextureLoader();
  images.forEach((url, index) => {
    if (!url) return;
    const x = (index % columns) * TILE_WIDTH;
    const y = Math.floor(index / columns) * TILE_HEIGHT;
    try {
      loader.load(url, (texture) => {
        if (disposed) {
          texture.dispose();
          return;
        }
        drawCover(context, texture.image, x, y, TILE_WIDTH, TILE_HEIGHT);
        texture.dispose();
        atlasTexture.needsUpdate = true;
      }, undefined, () => {});
    } catch (_) {
      // A failed cover leaves its bounded color tile in the atlas.
    }
  });

  return {
    animated: true,
    update({ time, delta }) {
      const currentAspect = container.clientWidth / Math.max(container.clientHeight, 1);
      if (Math.abs(currentAspect - layoutAspect) > 0.05) {
        const next = createGeometry(count, columns, rows, camera, container);
        const previous = mesh.geometry;
        mesh.geometry = next.geometry;
        previous.dispose();
        centers = next.centers;
        layoutAspect = next.aspect;
      }

      const focused = activeIndex >= 0 ? centers[activeIndex] : null;
      const targetX = focused ? focused.x * 0.16 : pointer.x * 0.12;
      const targetY = focused ? focused.y * 0.12 : pointer.y * 0.08;
      group.rotation.y += ((pointer.x * -0.035) - group.rotation.y) * Math.min(1, delta * 4);
      group.rotation.x += ((pointer.y * 0.025) - group.rotation.x) * Math.min(1, delta * 4);
      camera.position.x += (targetX - camera.position.x) * Math.min(1, delta * 2.2);
      camera.position.y += (targetY - camera.position.y) * Math.min(1, delta * 2.2);
      camera.lookAt(targetX * 0.25, targetY * 0.25, 0);
      group.rotation.z = Math.sin(time * 0.22) * 0.012;
    },
    dispose() {
      disposed = true;
      atlasTexture.dispose();
      if (section) {
        section.removeEventListener('pointermove', onPointerMove);
        section.removeEventListener('pointerleave', onPointerLeave);
        section.removeEventListener('focusin', onCardFocus);
        cards.forEach((card) => card.removeEventListener('mouseenter', onCardEnter));
      }
    },
  };
});
