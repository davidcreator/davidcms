<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Wizard — Passo 2: Pré-requisitos do ambiente.
 * Fonte da lista: docs/Guia-PHP8.md §9 (extensões obrigatórias) + Contrato-Bootstrap §1.3 (PHP >= 8.0).
 */
namespace Reamur\Install\Controller\Install;

class Step_2 extends \Reamur\System\Engine\Controller {

    /** Checagens executadas sempre (GET e POST) — avanço só com tudo verde */
    public function index(): void {
        $this->load->language('install/step_2');

        $this->document->setTitle($this->language->get('heading_title'));

        // Textos da view
        $data['step']               = sprintf($this->language->get('text_step'), 2);
        $data['heading_title']      = $this->language->get('heading_title');
        $data['text_requirements']  = $this->language->get('text_requirements');
        $data['text_column_check']  = $this->language->get('text_column_check');
        $data['text_column_status'] = $this->language->get('text_column_status');
        $data['text_column_detail'] = $this->language->get('text_column_detail');
        $data['text_ok']            = $this->language->get('text_ok');
        $data['text_fail']          = $this->language->get('text_fail');
        $data['text_error_summary'] = $this->language->get('text_error_summary');
        $data['button_back']        = $this->language->get('button_back');
        $data['button_continue']    = $this->language->get('button_continue');

        $checks = $this->checks();

        $ok = !in_array(false, array_column($checks, 'status'), true);

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $ok) {
            $this->response->redirect($this->url->link('install/step_3'));
            return;
        }

        $data['checks']  = $checks;
        $data['ok']      = $ok;
        $data['action']  = $this->url->link('install/step_2');
        $data['back']    = $this->url->link('install/step_1');

        // Chrome do wizard (ADR 0007)
        $data['page_title'] = $this->language->get('heading_title');
        $data['header']     = $this->load->controller('common/header', $data);
        $data['language']   = $this->load->controller('common/language');
        $data['footer']     = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('install/step_2', $data));
    }

    /**
     * Lista de verificação — nomes das checagens vêm da language (text_check_*).
     *
     * @return array<int, array{name: string, status: bool, detail: string}>
     */
    private function checks(): array {
        $dir = dirname(__DIR__, 3) . '/'; // pasta dev/

        $extensions = ['json', 'mbstring', 'openssl', 'curl', 'zip', 'xml'];

        $checks = [];

        // PHP >= 8.0 (Contrato-Bootstrap §1.3; núcleo valida de novo no startup.php)
        $checks[] = [
            'name'   => $this->language->get('text_check_php'),
            'status' => version_compare(PHP_VERSION, '8.0', '>='),
            'detail' => PHP_VERSION,
        ];

        // Extensões obrigatórias simples (Guia-PHP8 §9)
        foreach ($extensions as $ext) {
            $checks[] = [
                'name'   => $this->language->get('text_check_ext') . ' ' . $ext,
                'status' => extension_loaded($ext),
                'detail' => $ext,
            ];
        }

        // gd OU imagick (qualquer um atende library/image.php)
        $checks[] = [
            'name'   => $this->language->get('text_check_image'),
            'status' => extension_loaded('gd') || extension_loaded('imagick'),
            'detail' => 'gd | imagick',
        ];

        // mysqli OU pdo_mysql (ao menos um driver de banco)
        $checks[] = [
            'name'   => $this->language->get('text_check_db'),
            'status' => extension_loaded('mysqli') || extension_loaded('pdo_mysql'),
            'detail' => 'mysqli | pdo_mysql',
        ];

        // Pastas graváveis: storage do núcleo + raiz das apps que receberão config.php
        foreach (['system/storage/', 'system/storage/cache/', 'system/storage/logs/', 'system/storage/session/', 'catalog/', 'admin/'] as $path) {
            $checks[] = [
                'name'   => $this->language->get('text_check_writable'),
                'status' => is_dir($dir . $path) && is_writable($dir . $path),
                'detail' => $path,
            ];
        }

        return $checks;
    }
}
