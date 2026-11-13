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
 */
namespace Reamur\System\Engine;

/**
 * @property \Reamur\System\Library\Db    $db
 * @property \Reamur\System\Engine\Config $config
 * @property \Reamur\System\Library\Security\SecretBox $secret_box
 */
class Model {
    public function __construct(protected Registry $registry) {}

    /** Acesso preguiçoso a qualquer serviço registrado */
    public function __get(string $key): mixed {
        return $this->registry->get($key);
    }

    /**
     * Cifra dos segredos do banco (ADR 0094). Num Registry sem o serviço
     * (testes, instalador) devolve uma instância sem chave: grava e lê como antes.
     */
    protected function secrets(): \Reamur\System\Library\Security\SecretBox {
        $box = $this->registry->get('secret_box');

        return $box instanceof \Reamur\System\Library\Security\SecretBox
            ? $box
            : new \Reamur\System\Library\Security\SecretBox();
    }

    /** Prefixa o nome lógico da tabela com DB_PREFIX. */
    protected function t(string $table): string {
        return (defined('DB_PREFIX') ? DB_PREFIX : 'rms_') . $table;
    }

    /** Verifica se a tabela prefixada existe no banco atual (cacheada por requisição). */
    protected function hasTable(string $table): bool {
        static $cache = [];

        $full = $this->t($table);
        if (array_key_exists($full, $cache)) {
            return $cache[$full];
        }

        $db = $this->registry->get('db');
        if (!$db) {
            return false;
        }

        $query = $db->query("SHOW TABLES LIKE '" . $db->escape($full) . "'");
        $cache[$full] = $query instanceof \stdClass && $query->num_rows > 0;

        return $cache[$full];
    }

    /** Resolve o language_id do idioma ativo (cacheado por código na requisição). */
    protected function currentLanguageId(): int {
        static $cache = [];

        $config = $this->registry->get('config');
        $code = $config ? (string)$config->get('language_directory', 'en-us') : 'en-us';
        if ($code === '') {
            $code = 'en-us';
        }

        if (isset($cache[$code])) {
            return $cache[$code];
        }

        $db = $this->registry->get('db');
        if (!$db) {
            return 1;
        }

        $query = $db->query(
            "SELECT `language_id` FROM `" . $this->t('language') . "`
             WHERE `code` = '" . $db->escape($code) . "' LIMIT 1"
        );

        $cache[$code] = ($query instanceof \stdClass && !empty($query->row))
            ? (int)$query->row['language_id']
            : 1;

        return $cache[$code];
    }
}
