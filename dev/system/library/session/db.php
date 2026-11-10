<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Driver de sessão em banco de dados (tabela rms_session).
 * Implementa \SessionHandlerInterface para compatibilidade com session_set_save_handler().
 */
namespace Reamur\System\Library\Session;

class DB implements \SessionHandlerInterface {
    private \Reamur\System\Library\Db $db;
    private \Reamur\System\Engine\Config $config;

    public function __construct(\Reamur\System\Engine\Registry $registry) {
        $db     = $registry->get('db');
        $config = $registry->get('config');

        if (!$db instanceof \Reamur\System\Library\Db || !$config instanceof \Reamur\System\Engine\Config) {
            throw new \RuntimeException('Session\\DB requires db and config services in Registry');
        }

        $this->db     = $db;
        $this->config = $config;
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'rms_';
        $query  = $this->db->query(
            "SELECT `data` FROM `" . $prefix . "session` "
            . "WHERE `session_id` = '" . $this->db->escape($id) . "' "
            . "AND `expire` > '" . $this->db->escape(gmdate('Y-m-d H:i:s')) . "' LIMIT 1"
        );

        if ($query instanceof \stdClass && isset($query->row['data'])) {
            return (string)$query->row['data'];
        }

        return '';
    }

    public function write(string $id, string $data): bool {
        if ($id === '') {
            return false;
        }

        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'rms_';
        $ttl    = max(1, (int)$this->config->get('session_expire', 3600));

        return (bool)$this->db->query(
            "REPLACE INTO `" . $prefix . "session` SET "
            . "`session_id` = '" . $this->db->escape($id) . "', "
            . "`data` = '" . $this->db->escape($data) . "', "
            . "`expire` = '" . $this->db->escape(gmdate('Y-m-d H:i:s', time() + $ttl)) . "'"
        );
    }

    public function destroy(string $id): bool {
        if ($id === '') {
            return false;
        }

        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'rms_';

        return (bool)$this->db->query(
            "DELETE FROM `" . $prefix . "session` "
            . "WHERE `session_id` = '" . $this->db->escape($id) . "'"
        );
    }

    public function gc(int $max_lifetime): int|false {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'rms_';
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(0, $max_lifetime));

        $this->db->query(
            "DELETE FROM `" . $prefix . "session` "
            . "WHERE `expire` < '" . $this->db->escape($cutoff) . "'"
        );

        return $this->db->countAffected();
    }
}
