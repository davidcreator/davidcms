<?php
/**
 * F9-08 — leitura pública, estritamente filtrada, de valores custom em CMS/page.
 * Somente páginas publicadas + grupos ativos + fields visibility=public.
 */
namespace Reamur\Catalog\Model\Custom_field;

class Value extends \Reamur\System\Engine\Model {
    public function getPagePublicFields(int $page_id): array {
        $languageId=$this->currentLanguageId();
        $sql="SELECT f.`field_id`,f.`group_id`,f.`type`,f.`localized`,f.`options`,
                    COALESCE(NULLIF(fd.`label`,''),fd_en.`label`,f.`key`) AS `label`,
                    COALESCE(NULLIF(gd.`name`,''),gd_en.`name`,g.`name`) AS `group_label`,
                    COALESCE(NULLIF(v.`value_text`,''),v_en.`value_text`,'') AS `value_text`
               FROM `".$this->t('page')."` p
               INNER JOIN `".$this->t('custom_field')."` f ON f.`entity_type`='cms/page'
               INNER JOIN `".$this->t('custom_field_group')."` g ON g.`group_id`=f.`group_id` AND g.`entity_type`='cms/page' AND g.`status`='1'
          LEFT JOIN `".$this->t('custom_field_description')."` fd ON fd.`field_id`=f.`field_id` AND fd.`language_id`='".$languageId."'
          LEFT JOIN `".$this->t('custom_field_description')."` fd_en ON fd_en.`field_id`=f.`field_id` AND fd_en.`language_id`=1
          LEFT JOIN `".$this->t('custom_field_group_description')."` gd ON gd.`group_id`=g.`group_id` AND gd.`language_id`='".$languageId."'
          LEFT JOIN `".$this->t('custom_field_group_description')."` gd_en ON gd_en.`group_id`=g.`group_id` AND gd_en.`language_id`=1
          LEFT JOIN `".$this->t('custom_field_value')."` v ON v.`field_id`=f.`field_id` AND v.`entity_type`='cms/page' AND v.`entity_id`=p.`page_id` AND v.`language_id`=CASE WHEN f.`localized`=1 THEN '".$languageId."' ELSE 0 END
          LEFT JOIN `".$this->t('custom_field_value')."` v_en ON v_en.`field_id`=f.`field_id` AND v_en.`entity_type`='cms/page' AND v_en.`entity_id`=p.`page_id` AND v_en.`language_id`=1 AND f.`localized`=1
              WHERE p.`page_id`='".$page_id."' AND p.`status`='1' AND f.`visibility`='public'
                AND f.`type` IN ('text','textarea','number','boolean','url','select')
           ORDER BY g.`sort_order`,g.`group_id`,f.`sort_order`,f.`field_id`";
        $query=$this->db->query($sql);
        return ($query instanceof \stdClass && !empty($query->rows))?(array)$query->rows:[];
    }

    private function currentLanguageId(): int {
        $query=$this->db->query("SELECT `language_id` FROM `".$this->t('language')."` WHERE `code`='".$this->db->escape((string)$this->config->get('language_directory','en-us'))."' LIMIT 1");
        return ($query instanceof \stdClass && !empty($query->row))?(int)$query->row['language_id']:1;
    }
    private function t(string $table): string { return DB_PREFIX.$table; }
}
