/**
 * Campo de partículas reutilizável.
 * Esqueleto de docs/Implementacao-ThreeJS.md §5 (components/particle-field.js).
 */
import * as THREE from 'three';

export function createParticleField({ count = 400, color = '#8a4fff', spread = 12 } = {}) {
  const positions = new Float32Array(count * 3);
  for (let i = 0; i < count * 3; i++) {
    positions[i] = (Math.random() - 0.5) * spread;
  }

  const geometry = new THREE.BufferGeometry();
  geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));

  const material = new THREE.PointsMaterial({
    color: new THREE.Color(color),
    size: 0.035,
    transparent: true,
    opacity: 0.8,
  });

  const points = new THREE.Points(geometry, material);

  return {
    points,
    dispose: () => {
      geometry.dispose();
      material.dispose();
    },
  };
}
