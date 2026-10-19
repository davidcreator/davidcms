<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * user/user_group — list/criação/edição dos grupos e da matriz de permissões
 * da ACL (F2-6). Rotas publicadas são DESCOBERTAS do filesystem
 * (controller/**.php) — a tela nunca inventa rota; quem nasce, aparece.
 */
namespace Reamur\Admin\Controller\User;

class User_group extends \Reamur\System\Engine\Controller {

    /** Lista grupos com contagem de usuários vinculados (200) */
    public function index(): void {
        $this->load->language('user/user_group');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('user/user_group');

        $data['heading_title']  = $this->language->get('heading_title');
        $data['text_list']      = $this->language->get('text_list');
        $data['column_name']    = $this->language->get('column_name');
        $data['column_users']   = $this->language->get('column_users');
        $data['column_action']  = $this->language->get('column_action');
        $data['button_add']     = $this->language->get('button_add');
        $data['button_edit']    = $this->language->get('button_edit');
        $data['text_flash']     = $this->flash();

        $data['groups'] = [];

        foreach ($this->model_user_user_group->getUserGroups() as $group) {
            $group = (array)$group;
            $gid   = (int)$group['user_group_id'];

            $data['groups'][] = [
                'user_group_id' => $gid,
                'name'          => (string)$group['name'],
                'total_users'   => (int)$group['total_users'],
                'edit'          => $this->url->link('user/user_group.edit|' . $gid),
                'delete'        => $this->url->link('user/user_group.delete|' . $gid),
            ];
        }

        $data['add'] = $this->url->link('user/user_group.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('user/user_group_list', $data));
    }

    /** GET: matriz vazia para novo grupo · POST: valida e cria */
    public function add(): void {
        if (!$this->user->hasPermission('modify', 'user/user_group') && $this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->form(0, []);
    }

    /** GET: matriz do grupo · POST: persiste (matriz inteira, sem delta) — args do Action são strings */
    public function edit(string $user_group_id = '0'): void {
        $user_group_id = (int)$user_group_id;

        $this->load->model('user/user_group');

        $group = $this->model_user_user_group->getUserGroup($user_group_id);

        if (!$group) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $this->form($user_group_id, $group);
    }

    /** DELETE grupo (POST): trava por usuarios vinculados + última associação admin (ADR 0027) */
    public function delete(string $user_group_id = '0'): void {
        $user_group_id = (int)$user_group_id;

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'user/user_group')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('user/user_group');
        $this->load->model('user/user_group');

        $group = $this->model_user_user_group->getUserGroup($user_group_id);

        if (!$group) {
            $this->response->redirect($this->url->link('user/user_group'));

            return;
        }

        $total = $this->model_user_user_group->countUsers($user_group_id);

        if ($total > 0) {
            $this->setFlash(sprintf($this->language->get('error_group_in_use'), $total), 'error');
        } elseif (!$this->model_user_user_group->hasOtherGroupGrantingUserAdmin($user_group_id)) {
            // único grupo capaz de administrar usuários → remoção trancaria o painel
            $this->setFlash($this->language->get('error_group_last_admin'), 'error');
        } else {
            $this->model_user_user_group->deleteUserGroup($user_group_id);

            $this->setFlash($this->language->get('text_success_delete'));
        }

        $this->response->redirect($this->url->link('user/user_group'));
    }

    // ─── internos ────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $group
     */
    private function form(int $user_group_id, array $group): void {
        $this->load->language('user/user_group');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('user/user_group');

        $permission = ['access' => [], 'modify' => []];

        if (!empty($group['permission'])) {
            $decoded    = (array)json_decode((string)$group['permission'], true);
            $permission = [
                'access' => array_values(array_map('strval', (array)($decoded['access'] ?? []))),
                'modify' => array_values(array_map('strval', (array)($decoded['modify'] ?? []))),
            ];
        }

        $data['heading_title']   = $this->language->get('heading_title');
        $data['text_permissions'] = $this->language->get('text_permissions');
        $data['text_access']     = $this->language->get('text_access');
        $data['text_modify']     = $this->language->get('text_modify');
        $data['column_route']    = $this->language->get('column_route');
        $data['text_group_note'] = $this->language->get('text_group_note');
        $data['text_select_all'] = $this->language->get('text_select_all');
        $data['entry_name']      = $this->language->get('entry_name');
        $data['button_save']     = $this->language->get('button_save');
        $data['button_cancel']   = $this->language->get('button_cancel');
        $data['error']           = '';
        $data['text_flash']      = '';

        $data['name'] = (string)($this->request->post['name'] ?? ($group['name'] ?? ''));

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'user/user_group')) {
                $this->response->redirect($this->url->link('error/permission'));

                return;
            }

            $access = array_map('strval', (array)($this->request->post['access'] ?? []));
            $modify = array_map('strval', (array)($this->request->post['modify'] ?? []));

            $errors = [];

            if (mb_strlen($data['name']) < 1 || mb_strlen($data['name']) > 64) {
                $errors[] = $this->language->get('error_name');
            }

            if ($errors === []) {
                if ($user_group_id > 0) {
                    $this->model_user_user_group->editUserGroup($user_group_id, $data['name'], $access, $modify);
                } else {
                    $this->model_user_user_group->addUserGroup($data['name'], $access, $modify);
                }

                $this->setFlash(
                    $this->language->get($user_group_id > 0 ? 'text_success_edit' : 'text_success_add')
                );

                $this->response->redirect($this->url->link('user/user_group'));

                return;
            }

            $permission = ['access' => $access, 'modify' => $modify];
            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $user_group_id > 0
            ? $this->url->link('user/user_group.edit|' . $user_group_id)
            : $this->url->link('user/user_group.add');

        $data['cancel'] = $this->url->link('user/user_group');

        // Matriz: rotas publicadas desta app, com marcações atuais
        $data['routes'] = [];

        foreach ($this->allRoutes() as $route) {
            $data['routes'][] = [
                'route'         => $route,
                'access_granted' => in_array($route, $permission['access'], true),
                'modify_granted' => in_array($route, $permission['modify'], true),
            ];
        }

        $this->chrome($data);

        $this->response->setOutput($this->load->view('user/user_group_form', $data));
    }

    /**
     * Descobre TODAS as rotas publicadas da app a partir do filesystem
     * (controller/**.php). Ordena por grupo de origem (prefixo).
     *
     * @return array<int, string>
     */
    private function allRoutes(): array {
        $routes   = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(DIR_APPLICATION . 'controller/', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $routes[] = substr(str_replace(DIR_APPLICATION . 'controller/', '', $file->getPathname()), 0, -4);
            }
        }

        sort($routes, SORT_STRING);

        return $routes;
    }

    private function flash(): array {
        $session = $this->session;
        $data    = ['type' => '', 'text' => ''];

        if ($session instanceof \Reamur\System\Library\Session && !empty($session->data['flash'])) {
            $data = (array)$session->data['flash'];
            $data['type'] = (string)($data['type'] ?? 'success');
            $data['text'] = (string)($data['text'] ?? '');

            unset($session->data['flash']);
        }

        return $data;
    }

    private function setFlash(string $text, string $type = 'success'): void {
        $session = $this->session;

        if ($session instanceof \Reamur\System\Library\Session) {
            $session->data['flash'] = ['type' => $type, 'text' => $text];
        }
    }

    private function chrome(array &$data): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css');

        $data['body_class'] = 'rms-dash';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
