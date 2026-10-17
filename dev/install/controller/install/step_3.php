<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Wizard — Passo 3 (ADR 0007, fluxo em paridade com o instalador ReamurCMS):
 * Configuração UNIFICADA — conexão do banco + URL pública + administrador em
 * uma única tela (antes: s3 banco / s4 admin). Um POST valida tudo, grava os
 * configs (catalog+admin), importa o schema e semeia o grupo Administrator
 * com os grants mínimos do painel (F2-6). Método test| responde JSON para o
 * botão "Testar conexão" — leitura pura, nunca grava.
 */
namespace Reamur\Install\Controller\Install;

class Step_3 extends \Reamur\System\Engine\Controller {

    /** GET: formulário de configuração · POST: valida e executa a instalação inteira */
    public function index(): void {
        $this->load->language('install/step_3');

        $this->load->model('install/install');

        $this->document->setTitle($this->language->get('heading_title'));

        $data = $this->viewData();

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $data['error'] = $this->validate();

            if ($data['error'] === []) {
                $db = $this->dbCredentials();

                $prefix = trim((string)$this->request->post['db_prefix']);

                if (!$this->model_install_install->connect($db)) {
                    $data['error']['connect'] = $this->language->get('error_db_connect') . ' ' . $this->model_install_install->getError();
                } elseif ($this->model_install_install->hasTables($prefix)) {
                    // Banco não vazio: aborta (protege instalação existente)
                    $data['error']['database'] = $this->language->get('error_db_exists');
                } else {
                    $error_config = '';

                    if (!$this->writeConfigs($db, $prefix, $error_config)) {
                        $data['error']['config'] = $this->language->get('error_config') . ' ' . $error_config;
                    } else {
                        $this->model_install_install->importSchema($prefix);

                        // Grupo Administrator já nasce com access+modify em
                        // 'user/user' e 'user/user_group' (Adendo F2-6 do ADR 0005)
                        $this->model_install_install->createAdministrator([
                            'username'  => trim((string)$this->request->post['username']),
                            'firstname' => trim((string)$this->request->post['firstname']),
                            'lastname'  => trim((string)$this->request->post['lastname']),
                            'email'     => trim((string)$this->request->post['email']),
                            'password'  => (string)$this->request->post['password'],
                        ], $prefix);

                        // URLs públicas REAIS vêm da entrada do usuário (site_url) —
                        // jamais de SCRIPT_NAME/PATH_INFO (armadilha SAPI corrigida na F2-5).
                        $this->session->data['install_done'] = true;
                        $this->session->data['install_site'] = rtrim((string)$this->request->post['http_server'], '/') . '/';

                        unset($this->session->data['install_db']); // legado do fluxo s3/s4 separado

                        $this->response->redirect($this->url->link('install/step_4'));

                        return;
                    }
                }
            }
        }

        $this->chrome($data);

        $this->response->setOutput($this->load->view('install/step_3', $data));
    }

    /**
     * Botão "Testar conexão" do passo 3 — leitura pura (não grava configs,
     * não importa nada). Responde JSON {success, error}.
     */
    public function test(): void {
        $this->load->model('install/install');

        $db = $this->dbCredentials();

        $ok = in_array($db['db_driver'], ['mysqli', 'pdo'], true)
            && $this->model_install_install->connect($db);

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');

        $this->response->setOutput((string)json_encode([
            'success' => $ok,
            'error'   => $ok ? '' : $this->model_install_install->getError(),
        ]));
    }

    // ─── internos ────────────────────────────────────────────────────────

    /**
     * @return array{db_driver: string, db_hostname: string, db_username: string, db_password: string, db_database: string, db_port: string}
     */
    private function dbCredentials(): array {
        $post = $this->request->post;

        return [
            'db_driver'   => (string)($post['db_driver'] ?? ''),
            'db_hostname' => trim((string)($post['db_hostname'] ?? '')),
            'db_username' => trim((string)($post['db_username'] ?? '')),
            'db_password' => (string)($post['db_password'] ?? ''),
            'db_database' => trim((string)($post['db_database'] ?? '')),
            'db_port'     => trim((string)($post['db_port'] ?? '3306')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(): array {
        $this->load->language('install/step_3');

        $data = [];

        foreach ([
            'heading_title', 'text_step_3', 'text_db_connection', 'text_db_administration',
            'text_db_ok', 'text_password_hint', 'entry_db_driver', 'entry_db_hostname',
            'entry_db_username', 'entry_db_password', 'entry_db_database', 'entry_db_port',
            'entry_db_prefix', 'entry_http_server', 'entry_username', 'entry_firstname',
            'entry_lastname', 'entry_email', 'entry_password', 'entry_confirm',
            'button_back', 'button_continue', 'button_test_db', 'button_generate',
            'button_toggle_password',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['error'] = [];

        // Drivers oferecidos = os realmente disponíveis no ambiente (S2 do plano).
        // As OPÇÕES são as CHAVES das extensões carregadas — array_values aqui
        // devolveria os booleans (true → opção "1" no select, bug hotfix F2).
        $data['drivers'] = array_keys(array_filter([
            'mysqli' => extension_loaded('mysqli'),
            'pdo'    => extension_loaded('pdo_mysql'),
        ]));

        // Repopula o formulário (POST) ou aplica defaults honestos
        foreach (['db_driver', 'db_hostname', 'db_username', 'db_password', 'db_database', 'db_port', 'db_prefix', 'http_server', 'username', 'firstname', 'lastname', 'email'] as $field) {
            $data[$field] = $this->request->post[$field] ?? match ($field) {
                'db_driver'   => in_array('mysqli', $data['drivers'], true) ? 'mysqli' : (string)($data['drivers'][0] ?? 'mysqli'),
                'db_hostname' => 'localhost',
                'db_port'     => '3306',
                'db_prefix'   => 'rms_',
                default       => '',
            };
        }

        $data['action']    = $this->url->link('install/step_3');
        $data['test_json'] = $this->url->link('install/step_3.test');
        $data['back']      = $this->url->link('install/step_2');

        return $data;
    }

    /**
     * Validação unificada: banco (espelha o que o model vai exigir), URL
     * pública e administrador (espelho das regras do CRUD user/user, F2-6).
     *
     * @return array<string, string> Erros por campo (vazio = válido)
     */
    private function validate(): array {
        $error = [];
        $post  = $this->request->post;

        // ── conexão ──
        if (!in_array((string)($post['db_driver'] ?? ''), ['mysqli', 'pdo'], true)) {
            $error['db_driver'] = $this->language->get('error_db_driver');
        }

        foreach (['db_hostname', 'db_username', 'db_database'] as $field) {
            if (trim((string)($post[$field] ?? '')) === '') {
                $error[$field] = $this->language->get('error_required');
            }
        }

        if (!preg_match('/^[a-z][a-z0-9_]{0,19}$/', (string)($post['db_prefix'] ?? ''))) {
            $error['db_prefix'] = $this->language->get('error_db_prefix');
        }

        if ($this->varchar((string)($post['db_port'] ?? ''), 1, 5) === '' || !ctype_digit((string)($post['db_port'] ?? ''))) {
            $error['db_port'] = $this->language->get('error_db_port');
        }

        if (trim((string)($post['http_server'] ?? '')) === ''
            || !filter_var((string)$post['http_server'], FILTER_VALIDATE_URL)) {
            $error['http_server'] = $this->language->get('error_http_server');
        }

        // ── administração (mesmas regras do antigo s4 e do CRUD do painel) ──
        $username = trim((string)($post['username'] ?? ''));

        if (mb_strlen($username) < 3 || mb_strlen($username) > 32) {
            $error['username'] = $this->language->get('error_username');
        }

        foreach (['firstname' => 'error_firstname', 'lastname' => 'error_lastname'] as $field => $key) {
            $len = mb_strlen(trim((string)($post[$field] ?? '')));

            if ($len < 1 || $len > 32) {
                $error[$field] = $this->language->get($key);
            }
        }

        if (!filter_var((string)($post['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $error['email'] = $this->language->get('error_email');
        }

        $password = (string)($post['password'] ?? '');

        if (mb_strlen($password) < 8) {
            $error['password'] = $this->language->get('error_password');
        } elseif ($password !== (string)($post['confirm'] ?? '')) {
            $error['confirm'] = $this->language->get('error_confirm');
        }

        return $error;
    }

    private function varchar(string $value, int $min, int $max): string {
        $len = mb_strlen($value);

        return ($len >= $min && $len <= $max) ? $value : '';
    }

    /**
     * Gera dev/catalog/config.php e dev/admin/config.php a partir do template.
     * admin usa HTTP_SERVER = {url}admin/ (S5 do plano — precedente OpenCart).
     */
    private function writeConfigs(array $db, string $prefix, string &$error = ''): bool {
        $template = dirname(__DIR__, 2) . '/config.php.tpl'; // dev/install/config.php.tpl
        $dir_dev  = dirname(__DIR__, 3) . '/';               // dev/

        if (!is_file($template)) {
            $error = 'config.php.tpl';

            return false;
        }

        $tpl  = (string)file_get_contents($template);
        $base = rtrim((string)$this->request->post['http_server'], '/') . '/';

        foreach (['catalog', 'admin'] as $app) {
            $output = strtr($tpl, [
                '{{HTTP_SERVER}}'  => $app === 'admin' ? $base . 'admin/' : $base,
                '{{HTTPS_SERVER}}' => $app === 'admin' ? $base . 'admin/' : $base,
                '{{DIR_REAMUR}}'   => dirname($dir_dev) . '/',
                '{{APP_DIR}}'      => $app,
                '{{DB_DRIVER}}'    => $db['db_driver'],
                '{{DB_HOSTNAME}}'  => $db['db_hostname'],
                '{{DB_USERNAME}}'  => $db['db_username'],
                '{{DB_PASSWORD}}'  => $db['db_password'],
                '{{DB_DATABASE}}'  => $db['db_database'],
                '{{DB_PORT}}'      => (string)$db['db_port'],
                '{{DB_PREFIX}}'    => $prefix,
            ]);

            $target = $dir_dev . $app . '/config.php';

            if (!is_writable($dir_dev . $app) || file_put_contents($target, $output) === false) {
                $error = $target;

                return false;
            }
        }

        return true;
    }

    /** Chrome compartilhado do wizard (header + seletor de idiomas + footer) */
    private function chrome(array &$data): void {
        $data['page_title'] = $this->language->get('heading_title');
        $data['header']     = $this->load->controller('common/header', $data);
        $data['language']   = $this->load->controller('common/language');
        $data['footer']     = $this->load->controller('common/footer');
    }
}
