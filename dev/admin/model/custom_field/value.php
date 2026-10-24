<?php
/**
 * F9-07 — adapter de valores de campos personalizados para cms/page (admin only).
 * A saída pública do catálogo não é alterada por este model.
 */
namespace Reamur\Admin\Model\Custom_field;

class Value extends \Reamur\System\Engine\Model {
    private const ENTITY = 'cms/page';

    /** Definições cujo grupo está ativo, com labels/options para o formulário. */
    public function getPageFields(): array {
        $languageId=$this->currentLanguageId();
        $q=$this->db->query("SELECT f.`field_id`, f.`entity_type`, f.`group_id`, f.`key`, f.`type`, f.`localized`, f.`required`, f.`visibility`, f.`options`, f.`sort_order`, g.`key` AS `group_key`, g.`name` AS `group_name`, COALESCE(fd.`label`, fd_en.`label`, f.`key`) AS `label`, COALESCE(fd.`help`, fd_en.`help`, '') AS `help`, COALESCE(gd.`name`, gd_en.`name`, g.`name`) AS `group_label` FROM `".$this->t('custom_field')."` f INNER JOIN `".$this->t('custom_field_group')."` g ON g.`group_id`=f.`group_id` AND g.`entity_type`='".self::ENTITY."' AND g.`status`='1' LEFT JOIN `".$this->t('custom_field_description')."` fd ON fd.`field_id`=f.`field_id` AND fd.`language_id`='".$languageId."' LEFT JOIN `".$this->t('custom_field_description')."` fd_en ON fd_en.`field_id`=f.`field_id` AND fd_en.`language_id`=1 LEFT JOIN `".$this->t('custom_field_group_description')."` gd ON gd.`group_id`=g.`group_id` AND gd.`language_id`='".$languageId."' LEFT JOIN `".$this->t('custom_field_group_description')."` gd_en ON gd_en.`group_id`=g.`group_id` AND gd_en.`language_id`=1 WHERE f.`entity_type`='".self::ENTITY."' ORDER BY g.`sort_order`,g.`group_id`,f.`sort_order`,f.`field_id`");
        return ($q instanceof \stdClass && !empty($q->rows))?(array)$q->rows:[];
    }

    /** @return array<int,array<string,mixed>> */
    public function getActiveLanguages(): array {
        $q=$this->db->query("SELECT `language_id`,`name`,`code` FROM `".$this->t('language')."` WHERE `status`='1' ORDER BY `sort_order`,`language_id`");
        return ($q instanceof \stdClass && !empty($q->rows))?(array)$q->rows:[];
    }

    /** @return array<int,array<int,string>> field_id => language_id => value */
    public function getPageValues(int $page_id): array {
        $q=$this->db->query("SELECT v.`field_id`,v.`language_id`,v.`value_text` FROM `".$this->t('custom_field_value')."` v INNER JOIN `".$this->t('custom_field')."` f ON f.`field_id`=v.`field_id` AND f.`entity_type`='".self::ENTITY."' WHERE v.`entity_type`='".self::ENTITY."' AND v.`entity_id`='".$page_id."'");
        $out=[];
        if($q instanceof \stdClass && !empty($q->rows)) foreach((array)$q->rows as $r){$r=(array)$r;$out[(int)$r['field_id']][(int)$r['language_id']]=(string)$r['value_text'];}
        return $out;
    }

    /** Valida valor escalar segundo tipo e opções; retorna mensagem curta ou vazio. */
    public function validateValue(array $field, mixed $value): string {
        if(!is_scalar($value)) return 'invalid';
        $v=trim((string)$value);$type=(string)$field['type'];
        if(!empty($field['required']) && $v==='') return 'required';
        if($v==='') return '';
        if($type==='number' && !preg_match('/^-?\\d{1,32}(?:\\.\\d{1,6})?$/',$v)) return 'number';
        if($type==='boolean' && !in_array($v,['0','1'],true)) return 'boolean';
        if($type==='url' && (!filter_var($v,FILTER_VALIDATE_URL) || !preg_match('~^https?://~i',$v))) return 'url';
        if($type==='select'){
            $opts=json_decode((string)($field['options']??'[]'),true);$allowed=[];
            foreach(is_array($opts)?$opts:[] as $o) if(isset($o['value'])) $allowed[]=(string)$o['value'];
            if(!in_array($v,$allowed,true)) return 'select';
        }
        return '';
    }

    /** Upsert apenas definições ativas e pertencentes ao whitelist; sem DDL. */
    public function savePageValues(int $page_id, array $submitted): bool {
        $fields=$this->getPageFields();$allowed=[];foreach($fields as $f){$f=(array)$f;$allowed[(int)$f['field_id']]=$f;}
        $langs=array_map(static fn($r)=>(int)((array)$r)['language_id'],$this->getActiveLanguages());
        $writes=[];
        foreach($submitted as $fieldId=>$byLanguage){
            $fid=(int)$fieldId;if(!isset($allowed[$fid])||!is_array($byLanguage))continue;$field=$allowed[$fid];$localized=(int)$field['localized']===1;
            foreach($byLanguage as $languageId=>$value){$lid=(int)$languageId;if(($localized&&!in_array($lid,$langs,true))||(!$localized&&$lid!==0))continue;
                if($this->validateValue($field,$value)!=='') return false;
                $text=(string)$value;$number=(string)$field['type']==='number'&&$text!==""?"'".$this->db->escape($text)."'":'NULL';
                $writes[] = "INSERT INTO `".$this->t('custom_field_value')."` SET `entity_type`='".self::ENTITY."',`entity_id`='".$page_id."',`field_id`='".$fid."',`language_id`='".$lid."',`value_text`='".$this->db->escape($text)."',`value_number`=".$number." ON DUPLICATE KEY UPDATE `value_text`=VALUES(`value_text`),`value_number`=VALUES(`value_number`)";
            }
        }
        if(!$writes) return true;
        if($this->db->query('START TRANSACTION')===false) return false;
        foreach($writes as $sql){if($this->db->query($sql)===false){$this->db->query('ROLLBACK');return false;}}
        if($this->db->query('COMMIT')===false){$this->db->query('ROLLBACK');return false;}
        return true;
    }

    /** Cascata explícita no delete de página (sem FK conforme schema F9-02). */
    public function deletePageValues(int $page_id): bool {
        return $this->db->query("DELETE FROM `".$this->t('custom_field_value')."` WHERE `entity_type`='".self::ENTITY."' AND `entity_id`='".$page_id."'") !== false;
    }

    private function currentLanguageId(): int {
        $q=$this->db->query("SELECT `language_id` FROM `".$this->t('language')."` WHERE `code`='".$this->db->escape((string)$this->config->get('language_directory','en-us'))."' LIMIT 1");
        return ($q instanceof \stdClass && !empty($q->row))?(int)$q->row['language_id']:1;
    }
    private function t(string $table): string { return DB_PREFIX.$table; }
}
