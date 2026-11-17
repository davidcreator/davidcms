/**
 * Cena "david-universe" — voo com nave, orbes colecionáveis e scroll-driven.
 *
 * Frente BO. Porta da classe g3 da build Vite/React da amostra online para o
 * ciclo de vida nativo do ReamurCMS (SceneRunner). O renderer, a câmera, o
 * resize, a pausa por IntersectionObserver/visibilitychange e o descarte
 * continuam no runner; este módulo fornece APENAS construção e update.
 *
 * Sem React, sem bundle externo, sem tag de script no tema, sem recursos de
 * terceiros, sem rede, sem execução dinâmica, sem injeção de marcação.
 * DOM apenas por textContent/createElement; texturas procedurais em canvas
 * 2D local. Three.js self-hosted r186 (asset_manifest).
 *
 * Consome somente data-* allowlistados por theme-scene.twig:
 *   data-scene="david-universe"  data-count (50..800)  data-color (#rrggbb)
 *   data-speed (0.01..0.20)      data-slot
 * data-debug="1" liga o gancho de diagnóstico usado apenas pela prévia.
 */
import { registerScene, THREE } from '../core/scene-runner.js';

const HO = 320;            // extensão do voo: z 8 → −312
const SHIP_LAG = 9;       // nave 9 unidades atrás da câmera
const ORB_COUNT = 14;
const GATE_COUNT = 8;
const TRAIL = 60;
const TRAIL_LIFE = 0.95;
const MOON_KM = 384400;
const BG = 0x121516;
const CYAN = 0x5cc6d0;
const CORAL = 0xeb767e;
const STEEL = 0xa7b3b5;

const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
const lerp = (a, b, t) => a + (b - a) * t;

/** Textura radial suave, procedural, sem rede. */
function radialTexture(rgb = '255,255,255', core = 1, falloff = 0.55) {
  const c = document.createElement('canvas');
  c.width = c.height = 128;
  const ctx = c.getContext('2d');
  const g = ctx.createRadialGradient(64, 64, 0, 64, 64, 64);
  g.addColorStop(0, `rgba(${rgb},${core})`);
  g.addColorStop(falloff, `rgba(${rgb},${core * 0.28})`);
  g.addColorStop(1, `rgba(${rgb},0)`);
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, 128, 128);
  const tex = new THREE.CanvasTexture(c);
  tex.colorSpace = THREE.SRGBColorSpace;
  return tex;
}

/** Faixas horizontais de planeta, determinísticas por seed (sem rand livre). */
function bandTexture(bands, seed) {
  const c = document.createElement('canvas');
  c.width = 256; c.height = 128;
  const ctx = c.getContext('2d');
  ctx.fillStyle = bands[0];
  ctx.fillRect(0, 0, 256, 128);
  for (let y = 0; y < 128; y += 2) {
    const n = Math.sin(y * 0.19 + seed * 2.1) * 0.5 + Math.sin(y * 0.07 + seed) * 0.5;
    const idx = Math.abs(Math.floor(n * bands.length)) % bands.length;
    ctx.globalAlpha = 0.30 + Math.abs(n) * 0.42;
    ctx.fillStyle = bands[idx];
    ctx.fillRect(0, y, 256, 2 + Math.round(Math.abs(n) * 5));
  }
  ctx.globalAlpha = 1;
  const g = ctx.createLinearGradient(0, 0, 256, 0);
  g.addColorStop(0, 'rgba(0,0,0,.34)');
  g.addColorStop(0.42, 'rgba(255,255,255,.05)');
  g.addColorStop(1, 'rgba(0,0,0,.42)');
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, 256, 128);
  const tex = new THREE.CanvasTexture(c);
  tex.colorSpace = THREE.SRGBColorSpace;
  return tex;
}

/** Asteroide irregular, determinístico por semente. */
function asteroidGeometry(seed) {
  const g = new THREE.IcosahedronGeometry(1, 1);
  const p = g.attributes.position;
  for (let i = 0; i < p.count; i++) {
    const x = p.getX(i), y = p.getY(i), z = p.getZ(i);
    const n = Math.sin(x * 7.3 + seed * 2) * Math.cos(y * 5.1 - seed) * Math.sin(z * 6.7 + seed);
    const s = 1 + n * 0.22;
    p.setXYZ(i, x * s, y * s, z * s);
  }
  g.computeVertexNormals();
  return g;
}

/** HUD existente do tema: data-* primeiro, seletores de classe como fallback. */
function findHud() {
  const q = (s) => document.querySelector(s);
  return {
    orbes: q('[data-du-orbes]') || [...document.querySelectorAll('.du-orbs')]
      .find((n) => /ORBES/i.test(n.textContent || '')) || null,
    distancia: q('[data-du-distancia]') || q('.du-status b') || null,
    fase: q('[data-du-fase]') || q('.du-status span') || null,
    hints: [...document.querySelectorAll('[data-du-hint-distancia]')],
    progresso: q('[data-du-progresso]') || q('.du-universe-progress') || null,
    toast: q('[data-du-toast]') || q('.du-toast') || null,
  };
}

const PHASES = [
  'DECOLAGEM', 'SOBRE', 'PROCESSO', 'POR QUE INVESTIR', 'HABILIDADES',
  'SERVIÇOS', 'CATÁLOGO', 'DEPOIMENTOS', 'CONTATO',
];

/**
 * KZ-20 (ADR 0113): texto que o TEMA escreve num atributo data-*, no idioma do
 * visitante. Sem o atributo (tema antigo), vale o texto fixo de antes. Lido por
 * getAttribute e usado so em textContent: nunca vira HTML.
 */
function themeText(node, name, fallback) {
  const raw = node && typeof node.getAttribute === 'function' ? node.getAttribute(name) : null;
  const text = typeof raw === 'string' ? raw.trim() : '';
  return text !== '' && text.length <= 120 ? text : fallback;
}

registerScene('david-universe', ({ scene, camera, renderer, container, config }) => {
  // ── Câmera: o runner cria uma PerspectiveCamera genérica; ajustamos aqui.
  camera.near = 0.1;
  camera.far = 800;
  camera.fov = 66;
  camera.position.set(0, 1.5, 8);
  camera.lookAt(0, 0.7, -22);
  camera.updateProjectionMatrix();
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
  scene.fog = new THREE.FogExp2(BG, 0.0072);
  scene.background = new THREE.Color(BG); // KZ-29: fundo opaco — o renderer nasce com alpha:true e o fallback CSS vazava pelo canvas

  const reduced = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  const coarse = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
  const accent = /^#[0-9a-fA-F]{6}$/.test(String(config.color || '')) ? config.color : '#5cc6d0';
  const accentHex = new THREE.Color(accent).getHex();
  const stars = clamp(parseInt(config.count, 10) || 520, 50, 800);
  const speed = clamp(parseFloat(config.speed) || 0.06, 0.01, 0.20);

  const textures = [];
  const track = (t) => (textures.push(t), t);
  const spins = [];
  const orbs = [];
  const bursts = [];
  const gates = [];
  const nebulae = [];

  // ── Luzes ────────────────────────────────────────────────────────────
  scene.add(new THREE.AmbientLight(0x8fb7b8, 0.8));
  const key = new THREE.DirectionalLight(0xdfe8e8, 1.6);
  key.position.set(6, 8, 10);
  scene.add(key);
  const rim = new THREE.DirectionalLight(CORAL, 0.9);
  rim.position.set(-8, -3, -6);
  scene.add(rim);

  // ── Estrelas (3 camadas) ─────────────────────────────────────────────
  const starTex = track(radialTexture('255,255,255', 1, 0.55));
  function starLayer(count, color, size, spreadX, spreadY, opacity) {
    const pos = new Float32Array(count * 3);
    for (let i = 0; i < count; i++) {
      pos[i * 3] = (Math.random() - 0.5) * spreadX;
      pos[i * 3 + 1] = (Math.random() - 0.5) * spreadY;
      pos[i * 3 + 2] = 40 - Math.random() * (HO + 170);
    }
    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    const mat = new THREE.PointsMaterial({
      color, size, map: starTex, transparent: true, opacity,
      depthWrite: false, blending: THREE.AdditiveBlending,
    });
    const points = new THREE.Points(geo, mat);
    points.frustumCulled = false;
    scene.add(points);
  }
  starLayer(Math.round(stars * 1.4), STEEL, 0.34, 230, 130, 0.9);
  starLayer(Math.round(stars * 0.4), accentHex, 0.42, 190, 110, 0.85);
  starLayer(150, CORAL, 0.36, 210, 120, 0.7);

  // ── Nebulosas ────────────────────────────────────────────────────────
  const nebTex = track(radialTexture('255,255,255', 0.9, 0.3));
  const NEBULAE = [
    [34, 8, -52, 130, accentHex, 0.16], [-40, -6, -112, 160, CORAL, 0.12],
    [24, 14, -172, 180, 0x2f7b88, 0.22], [-32, 10, -232, 170, accentHex, 0.14],
    [10, -14, -292, 210, CORAL, 0.12], [-14, 20, -18, 130, 0x2a5f66, 0.18],
    [40, -10, -250, 150, 0x2f7b88, 0.16],
  ];
  for (const [x, y, z, s, color, opacity] of NEBULAE) {
    const mat = new THREE.SpriteMaterial({
      map: nebTex, color, transparent: true, opacity,
      depthWrite: false, blending: THREE.AdditiveBlending, fog: false,
    });
    const sprite = new THREE.Sprite(mat);
    sprite.position.set(x, y, z);
    sprite.scale.set(s, s, 1);
    scene.add(sprite);
    nebulae.push(sprite);
  }

  // ── Planetas ─────────────────────────────────────────────────────────
  const glowTex = track(radialTexture('255,255,255', 0.8, 0.28));
  const PLANETS = [
    { x: 21, y: 5, z: -62, r: 11, color: accentHex,
      bands: ['#1d4a50', '#2a6a72', '#18383d', '#357f88', '#214e55'], seed: 1,
      ring: { inner: 15, outer: 20.5, color: accentHex, opacity: 0.32, tilt: 0.42 }, wire: 0.16, glow: 0.42 },
    { x: -27, y: -4, z: -142, r: 8.5, color: CORAL,
      bands: ['#7a2f36', '#eb767e', '#a24850', '#5a2227', '#c05b63'], seed: 2, glow: 0.30 },
    { x: 27, y: 10, z: -216, r: 13, color: 0x8fac93,
      bands: ['#2c3234', '#3d4649', '#242a2c', '#4b5659', '#323a3d'], seed: 3,
      ring: { inner: 18, outer: 25, color: STEEL, opacity: 0.16, tilt: 0.30 }, wire: 0.10, glow: 0.22 },
    { x: -17, y: 5, z: -266, r: 4.2, color: STEEL,
      bands: ['#8c9899', '#a7b3b5', '#6f7c7e', '#b8c3c5'], seed: 4, glow: 0.20 },
    { x: 14, y: -6, z: -306, r: 6.5, color: CORAL,
      bands: ['#3a2427', '#eb767e', '#7a2f36', '#f1a0a6'], seed: 5, wire: 0.22, glow: 0.34 },
  ];
  for (const p of PLANETS) {
    const group = new THREE.Group();
    group.position.set(p.x, p.y, p.z);
    const sphere = new THREE.Mesh(
      new THREE.SphereGeometry(p.r, 40, 28),
      new THREE.MeshStandardMaterial({
        map: track(bandTexture(p.bands, p.seed)), color: 0xffffff,
        roughness: 0.88, metalness: 0.04, emissive: p.color, emissiveIntensity: 0.07,
      })
    );
    group.add(sphere);
    spins.push({ obj: sphere, rx: 0, ry: 0.025, rz: 0 });
    if (p.wire) {
      const wire = new THREE.Mesh(
        new THREE.SphereGeometry(p.r * 1.015, 24, 16),
        new THREE.MeshBasicMaterial({ color: accentHex, wireframe: true, transparent: true, opacity: p.wire })
      );
      group.add(wire);
      spins.push({ obj: wire, rx: 0, ry: 0.012, rz: 0 });
    }
    if (p.glow) {
      const glow = new THREE.Sprite(new THREE.SpriteMaterial({
        map: glowTex, color: p.color, transparent: true, opacity: p.glow,
        depthWrite: false, blending: THREE.AdditiveBlending, fog: false,
      }));
      glow.scale.setScalar(p.r * 3.6);
      group.add(glow);
    }
    if (p.ring) {
      const ringMat = () => new THREE.MeshBasicMaterial({
        color: p.ring.color, transparent: true, opacity: p.ring.opacity,
        side: THREE.DoubleSide, depthWrite: false,
      });
      const a = new THREE.Mesh(new THREE.RingGeometry(p.ring.inner, p.ring.outer, 96), ringMat());
      a.rotation.x = Math.PI / 2 - p.ring.tilt;
      a.rotation.y = 0.25;
      group.add(a);
      const b = new THREE.Mesh(
        new THREE.RingGeometry(p.ring.outer * 1.08, p.ring.outer * 1.16, 96),
        ringMat()
      );
      b.material.opacity = p.ring.opacity * 0.55;
      b.rotation.copy(a.rotation);
      group.add(b);
    }
    scene.add(group);
  }

  // ── Asteroides ───────────────────────────────────────────────────────
  const astGeo = [asteroidGeometry(1), asteroidGeometry(2), asteroidGeometry(3)];
  const astMat = [0x4a585b, 0x3a4448, 0x5d4a52].map((color) =>
    new THREE.MeshStandardMaterial({ color, roughness: 1, metalness: 0.05, flatShading: true }));
  for (let i = 0; i < 46; i++) {
    const m = new THREE.Mesh(astGeo[i % 3], astMat[(i * 7) % 3]);
    const s = 0.5 + Math.random() * 2.1;
    m.scale.set(s, s * (0.7 + Math.random() * 0.5), s * (0.8 + Math.random() * 0.5));
    m.position.set(
      (Math.random() < 0.5 ? -1 : 1) * (8 + Math.random() * 26),
      -7 + Math.random() * 18,
      -14 - Math.random() * (HO + 10)
    );
    m.rotation.set(Math.random() * 6, Math.random() * 6, Math.random() * 6);
    scene.add(m);
    spins.push({ obj: m, rx: (Math.random() - 0.5) * 0.4, ry: (Math.random() - 0.5) * 0.4, rz: (Math.random() - 0.5) * 0.2 });
  }

  // ── Astronautas ──────────────────────────────────────────────────────
  function astronaut(x, y, z, scale) {
    const g = new THREE.Group();
    const suit = new THREE.MeshStandardMaterial({ color: STEEL, roughness: 0.55, metalness: 0.10 });
    const pack = new THREE.MeshStandardMaterial({ color: 0x2b3134, roughness: 0.5, metalness: 0.5 });
    const visor = new THREE.MeshStandardMaterial({ color: 0x0d1214, roughness: 0.12, metalness: 0.9, emissive: accentHex, emissiveIntensity: 0.18 });
    const glow = new THREE.MeshStandardMaterial({ color: accentHex, emissive: accentHex, emissiveIntensity: 0.6 });
    const led = new THREE.MeshStandardMaterial({ color: CORAL, emissive: CORAL, emissiveIntensity: 0.5 });
    const body = new THREE.Mesh(new THREE.CylinderGeometry(0.55, 0.7, 6, 14), suit);
    const helmet = new THREE.Mesh(new THREE.SphereGeometry(0.58, 22, 16), suit);
    helmet.position.y = 1.08;
    const face = new THREE.Mesh(new THREE.SphereGeometry(0.44, 22, 16), visor);
    face.position.set(0, 1.1, 0.24);
    face.scale.set(1, 0.85, 0.8);
    const backpack = new THREE.Mesh(new THREE.BoxGeometry(0.95, 1.15, 0.45), pack);
    backpack.position.set(0, 0.1, -0.58);
    const collar = new THREE.Mesh(new THREE.CylinderGeometry(0.58, 0.58, 0.10, 20), glow);
    collar.position.y = -0.2;
    const lamp = new THREE.Mesh(new THREE.SphereGeometry(0.07, 10, 8), led);
    lamp.position.set(0.22, 0.38, 0.54);
    const armL = new THREE.Mesh(new THREE.CylinderGeometry(0.17, 0.6, 4, 10), suit);
    armL.position.set(-0.82, 0.25, 0.1); armL.rotation.z = 0.9;
    const armR = armL.clone(); armR.position.x = 0.82; armR.rotation.z = -0.9;
    const legL = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.65, 4, 10), suit);
    legL.position.set(-0.3, -1.1, 0); legL.rotation.x = 0.25;
    const legR = legL.clone(); legR.position.x = 0.3; legR.rotation.x = -0.2;
    g.add(body, helmet, face, backpack, collar, lamp, armL, armR, legL, legR);
    g.position.set(x, y, z);
    g.scale.setScalar(scale);
    g.rotation.set(0.4, 0.8, 0.2);
    scene.add(g);
    spins.push({ obj: g, rx: 0.10, ry: 0.17, rz: 0.08, bob: 0.6, bobSpeed: 0.55, phase: Math.random() * 6, baseY: y });
  }
  astronaut(-10, 2.5, -80, 1.1);
  astronaut(12, -2.5, -208, 1.35);

  // ── Gates ────────────────────────────────────────────────────────────
  for (let i = 0; i < GATE_COUNT; i++) {
    const z = -((HO - 20) * (i + 1)) / 8.4;
    const group = new THREE.Group();
    group.position.set(0, 0.6, z);
    const mats = [];
    const torus = (radius, tube, opacity, color) => {
      const mat = new THREE.MeshBasicMaterial({ color, transparent: true, opacity, depthWrite: false });
      mats.push(mat);
      return new THREE.Mesh(new THREE.TorusGeometry(radius, tube, 8, 90), mat);
    };
    group.add(torus(10.5, 0.07, 0.35, accentHex));
    group.add(torus(9.2, 0.03, 0.22, STEEL));
    for (let j = 0; j < 4; j++) {
      const a = (j * Math.PI) / 2;
      const mark = new THREE.Mesh(
        new THREE.BoxGeometry(0.16, 1.1, 0.06),
        new THREE.MeshBasicMaterial({ color: accentHex, transparent: true, opacity: 0.55 })
      );
      mark.position.set(Math.cos(a) * 10.5, Math.sin(a) * 10.5, 0);
      mark.rotation.z = a;
      group.add(mark);
    }
    scene.add(group);
    gates.push({ group, z, passed: false, t0: 0, mats });
  }

  // ── Orbes ────────────────────────────────────────────────────────────
  const orbTex = track(radialTexture('255,255,255', 0.95, 0.35));
  const orbGeo = new THREE.OctahedronGeometry(0.6, 0);
  const wireGeo = new THREE.OctahedronGeometry(1, 0);
  const orbMat = new THREE.MeshBasicMaterial({ color: accentHex });
  const wireMat = new THREE.MeshBasicMaterial({ color: STEEL, wireframe: true, transparent: true, opacity: 0.7 });
  for (let i = 0; i < ORB_COUNT; i++) {
    const group = new THREE.Group();
    const halo = new THREE.Sprite(new THREE.SpriteMaterial({
      map: orbTex, color: accentHex, transparent: true, opacity: 0.85,
      depthWrite: false, blending: THREE.AdditiveBlending, fog: false,
    }));
    halo.scale.setScalar(4.4);
    group.add(new THREE.Mesh(orbGeo, orbMat), new THREE.Mesh(wireGeo, wireMat), halo);
    const z = -26 - ((HO - 48) * i) / (ORB_COUNT - 1);
    const x = (Math.random() - 0.5) * 11;
    const y = (Math.random() - 0.5) * 5.6 + 0.2;
    group.position.set(x, y, z);
    scene.add(group);
    orbs.push({ group, halo, x, y, z, baseY: y, phase: Math.random() * 6, collected: false, hitAt: 0 });
  }

  // ── A NAVE ───────────────────────────────────────────────────────────
  const ship = new THREE.Group();
  const hullMat = new THREE.MeshStandardMaterial({ color: STEEL, metalness: 0.45, roughness: 0.32 });
  const darkMat = new THREE.MeshStandardMaterial({ color: 0x2b3134, metalness: 0.60, roughness: 0.40 });
  const cyanMat = new THREE.MeshStandardMaterial({ color: accentHex, emissive: accentHex, emissiveIntensity: 0.6, metalness: 0.2, roughness: 0.4 });
  const coralMat = new THREE.MeshStandardMaterial({ color: CORAL, emissive: CORAL, emissiveIntensity: 0.35, metalness: 0.3, roughness: 0.4 });

  const fuselage = new THREE.Mesh(new THREE.CylinderGeometry(0.5, 0.42, 1.9, 18), hullMat);
  fuselage.rotation.x = Math.PI / 2;
  const nose = new THREE.Mesh(new THREE.ConeGeometry(0.42, 1.25, 18), coralMat);
  nose.rotation.x = -Math.PI / 2;
  nose.position.z = -1.58;
  const engine = new THREE.Mesh(new THREE.CylinderGeometry(0.44, 0.30, 0.45, 14), darkMat);
  engine.rotation.x = Math.PI / 2;
  engine.position.z = 1.15;
  const lightRing = new THREE.Mesh(new THREE.CylinderGeometry(0.505, 0.505, 0.14, 18), cyanMat);
  lightRing.rotation.x = Math.PI / 2;
  lightRing.position.z = 0.35;
  const canopy = new THREE.Mesh(new THREE.SphereGeometry(0.3, 16, 12), cyanMat);
  canopy.scale.set(1, 0.8, 1.6);
  canopy.position.set(0, 0.4, -0.4);

  const wingShape = new THREE.Shape();
  wingShape.moveTo(0, 0);
  wingShape.lineTo(1.6, 0.7);
  wingShape.lineTo(1.6, 1.15);
  wingShape.lineTo(0, 0.95);
  wingShape.closePath();
  const wingGeo = new THREE.ExtrudeGeometry(wingShape, { depth: 0.06, bevelEnabled: false });
  const wingR = new THREE.Mesh(wingGeo, hullMat);
  wingR.rotation.x = Math.PI / 2;
  wingR.position.set(0.38, 0.03, -0.10);
  const wingL = wingR.clone();
  wingL.scale.x = -1;
  wingL.position.x = -0.38;
  const gunR = new THREE.Mesh(new THREE.BoxGeometry(0.07, 0.16, 0.5), cyanMat);
  gunR.position.set(1.98, 0, 0.62);
  const gunL = gunR.clone();
  gunL.position.x = -1.98;
  const fin = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.75, 0.75), coralMat);
  fin.position.set(0, 0.55, 0.8);
  fin.rotation.x = 0.5;

  const flameOuterGeo = new THREE.ConeGeometry(0.30, 1.8, 14);
  flameOuterGeo.translate(0, 0.9, 0);
  const flameInnerGeo = new THREE.ConeGeometry(0.17, 1.3, 12);
  flameInnerGeo.translate(0, 0.65, 0);
  const flameOuter = new THREE.Mesh(flameOuterGeo, new THREE.MeshBasicMaterial({
    color: accentHex, transparent: true, opacity: 0.8, blending: THREE.AdditiveBlending, depthWrite: false,
  }));
  const flameInner = new THREE.Mesh(flameInnerGeo, new THREE.MeshBasicMaterial({
    color: 0xffffff, transparent: true, opacity: 0.9, blending: THREE.AdditiveBlending, depthWrite: false,
  }));
  flameOuter.rotation.x = flameInner.rotation.x = Math.PI / 2;
  flameOuter.position.z = flameInner.position.z = 1.37;
  const shipLight = new THREE.PointLight(accentHex, 3.2, 14);
  shipLight.position.set(0, 0, 2);

  ship.add(fuselage, nose, engine, lightRing, canopy, wingR, wingL, gunR, gunL, fin, flameOuter, flameInner, shipLight);
  ship.scale.setScalar(0.95);
  ship.position.set(0, 0, -1);
  scene.add(ship);

  // ── Trilha ───────────────────────────────────────────────────────────
  const trailPos = new Float32Array(TRAIL * 3);
  const trailCol = new Float32Array(TRAIL * 3);
  const trailAge = new Float32Array(TRAIL);
  const trailVel = new Float32Array(TRAIL * 3);
  for (let i = 0; i < TRAIL; i++) {
    trailAge[i] = Math.random() * TRAIL_LIFE;
    trailPos[i * 3 + 2] = -1;
  }
  const trailGeo = new THREE.BufferGeometry();
  trailGeo.setAttribute('position', new THREE.BufferAttribute(trailPos, 3));
  trailGeo.setAttribute('color', new THREE.BufferAttribute(trailCol, 3));
  const trail = new THREE.Points(trailGeo, new THREE.PointsMaterial({
    size: 0.42, map: track(radialTexture('255,255,255', 1, 0.5)),
    vertexColors: true, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
  }));
  trail.frustumCulled = false;
  scene.add(trail);

  // ── Estado / interações ──────────────────────────────────────────────
  const hud = findHud();
  let toastEl = hud.toast;
  if (!toastEl) {
    toastEl = document.createElement('div');
    toastEl.className = 'du-toast';
    toastEl.setAttribute('aria-live', 'polite');
    container.appendChild(toastEl);
  }
  let toastTimer = 0;
  function toast(message) {
    toastEl.textContent = message;
    toastEl.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('is-on'), 1900);
  }
  function setOrbes(n, total) {
    if (hud.orbes) {
      // Preserva os <span class="du-orb"> quando o fallback por classe é usado.
      if (hud.orbes.hasAttribute('data-du-orbes')) hud.orbes.textContent = `${String(n).padStart(2, '0')}/${total}`;
      else hud.orbes.firstChild
        ? (hud.orbes.firstChild.textContent = `ORBES ${String(n).padStart(2, '0')}/${total}`)
        : (hud.orbes.textContent = `ORBES ${String(n).padStart(2, '0')}/${total}`);
    }
  }
  function setDistancia(km) {
    const txt = `${km.toLocaleString('en-US')} KM`;
    if (hud.distancia) hud.distancia.textContent = txt;
    hud.hints.forEach((node) => { node.textContent = txt; });
  }
  function setFase(n, section) {
    if (!hud.fase) return;
    // Rotulo ("FASE") no proprio marcador do HUD; nome da fase na secao que entrou na tela.
    const label = themeText(hud.fase, 'data-du-rotulo', 'FASE');
    const name = themeText(section, 'data-du-fase-nome', PHASES[n] || PHASES[0]);
    hud.fase.textContent = `${label} ${String(n).padStart(2, '0')} — ${name}`;
  }
  function setProgresso(p) {
    if (hud.progresso) hud.progresso.style.width = `${(p * 100).toFixed(1)}%`;
  }

  const state = {
    progress: 0, target: 0, prevCamZ: 8, prevShipZ: -1, warp: 0,
    pointer: new THREE.Vector2(0, 0), lastPointerAt: -100, collected: 0,
    time: 0, disposed: false, readyFired: false,
  };

  function onPointer(event) {
    if (event.pointerType === 'touch') return;
    state.pointer.set(event.clientX / window.innerWidth - 0.5, event.clientY / window.innerHeight - 0.5);
    state.lastPointerAt = performance.now() / 1000;
  }
  function onScroll() {
    const max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
    state.target = clamp(window.scrollY / max, 0, 1);
  }
  window.addEventListener('pointermove', onPointer, { passive: true });
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // Fases: IntersectionObserver nos [id^="fase-"], como na build original.
  let phaseObserver = null;
  if (window.IntersectionObserver) {
    const sections = document.querySelectorAll('[id^="fase-"]');
    if (sections.length) {
      phaseObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          const n = parseInt(String(entry.target.id).replace('fase-', ''), 10);
          if (Number.isFinite(n) && n >= 0 && n < PHASES.length) setFase(n, entry.target);
        });
      }, { threshold: 0.15 });
      sections.forEach((s) => phaseObserver.observe(s));
    }
  }

  function nextOrb(z) {
    let best = null;
    for (const orb of orbs) {
      if (orb.collected || orb.z >= z - 0.5 || orb.z <= z - 60) continue;
      if (!best || orb.z > best.z) best = orb;
    }
    return best;
  }

  function collect(orb) {
    orb.collected = true;
    orb.hitAt = state.time;
    state.collected += 1;
    const ring = new THREE.Mesh(
      new THREE.RingGeometry(0.5, 0.75, 40),
      new THREE.MeshBasicMaterial({
        color: accentHex, transparent: true, opacity: 0.9, side: THREE.DoubleSide,
        blending: THREE.AdditiveBlending, depthWrite: false,
      })
    );
    ring.position.set(orb.x, orb.group.position.y, orb.z);
    scene.add(ring);
    bursts.push({ mesh: ring, t0: state.time });
    setOrbes(state.collected, orbs.length);
    toast(state.collected === orbs.length
      ? themeText(toastEl, 'data-du-msg-todos', 'Todos os orbes coletados')
      : `${themeText(toastEl, 'data-du-msg-orbe', 'Orbe coletado')} ${state.collected}/${orbs.length}`);
  }

  // Gancho de diagnóstico (desligado em produção; theme-scene.twig não o emite).
  let debug = null;
  if (container.dataset.debug === '1') {
    debug = {
      get revision() { return THREE.REVISION; },
      get progress() { return state.progress; },
      get target() { return state.target; },
      get scroll() { return { y: window.scrollY, max: Math.max(1, document.documentElement.scrollHeight - window.innerHeight) }; },
      get warp() { return state.warp; },
      get collected() { return state.collected; },
      get totalOrbs() { return orbs.length; },
      get ship() { return { x: ship.position.x, y: ship.position.y, z: ship.position.z }; },
      get camera() { return { z: camera.position.z, fov: camera.fov }; },
      get counts() { return { stars, planets: PLANETS.length, asteroids: 46, astronauts: 2, gates: gates.length, orbs: orbs.length, trail: TRAIL }; },
      get sceneChildren() { return scene.children.length; },
      get sceneObjects() { let n = 0; scene.traverse(() => { n += 1; }); return n; },
      get reduced() { return reduced; },
      get coarse() { return coarse; },
      get orbPositions() { return orbs.map((o) => ({ x: o.x, y: o.group.position.y, z: o.z, collected: o.collected })); },
      // usado apenas pela prévia para simular pilotagem/coleta
      setPointer(x, y) { state.pointer.set(x, y); state.lastPointerAt = performance.now() / 1000; },
      setProgress(p) { state.target = clamp(p, 0, 1); },
      // Diagnostico:congela o alvo sem que o listener de scroll o sobrescreva.
      hold(p) { window.removeEventListener('scroll', onScroll); state.target = clamp(p, 0, 1); },
      resume() { window.addEventListener('scroll', onScroll, { passive: true }); onScroll(); },
    };
    window.__duScene = debug;
  }

  setOrbes(0, orbs.length);
  setDistancia(0);
  setFase(0, document.getElementById('fase-00'));
  setProgresso(0);

  return {
    animated: true,

    update({ delta, time }) {
      if (state.disposed) return;
      const dt = Math.min(Math.max(delta, 0), 0.05);
      state.time += dt;
      const now = time;
      const step = Math.max(dt, 1e-4);

      // progresso do voo (scroll) com suavização
      state.progress += (state.target - state.progress) * (1 - Math.exp(-dt * (reduced ? 12 : 3.4 + speed * 6)));
      const camZ = 8 - state.progress * HO;
      const camSpeed = Math.abs(camZ - state.prevCamZ) / step;
      state.prevCamZ = camZ;
      state.warp += (Math.min(1, camSpeed / 140) - state.warp) * (1 - Math.exp(-dt * 6));

      const shipZ = camZ - SHIP_LAG;
      const idle = now - state.lastPointerAt > 3.5;
      let tx, ty;
      if (coarse || idle) {
        const auto = coarse ? nextOrb(shipZ) : null;
        if (auto) { tx = auto.x; ty = auto.y; }
        else { tx = Math.sin(now * 0.55) * 3.4; ty = Math.cos(now * 0.8) * 1.5; }
      } else {
        tx = state.pointer.x * 12;
        ty = -state.pointer.y * 7;
      }
      tx = clamp(tx, -6.5, 6.5);
      ty = clamp(ty, -3.6, 3.6);

      const px = ship.position.x;
      const py = ship.position.y - Math.sin((now - dt) * 1.6) * 0.06;
      const ease = 1 - Math.exp(-dt * (coarse ? 2.4 : 4.2));
      const nx = px + (tx - px) * ease;
      const ny = py + (ty - py) * ease;
      const vx = (nx - px) / step;
      const vy = (ny - py) / step;
      ship.position.set(nx, ny + Math.sin(now * 1.6) * 0.06, shipZ);
      ship.rotation.z = lerp(ship.rotation.z, clamp(-vx * 0.09, -0.65, 0.65), 0.15);
      ship.rotation.x = lerp(ship.rotation.x, clamp(vy * 0.05, -0.35, 0.35), 0.15);
      ship.rotation.y = lerp(ship.rotation.y, clamp(-vx * 0.025, -0.3, 0.3), 0.15);

      const flicker = 1 + Math.sin(now * 38) * 0.08 + Math.sin(now * 61) * 0.05;
      flameOuter.scale.set(1, (1 + state.warp * 1.3) * flicker, 1);
      flameInner.scale.set(1, (1 + state.warp * 1.6) * flicker, 1);

      camera.position.set(
        nx * 0.5 + (reduced ? 0 : Math.sin(now * 0.13) * 0.7),
        ny * 0.45 + 1.5 + (reduced ? 0 : Math.cos(now * 0.10) * 0.35),
        camZ
      );
      camera.lookAt(nx * 0.7, ny * 0.7 + 0.2, camZ - 30);
      const fov = 66 + state.warp * 16;
      if (Math.abs(fov - camera.fov) > 0.02) {
        camera.fov = fov;
        camera.updateProjectionMatrix();
      }

      // coleta: janela de 1 frame ao cruzar o orbe
      const zLo = Math.min(shipZ, state.prevShipZ) - 1.2;
      const zHi = Math.max(shipZ, state.prevShipZ) + 1.2;
      const radius = coarse ? 2.7 : 2;
      for (const orb of orbs) {
        if (orb.collected || orb.z < zLo || orb.z > zHi) continue;
        const dx = orb.x - nx;
        const dy = orb.group.position.y - ny;
        if (dx * dx + dy * dy < radius * radius) collect(orb);
      }
      state.prevShipZ = shipZ;

      // rotações e flutuação
      for (const s of spins) {
        s.obj.rotation.x += dt * s.rx;
        s.obj.rotation.y += dt * s.ry;
        s.obj.rotation.z += dt * s.rz;
        if (s.bob && !reduced) s.obj.position.y = (s.baseY || 0) + Math.sin(now * (s.bobSpeed || 1) + (s.phase || 0)) * s.bob;
      }
      for (const orb of orbs) {
        if (orb.collected) {
          const t = (state.time - orb.hitAt) / 0.28;
          if (t >= 1) orb.group.visible = false;
          else orb.group.scale.setScalar(Math.max(0.001, 1 - t));
          continue;
        }
        orb.group.rotation.y += dt * 1.7;
        orb.group.rotation.x += dt * 0.6;
        orb.group.position.y = orb.baseY + Math.sin(state.time * 1.4 + orb.phase) * 0.25;
        orb.halo.material.opacity = 0.65 + Math.sin(state.time * 3 + orb.phase) * 0.2;
      }
      for (let i = bursts.length - 1; i >= 0; i--) {
        const b = bursts[i];
        const t = (state.time - b.t0) / 0.6;
        if (t >= 1) {
          scene.remove(b.mesh);
          b.mesh.geometry.dispose();
          b.mesh.material.dispose();
          bursts.splice(i, 1);
        } else {
          b.mesh.scale.setScalar(1 + t * 5);
          b.mesh.material.opacity = 0.9 * (1 - t);
        }
      }
      for (const gate of gates) {
        if (!gate.passed && camZ < gate.z) { gate.passed = true; gate.t0 = state.time; }
        else if (gate.passed && camZ > gate.z + 3) gate.passed = false;
        const pulse = gate.passed ? Math.max(0, 1 - (state.time - gate.t0) / 1.1) : 0;
        gate.group.scale.setScalar(1 + pulse * 0.22);
        gate.group.rotation.z += dt * 0.12;
        gate.mats[0].opacity = 0.35 + pulse * 0.6;
        gate.mats[1].opacity = 0.22 + pulse * 0.4;
      }
      for (const n of nebulae) n.material.rotation += dt * 0.01;

      // trilha de partículas
      const ex = nx;
      const ey = ny + Math.sin(now * 1.6) * 0.06;
      const ez = shipZ + 1.7;
      for (let i = 0; i < TRAIL; i++) {
        trailAge[i] += dt;
        if (trailAge[i] > TRAIL_LIFE) {
          trailAge[i] = 0;
          trailPos[i * 3] = ex + (Math.random() - 0.5) * 0.18;
          trailPos[i * 3 + 1] = ey + (Math.random() - 0.5) * 0.18;
          trailPos[i * 3 + 2] = ez;
          trailVel[i * 3] = (Math.random() - 0.5) * 0.7;
          trailVel[i * 3 + 1] = (Math.random() - 0.5) * 0.7;
          trailVel[i * 3 + 2] = 5 + Math.random() * 3 + state.warp * 14;
        }
        trailPos[i * 3] += trailVel[i * 3] * dt;
        trailPos[i * 3 + 1] += trailVel[i * 3 + 1] * dt;
        trailPos[i * 3 + 2] += trailVel[i * 3 + 2] * dt;
        const a = Math.pow(1 - trailAge[i] / TRAIL_LIFE, 1.6);
        trailCol[i * 3] = 0.36 * a;
        trailCol[i * 3 + 1] = 0.78 * a;
        trailCol[i * 3 + 2] = 0.82 * a;
      }
      trailGeo.attributes.position.needsUpdate = true;
      trailGeo.attributes.color.needsUpdate = true;

      // HUD
      setDistancia(Math.round(state.progress * MOON_KM));
      setProgresso(state.progress);
      if (!state.readyFired) {
        state.readyFired = true;
        toast(themeText(toastEl, 'data-du-msg-inicio', 'Mova o mouse para pilotar · Role para voar'));
      }
    },

    resize() {
      // O runner já ajusta aspect/size; apenas garantimos near/far após resize.
      camera.near = 0.1;
      camera.far = 800;
    },

    dispose() {
      state.disposed = true;
      clearTimeout(toastTimer);
      window.removeEventListener('pointermove', onPointer);
      window.removeEventListener('scroll', onScroll);
      if (phaseObserver) phaseObserver.disconnect();
      textures.forEach((t) => t.dispose());
      if (debug && window.__duScene === debug) delete window.__duScene;
    },
  };
});
