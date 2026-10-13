<?php
/*
CREATE TABLE IF NOT EXISTS `session` (
  `session_id` varchar(32) NOT NULL,
  `data` text NOT NULL,
  `expire` datetime NOT NULL,
  PRIMARY KEY (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/
namespace Reamur\System\Library\Session;

/**
 * Class DB
 * Database-based session handler
 */
class DB {
    /** @var \Reamur\System\Library\DB\Driver\* */
    private object $db;
    
    /** @var \Reamur\System\Library\Config */
    private object $config;

    /**
     * Constructor
     * @param \Reamur\System\Engine\Registry $registry
     */
    public function __construct(\Reamur\System\Engine\Registry $registry) {
        $this->db = $registry->get('db');
        $this->config = $registry->get('config');
    }

    /**
     * Read session data
     * @param string $session_id
     * @return array
     * @throws \RuntimeException If JSON decode fails
     */
    public function read(string $session_id): array {
        $query = $this->db->query(
            "SELECT `data` FROM `" . DB_PREFIX . "session` " .
            "WHERE `session_id` = '" . $this->db->escape($session_id) . "' " .
            "AND `expire` > '" . $this->db->escape(gmdate('Y-m-d H:i:s')) . "'"
        );

        if ($query->num_rows) {
            $data = json_decode($query->row['data'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Failed to decode session data: ' . json_last_error_msg());
            }
            return (array)$data;
        }
        
        return [];
    }
    
    /**
     * Write session data
     * @param string $session_id
     * @param array $data
     * @return bool
     * @throws \RuntimeException If session ID is empty or JSON encode fails
     */
    public function write(string $session_id, array $data): bool {
        if (empty($session_id)) {
            throw new \RuntimeException('Session ID cannot be empty');
        }

        $json = json_encode($data);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode session data: ' . json_last_error_msg());
        }

        $this->db->query(
            "REPLACE INTO `" . DB_PREFIX . "session` SET " .
            "`session_id` = '" . $this->db->escape($session_id) . "', " .
            "`data` = '" . $this->db->escape($json) . "', " .
            "`expire` = '" . $this->db->escape(gmdate('Y-m-d H:i:s', time() + (int)$this->config->get('session_expire'))) . "'"
        );

        return true;
    }

    /**
     * Destroy session
     * @param string $session_id
     * @return bool
     * @throws \RuntimeException If session ID is empty
     */
    public function destroy(string $session_id): bool {
        if (empty($session_id)) {
            throw new \RuntimeException('Session ID cannot be empty');
        }

        $this->db->query(
            "DELETE FROM `" . DB_PREFIX . "session` " .
            "WHERE `session_id` = '" . $this->db->escape($session_id) . "'"
        );

        return true;
    }

    /**
     * Garbage collection
     * @return bool
     */
    public function gc(): bool {
        $probability = (int)$this->config->get('session_probability');
        $divisor = (int)$this->config->get('session_divisor');
        
        if ($probability > 0 && $divisor > 0 && random_int(1, $divisor) <= $probability) {
            $this->db->query(
                "DELETE FROM `" . DB_PREFIX . "session` " .
                "WHERE `expire` < '" . $this->db->escape(gmdate('Y-m-d H:i:s', time())) . "'"
            );
        }

        return true;
    }
}

