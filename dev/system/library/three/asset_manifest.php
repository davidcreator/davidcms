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
    /** @var array<string, string> */
    private const FILES = [
        'three.module.js' => 'runtime/three.module.js',
        'three.core.js' => 'runtime/three.core.js',
        'threejs-thumb.js' => 'runtime/threejs-thumb.js',
        'three/main.js' => 'runtime/three/main.js',
        'three/core/scene-runner.js' => 'runtime/three/core/scene-runner.js',
        'three/core/dom.js' => 'runtime/three/core/dom.js',
        'three/components/particle-field.js' => 'runtime/three/components/particle-field.js',
        'three/scenes/hero-particles.js' => 'runtime/three/scenes/hero-particles.js',
        'three/scenes/not-found.js' => 'runtime/three/scenes/not-found.js',
    ];

    /** Return the single public Catalog route prefix used by every consumer. */
    public function publicBasePath(): string {
        return 'three-runtime/';
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

        return [
            'path' => DIR_SYSTEM . 'library/three/' . self::FILES[$asset],
            'content_type' => 'application/javascript; charset=utf-8',
        ];
    }
}
