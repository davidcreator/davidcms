<?php
namespace Reamur\System\Library\Cache;

use Exception;
use Memcache;

/**
 * Class Mem - Wrapper para cache Memcache
 * Classe para gerenciamento de cache usando Memcache com melhorias de performance e confiabilidade
 * 
 * @package Reamur\System\Library\Cache
 * @author Sistema MVCL baseado em OpenCart
 */
class Mem {
    /** @var Memcache|null Instância do Memcache */
    private ?Memcache $memcache = null;

    /** @var int Tempo padrão de expiração em segundos */
    private int $expire;

    /** @var bool Status da conexão com o Memcache */
    private bool $connected = false;

    /** @var int Limite para dump do cache */
    private const CACHEDUMP_LIMIT = 9999;

    /** @var int Timeout para conexão em segundos */
    private const CONNECTION_TIMEOUT = 1;

    /** @var int Número máximo de tentativas de reconexão */
    private const MAX_RETRY_ATTEMPTS = 3;

    /**
     * Constructor - Inicializa a conexão com Memcache
     * 
     * @param int $expire Tempo de expiração padrão em segundos (padrão: 3600 = 1 hora)
     * @throws Exception Quando não conseguir conectar ao Memcache
     */
    public function __construct(int $expire = 3600) {
        $this->expire = $expire;
        $this->initializeMemcache();
    }

    /**
     * Inicializa a conexão com o Memcache
     * 
     * @throws Exception Quando constantes não estão definidas ou conexão falha
     */
    private function initializeMemcache(): void {
        if (!defined('CACHE_HOSTNAME') || !defined('CACHE_PORT') || !defined('CACHE_PREFIX')) {
            throw new Exception('Constantes de cache não definidas (CACHE_HOSTNAME, CACHE_PORT, CACHE_PREFIX)');
        }

        if (!class_exists('Memcache')) {
            throw new Exception('Extensão Memcache não está instalada');
        }

        $this->memcache = new Memcache();
        
        // Configurar timeout de conexão
        $this->memcache->setConnectTimeout(self::CONNECTION_TIMEOUT * 1000); // em milissegundos

        $this->connect();
    }

    /**
     * Estabelece conexão com o servidor Memcache com retry
     * 
     * @throws Exception Quando não conseguir conectar após todas as tentativas
     */
    private function connect(): void {
        $attempts = 0;
        
        while ($attempts < self::MAX_RETRY_ATTEMPTS && !$this->connected) {
            $attempts++;
            
            try {
                $this->connected = $this->memcache->pconnect(CACHE_HOSTNAME, CACHE_PORT);
                
                if (!$this->connected) {
                    throw new Exception("Falha na conexão persistente - tentativa {$attempts}");
                }
            } catch (Exception $e) {
                if ($attempts >= self::MAX_RETRY_ATTEMPTS) {
                    throw new Exception("Não foi possível conectar ao Memcache após {$attempts} tentativas: " . $e->getMessage());
                }
                
                // Aguardar antes da próxima tentativa
                usleep(100000); // 100ms
            }
        }
    }

    /**
     * Verifica se a conexão está ativa e reconecta se necessário
     * 
     * @return bool Status da conexão
     */
    private function ensureConnection(): bool {
        if (!$this->connected || !$this->memcache) {
            try {
                $this->connect();
            } catch (Exception $e) {
                error_log("Erro ao reconectar Memcache: " . $e->getMessage());
                return false;
            }
        }
        return $this->connected;
    }

    /**
     * Recupera um valor do cache
     * 
     * @param string $key Chave do cache
     * @return mixed|null Valor armazenado ou null se não encontrado/erro
     */
    public function get(string $key): mixed {
        if (empty($key)) {
            return null;
        }

        if (!$this->ensureConnection()) {
            return null;
        }

        try {
            $result = $this->memcache->get(CACHE_PREFIX . $key);
            return $result === false ? null : $result;
        } catch (Exception $e) {
            error_log("Erro ao recuperar cache '{$key}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Armazena um valor no cache
     * 
     * @param string $key Chave do cache
     * @param mixed $value Valor a ser armazenado
     * @param int $expire Tempo de expiração (0 = usar padrão da classe)
     * @return bool Sucesso da operação
     */
    public function set(string $key, mixed $value, int $expire = 0): bool {
        if (empty($key)) {
            return false;
        }

        if (!$this->ensureConnection()) {
            return false;
        }

        if ($expire <= 0) {
            $expire = $this->expire;
        }

        try {
            return $this->memcache->set(
                CACHE_PREFIX . $key, 
                $value, 
                MEMCACHE_COMPRESSED, 
                $expire
            );
        } catch (Exception $e) {
            error_log("Erro ao armazenar cache '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove um valor do cache
     * 
     * @param string $key Chave do cache
     * @return bool Sucesso da operação
     */
    public function delete(string $key): bool {
        if (empty($key)) {
            return false;
        }

        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            return $this->memcache->delete(CACHE_PREFIX . $key);
        } catch (Exception $e) {
            error_log("Erro ao deletar cache '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Limpa todo o cache
     * 
     * @return bool Sucesso da operação
     */
    public function flush(): bool {
        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            return $this->memcache->flush();
        } catch (Exception $e) {
            error_log("Erro ao limpar cache: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica se uma chave existe no cache
     * 
     * @param string $key Chave do cache
     * @return bool True se a chave existe
     */
    public function exists(string $key): bool {
        if (empty($key)) {
            return false;
        }

        return $this->get($key) !== null;
    }

    /**
     * Obtém estatísticas do servidor Memcache
     * 
     * @return array|null Estatísticas ou null em caso de erro
     */
    public function getStats(): ?array {
        if (!$this->ensureConnection()) {
            return null;
        }

        try {
            return $this->memcache->getStats();
        } catch (Exception $e) {
            error_log("Erro ao obter estatísticas do cache: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtém informações sobre a versão do servidor
     * 
     * @return string|null Versão do servidor ou null em caso de erro
     */
    public function getVersion(): ?string {
        if (!$this->ensureConnection()) {
            return null;
        }

        try {
            return $this->memcache->getVersion();
        } catch (Exception $e) {
            error_log("Erro ao obter versão do servidor: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verifica se a conexão está ativa
     * 
     * @return bool Status da conexão
     */
    public function isConnected(): bool {
        return $this->connected && $this->memcache !== null;
    }

    /**
     * Fecha a conexão com o Memcache
     */
    public function close(): void {
        if ($this->memcache && $this->connected) {
            try {
                $this->memcache->close();
            } catch (Exception $e) {
                error_log("Erro ao fechar conexão Memcache: " . $e->getMessage());
            } finally {
                $this->connected = false;
                $this->memcache = null;
            }
        }
    }

    /**
     * Destructor - Garante que a conexão seja fechada
     */
    public function __destruct() {
        $this->close();
    }
}