<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 */
namespace Reamur\Admin\Controller\{{GROUP}};

/**
 * TEMPLATE: CRUD padrão do admin (agente-admin §4).
 * Placeholders: {{GROUP}} ex.: Cms · {{ENTITY}} ex.: Project · {{entity}} ex.: project · {{ROUTE}} ex.: cms/project
 */
class {{ENTITY}} extends \Reamur\System\Engine\Controller {
    /** @var array<string, string> */
    private array $error = [];

    /** Lista com paginação + ordenação */
    public function index(): void {
        $this->load->language('{{ROUTE}}');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('{{ROUTE}}');

        $page   = max(1, (int)($this->request->get['page'] ?? 1));
        $limit  = (int)$this->config->get('config_limit_admin') ?: 20;

        $data['items'] = $this->model_{{GROUP}}_{{ENTITY}}->get{{ENTITY}}List([
            'start' => ($page - 1) * $limit,
            'limit' => $limit,
        ]);

        $total = $this->model_{{GROUP}}_{{ENTITY}}->getTotal{{ENTITY}}();

        // Paginação + breadcrumbs + ações (add/delete) — padrão do painel
        // $pagination = new \Reamur\System\Library\Pagination();
        // ... montar $data['pagination'], $data['results'], $data['breadcrumbs']

        $data['add']    = $this->url->link('{{ROUTE}}.form', 'user_token=' . $this->session->data['user_token']);
        $data['delete'] = $this->url->link('{{ROUTE}}.delete', 'user_token=' . $this->session->data['user_token']);

        $this->response->setOutput($this->load->view('{{ROUTE}}/{{entity}}_list', $data));
    }

    /** Formulário de criação/edição */
    public function form(): void {
        $this->load->language('{{ROUTE}}');
        $this->load->model('{{ROUTE}}');

        $id = (int)($this->request->get['{{entity}}_id'] ?? 0);

        $data['item']   = $id ? $this->model_{{GROUP}}_{{ENTITY}}->get{{ENTITY}}($id) : [];
        $data['action'] = $this->url->link('{{ROUTE}}.save', 'user_token=' . $this->session->data['user_token']);
        $data['error']  = $this->error;

        $this->response->setOutput($this->load->view('{{ROUTE}}/{{entity}}_form', $data));
    }

    /** Persiste (POST + CSRF + validação) */
    public function save(): void {
        $this->load->language('{{ROUTE}}');
        $this->load->model('{{ROUTE}}');

        $json = [];

        if (!$this->validateCsrf() || !$this->validateForm()) {
            $json['error'] = $this->error;
        }

        if (!$json) {
            $id = (int)($this->request->post['{{entity}}_id'] ?? 0);
            $id
                ? $this->model_{{GROUP}}_{{ENTITY}}->edit{{ENTITY}}($id, $this->request->post)
                : $this->model_{{GROUP}}_{{ENTITY}}->add{{ENTITY}}($this->request->post);

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /** Exclusão em massa (POST + CSRF) */
    public function delete(): void {
        $this->load->language('{{ROUTE}}');
        $this->load->model('{{ROUTE}}');

        $json = [];

        if (!$this->validateCsrf()) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }

        $selected = $this->request->post['selected'] ?? [];

        if (!$json && $selected && is_array($selected)) {
            foreach ($selected as $id) {
                $this->model_{{GROUP}}_{{ENTITY}}->delete{{ENTITY}}((int)$id);
            }
            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /** Validação de formulário — toda mensagem via language (error_*) */
    protected function validateForm(): bool {
        // TEMPLATE: permissão primeiro
        // if (!$this->user->hasPermission('modify', '{{ROUTE}}')) {
        //     $this->error['warning'] = $this->language->get('error_permission');
        // }

        // Ex.: campo obrigatório
        if (empty($this->request->post['name'])) {
            $this->error['name'] = $this->language->get('error_name');
        }

        return !$this->error;
    }

    /** CSRF via token de sessão do admin */
    protected function validateCsrf(): bool {
        return isset($this->request->post['csrf_token'], $this->session->data['csrf_token'])
            && hash_equals($this->session->data['csrf_token'], (string)$this->request->post['csrf_token']);
    }
}
