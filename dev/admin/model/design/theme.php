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
        $config=$this->db->escape(json_encode($data['config'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?: '{}');
        if ($id) {
            $old=$this->getTheme($id); if (!$old) return 0;
            $this->db->query("INSERT INTO `".$this->table('design_theme_revision')."` SET `theme_id`='".$id."', `snapshot`='".$this->db->escape(json_encode($old, JSON_UNESCAPED_UNICODE) ?: '{}')."', `user_id`='".$userId."', `date_added`=NOW()");
            $this->db->query("UPDATE `".$this->table('design_theme')."` SET `name`='".$name."',`slug`='".$slug."',`config`='".$config."',`date_modified`=NOW() WHERE `theme_id`='".$id."'");
            return $id;
        }
        $this->db->query("INSERT INTO `".$this->table('design_theme')."` SET `name`='".$name."',`slug`='".$slug."',`config`='".$config."',`status`='0',`date_added`=NOW(),`date_modified`=NOW()");
        return (int)$this->db->getLastId();
    }
    public function activate(int $id, int $userId): bool {
        if (!$this->getTheme($id)) return false;
        $this->db->query("UPDATE `".$this->table('design_theme')."` SET `status`='0'");
        $this->db->query("UPDATE `".$this->table('design_theme')."` SET `status`='1',`date_modified`=NOW() WHERE `theme_id`='".$id."'");
        $theme=$this->getTheme($id);
        $this->db->query("INSERT INTO `".$this->table('design_theme_revision')."` SET `theme_id`='".$id."',`snapshot`='".$this->db->escape(json_encode($theme,JSON_UNESCAPED_UNICODE)?:'{}')."',`user_id`='".$userId."',`date_added`=NOW()");
        return true;
    }
    public function getRevisions(int $id): array {
        $q=$this->db->query("SELECT `revision_id`,`user_id`,`date_added` FROM `".$this->table('design_theme_revision')."` WHERE `theme_id`='".$id."' ORDER BY `revision_id` DESC LIMIT 50");
        return ($q instanceof \stdClass && !empty($q->rows)) ? (array)$q->rows : [];
    }
}
