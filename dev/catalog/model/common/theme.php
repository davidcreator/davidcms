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

    /**
     * F13-H: publicado + rascunho do tema ATIVO numa unica query, somente
     * leitura. Nenhuma coluna nova, migration, cron ou escrita: a decisao de
     * rollout e feita em runtime pelo startup/theme.
     * @return array{published:array,draft:array,slug:string}
     */
    public function getEffectiveTheme(): array {
        $q=$this->db->query("SELECT `theme_id`,`slug`,`config`,`published_config`,`draft_config` FROM `".DB_PREFIX."design_theme` WHERE `status`='1' ORDER BY `theme_id` DESC LIMIT 1");
        if (!($q instanceof \stdClass) || empty($q->row)) return ['published'=>[],'draft'=>[],'slug'=>''];
        $row=(array)$q->row;
        $decode=static function($primary,$fallback): array {
            $cfg=json_decode((string)($primary ?: $fallback),true);
            return is_array($cfg) ? $cfg : [];
        };
        return [
            'published'=>$decode($row['published_config']??'', $row['config']??'{}'),
            'draft'=>$decode($row['draft_config']??'', $row['config']??'{}'),
            'slug'=>(string)($row['slug']??''),
        ];
    }

    public function getPublishedTheme(): array {
        $q=$this->db->query("SELECT `theme_id`,`config`,`published_config` FROM `".DB_PREFIX."design_theme` WHERE `status`='1' ORDER BY `theme_id` DESC LIMIT 1");
        if (!($q instanceof \stdClass) || empty($q->row)) return [];
        $row=(array)$q->row; $config=json_decode((string)($row['published_config'] ?: $row['config']),true);
        return is_array($config) ? $config : [];
    }
}
