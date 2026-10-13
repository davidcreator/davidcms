<?php
namespace Reamur\System\Library\Template;

/**
 * Class Template
 *
 * Sistema de templates multi-extensão com suporte a:
 * .twig, .blade, .phtml, .tpl, .php, .mustache, .hbs, .latte
 *
 * @package Reamur\System\Library\Template
 */
class Template {
    protected string $directory = '';
    protected array $path = [];
    
    /**
     * Extensões de template suportadas (em ordem de prioridade)
     */
    protected const SUPPORTED_EXTENSIONS = [
        'twig', 'blade', 'phtml', 'tpl', 'php', 'mustache', 'hbs', 'latte'
    ];
    
    /**
     * Cache de arquivos resolvidos para melhor performance
     */
    protected static array $resolvedPathCache = [];

    /**
     * Add template path
     *
     * @param string $namespace Namespace for the template path
     * @param string $directory Directory path (optional)
     * @throws \InvalidArgumentException If directory is invalid
     */
    public function addPath(string $namespace, string $directory = ''): void {
        if (!$directory) {
            if (!is_dir($namespace)) {
                throw new \InvalidArgumentException("Invalid template directory: $namespace");
            }
            $this->directory = $this->normalizePath($namespace);
        } else {
            if (!is_dir($directory)) {
                throw new \InvalidArgumentException("Invalid template directory: $directory");
            }
            $this->path[$namespace] = $this->normalizePath($directory);
        }
        
        // Limpa o cache quando um novo caminho é adicionado
        static::$resolvedPathCache = [];
    }

    /**
     * Render template with data
     *
     * @param string $filename Template filename
     * @param array $data Data to pass to template
     * @param string $code Direct template code (optional)
     * @return string Rendered output
     * @throws \RuntimeException If template cannot be loaded
     */
    public function render(string $filename, array $data = [], string $code = ''): string {
        if (!$code) {
            $file = $this->resolveTemplatePath($filename);
            
            if (!is_file($file)) {
                throw new \RuntimeException("Template file not found: $filename");
            }
            
            $code = file_get_contents($file);
            if ($code === false) {
                throw new \RuntimeException("Failed to read template: $filename");
            }
        }

        if (empty($code)) {
            return '';
        }

        ob_start();
        try {
            // Previne sobrescrita de variáveis existentes
            extract($data, EXTR_SKIP);
            
            $compiledTemplate = $this->compile($filename, $code);
            
            // Validação adicional do arquivo compilado
            if (!is_file($compiledTemplate)) {
                throw new \RuntimeException("Compiled template not found: $compiledTemplate");
            }
            
            include $compiledTemplate;
            return ob_get_clean() ?: '';
            
        } catch (\Throwable $e) {
            ob_end_clean();
            
            // Log do erro para debugging
            $this->logError("Template rendering failed for '$filename': " . $e->getMessage());
            
            throw new \RuntimeException(
                "Template rendering failed for '$filename': " . $e->getMessage(), 
                $e->getCode(), 
                $e
            );
        }
    }

    /**
     * Resolve template path com suporte a múltiplas extensões
     *
     * @param string $filename Template filename
     * @return string Full template path
     * @throws \RuntimeException If template file not found
     */
    protected function resolveTemplatePath(string $filename): string {
        // Verifica cache primeiro
        $cacheKey = $filename . '|' . serialize(array_keys($this->path)) . '|' . $this->directory;
        if (isset(static::$resolvedPathCache[$cacheKey])) {
            return static::$resolvedPathCache[$cacheKey];
        }

        // Se já tem extensão, tenta encontrar diretamente
        if ($this->hasFileExtension($filename)) {
            $file = $this->findTemplateFile($filename);
            if ($file) {
                return static::$resolvedPathCache[$cacheKey] = $file;
            }
        }

        // Busca por extensões suportadas
        foreach (self::SUPPORTED_EXTENSIONS as $extension) {
            $filenameWithExt = $filename . '.' . $extension;
            $file = $this->findTemplateFile($filenameWithExt);
            
            if ($file) {
                return static::$resolvedPathCache[$cacheKey] = $file;
            }
        }

        throw new \RuntimeException("Template not found: $filename (searched extensions: " . implode(', ', self::SUPPORTED_EXTENSIONS) . ")");
    }

    /**
     * Encontra arquivo de template nos caminhos configurados
     *
     * @param string $filename Nome do arquivo com extensão
     * @return string|null Caminho completo ou null se não encontrado
     */
    protected function findTemplateFile(string $filename): ?string {
        // Verifica no diretório principal
        $file = $this->directory . $filename;
        if (is_file($file)) {
            return $file;
        }

        // Verifica nos namespaces configurados
        $namespace = '';
        $parts = explode('/', $filename);

        foreach ($parts as $part) {
            $namespace = $namespace ? $namespace . '/' . $part : $part;
            
            if (isset($this->path[$namespace])) {
                $remainingPath = substr($filename, strlen($namespace) + 1);
                $file = $this->path[$namespace] . $remainingPath;
                
                if (is_file($file)) {
                    return $file;
                }
            }
        }

        return null;
    }

    /**
     * Compile template code
     *
     * @param string $filename Template filename
     * @param string $code Template code
     * @return string Path to compiled template
     * @throws \RuntimeException If compilation fails
     */
    protected function compile(string $filename, string $code): string {
        $cacheDir = $this->getCacheDirectory();
        
        // Hash mais específico incluindo modificações recentes
        $hash = hash('sha256', $filename . $code . filemtime($this->resolveTemplatePath($filename)));
        $file = $cacheDir . $hash . '.php';

        // Verifica se precisa recompilar
        if (!is_file($file) || $this->needsRecompile($file, $filename)) {
            $success = file_put_contents($file, $code, LOCK_EX);
            
            if ($success === false) {
                throw new \RuntimeException("Failed to write compiled template: $file");
            }
            
            // Verifica se o arquivo foi criado corretamente
            if (!is_file($file)) {
                throw new \RuntimeException("Compiled template file was not created: $file");
            }
        }

        return $file;
    }

    /**
     * Obtém o diretório de cache, criando se necessário
     *
     * @return string Caminho do diretório de cache
     * @throws \RuntimeException Se não conseguir criar o diretório
     */
    protected function getCacheDirectory(): string {
        $cacheDir = DIR_CACHE . 'template/';
        
        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true)) {
                throw new \RuntimeException("Failed to create cache directory: $cacheDir");
            }
            
            // Verifica se o diretório foi criado
            if (!is_dir($cacheDir)) {
                throw new \RuntimeException("Cache directory was not created: $cacheDir");
            }
        }
        
        // Verifica permissões de escrita
        if (!is_writable($cacheDir)) {
            throw new \RuntimeException("Cache directory is not writable: $cacheDir");
        }

        return $cacheDir;
    }

    /**
     * Verifica se o arquivo precisa ser recompilado
     *
     * @param string $compiledFile Arquivo compilado
     * @param string $sourceFile Arquivo fonte
     * @return bool True se precisa recompilar
     */
    protected function needsRecompile(string $compiledFile, string $sourceFile): bool {
        if (!is_file($compiledFile)) {
            return true;
        }

        try {
            $sourcePath = $this->resolveTemplatePath($sourceFile);
            return filemtime($sourcePath) > filemtime($compiledFile);
        } catch (\RuntimeException $e) {
            return true;
        }
    }

    /**
     * Normaliza caminho removendo barras extras e adicionando barra final
     *
     * @param string $path Caminho a normalizar
     * @return string Caminho normalizado
     */
    protected function normalizePath(string $path): string {
        return rtrim(str_replace('\\', '/', $path), '/') . '/';
    }

    /**
     * Verifica se o filename já possui uma extensão
     *
     * @param string $filename Nome do arquivo
     * @return bool True se possui extensão
     */
    protected function hasFileExtension(string $filename): bool {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, self::SUPPORTED_EXTENSIONS, true);
    }

    /**
     * Log de erros (implementação básica)
     *
     * @param string $message Mensagem de erro
     */
    protected function logError(string $message): void {
        // Implementação básica de log
        // Pode ser substituída por um sistema de log mais robusto
        if (defined('DIR_LOGS')) {
            $logFile = DIR_LOGS . 'template_errors.log';
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[$timestamp] $message" . PHP_EOL;
            
            @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Limpa o cache de templates
     *
     * @param string|null $pattern Padrão opcional para limpar arquivos específicos
     * @return bool True se conseguiu limpar
     */
    public function clearCache(?string $pattern = null): bool {
        try {
            $cacheDir = $this->getCacheDirectory();
            $pattern = $pattern ?: '*.php';
            
            $files = glob($cacheDir . $pattern);
            if ($files === false) {
                return false;
            }
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            
            // Limpa também o cache de paths resolvidos
            static::$resolvedPathCache = [];
            
            return true;
        } catch (\Exception $e) {
            $this->logError("Failed to clear cache: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém informações sobre as extensões suportadas
     *
     * @return array Lista de extensões suportadas
     */
    public function getSupportedExtensions(): array {
        return self::SUPPORTED_EXTENSIONS;
    }

    /**
     * Verifica se uma extensão é suportada
     *
     * @param string $extension Extensão a verificar (sem o ponto)
     * @return bool True se suportada
     */
    public function isExtensionSupported(string $extension): bool {
        return in_array(strtolower($extension), self::SUPPORTED_EXTENSIONS, true);
    }
}