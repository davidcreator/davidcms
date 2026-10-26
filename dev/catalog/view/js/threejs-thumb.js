/* ReamurCMS Three.js thumbnail runtime — optional user code runs only in an opaque sandboxed iframe. */
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
  var moduleDataUrlPromise = null;
  function encodeBase64(text) {
    var bytes = new TextEncoder().encode(text), binary = '', chunk = 0x8000;
    for (var i = 0; i < bytes.length; i += chunk) binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
    return btoa(binary);
  }
  function decodeBase64(text) {
    var binary = atob(text), bytes = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return new TextDecoder().decode(bytes);
  }
  function moduleDataUrl() {
    if (!moduleDataUrlPromise) {
      var coreUrl = new URL('./three.core.js', scriptUrl).href;
      moduleDataUrlPromise = Promise.all([fetch(moduleUrl, { credentials:'same-origin' }), fetch(coreUrl, { credentials:'same-origin' })]).then(function (responses) {
        if (!responses[0].ok || !responses[1].ok) throw new Error('three_module_fetch_failed');
        return Promise.all([responses[0].text(), responses[1].text()]);
      }).then(function (sources) {
        var coreData = 'data:text/javascript;base64,' + encodeBase64(sources[1]);
        var moduleSource = sources[0].replace(/from ['\"]\.\/three\.core\.js['\"]/g, 'from \"three-core\"');
        if (moduleSource === sources[0]) throw new Error('three_core_import_not_found');
        return { core:coreData, module:'data:text/javascript;base64,' + encodeBase64(moduleSource) };
      });
    }
    return moduleDataUrlPromise;
  }
  function safeScriptLiteral(value) { return JSON.stringify(value).replace(/</g, '\\u003c'); }
  function sandboxDocument(sceneData, userScript, moduleData) {
    var sceneLiteral = safeScriptLiteral(sceneData), scriptLiteral = safeScriptLiteral(userScript), moduleLiteral = safeScriptLiteral(moduleData.module), coreLiteral = safeScriptLiteral(moduleData.core);
    var policy = "default-src 'none'; script-src 'unsafe-inline' 'unsafe-eval' data:; style-src 'unsafe-inline'; img-src data:; font-src data:; connect-src 'none'; media-src 'none'; object-src 'none'; frame-src 'none'; worker-src 'none'; form-action 'none'; base-uri 'none'";
    return '<!doctype html><html><head><meta charset=\"utf-8\"><meta http-equiv=\"Content-Security-Policy\" content=\"' + policy + '\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><style>html,body{margin:0;width:100%;height:100%;overflow:hidden;background:#101827}canvas{display:block;width:100%;height:100%}#error{position:absolute;inset:auto 0 0;padding:6px;background:#5a1010;color:#fff;font:12px/1.4 sans-serif;white-space:pre-wrap}</style></head><body><canvas id=\"stage\"></canvas><div id=\"error\" hidden></div><script type=\"importmap\">{\"imports\":{\"three-core\":' + coreLiteral + '}}</script><script type=\"module\">import * as THREE from ' + moduleLiteral + ';const config=' + sceneLiteral + ';const source=' + scriptLiteral + ';const canvas=document.getElementById(\'stage\');const error=document.getElementById(\'error\');let active=true,frame=0,userFrame=null,last=0;function fail(e){error.hidden=false;error.textContent=String(e&&e.message||e).slice(0,500)}try{const scene=new THREE.Scene();scene.background=new THREE.Color(config.background);if(config.fog)scene.fog=new THREE.Fog(config.fog.color,config.fog.near,config.fog.far);const camera=new THREE.PerspectiveCamera(config.camera.fov,1,0.1,100);camera.position.set(...config.camera.position);camera.lookAt(0,0,0);const renderer=new THREE.WebGLRenderer({canvas:canvas,alpha:false,antialias:false,powerPreference:\'low-power\',stencil:false});renderer.setPixelRatio(Math.min(devicePixelRatio||1,1.5));renderer.outputColorSpace=THREE.SRGBColorSpace;function resize(){const w=Math.max(1,innerWidth),h=Math.max(1,innerHeight);camera.aspect=w/h;camera.updateProjectionMatrix();renderer.setSize(w,h,false)}resize();addEventListener(\'resize\',resize);config.lights.forEach(l=>{let x=l.type===\'ambient\'?new THREE.AmbientLight(l.color,l.intensity):l.type===\'point\'?new THREE.PointLight(l.color,l.intensity,0,2):new THREE.DirectionalLight(l.color,l.intensity);if(l.position)x.position.set(...l.position);scene.add(x)});const meshes=[];config.objects.forEach(o=>{const g={box:()=>new THREE.BoxGeometry(1,1,1),sphere:()=>new THREE.SphereGeometry(.68,24,16),torus:()=>new THREE.TorusGeometry(.62,.22,12,36),cone:()=>new THREE.ConeGeometry(.62,1.15,24),cylinder:()=>new THREE.CylinderGeometry(.5,.5,1,24),icosahedron:()=>new THREE.IcosahedronGeometry(.68,1),torusKnot:()=>new THREE.TorusKnotGeometry(.48,.16,72,8)}[o.type]();const m=new THREE.Mesh(g,new THREE.MeshStandardMaterial({color:o.color,roughness:o.roughness===undefined?.45:o.roughness,metalness:o.metalness===undefined?.1:o.metalness,wireframe:!!o.wireframe}));m.position.set(...o.position);m.rotation.set(...o.rotation);m.scale.setScalar(o.scale);m.userData.thumbAnimation=o.animation;m.userData.baseY=o.position[1];m.userData.baseScale=o.scale;scene.add(m);meshes.push(m)});const onFrame=fn=>{if(typeof fn===\'function\')userFrame=fn};new Function(\'THREE\',\'scene\',\'camera\',\'renderer\',\'canvas\',\'onFrame\',source)(THREE,scene,camera,renderer,canvas,onFrame);function draw(t){if(!active)return;const sec=t*.001,delta=last?Math.min((t-last)*.001,.05):0;last=t;if(userFrame)userFrame({time:sec,delta:delta,THREE:THREE,scene:scene,camera:camera,renderer:renderer,canvas:canvas});else{if(config.auto_rotate)scene.rotation.y+=delta*.22;meshes.forEach(m=>{if(m.userData.thumbAnimation===\'spin\')m.rotation.y+=delta*.48;else if(m.userData.thumbAnimation===\'bob\')m.position.y=m.userData.baseY+Math.sin(sec*1.5)*.12;else if(m.userData.thumbAnimation===\'pulse\')m.scale.setScalar(m.userData.baseScale*(1+Math.sin(sec*1.8)*.08))})}renderer.render(scene,camera);if(userFrame||config.auto_rotate||meshes.some(m=>m.userData.thumbAnimation!==\'none\'))frame=requestAnimationFrame(draw)}renderer.render(scene,camera);if(userFrame||config.auto_rotate||meshes.some(m=>m.userData.thumbAnimation!==\'none\'))frame=requestAnimationFrame(draw);addEventListener(\'message\',e=>{if(e.source!==parent)return;if(e.data===\'pause\'){active=false;if(frame)cancelAnimationFrame(frame);frame=0;last=0}else if(e.data===\'resume\'&&!active){active=true;if(frame)cancelAnimationFrame(frame);frame=requestAnimationFrame(draw)}})}catch(e){fail(e)}</script></body></html>';
  }
  function mountSandbox(element, sceneData, userScript) {
    var fallback = element.querySelector('.rms-block-threejs-thumb__fallback');
    var frame = document.createElement('iframe'); frame.className='rms-block-threejs-thumb__sandbox';
    frame.setAttribute('sandbox','allow-scripts'); frame.setAttribute('referrerpolicy','no-referrer');
    frame.setAttribute('title',element.getAttribute('aria-label')||'Interactive 3D scene'); frame.setAttribute('aria-label',element.getAttribute('aria-label')||'Interactive 3D scene');
    element.appendChild(frame);
    var instance={frameElement:frame,frame:0,active:false,resize:null,intersection:null}; instances.set(element,instance);
    instance.intersection=new IntersectionObserver(function(entries){entries.forEach(function(entry){
      if(!entry.isIntersecting){instance.active=false;if(frame.contentWindow)frame.contentWindow.postMessage('pause','*');return;}
      instance.active=true;
      if(frame.dataset.loaded==='true'){if(frame.contentWindow)frame.contentWindow.postMessage('resume','*');return;}
      frame.dataset.loaded='true';
      moduleDataUrl().then(function(moduleData){
        if(!frame.isConnected)return;
        frame.srcdoc=sandboxDocument(sceneData,userScript,moduleData);
        frame.addEventListener('load',function(){if(frame.contentWindow)frame.contentWindow.postMessage(instance.active?'resume':'pause','*');if(fallback)fallback.hidden=true},{once:true});
      }).catch(function(){frame.remove();if(fallback)fallback.hidden=false;});
    })},{rootMargin:'100px'});
    instance.intersection.observe(element); element.dataset.threejsReady='sandbox';
  }
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
    if (instance.frameElement) { if (instance.frameElement.contentWindow) instance.frameElement.contentWindow.postMessage('pause','*'); instance.frameElement.remove(); return; }
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
    var encodedScript = element.getAttribute('data-custom-js') || '';
    if (encodedScript) {
      try { var customScript = decodeBase64(encodedScript); if (customScript.trim()) { mountSandbox(element, sceneData, customScript); return; } } catch (_) { return; }
    }
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
