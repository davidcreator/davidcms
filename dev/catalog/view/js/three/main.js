/**
 * Entrypoint Three.js — registra as cenas da página e inicializa o boot.
 * docs/Implementacao-ThreeJS.md §5 (main.js). Módulo carregado com `defer`
 * apenas em páginas com has_3d (ver common/header.twig + ADR 0020).
 */
import { boot } from './core/scene-runner.js';
import './scenes/hero-particles.js'; // auto-registro via registerScene()

boot();
