/* Frente BO-r2 — diagnostico do runtime 3D.
   Cole no console do navegador (ou use o snippet integrado em DIAGNOSTICO-3D.md).
   Somente leitura: nao altera o DOM, nao faz requisicoes, nao escreve no servidor. */
(() => {
  const s = document.querySelector('[data-scene]');
  const res = performance.getEntriesByType('resource').map(r => r.name);
  const hit = p => res.filter(n => n.includes(p));
  const c = document.createElement('canvas');
  let gl = false;
  try { gl = !!(c.getContext('webgl2') || c.getContext('webgl')); } catch (e) {}
  const out = {
    slot: s ? {
      scene: s.dataset.scene,
      count: s.dataset.count,
      canvas: !!s.querySelector('canvas'),
      w: Math.round(s.getBoundingClientRect().width),
      h: Math.round(s.getBoundingClientRect().height)
    } : null,
    mainJs:   hit('three/main.js'),
    sceneJs:  hit('david-universe'),
    three:    hit('three.module.js'),
    modulos:  [...document.scripts].filter(x => x.type === 'module').map(x => x.src || '(inline)'),
    webgl:    gl,
    reduced:  matchMedia('(prefers-reduced-motion: reduce)').matches,
    erros404: res.filter(n => /david-universe|three\/main/.test(n))
  };
  console.log('[BO-r2] diagnostico 3D', out);
  return out;
})()
