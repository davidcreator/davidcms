<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Adaptador Twig — engine padrão oficial (Guia-Twig §1).
 * Resolve {rota}.twig em DIR_TEMPLATE (com subpasta de tema via config
 * template_directory, se existir). Cache compilado: storage/cache/template/.
 */
namespace Reamur\System\Library\Template;

final class Twig {
    private \Twig\Environment $twig;

    public function __construct(string $directory = '', bool $cache = false) {
        $paths = [DIR_TEMPLATE];

        if ($directory !== '' && is_dir(DIR_TEMPLATE . $directory)) {
            array_unshift($paths, DIR_TEMPLATE . rtrim($directory, '/') . '/');
        }

        $cacheDir = DIR_STORAGE . 'cache/template/';

        if ($cache && !is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $this->twig = new \Twig\Environment(
            new \Twig\Loader\FilesystemLoader($paths),
            [
                'autoescape'  => 'html',
                'cache'       => $cache ? $cacheDir : false,
                'auto_reload' => true,
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $route, array $data = []): string {
        return $this->twig->render($route . '.twig', $data);
    }
}
