<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Allowlisted public assets for the single self-hosted Three.js runtime.
 * Files remain under system/ (not web-readable); Catalog exposes only this map.
 */
namespace Reamur\System\Library\Three;

final class AssetManifest {
    /** Allowlisted name of the optional Hero logo model (Cena 2). */
    private const HERO_MODEL = 'three/models/logo-da.glb';

    /** @var array<string, string> */
    private const FILES = [
        'three.module.js' => 'runtime/three.module.js',
        'three.core.js' => 'runtime/three.core.js',
        'threejs-thumb.js' => 'runtime/threejs-thumb.js',
        'three/main.js' => 'runtime/three/main.js',
        'three/core/scene-runner.js' => 'runtime/three/core/scene-runner.js',
        'three/core/dom.js' => 'runtime/three/core/dom.js',
        'three/thumbnail-runner.js' => 'runtime/three/thumbnail-runner.js',
        'three/components/particle-field.js' => 'runtime/three/components/particle-field.js',
        'three/components/studio-lighting.js' => 'runtime/three/components/studio-lighting.js',
        'three/scenes/hero-particles.js' => 'runtime/three/scenes/hero-particles.js',
        'three/scenes/not-found.js' => 'runtime/three/scenes/not-found.js',
        'three/scenes/portfolio-3d.js' => 'runtime/three/scenes/portfolio-3d.js',
        'three/addons/loaders/GLTFLoader.js' => 'runtime/three/addons/loaders/GLTFLoader.js',
        'three/addons/utils/BufferGeometryUtils.js' => 'runtime/three/addons/utils/BufferGeometryUtils.js',
        'three/addons/utils/SkeletonUtils.js' => 'runtime/three/addons/utils/SkeletonUtils.js',
        self::HERO_MODEL => 'runtime/three/models/logo-da.glb',
    ];

    /** MIME type per allowed extension; any other extension is not publishable. */
    private const TYPES = [
        'js' => 'application/javascript; charset=utf-8',
        'glb' => 'model/gltf-binary',
    ];

    /** Return the single public Catalog route prefix used by every consumer. */
    public function publicBasePath(): string {
        return 'three-runtime/';
    }

    /** Relative public URL of the Hero logo model; null when not published. */
    public function heroModelUrl(): ?string {
        return isset(self::FILES[self::HERO_MODEL])
            ? $this->publicBasePath() . self::HERO_MODEL
            : null;
    }

    /**
     * Resolve only a known runtime asset; caller must still verify readability.
     *
     * @return array{path:string, content_type:string}|null
     */
    public function resolve(string $asset): ?array {
        if (!isset(self::FILES[$asset])) {
            return null;
        }

        $extension = strtolower(pathinfo($asset, PATHINFO_EXTENSION));

        if (!isset(self::TYPES[$extension])) {
            return null;
        }

        return [
            'path' => DIR_SYSTEM . 'library/three/' . self::FILES[$asset],
            'content_type' => self::TYPES[$extension],
        ];
    }
}
