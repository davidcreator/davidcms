<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * marketing/client_logo (CATALOG) — logos de clientes ATIVOS públicos (F2-17).
 * Sem tabela de idiomas: nome é identidade da marca.
 */
namespace Reamur\Catalog\Model\Marketing;

class Client_logo extends \Reamur\System\Engine\Model {

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClientLogos(): array {
        $query = $this->db->query(
            "SELECT `client_logo_id`, `name`, `image`, `url`
               FROM `" . DB_PREFIX . "client_logo`
              WHERE `status` = '1'
              ORDER BY `sort_order` ASC, `client_logo_id` ASC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }
}
