<?php
/** Catalog's read-only view of the currently published design theme. */
namespace Reamur\Catalog\Model\Common;
class Theme extends \Reamur\System\Engine\Model {
    /** F13-E: slug => config publicado de todos os temas, para resolver a heranca. */
    public function getThemeMap(string $source = 'published'): array {
        $column = $source === 'draft' ? 'draft_config' : 'published_config';
        $q=$this->db->query("SELECT `slug`,`config`,`".$column."` FROM `".DB_PREFIX."design_theme`");
        if (!($q instanceof \stdClass) || empty($q->rows)) return [];
        $map=[];
        foreach ((array)$q->rows as $row) {
            $row=(array)$row; $slug=(string)($row['slug']??'');
            if ($slug==='' || preg_match('/^[a-z0-9_-]{1,48}$/D',$slug)!==1) continue;
            $raw=(string)($row[$column] ?: ($row['config']??'{}'));
            $cfg=json_decode($raw,true);
            if (is_array($cfg)) $map[$slug]=$cfg;
        }
        return $map;
    }

    public function getPublishedTheme(): array {
        $q=$this->db->query("SELECT `theme_id`,`config`,`published_config` FROM `".DB_PREFIX."design_theme` WHERE `status`='1' ORDER BY `theme_id` DESC LIMIT 1");
        if (!($q instanceof \stdClass) || empty($q->row)) return [];
        $row=(array)$q->row; $config=json_decode((string)($row['published_config'] ?: $row['config']),true);
        return is_array($config) ? $config : [];
    }
}
