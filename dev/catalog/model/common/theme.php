<?php
/** Catalog's read-only view of the currently published design theme. */
namespace Reamur\Catalog\Model\Common;
class Theme extends \Reamur\System\Engine\Model {
    public function getPublishedTheme(): array {
        $q=$this->db->query("SELECT `theme_id`,`config`,`published_config` FROM `".DB_PREFIX."design_theme` WHERE `status`='1' ORDER BY `theme_id` DESC LIMIT 1");
        if (!($q instanceof \stdClass) || empty($q->row)) return [];
        $row=(array)$q->row; $config=json_decode((string)($row['published_config'] ?: $row['config']),true);
        return is_array($config) ? $config : [];
    }
}
