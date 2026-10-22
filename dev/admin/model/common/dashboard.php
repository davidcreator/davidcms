<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * common/dashboard (ADMIN MODEL) — contadores e listas rápidas do painel
 * (F8-01, ADR 0045). Defensivo no espírito da ADR 0043: cada consulta
 * passa por tableExists() — módulo não instalado = card/selo omitido,
 * dashboard NUNCA quebra por tabela ausente.
 */
namespace Reamur\Admin\Model\Common;

class Dashboard extends \Reamur\System\Engine\Model {

    /** SHOW TABLES defensivo (padrão ADR 0043 §model-sabichão) */
    private function tableExists(string $table): bool {
        $query = $this->db->query(
            "SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'"
        );

        return $query instanceof \stdClass && $query->num_rows > 0;
    }

    /**
     * Contadores dos cards do dashboard. Cada entrada:
     * [ 'table', 'where', 'icon', 'module_route', 'label_key', 'extra_sql' ]
     * extra_sql: SELECT opcional de contexto ("hoje: N", "7d: N").
     *
     * @return array<string, array<string, mixed>>
     */
    public function getStats(): array {
        $defs = [
            'posts' => [
                'table' => 'blog_post',
                'where' => "status = '1'",
                'icon'  => 'bi bi-journal-text',
                'route' => 'blog/blog',
                'label' => 'card_posts',
                'extra' => "SELECT COUNT(*) AS n FROM `" . DB_PREFIX . "blog_post` WHERE status = '1' AND date_published >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                'extra_label' => 'card_30d',
            ],
            'pages' => [
                'table' => 'page',
                'where' => "status = '1'",
                'icon'  => 'bi bi-file-earmark-text',
                'route' => 'cms/page',
                'label' => 'card_pages',
            ],
            'projects' => [
                'table' => 'project',
                'where' => '',
                'icon'  => 'bi bi-briefcase',
                'route' => 'catalog/project',
                'label' => 'card_projects',
            ],
            'orders' => [
                'table' => 'order',
                'where' => '',
                'icon'  => 'bi bi-cart3',
                'route' => 'sale/order',
                'label' => 'card_orders',
                'extra' => "SELECT COUNT(*) AS n FROM `" . DB_PREFIX . "order` WHERE date_added >= CURDATE()",
                'extra_label' => 'card_today',
            ],
            'messages' => [
                'table' => 'contact_message',
                'where' => '',
                'icon'  => 'bi bi-chat-dots',
                'route' => 'communication/contact_message',
                'label' => 'card_messages',
                'extra' => "SELECT COUNT(*) AS n FROM `" . DB_PREFIX . "contact_message` WHERE date_added >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                'extra_label' => 'card_7d',
            ],
            'testimonials' => [
                'table' => 'testimonial',
                'where' => '',
                'icon'  => 'bi bi-chat-quote',
                'route' => 'marketing/testimonial',
                'label' => 'card_testimonials',
            ],
            'tools' => [
                'table' => 'tool',
                'where' => '',
                'icon'  => 'bi bi-tools',
                'route' => 'tool/tool',
                'label' => 'card_tools',
            ],
            'webhook_fails' => [
                'table' => 'webhook_log',
                'where' => "http_code >= 400 OR http_code = 0",
                'icon'  => 'bi bi-diagram-3',
                'route' => 'setting/webhook',
                'label' => 'card_webhook_fails',
                'extra' => "SELECT COUNT(*) AS n FROM `" . DB_PREFIX . "webhook_log` WHERE (http_code >= 400 OR http_code = 0) AND date_added >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                'extra_label' => 'card_24h',
            ],
        ];

        $stats = [];

        foreach ($defs as $key => $def) {
            if (!$this->tableExists($def['table'])) {
                continue; // módulo SQL não aplicado neste banco — card omitido
            }

            $where = $def['where'] !== '' ? 'WHERE ' . $def['where'] : '';

            $query = $this->db->query(
                "SELECT COUNT(*) AS n FROM `" . DB_PREFIX . $def['table'] . "` " . $where
            );

            $count = 0;

            if ($query instanceof \stdClass && !empty($query->row)) {
                $count = (int)($query->row['n'] ?? 0);
            }

            $extra = 0;

            if (!empty($def['extra'])) {
                $eq = $this->db->query($def['extra']);

                if ($eq instanceof \stdClass && !empty($eq->row)) {
                    $extra = (int)($eq->row['n'] ?? 0);
                }
            }

            $stats[$key] = [
                'count'       => $count,
                'extra'       => $extra,
                'extra_label' => (string)($def['extra_label'] ?? ''),
                'icon'        => $def['icon'],
                'route'       => $def['route'],
                'label'       => $def['label'],
            ];
        }

        return $stats;
    }

    /**
     * Últimos posts publicados (âncora en-US + coalesce; ADR 0042).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLatestPosts(int $limit = 5): array {
        if (!$this->tableExists('blog_post')) {
            return [];
        }

        $limit = max(1, min(20, $limit));

        $query = $this->db->query(
            "SELECT p.post_id,
                    COALESCE(NULLIF(pd.title, ''), pd_en.title, '') AS title,
                    p.date_published, p.status
               FROM `" . DB_PREFIX . "blog_post` p
               LEFT JOIN `" . DB_PREFIX . "blog_post_description` pd
                 ON pd.post_id = p.post_id AND pd.language_id = '1'
               LEFT JOIN `" . DB_PREFIX . "blog_post_description` pd_en
                 ON pd_en.post_id = p.post_id AND pd_en.language_id = 1
              ORDER BY p.date_published DESC, p.post_id DESC
              LIMIT " . $limit
        );

        return ($query instanceof \stdClass) ? (array)$query->rows : [];
    }

    /**
     * Últimas mensagens de contato recebidas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLatestMessages(int $limit = 5): array {
        if (!$this->tableExists('contact_message')) {
            return [];
        }

        $limit = max(1, min(20, $limit));

        $query = $this->db->query(
            "SELECT contact_message_id, name, email, subject, status, date_added
               FROM `" . DB_PREFIX . "contact_message`
              ORDER BY date_added DESC, contact_message_id DESC
              LIMIT " . $limit
        );

        return ($query instanceof \stdClass) ? (array)$query->rows : [];
    }
}
