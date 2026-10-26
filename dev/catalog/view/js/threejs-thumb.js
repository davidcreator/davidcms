/* ReamurCMS Three.js thumbnail runtime — declarative scenes only, no user JavaScript. */
(function () {
  'use strict';
  var scriptUrl = document.currentScript && document.currentScript.src;
  if (!scriptUrl || !window.IntersectionObserver || !window.ResizeObserver) return;
  var moduleUrl = new URL('./three.module.js', scriptUrl).href;
  var enginePromise = import(moduleUrl);
  var instances = new WeakMap();
  var geometries = {
    box: function (T) { return new T.BoxGeometry(1, 1, 1); },
    sphere: function (T) { return new T.SphereGeometry(0.68, 24, 16); },
    torus: function (T) { return new T.TorusGeometry(0.62, 0.22, 12, 36); },
    cone: function (T) { return new T.ConeGeometry(0.62, 1.15, 24); },
    cylinder: function (T) { return new T.CylinderGeometry(0.5, 0.5, 1, 24); },
    icosahedron: function (T) { return new T.IcosahedronGeometry(0.68, 1); },
    torusKnot: function (T) { return new T.TorusKnotGeometry(0.48, 0.16, 72, 8); }
  };
  function validScene(scene) {
    return scene && scene.version === 1 && Array.isArray(scene.objects) && scene.objects.length > 0 && scene.objects.length <= 12 &&
      Array.isArray(scene.lights) && scene.lights.length > 0 && scene.lights.length <= 4 && typeof scene.background === 'string' &&
      /^#[0-9a-f]{6}$/i.test(scene.background) && scene.camera && Array.isArray(scene.camera.position) && scene.camera.position.length === 3;
  }
  function dispose(instance) {
    if (!instance) return;
    if (instance.frame) cancelAnimationFrame(instance.frame);
    if (instance.resize) instance.resize.disconnect();
    if (instance.intersection) instance.intersection.disconnect();
    instance.scene.traverse(function (item) {
      if (item.geometry) item.geometry.dispose();
      if (item.material) (Array.isArray(item.material) ? item.material : [item.material]).forEach(function (material) { material.dispose(); });
    });
    instance.renderer.dispose();
    if (instance.renderer.domElement.parentNode) instance.renderer.domElement.remove();
  }
  function mount(element, T) {
    if (instances.has(element)) return;
    var sceneData;
    try { sceneData = JSON.parse(element.getAttribute('data-scene') || '{}'); } catch (_) { return; }
    if (!validScene(sceneData)) return;
    var width = Math.max(1, element.clientWidth), height = Math.max(1, element.clientHeight);
    var scene = new T.Scene(); scene.background = new T.Color(sceneData.background);
    if (sceneData.fog) scene.fog = new T.Fog(sceneData.fog.color, sceneData.fog.near, sceneData.fog.far);
    var camera = new T.PerspectiveCamera(sceneData.camera.fov, width / height, 0.1, 100);
    camera.position.set(sceneData.camera.position[0], sceneData.camera.position[1], sceneData.camera.position[2]);
    camera.lookAt(0, 0, 0);
    var renderer = new T.WebGLRenderer({ alpha: false, antialias: false, powerPreference: 'low-power', stencil: false, depth: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
    renderer.setSize(width, height, false);
    renderer.outputColorSpace = T.SRGBColorSpace;
    renderer.domElement.setAttribute('aria-hidden', 'true');
    renderer.domElement.className = 'rms-block-threejs-thumb__canvas';
    element.appendChild(renderer.domElement);
    var fallback = element.querySelector('.rms-block-threejs-thumb__fallback');
    if (fallback) fallback.hidden = true;
    sceneData.lights.forEach(function (item) {
      var light;
      if (item.type === 'ambient') light = new T.AmbientLight(item.color, item.intensity);
      else if (item.type === 'point') light = new T.PointLight(item.color, item.intensity, 0, 2);
      else light = new T.DirectionalLight(item.color, item.intensity);
      if (item.position) light.position.set(item.position[0], item.position[1], item.position[2]);
      scene.add(light);
    });
    var group = new T.Group(); scene.add(group);
    var animated = !!sceneData.auto_rotate;
    sceneData.objects.forEach(function (item) {
      var geometryFactory = geometries[item.type]; if (!geometryFactory) return;
      var material = new T.MeshStandardMaterial({ color: item.color, roughness: item.roughness === undefined ? 0.45 : item.roughness,
        metalness: item.metalness === undefined ? 0.1 : item.metalness, wireframe: !!item.wireframe });
      var mesh = new T.Mesh(geometryFactory(T), material);
      mesh.position.set(item.position[0], item.position[1], item.position[2]);
      mesh.rotation.set(item.rotation[0], item.rotation[1], item.rotation[2]);
      mesh.scale.setScalar(item.scale);
      mesh.userData.thumbAnimation = item.animation;
      mesh.userData.baseY = item.position[1]; mesh.userData.baseScale = item.scale;
      if (item.animation !== 'none') animated = true;
      group.add(mesh);
    });
    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reducedMotion) animated = false;
    var instance = { scene: scene, camera: camera, renderer: renderer, group: group, frame: 0, active: false, resize: null, intersection: null, lastTime: 0 };
    function draw(time) {
      if (!instance.active) return;
      var seconds = time * 0.001, delta = instance.lastTime ? Math.min((time - instance.lastTime) * 0.001, 0.05) : 0;
      instance.lastTime = time;
      if (animated && !reducedMotion) {
        if (sceneData.auto_rotate) group.rotation.y += delta * 0.22;
        group.children.forEach(function (mesh) {
          if (mesh.userData.thumbAnimation === 'spin') mesh.rotation.y += delta * 0.48;
          else if (mesh.userData.thumbAnimation === 'bob') mesh.position.y = mesh.userData.baseY + Math.sin(seconds * 1.5) * 0.12;
          else if (mesh.userData.thumbAnimation === 'pulse') mesh.scale.setScalar(mesh.userData.baseScale * (1 + Math.sin(seconds * 1.8) * 0.08));
        });
      }
      renderer.render(scene, camera);
      if (animated && !reducedMotion) instance.frame = requestAnimationFrame(draw);
    }
    function renderOnce() { if (instance.active) renderer.render(scene, camera); }
    instance.resize = new ResizeObserver(function () {
      var w = Math.max(1, element.clientWidth), h = Math.max(1, element.clientHeight);
      camera.aspect = w / h; camera.updateProjectionMatrix(); renderer.setSize(w, h, false); renderOnce();
    });
    instance.resize.observe(element);
    instance.intersection = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) { instance.active = false; if (instance.frame) cancelAnimationFrame(instance.frame); instance.frame = 0; instance.lastTime = 0; return; }
        if (instance.active) return;
        instance.active = true;
        if (animated && !reducedMotion) instance.frame = requestAnimationFrame(draw); else renderOnce();
      });
    }, { rootMargin: '100px' });
    instance.intersection.observe(element);
    element.dataset.threejsReady = 'true';
    instances.set(element, instance);
  }
  function scan(root, T) {
    if (root.nodeType === 1 && root.matches && root.matches('[data-threejs-thumb]')) mount(root, T);
    if (root.querySelectorAll) root.querySelectorAll('[data-threejs-thumb]').forEach(function (element) { mount(element, T); });
  }
  enginePromise.then(function (THREE) {
    scan(document, THREE);
    var observer = new MutationObserver(function (records) {
      records.forEach(function (record) {
        record.addedNodes.forEach(function (node) { if (node.nodeType === 1) scan(node, THREE); });
        record.removedNodes.forEach(function (node) {
          if (node.nodeType === 1) {
            if (instances.has(node)) { dispose(instances.get(node)); instances.delete(node); }
            if (node.querySelectorAll) node.querySelectorAll('[data-threejs-thumb]').forEach(function (element) { if (instances.has(element)) { dispose(instances.get(element)); instances.delete(element); } });
          }
        });
      });
    });
    if (document.documentElement) observer.observe(document.documentElement, { childList: true, subtree: true });
  }).catch(function () { /* Keep accessible text fallback when WebGL or the local module is unavailable. */ });
})();
