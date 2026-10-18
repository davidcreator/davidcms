<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * communication/contact_message (CATALOG) — gravação pública do formulário
 * "Let's have a chat?" (F2-17): ÚNICA escrita do front é INSERT em nova
 * mensagem (status 0 = novo, IP do cliente — Dicionário-de-Dados §9).
 * O resto do ciclo de vida (ler/operar/excluir) fica no admin (F2-15).
 */
namespace Reamur\Catalog\Model\Communication;

class Contact_message extends \Reamur\System\Engine\Model {

    /**
     * @param array<string, mixed> $data Payload já saneado pelo controller
     */
    public function addContactMessage(array $data): int {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "contact_message` SET
                `name`       = '" . $this->db->escape((string)$data['name']) . "',
                `email`      = '" . $this->db->escape((string)$data['email']) . "',
                `telephone`  = '" . $this->db->escape((string)$data['telephone']) . "',
                `subject`    = '" . $this->db->escape((string)$data['subject']) . "',
                `message`    = '" . $this->db->escape((string)$data['message']) . "',
                `ip`         = '" . $this->db->escape((string)$data['ip']) . "',
                `status`     = '0',
                `date_added` = NOW()"
        );

        return (int)$this->db->getLastId();
    }
}
