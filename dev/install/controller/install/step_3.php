<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Wizard — Passo 3: Banco de dados.
 * Testa a conexão, importa sql/schema.sql (Tarefa 1) e GERA os config.php
 * de catalog e admin a partir de dev/install/config.php.tpl (Contrato §1.1).
 * As credenciais validadas ficam na sessão efêmera do wizard para o passo 4.
 */
namespace Reamur\Install\Controller\Install;

class Step_3 extends \Reamur\System\Engine\Controller {

    /** GET exibe o formulário; POST valida, instala e avança */
    public function index(): void {
        $this->load->language('install/step_3');
        $this->load->model('install/install');

        $this->document->setTitle($this->language->get('heading_title'));

        // Textos da view
        $data['step']             = sprintf($this->language->get('text_step'), 3);
        $data['heading_title']    = $this->language->get('heading_title');
        $data['text_db']          = $this->language->get('text_db');
        $data['entry_db_driver']  = $this->language->get('entry_db_driver');
        $data['entry_db_hostname']= $this->language->get('entry_db_hostname');
        $data['entry_db_username']= $this->language->get('entry_db_username');
        $data['entry_db_password']= $this->language->get('entry_db_password');
        $data['entry_db_database']= $this->language->get('entry_db_database');
        $data['entry_db_port']    = $this->language->get('entry_db_port');
        $data['entry_db_prefix']  = $this->language->get('entry_db_prefix');
        $data['entry_http_server']= $this->language->get('entry_http_server');
        $data['button_back']      = $this->language->get('button_back');
        $data['button_continue']  = $this->language->get('button_continue');

        $data['error'] = [];

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $data['error'] = $this->validate();

            if (!$data['error']) {
                $db = [
                    'db_driver'   => (string)$this->request->post['db_driver'],
                    'db_hostname' => trim((string)$this->request->post['db_hostname']),
                    'db_username' => trim((string)$this->request->post['db_username']),
                    'db_password' => (string)$this->request->post['db_password'],
                    'db_database' => trim((string)$this->request->post['db_database']),
                    'db_port'     => (int)$this->request->post['db_port'],
                ];

                $prefix = strtolower(trim((string)$this->request->post['db_prefix']));
                $error  = '';

                if (!$this->model_install_install->connect($db)) {
                    $data['error']['connect'] = $this->language->get('error_connect') . ' ' . $this->model_install_install->getError();
                } elseif ($this->model_install_install->hasTables($prefix)) {
                    // Banco não vazio: aborta antes de importar (protege instalação existente)
                    $data['error']['database'] = $this->language->get('error_db_exists');
                } elseif (!$this->writeConfigs($db, $prefix, $error)) {
                    $data['error']['config'] = $this->language->get('error_config') . ' ' . $error;
                } else {
                    $this->model_install_install->importSchema($prefix);

                    // Sessão efêmera do wizard (Contrato-Bootstrap §4): passo 4 precisa das credenciais
                    $this->session->data['install_db'] = $db + ['db_prefix' => $prefix];

                    $this->response->redirect($this->url->link('install/step_4'));
                    return;
                }
            }
        }

        // Drivers oferecidos = os realmente disponíveis no ambiente (S2 do plano)
        $data['drivers'] = array_values(array_filter([
            'mysqli' => extension_loaded('mysqli'),
            'pdo'    => extension_loaded('pdo_mysql'),
        ]));

        // Repopula o formulário
        foreach (['db_driver', 'db_hostname', 'db_username', 'db_password', 'db_database', 'db_port', 'db_prefix', 'http_server'] as $field) {
            $data[$field] = $this->request->post[$field] ?? match ($field) {
                'db_driver'   => 'mysqli',
                'db_hostname' => 'localhost',
                'db_port'     => '3306',
                'db_prefix'   => 'rms_',
                default       => '',
            };
        }

        $data['action'] = $this->url->link('install/step_3');
        $data['back']   = $this->url->link('install/step_2');

        $this->response->setOutput($this->load->view('install/step_3', $data));
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

    /**
     * @return array<string, string> Erros por campo (todos via language)
     */
    private function validate(): array {
        $error = [];

        $driver = (string)($this->request->post['db_driver'] ?? '');

        if (!in_array($driver, ['mysqli', 'pdo'], true)) {
            $error['db_driver'] = $this->language->get('error_db_driver');
        }

        foreach (['db_hostname', 'db_username', 'db_database'] as $field) {
            if (trim((string)($this->request->post[$field] ?? '')) === '') {
                $error[$field] = $this->language->get('error_required');
            }
        }

        $prefix = (string)($this->request->post['db_prefix'] ?? '');

        if (!preg_match('/^[a-z][a-z0-9_]{0,19}$/', $prefix)) {
            $error['db_prefix'] = $this->language->get('error_db_prefix');
        }

        if (trim((string)($this->request->post['http_server'] ?? '')) === ''
            || !filter_var((string)$this->request->post['http_server'], FILTER_VALIDATE_URL)) {
            $error['http_server'] = $this->language->get('error_http_server');
        }

        return $error;
    }
}
