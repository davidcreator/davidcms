/**
 * Rig de luz de estúdio reutilizável (Guia §6 — Cena 2, logo GLB).
 * O modelo do glTF chega como MeshStandardMaterial e só é visível com luz:
 * key frontal, fill na cor da marca e rim atrás para separar do fundo.
 */
import * as THREE from 'three';

export function createStudioLighting({ color = '#8a4fff' } = {}) {
  const group = new THREE.Group();
  const key = new THREE.DirectionalLight('#ffffff', 2.2);
  const fill = new THREE.DirectionalLight(color, 1.1);
  const rim = new THREE.DirectionalLight('#ffffff', 1.6);

  key.position.set(2.5, 3, 4);
  fill.position.set(-4, -1, 2);
  rim.position.set(0, 2, -5);
  group.add(key, fill, rim);

  return {
    group,
    dispose() {
      key.dispose();
      fill.dispose();
      rim.dispose();
    },
  };
}
