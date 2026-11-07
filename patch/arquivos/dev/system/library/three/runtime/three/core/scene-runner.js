/**
 * SceneRunner — única camada WebGL para cenas nativas e thumbs declarativas.
 * Cenas fornecem apenas construção/atualização; este módulo possui renderer,
 * câmera, lazy-init, resize, animação, pausa e descarte.
 */
import * as THREE from '../../three.module.js';

export { THREE };
export const SCENES = Object.create(null);

export function registerScene(name, factory) {
  if (typeof name === 'string' && name && typeof factory === 'function') SCENES[name] = factory;
}

/** Monta uma cena usando o ciclo de vida compartilhado do engine. */
export function mountScene(container, factory, config = {}, options = {}) {
  const reducedMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  if (reducedMotion && options.skipReducedMotion) return { play() {}, pause() {}, resize() {}, dispose() {} };
  if (!window.IntersectionObserver || !window.ResizeObserver) return { play() {}, pause() {}, resize() {}, dispose() {} };

  let scene = null;
  let camera = null;
  let renderer = null;
  let sceneHandle = null;
  let intersection = null;
  let resizeObserver = null;
  let animationFrame = 0;
  let active = false;
  let visible = false;
  let disposed = false;
  let ready = false;
  let lastTime = 0;

  function renderOnce() {
    if (!renderer || !scene || !camera || !active || disposed) return;
    renderer.render(scene, camera);
  }

  function pause() {
    active = false;
    lastTime = 0;
    if (animationFrame) cancelAnimationFrame(animationFrame);
    animationFrame = 0;
  }

  function tick(now) {
    animationFrame = 0;
    if (!active || disposed || !renderer || !scene || !camera) return;
    const delta = lastTime ? Math.min((now - lastTime) / 1000, 0.05) : 0;
    lastTime = now;
    try {
      if (sceneHandle && typeof sceneHandle.update === 'function') {
        sceneHandle.update({ THREE, scene, camera, renderer, container, config, time: now / 1000, delta });
      }
      renderer.render(scene, camera);
    } catch (error) {
      fail(error);
      return;
    }
    if (sceneHandle && sceneHandle.animated && !reducedMotion) animationFrame = requestAnimationFrame(tick);
  }

  function play() {
    if (disposed) return;
    active = true;
    if (sceneHandle && sceneHandle.animated && !reducedMotion) {
      if (!animationFrame) animationFrame = requestAnimationFrame(tick);
    } else {
      tick(performance.now());
    }
  }

  function resize() {
    if (!renderer || !camera || disposed) return;
    const width = Math.max(1, container.clientWidth);
    const height = Math.max(1, container.clientHeight);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height, false);
    renderOnce();
  }

  function releaseResources() {
    if (sceneHandle && typeof sceneHandle.dispose === 'function') sceneHandle.dispose();
    if (scene) {
      scene.traverse((object) => {
        if (object.geometry && typeof object.geometry.dispose === 'function') object.geometry.dispose();
        if (object.material) {
          const materials = Array.isArray(object.material) ? object.material : [object.material];
          materials.forEach((material) => { if (material && typeof material.dispose === 'function') material.dispose(); });
        }
      });
    }
    if (renderer) {
      renderer.dispose();
      if (renderer.domElement.parentNode) renderer.domElement.remove();
    }
    sceneHandle = null;
    renderer = null;
    camera = null;
    scene = null;
  }

  function dispose() {
    if (disposed) return;
    disposed = true;
    pause();
    if (intersection) intersection.disconnect();
    if (resizeObserver) resizeObserver.disconnect();
    document.removeEventListener('visibilitychange', onVisibilityChange);
    window.removeEventListener('pagehide', dispose);
    releaseResources();
  }

  function fail(error) {
    if (options.onError) options.onError(error);
    dispose();
  }

  function initialize() {
    if (renderer || disposed) return;
    try {
      const width = Math.max(1, container.clientWidth);
      const height = Math.max(1, container.clientHeight);
      renderer = new THREE.WebGLRenderer({
        alpha: options.alpha !== undefined ? !!options.alpha : true,
        antialias: options.antialias !== undefined ? !!options.antialias : true,
        powerPreference: options.powerPreference || 'low-power',
        stencil: false,
        depth: true,
      });
      renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, options.maxPixelRatio || 2));
      renderer.setSize(width, height, false);
      renderer.outputColorSpace = THREE.SRGBColorSpace;
      renderer.domElement.setAttribute('aria-hidden', 'true');
      if (options.canvasClass) renderer.domElement.className = options.canvasClass;

      scene = new THREE.Scene();
      const background = config.background || options.background;
      if (background) scene.background = new THREE.Color(background);
      if (config.fog && config.fog.color) scene.fog = new THREE.Fog(config.fog.color, config.fog.near, config.fog.far);

      const cameraConfig = config.camera || {};
      const fov = Number(cameraConfig.fov || options.fov || 50);
      camera = new THREE.PerspectiveCamera(fov, width / height, 0.1, 100);
      const position = cameraConfig.position || options.cameraPosition || [0, 0, 4];
      camera.position.set(Number(position[0]) || 0, Number(position[1]) || 0, Number(position[2]) || 0);
      camera.lookAt(0, 0, 0);

      sceneHandle = factory({ THREE, scene, camera, renderer, container, config });
      if (!sceneHandle) throw new Error('three_scene_factory_failed');
      container.appendChild(renderer.domElement);
      if (typeof sceneHandle.resize === 'function') sceneHandle.resize();
      tick(performance.now());
      if (!ready) {
        ready = true;
        if (options.onReady) options.onReady();
      }
    } catch (error) {
      fail(error);
    }
  }

  function onVisibilityChange() {
    if (document.hidden) pause();
    else if (visible) play();
  }

  resizeObserver = new ResizeObserver(resize);
  resizeObserver.observe(container);
  intersection = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      visible = entry.isIntersecting;
      if (!visible) {
        pause();
        return;
      }
      if (!renderer) initialize();
      if (renderer && !document.hidden) play();
    });
  }, { rootMargin: options.rootMargin || '200px' });
  intersection.observe(container);
  document.addEventListener('visibilitychange', onVisibilityChange);
  window.addEventListener('pagehide', dispose, { once: true });

  return { play, pause, resize, dispose };
}

/** Inicializa as cenas nativas declaradas por data-scene. */
export function boot(root = document) {
  const handles = [];
  root.querySelectorAll('[data-scene]').forEach((container) => {
    const name = container.dataset.scene;
    const factory = SCENES[name];
    if (!factory) return;
    const config = { ...container.dataset, ...(window[`${name}Config`] ?? {}) };
    const handle = mountScene(container, factory, config, { alpha: true, antialias: true, maxPixelRatio: 2, skipReducedMotion: true, rootMargin: '200px' });
    handles.push(handle);
  });
  window.addEventListener('pagehide', () => handles.forEach((handle) => handle.dispose()), { once: true });
}
