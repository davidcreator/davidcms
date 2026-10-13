<?php
namespace Reamur\System\Library\Session;

/**
 * Class File - Session handler using filesystem storage
 * 
 * @package Reamur\System\Library\Session
 */
class File {
    private object $config;
    private string $sessionPath;

    /**
     * Constructor
     *
     * @param object $registry
     * @throws \InvalidArgumentException If session directory is invalid
     */
    public function __construct(\Reamur\System\Engine\Registry $registry) {
        $this->config = $registry->get('config');
        $this->sessionPath = rtrim(DIR_SESSION, '\\/') . DIRECTORY_SEPARATOR;
        
        if (!is_dir($this->sessionPath) || !is_writable($this->sessionPath)) {
            throw new \InvalidArgumentException('Session directory is invalid or not writable');
        }
    }

    /**
     * Read session data
     *
     * @param string $session_id
     * @return array
     * @throws \RuntimeException If session file cannot be read
     */
    public function read(string $session_id): array {
        $file = $this->getSessionFile($session_id);

        if (is_file($file)) {
            $data = file_get_contents($file);
            if ($data === false) {
                throw new \RuntimeException('Failed to read session file');
            }
            
            $result = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid session data format');
            }
            
            return $result ?? [];
        }
        
        return [];
    }

    /**
     * Write session data
     *
     * @param string $session_id
     * @param array $data
     * @return bool
     * @throws \RuntimeException If session cannot be written
     */
    public function write(string $session_id, array $data): bool {
        $file = $this->getSessionFile($session_id);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        if ($json === false) {
            throw new \RuntimeException('Failed to encode session data');
        }
        
        $result = file_put_contents($file, $json, LOCK_EX);
        if ($result === false) {
            throw new \RuntimeException('Failed to write session file');
        }
        
        return true;
    }

    /**
     * Destroy session
     *
     * @param string $session_id
     * @return void
     */
    public function destroy(string $session_id): void {
        $file = $this->getSessionFile($session_id);

        if (is_file($file)) {
            @unlink($file);
        }
    }
    
    /**
     * Garbage collection
     *
     * @return void
     */
    public function gc(): void {
        if (random_int(1, $this->config->get('session_divisor')) <= $this->config->get('session_probability')) {
            $expire = time() - (int)$this->config->get('session_expire');
            $files = glob($this->sessionPath . 'sess_*');

            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $expire) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Get full path to session file
     *
     * @param string $session_id
     * @return string
     * @throws \InvalidArgumentException If session ID is invalid
     */
    private function getSessionFile(string $session_id): string {
        if (!preg_match('/^[a-zA-Z0-9,-]{22,256}$/', $session_id)) {
            throw new \InvalidArgumentException('Invalid session ID format');
        }
        
        return $this->sessionPath . 'sess_' . basename($session_id);
    }
}
