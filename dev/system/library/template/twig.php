<?php
namespace Reamur\System\Library\Template;

/**
 * Class Twig
 * Provides Twig template engine integration with custom namespace handling
 */
class Twig {
    protected string $root;
    protected object $loader;
    protected string $directory;
    protected array $path = [];

    /**
     * Constructor
     * Initializes Twig filesystem loader with root directory
     */
    public function __construct() {
        $this->root = rtrim(str_replace('\\', '/', DIR_REAMUR), '/');
        $this->loader = new \Twig\Loader\FilesystemLoader('/', $this->root);
    }

    /**
     * Add template path namespace
     * @param string $namespace Path namespace
     * @param string $directory Directory path (optional)
     * @throws \InvalidArgumentException If namespace is empty
     */
    public function addPath(string $namespace, string $directory = ''): void {
        if (empty($namespace)) {
            throw new \InvalidArgumentException('Namespace cannot be empty');
        }

        $source = '';

        if (!$directory) {
            $this->directory = $namespace;
            $path = $this->normalizeLoaderPath($namespace);
            $source = $namespace;
        } else {
            $this->path[$namespace] = $directory;
            $path = $this->normalizeLoaderPath($directory);
            $source = $directory;
        }

        if ($path !== '' && is_dir($source)) {
            $this->loader->addPath($path);
        }
    }

    /**
     * Normalize an absolute template directory to the loader root.
     */
    protected function normalizeLoaderPath(string $path): string {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with($path, $this->root . '/')) {
            return substr($path, strlen($this->root) + 1);
        }

        if ($path === $this->root) {
            return '/';
        }

        return ltrim($path, '/');
    }

    /**
     * Render template
     * @param string $filename Template filename
     * @param array $data Template variables
     * @param string $code Optional template code to render instead of file
     * @return string Rendered template
     * @throws \RuntimeException If template cannot be loaded or rendered
     */
    public function render(string $filename, array $data = [], string $code = ''): string {
        if (empty($filename)) {
            throw new \InvalidArgumentException('Filename cannot be empty');
        }

        $file = $this->directory . $filename . '.twig';
        $namespace = '';

        $parts = explode('/', $filename);
        foreach ($parts as $part) {
            $namespace = $namespace ? $namespace . '/' . $part : $part;

            if (isset($this->path[$namespace])) {
                $file = $this->path[$namespace] . substr($filename, strlen($namespace) + 1) . '.twig';
            }
        }

        $file = substr($file, strlen($this->root) + 1);

        try {
            $loader = $code ? new \Twig\Loader\ArrayLoader([$file => $code]) : $this->loader;
            
            $config = [
                'charset'     => 'utf-8',
                'autoescape'  => false,
                'debug'       => false,
                'auto_reload' => true,
                'cache'       => new TwigCache(
                    DIR_CACHE . 'template/',
                    \Twig\Cache\FilesystemCache::FORCE_BYTECODE_INVALIDATION
                )
            ];

            $twig = new \Twig\Environment($loader, $config);
            return $twig->render($file, $data);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to render template: ' . $e->getMessage());
        }
    }
}
