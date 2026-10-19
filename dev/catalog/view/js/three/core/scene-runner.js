/**
 * SceneRunner — orquestra o ciclo de vida das cenas Three.js da página.
 * Baseado no esqueleto de docs/Implementacao-ThreeJS.md §4.
 *
 * Contratos:
 *  - Containers declaram data-scene="nome" + configs em data-* e window.*;
 *  - Lazy-init: a cena só nasce a 200px da viewport;
 *  - prefers-reduced-motion: cena não inicia (fallback do HTML permanece);
 *  - Pausa fora da viewport e com aba oculta; dispose no pagehide.
 */

export const SCENES = {}; // registry: { 'nome-da-cena': factoryFn }

export function registerScene(name, factory) {
  SCENES[name] = factory;
}

/** Inicializa as cenas declaradas na página (data-scene="nome"). */
export function boot() {
  const handles = [];

  document.querySelectorAll('[data-scene]').forEach((container) => {
    const name = container.dataset.scene;
    const factory = SCENES[name];

    if (!factory) {
      console.warn(`[three] cena não registrada: ${name}`);
      return;
    }

    // Respeita prefers-reduced-motion: mantém o fallback do HTML
    if (!window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
      return;
    }

    const io = new IntersectionObserver(async (entries, observer) => {
      const [entry] = entries;
      if (!entry.isIntersecting) return;
      observer.disconnect();

      let scene = null;
      try {
        scene = await factory(container, readConfig(container));
      } catch (err) {
        console.warn(`[three] falha ao iniciar cena ${name}:`, err);
      }
      if (!scene) return; // WebGL indisponível → fallback permanece

      handles.push(scene);
      watchVisibility(container, scene);
    }, { rootMargin: '200px' }); // pré-carrega 200px antes de aparecer

    io.observe(container);
  });

  // Anti-leak de navegação ida-e-volta (bfcache): libera tudo ao sair
  window.addEventListener('pagehide', () => {
    handles.forEach((h) => h.dispose());
  }, { once: true });
}

function readConfig(container) {
  return {
    ...container.dataset,
    ...(window[`${container.dataset.scene}Config`] ?? {}),
  };
}

function watchVisibility(container, scene) {
  new IntersectionObserver(([e]) => {
    e.isIntersecting ? scene.play() : scene.pause();
  }).observe(container);

  document.addEventListener('visibilitychange', () => {
    document.hidden ? scene.pause() : scene.play();
  });
}
