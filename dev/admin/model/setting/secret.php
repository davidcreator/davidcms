<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/secret (MODEL admin) — inventário e conversão dos segredos
 * guardados no banco (ADR 0094, S9). Não tem tela própria: a seção
 * "Proteção dos segredos" de setting/setting e o aviso do painel usam
 * summary(); o botão "Proteger segredos" usa protect().
 *
 * Nenhum método devolve o conteúdo de um segredo: só contagens por estado.
 */
namespace Reamur\Admin\Model\Setting;

use Reamur\System\Library\Security\SecretBox;

class Secret extends \Reamur\System\Engine\Model {

    /**
     * Onde há segredo, em que estado.
     *
     * @return array{
     *   groups: array<string, array{clear:int, sealed:int, old:int, locked:int}>,
     *   clear: int, sealed: int, old: int, locked: int
     * }
     */
    public function summary(): array {
        $groups = [];

        foreach ($this->locations() as $location) {
            $group = $location['group'];
            $groups[$group] ??= ['clear' => 0, 'sealed' => 0, 'old' => 0, 'locked' => 0];

            $state = $this->secrets()->state($location['value'], $location['context']);

            if (isset($groups[$group][$state])) {
                $groups[$group][$state]++;
            }
        }

        $total = ['clear' => 0, 'sealed' => 0, 'old' => 0, 'locked' => 0];

        foreach ($groups as $counts) {
            foreach ($counts as $state => $n) {
                $total[$state] += $n;
            }
        }

        return ['groups' => $groups] + $total;
    }

    /**
     * Situação em uma palavra, para a tela de configurações e o aviso do painel:
     *   unavailable      PHP sem OpenSSL/AES-256-GCM
     *   invalid          REAMUR_SECRET_KEY presente, mas não é uma chave gerada
     *   missing          sem REAMUR_SECRET_KEY no admin/config.php
     *   peer_missing     catalog/config.php sem a chave
     *   peer_different   catalog/config.php com outra chave
     *   peer_unreadable  catalog/config.php não pôde ser lido
     *   pending          chave certa; ainda há valor legível ou da chave anterior
     *   locked           chave válida, mas há valor cifrado que ela não abre
     *   ok               chave certa; nada a converter
     *
     * @return array<string, mixed> code, can_protect, total e as contagens de summary()
     */
    public function status(): array {
        $box     = $this->secrets();
        $summary = $this->summary();
        $pending = $summary['clear'] + $summary['old'];

        $code = match ($box->keyState()) {
            SecretBox::KEY_UNAVAILABLE => 'unavailable',
            SecretBox::KEY_INVALID     => 'invalid',
            SecretBox::KEY_MISSING     => 'missing',
            default                    => match ($box->peerState()) {
                SecretBox::PEER_MISSING    => 'peer_missing',
                SecretBox::PEER_DIFFERENT  => 'peer_different',
                SecretBox::PEER_UNREADABLE => 'peer_unreadable',
                default                    => $pending > 0 ? 'pending' : ($summary['locked'] > 0 ? 'locked' : 'ok'),
            },
        };

        return [
            'code'        => $code,
            'can_protect' => $box->canSeal() && $pending > 0,
            'total'       => $summary['clear'] + $summary['sealed'] + $summary['old'] + $summary['locked'],
        ] + $summary;
    }

    /**
     * Cifra o que ainda está legível e regrava com a chave atual o que foi
     * cifrado com a chave anterior. Pode ser repetido: o que já está certo
     * não é tocado. O que não abre fica como está (não é apagado).
     *
     * @param bool $webhookColumnReady a coluna rms_webhook.secret comporta o valor cifrado
     * @return array{converted:int, skipped_webhook:int}
     */
    public function protect(bool $webhookColumnReady): array {
        $converted      = 0;
        $skippedWebhook = 0;

        if (!$this->secrets()->canSeal()) {
            return ['converted' => 0, 'skipped_webhook' => 0];
        }

        foreach ($this->locations() as $location) {
            $sealed = $this->secrets()->reseal($location['value'], $location['context']);

            if ($sealed === null) {
                continue;
            }

            if ($location['group'] === 'webhook' && !$webhookColumnReady) {
                $skippedWebhook++;

                continue;
            }

            $this->db->query(
                "UPDATE `" . $this->t($location['table']) . "`
                    SET `" . $location['column'] . "` = '" . $this->db->escape($sealed) . "'
                  WHERE `" . $location['id_column'] . "` = '" . $this->db->escape($location['id']) . "'
                    AND `" . $location['column'] . "` = '" . $this->db->escape($location['value']) . "'"
            );

            $converted++;
        }

        // rms_setting é lida pelo cache: sem isto o valor legível continuaria no arquivo.
        $this->cache->delete('setting.config');

        return ['converted' => $converted, 'skipped_webhook' => $skippedWebhook];
    }

    /**
     * Todos os segredos gravados, um por linha. Nomes de tabela e coluna são
     * fixos (nunca vêm de fora).
     *
     * @return list<array{group:string, table:string, column:string, id_column:string, id:string, context:string, value:string}>
     */
    private function locations(): array {
        $out = [];

        if ($this->hasTable('setting')) {
            $keys = "'" . implode("','", SecretBox::SETTING_KEYS) . "'";

            foreach ($this->rows(
                "SELECT `setting_id`, `key`, `value` FROM `" . $this->t('setting') . "`
                  WHERE `code` = 'config' AND `serialized` = 0 AND `value` <> '' AND `key` IN (" . $keys . ")"
            ) as $row) {
                $out[] = [
                    'group'     => (string)$row['key'],
                    'table'     => 'setting',
                    'column'    => 'value',
                    'id_column' => 'setting_id',
                    'id'        => (string)$row['setting_id'],
                    'context'   => SecretBox::settingContext((string)$row['key']),
                    'value'     => (string)$row['value'],
                ];
            }
        }

        if ($this->hasTable('mp_seller')) {
            foreach ($this->rows(
                "SELECT `mp_seller_id`, `access_token`, `refresh_token` FROM `" . $this->t('mp_seller') . "`"
            ) as $row) {
                foreach (['access_token' => SecretBox::CTX_MP_ACCESS, 'refresh_token' => SecretBox::CTX_MP_REFRESH] as $column => $context) {
                    if ((string)$row[$column] === '') {
                        continue;
                    }

                    $out[] = [
                        'group'     => 'mp_seller',
                        'table'     => 'mp_seller',
                        'column'    => $column,
                        'id_column' => 'mp_seller_id',
                        'id'        => (string)$row['mp_seller_id'],
                        'context'   => $context,
                        'value'     => (string)$row[$column],
                    ];
                }
            }
        }

        if ($this->hasTable('webhook')) {
            foreach ($this->rows(
                "SELECT `webhook_id`, `secret` FROM `" . $this->t('webhook') . "` WHERE `secret` <> ''"
            ) as $row) {
                $out[] = [
                    'group'     => 'webhook',
                    'table'     => 'webhook',
                    'column'    => 'secret',
                    'id_column' => 'webhook_id',
                    'id'        => (string)$row['webhook_id'],
                    'context'   => SecretBox::CTX_WEBHOOK,
                    'value'     => (string)$row['secret'],
                ];
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $sql): array {
        $query = $this->db->query($sql);

        if (!$query instanceof \stdClass || empty($query->rows)) {
            return [];
        }

        return array_values(array_map(static fn ($row): array => (array)$row, (array)$query->rows));
    }
}
