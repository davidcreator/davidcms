<?php
/** Theme administration persistence; data remains isolated from CMS pages and Blog. */
namespace Reamur\Admin\Model\Design;
class Theme extends \Reamur\System\Engine\Model {
    private function table(string $name): string { return DB_PREFIX . $name; }
    public function getThemes(): array {
        $q=$this->db->query("SELECT * FROM `".$this->table('design_theme')."` ORDER BY `name` ASC");
        return ($q instanceof \stdClass && !empty($q->rows)) ? (array)$q->rows : [];
    }
    public function getTheme(int $id): array {
        $q=$this->db->query("SELECT * FROM `".$this->table('design_theme')."` WHERE `theme_id`='".$id."' LIMIT 1");
        return ($q instanceof \stdClass && !empty($q->row)) ? (array)$q->row : [];
    }
    public function save(int $id, array $data, int $userId): int {
        $name=$this->db->escape($data['name']); $slug=$this->db->escape($data['slug']);
        $configJson=json_encode($data['config'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?: '{}';
        $config=$this->db->escape($configJson);
        if ($id) {
            $old=$this->getTheme($id); if (!$old) return 0;
            $this->db->query("INSERT INTO `".$this->table('design_theme_revision')."` SET `theme_id`='".$id."', `snapshot`='".$this->db->escape(json_encode($old, JSON_UNESCAPED_UNICODE) ?: '{}')."', `user_id`='".$userId."', `date_added`=NOW()");
            // F13-C: SALVAR e RASCUNHO. `config` e `published_config` sao o
            // payload publico (o Catalog usa `published_config ?: config`) e
            // nao podem ser contaminados por uma edicao nao publicada (ADR 0080).
            $this->db->query("UPDATE `".$this->table('design_theme')."` SET `name`='".$name."',`slug`='".$slug."',`draft_config`='".$config."',`date_modified`=NOW() WHERE `theme_id`='".$id."'");
            return $id;
        }
        $this->db->query("INSERT INTO `".$this->table('design_theme')."` SET `name`='".$name."',`slug`='".$slug."',`config`='".$config."',`draft_config`='".$config."',`published_config`='{}',`status`='0',`date_added`=NOW(),`date_modified`=NOW()");
        return (int)$this->db->getLastId();
    }
    public function activate(int $id, int $userId): bool {
        if (!$this->getTheme($id)) return false;
        $this->db->query("UPDATE `".$this->table('design_theme')."` SET `status`='0'");
        $this->db->query("UPDATE `".$this->table('design_theme')."` SET `status`='1',`published_config`=`draft_config`,`config`=`draft_config`,`date_modified`=NOW() WHERE `theme_id`='".$id."'");
        $theme=$this->getTheme($id);
        $this->db->query("INSERT INTO `".$this->table('design_theme_revision')."` SET `theme_id`='".$id."',`snapshot`='".$this->db->escape(json_encode($theme,JSON_UNESCAPED_UNICODE)?:'{}')."',`user_id`='".$userId."',`date_added`=NOW()");
        return true;
    }
    /** Frente BE: despublica sem apagar payload; registra revisao. Sem ALTER. */
    public function deactivate(int $id, int $userId): bool {
        $theme=$this->getTheme($id);
        if (!$theme || (int)($theme['status']??0)!==1) return false;
        $this->db->query("UPDATE `".$this->table('design_theme')."` SET `status`='0',`date_modified`=NOW() WHERE `theme_id`='".$id."'");
        $after=$this->getTheme($id);
        $this->db->query("INSERT INTO `".$this->table('design_theme_revision')."` SET `theme_id`='".$id."', `snapshot`='".$this->db->escape(json_encode($after,JSON_UNESCAPED_UNICODE)?:'{}')."', `user_id`='".$userId."', `date_added`=NOW()");
        return true;
    }
    /** Frente BE: exclusao definitiva. Fail-closed: nunca remove tema ativo.
     *  Remove tambem as revisoes para nao deixar orfaos. Sem ALTER/migration. */
    public function delete(int $id): bool {
        $theme=$this->getTheme($id);
        if (!$theme || (int)($theme['status']??0)===1) return false;
        $this->db->query("DELETE FROM `".$this->table('design_theme_revision')."` WHERE `theme_id`='".$id."'");
        $this->db->query("DELETE FROM `".$this->table('design_theme')."` WHERE `theme_id`='".$id."'");
        return true;
    }

    public function restoreRevision(int $themeId, int $revisionId, int $userId): bool {
        $q=$this->db->query("SELECT `snapshot` FROM `".$this->table('design_theme_revision')."` WHERE `theme_id`='".$themeId."' AND `revision_id`='".$revisionId."' LIMIT 1");
        if (!($q instanceof \stdClass) || empty($q->row['snapshot'])) return false;
        $snapshot=json_decode((string)$q->row['snapshot'],true);
        if (!is_array($snapshot) || empty($snapshot['theme_id']) || (int)$snapshot['theme_id']!==$themeId) return false;
        $current=$this->getTheme($themeId);
        if (!$current) return false;
        $this->db->query("INSERT INTO `".$this->table('design_theme_revision')."` SET `theme_id`='".$themeId."',`snapshot`='".$this->db->escape(json_encode($current,JSON_UNESCAPED_UNICODE)?:'{}')."',`user_id`='".$userId."',`date_added`=NOW()");
        $draft=(string)($snapshot['draft_config']??$snapshot['config']??'{}');
        $published=(int)($current['status']??0) ? (string)($snapshot['published_config']??$draft) : (string)($current['published_config']??'{}');
        $name=$this->db->escape((string)($snapshot['name']??$current['name']));
        $slug=$this->db->escape((string)($snapshot['slug']??$current['slug']));
        $this->db->query("UPDATE `".$this->table('design_theme')."` SET `name`='".$name."',`slug`='".$slug."',`config`='".$this->db->escape($draft)."',`draft_config`='".$this->db->escape($draft)."',`published_config`='".$this->db->escape($published)."',`date_modified`=NOW() WHERE `theme_id`='".$themeId."'");
        return true;
    }

    /** F13-E: slug => config de todos os temas (draft ou published). */
    public function getThemeMap(string $source = 'draft'): array {
        $column = $source === 'published' ? 'published_config' : 'draft_config';
        $q=$this->db->query("SELECT `slug`,`config`,`".$column."` FROM `".$this->table('design_theme')."`");
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

    /** F13-C: payload explicitamente publicado, com fallback legado seguro. */
    public function getPublishedConfig(int $id): array {
        $theme=$this->getTheme($id);
        if (!$theme) return [];
        $raw=(string)($theme['published_config']??'');
        if ($raw==='' || $raw==='{}') $raw=(string)($theme['config']??'{}');
        $config=json_decode($raw,true);
        return is_array($config)?$config:[];
    }
    /** F13-C: ultimo rascunho salvo (usado quando o POST da previa e invalido). */
    public function getDraftConfig(int $id): array {
        $theme=$this->getTheme($id);
        if (!$theme) return [];
        $config=json_decode((string)($theme['draft_config']??$theme['config']??'{}'),true);
        return is_array($config)?$config:[];
    }

    public function getRevisions(int $id): array {
        $q=$this->db->query("SELECT `revision_id`,`user_id`,`date_added` FROM `".$this->table('design_theme_revision')."` WHERE `theme_id`='".$id."' ORDER BY `revision_id` DESC LIMIT 50");
        return ($q instanceof \stdClass && !empty($q->rows)) ? (array)$q->rows : [];
    }
}
