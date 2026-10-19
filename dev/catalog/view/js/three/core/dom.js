/**
 * Helpers de DOM para cenas Three.js.
 * Esqueleto de docs/Implementacao-ThreeJS.md §4 (core/dom.js).
 */

export function clearFallback(container) {
  container.querySelectorAll('[data-fallback]').forEach((el) => el.remove());
}

export function intAttr(container, attr, fallback) {
  const v = parseInt(container.dataset[attr], 10);
  return Number.isFinite(v) ? v : fallback;
}

export function floatAttr(container, attr, fallback) {
  const v = parseFloat(container.dataset[attr]);
  return Number.isFinite(v) ? v : fallback;
}
