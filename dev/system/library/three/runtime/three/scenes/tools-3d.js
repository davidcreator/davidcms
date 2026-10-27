/**
 * Cena 5 — ícones decorativos flutuantes na seção Ferramentas.
 * Cards/links HTML continuam sendo o conteúdo; SceneRunner mantém todo o
 * ciclo de vida WebGL. Sem imagem cadastrada, a textura usa a inicial do nome.
 */
import { registerScene, THREE } from '../core/scene-runner.js';

const MAX_TOOLS = 4;
const TEXTURE_SIZE = 256;

function sameOriginUrl(value) {
  if (typeof value !== 'string' || value === '') return '';
  try {
    const url = new URL(value, window.location.href);
    return url.origin === window.location.origin ? url.href : '';
  } catch (_) {
    return '';
  }
}

function readItems(value) {
  let items;
  try {
    items = JSON.parse(value || '[]');
  } catch (_) {
    return [];
  }
  if (!Array.isArray(items)) return [];
  return items.slice(0, MAX_TOOLS).map((item) => ({
    name: typeof item?.name === 'string' ? item.name.trim().slice(0, 64) : '',
    image: sameOriginUrl(item?.image),
  }));
}

function roundedRect(context, x, y, width, height, radius) {
  const r = Math.min(radius, width / 2, height / 2);
  context.beginPath();
  context.moveTo(x + r, y);
  context.arcTo(x + width, y, x + width, y + height, r);
  context.arcTo(x + width, y + height, x, y + height, r);
  context.arcTo(x, y + height, x, y, r);
  context.arcTo(x, y, x + width, y, r);
  context.closePath();
}

function paintTile(context, name, index) {
  const gradient = context.createLinearGradient(0, 0, TEXTURE_SIZE, TEXTURE_SIZE);
  gradient.addColorStop(0, index % 2 ? '#4338ca' : '#6d28d9');
  gradient.addColorStop(1, '#17122d');
  context.clearRect(0, 0, TEXTURE_SIZE, TEXTURE_SIZE);
  context.fillStyle = gradient;
  roundedRect(context, 5, 5, TEXTURE_SIZE - 10, TEXTURE_SIZE - 10, 38);
  context.fill();
  context.strokeStyle = 'rgba(255,255,255,.55)';
  context.lineWidth = 5;
  context.stroke();
  const initial = Array.from(name || '?')[0] || '?';
  context.fillStyle = '#ffffff';
  context.font = '800 142px system-ui, sans-serif';
  context.textAlign = 'center';
  context.textBaseline = 'middle';
  context.fillText(initial.toLocaleUpperCase(), TEXTURE_SIZE / 2, TEXTURE_SIZE / 2 + 4, TEXTURE_SIZE - 48);
}

function drawImageContain(context, image) {
  const width = image.naturalWidth || image.width;
  const height = image.naturalHeight || image.height;
  if (!width || !height) return;
  const maxSize = TEXTURE_SIZE * 0.68;
  const scale = Math.min(maxSize / width, maxSize / height);
  const drawWidth = width * scale;
  const drawHeight = height * scale;
  context.drawImage(image, (TEXTURE_SIZE - drawWidth) / 2, (TEXTURE_SIZE - drawHeight) / 2, drawWidth, drawHeight);
}

registerScene('tools-3d', ({ scene, camera, container, config }) => {
  const items = readItems(config.items);
  if (!items.length) return { animated: false, update() {}, dispose() {} };

  const section = container.closest('#tools');
  if (!section) return { animated: false, update() {}, dispose() {} };
  const cards = Array.from(section.querySelectorAll('.tool-card')).slice(0, items.length);
  if (!cards.length) return { animated: false, update() {}, dispose() {} };

  const ambient = new THREE.AmbientLight('#ffffff', 1.35);
  const key = new THREE.DirectionalLight('#ffffff', 1.1);
  key.position.set(-2, 3, 5);
  scene.add(ambient, key);

  const loader = new THREE.TextureLoader();
  const entries = [];
  let disposed = false;
  let activeIndex = -1;
  let layoutWidth = 0;
  let layoutHeight = 0;

  for (let index = 0; index < cards.length; index++) {
    const icon = cards[index].querySelector('.tool-icon');
    if (!icon) continue;

    const canvas = document.createElement('canvas');
    canvas.width = TEXTURE_SIZE;
    canvas.height = TEXTURE_SIZE;
    const context2d = canvas.getContext('2d');
    if (!context2d) continue;
    paintTile(context2d, items[index]?.name || '', index);

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    const material = new THREE.MeshStandardMaterial({
      map: texture,
      roughness: 0.62,
      metalness: 0.08,
      side: THREE.DoubleSide,
    });
    const mesh = new THREE.Mesh(new THREE.PlaneGeometry(1, 1), material);
    mesh.position.z = -0.08;
    mesh.frustumCulled = false;
    scene.add(mesh);

    entries.push({
      card: cards[index],
      icon,
      item: items[index],
      texture,
      context: context2d,
      mesh,
      baseX: 0,
      baseY: 0,
      baseScaleX: 0,
      baseScaleY: 0,
      phase: index * 1.47,
      activation: 0,
    });
  }

  if (!entries.length) return { animated: false, update() {}, dispose() {} };

  function layout() {
    const bounds = container.getBoundingClientRect();
    const width = Math.max(1, bounds.width);
    const height = Math.max(1, bounds.height);
    const distance = Math.max(0.1, Math.abs(camera.position.z));
    const visibleHeight = 2 * Math.tan((camera.fov * Math.PI) / 360) * distance;
    const visibleWidth = visibleHeight * camera.aspect;

    entries.forEach((entry) => {
      const rect = entry.icon.getBoundingClientRect();
      const centerX = rect.left + rect.width / 2 - bounds.left;
      const centerY = rect.top + rect.height / 2 - bounds.top;
      entry.baseX = (centerX / width - 0.5) * visibleWidth;
      entry.baseY = (0.5 - centerY / height) * visibleHeight;
      entry.baseScaleX = (rect.width / width) * visibleWidth * 1.18;
      entry.baseScaleY = (rect.height / height) * visibleHeight * 1.18;
      entry.mesh.position.x = entry.baseX;
      entry.mesh.position.y = entry.baseY;
      entry.mesh.scale.set(entry.baseScaleX, entry.baseScaleY, 1);
    });
    layoutWidth = container.clientWidth;
    layoutHeight = container.clientHeight;
  }

  const handlers = entries.map((entry, index) => {
    const onEnter = () => { activeIndex = index; };
    const onLeave = () => {
      if (activeIndex === index && !entry.card.matches(':hover') && !entry.card.contains(document.activeElement)) activeIndex = -1;
    };
    const onFocus = () => { activeIndex = index; };
    const onBlur = (event) => {
      if (!entry.card.contains(event.relatedTarget) && !entry.card.matches(':hover') && activeIndex === index) activeIndex = -1;
    };
    entry.card.addEventListener('mouseenter', onEnter, { passive: true });
    entry.card.addEventListener('mouseleave', onLeave, { passive: true });
    entry.card.addEventListener('focusin', onFocus);
    entry.card.addEventListener('focusout', onBlur);
    return { card: entry.card, onEnter, onLeave, onFocus, onBlur };
  });

  layout();
  section.classList.add('tools-3d-active');

  entries.forEach((entry) => {
    if (!entry.item.image) return;
    try {
      loader.load(entry.item.image, (source) => {
        if (disposed) {
          source.dispose();
          return;
        }
        paintTile(entry.context, entry.item.name, entries.indexOf(entry));
        drawImageContain(entry.context, source.image);
        source.dispose();
        entry.texture.needsUpdate = true;
      }, undefined, () => {});
    } catch (_) {
      // A textura de inicial permanece como fallback se a capa falhar.
    }
  });

  return {
    animated: true,
    update({ time }) {
      if (container.clientWidth !== layoutWidth || container.clientHeight !== layoutHeight) layout();
      entries.forEach((entry, index) => {
        const active = activeIndex === index ? 1 : 0;
        entry.activation += (active - entry.activation) * 0.12;
        const phase = time * 0.72 + entry.phase;
        const emphasis = entry.activation;
        entry.mesh.position.x = entry.baseX;
        entry.mesh.position.y = entry.baseY + Math.sin(phase) * 0.025;
        entry.mesh.position.z = -0.08 + emphasis * 0.12;
        entry.mesh.rotation.x = Math.sin(phase * 0.72) * 0.08 - emphasis * 0.1;
        entry.mesh.rotation.y = Math.cos(phase * 0.58) * 0.14 + emphasis * 0.2;
        entry.mesh.rotation.z = Math.sin(phase * 0.4) * 0.025;
        const scale = 1 + emphasis * 0.13;
        entry.mesh.scale.set(entry.baseScaleX * scale, entry.baseScaleY * scale, 1);
      });
    },
    dispose() {
      disposed = true;
      section.classList.remove('tools-3d-active');
      handlers.forEach(({ card, onEnter, onLeave, onFocus, onBlur }) => {
        card.removeEventListener('mouseenter', onEnter);
        card.removeEventListener('mouseleave', onLeave);
        card.removeEventListener('focusin', onFocus);
        card.removeEventListener('focusout', onBlur);
      });
      entries.forEach((entry) => entry.texture.dispose());
    },
  };
});
