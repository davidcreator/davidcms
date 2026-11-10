<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Classe base de todo model (Arquitetura-MVCL §7).
 * Mesmo acesso mágico ao Registry dos controllers: $this->db, $this->config, ...
 *
 * @property \Reamur\System\Library\Db    $db
 * @property \Reamur\System\Engine\Config $config
 */
namespace Reamur\System\Engine;

class Model {
    public function __construct(protected Registry $registry) {}

    /** Acesso preguiçoso a qualquer serviço registrado */
    public function __get(string $key): mixed {
        return $this->registry->get($key);
    }

    /** Verifica se a tabela prefixada existe no banco atual (cacheada por requisição). */
    protected function hasTable(string $table): bool {
        static $cache = [];

        $full = DB_PREFIX . $table;
        if (array_key_exists($full, $cache)) {
            return $cache[$full];
        }

        $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($full) . "'");
        $cache[$full] = $query instanceof \stdClass && $query->num_rows > 0;

        return $cache[$full];
    }

    /** Resolve o language_id do idioma ativo (cacheado por código na requisição). */
    protected function currentLanguageId(): int {
        static $cache = [];

        $code = (string)$this->config->get('language_directory', 'en-us');
        if ($code === '') {
            $code = 'en-us';
        }

        if (isset($cache[$code])) {
            return $cache[$code];
        }

        $query = $this->db->query(
            "SELECT `language_id` FROM `" . DB_PREFIX . "language`
             WHERE `code` = '" . $this->db->escape($code) . "' LIMIT 1"
        );

        $cache[$code] = ($query instanceof \stdClass && !empty($query->row))
            ? (int)$query->row['language_id']
            : 1;

        return $cache[$code];
    }
}
