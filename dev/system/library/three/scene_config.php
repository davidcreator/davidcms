<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * ReamurCMS — centralized, allowlisted Three.js scene settings.
 * No executable scene code is stored here: only bounded values consumed by
 * the self-hosted Catalog scene modules.
 */
namespace Reamur\System\Library\Three;

final class SceneConfig {
    private const DEFAULTS = [
        'three_hero_enabled' => '1',
        'three_hero_particle_count' => '400',
        'three_hero_color' => '#8a4fff',
        'three_hero_speed' => '0.05',
        'three_not_found_enabled' => '1',
    ];

    /**
     * Return safe values for admin/catalog with per-key fallback to defaults.
     *
     * @param array<string, mixed> $settings Persisted settings map.
     * @return array<string, string>
     */
    public function normalizeSettings(array $settings): array {
        $out = self::DEFAULTS;
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $settings)) $out[$key] = trim((string)$settings[$key]);
        }
        foreach (['three_hero_enabled', 'three_not_found_enabled'] as $key) {
            if (!in_array($out[$key], ['0', '1'], true)) $out[$key] = self::DEFAULTS[$key];
        }
        $count = filter_var($out['three_hero_particle_count'], FILTER_VALIDATE_INT);
        if ($count === false || $count < 50 || $count > 800) $out['three_hero_particle_count'] = self::DEFAULTS['three_hero_particle_count'];
        if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $out['three_hero_color'])) $out['three_hero_color'] = self::DEFAULTS['three_hero_color'];
        if (!is_numeric($out['three_hero_speed']) || (float)$out['three_hero_speed'] < 0.01 || (float)$out['three_hero_speed'] > 0.20) {
            $out['three_hero_speed'] = self::DEFAULTS['three_hero_speed'];
        } else {
            $out['three_hero_speed'] = number_format((float)$out['three_hero_speed'], 2, '.', '');
        }
        return $out;
    }

    /**
     * Validate an admin POST and return field => stable error code.
     *
     * @param array<string, mixed> $input Submitted configuration.
     * @return array<string, string>
     */
    public function validateSettings(array $input): array {
        $errors = [];
        foreach (['three_hero_enabled', 'three_not_found_enabled'] as $key) {
            if (!in_array((string)($input[$key] ?? ''), ['0', '1'], true)) $errors[$key] = 'toggle_invalid';
        }
        $count = filter_var($input['three_hero_particle_count'] ?? null, FILTER_VALIDATE_INT);
        if ($count === false || $count < 50 || $count > 800) $errors['three_hero_particle_count'] = 'count_out_of_range';
        if (!is_string($input['three_hero_color'] ?? null) || !preg_match('/^#[0-9a-fA-F]{6}$/D', $input['three_hero_color'])) $errors['three_hero_color'] = 'color_invalid';
        $speed = $input['three_hero_speed'] ?? null;
        if (!is_numeric($speed) || (float)$speed < 0.01 || (float)$speed > 0.20 || abs((float)$speed * 100 - round((float)$speed * 100)) > 0.000001) {
            $errors['three_hero_speed'] = 'speed_out_of_range';
        }
        return $errors;
    }

    /**
     * Build typed, bounded configuration for the public scene controllers.
     *
     * @param array<string, mixed> $settings Persisted settings map.
     * @return array{hero_enabled:bool,hero:array{count:int,color:string,speed:float},not_found_enabled:bool,not_found:array{count:int,color:string,speed:float,drift:float}}
     */
    public function runtime(array $settings): array {
        $values = self::normalizeSettings($settings);
        return [
            'hero_enabled' => $values['three_hero_enabled'] === '1',
            'hero' => [
                'count' => (int)$values['three_hero_particle_count'],
                'color' => $values['three_hero_color'],
                'speed' => (float)$values['three_hero_speed'],
            ],
            'not_found_enabled' => $values['three_not_found_enabled'] === '1',
            'not_found' => [
                'count' => 120,
                'color' => $values['three_hero_color'],
                'speed' => (float)$values['three_hero_speed'],
                'drift' => 0.4,
            ],
        ];
    }
}
