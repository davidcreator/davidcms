<?php declare(strict_types=1);
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
            if (!$this->csrfValid()) {
                $data['error']['csrf'] = $this->language->get('error_csrf');
            } else {
                $data['error'] = $this->validate();
            }

            if ($data['error'] === []) {
                $db = $this->dbCredentials();

                $prefix = trim($this->postScalar('db_prefix'));

                if (!$this->model_install_install->connect($db)) {
                    $data['error']['connect'] = $this->language->get('error_db_connect') . ' ' . $this->model_install_install->getError();
                } elseif ($this->model_install_install->hasTables($prefix)) {
                    // Banco não vazio: aborta (protege instalação existente)
                    $data['error']['database'] = $this->language->get('error_db_exists');
                } else {
                    $data['error'] = $this->install($db, $prefix);

                    if ($data['error'] === []) {
                        // URLs públicas REAIS vêm da entrada do usuário (site_url) —
                        // jamais de SCRIPT_NAME/PATH_INFO (armadilha SAPI corrigida na F2-5).
                        $this->session->data['install_done']  = true;
                        $this->session->data['install_site']  = rtrim($this->postScalar('http_server'), '/') . '/';
                        $this->session->data['install_admin'] = $this->adminUrl((string)$this->session->data['install_site']);

                        unset($this->session->data['install_db']); // legado do fluxo s3/s4 separado

                        // ADR 0091 (S12): a conclusão sai NESTA resposta. O
                        // instalador já está travado; um redirect para o
                        // passo 4 receberia o 403 da trava.
                        $this->load->controller('install/step_4');

                        return;
                    }
                }
            }
        }

        $this->installChrome($data);

        $this->response->setOutput($this->load->view('install/step_3', $data));
    }

    /**
     * Botão "Testar conexão" do passo 3 — leitura pura (não grava configs,
     * não importa nada). Responde JSON {success, error}.
     */
    public function test(): void {
        $this->load->language('install/step_3');

        if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST' || !$this->csrfValid()) {
            $this->jsonResponse([
                'success' => false,
                'error'   => $this->language->get('error_csrf'),
            ], 403, true);

            return;
        }

        $this->load->model('install/install');

        $db = $this->dbCredentials();

        // ADR 0091: nome de servidor/banco fora do padrão nem chega ao driver,
        // e o teste desiste em poucos segundos (um destino que aceita a
        // conexão e fica calado prendia o PHP por tempo indeterminado).
        if (!self::isDbHost($db['db_hostname']) || !self::isDbName($db['db_database']) || !ctype_digit($db['db_port']) || strlen($db['db_port']) > 5) {
            $this->jsonResponse([
                'success' => false,
                'error'   => $this->language->get('error_db_hostname'),
            ], 200, true);

            return;
        }

        $ok = in_array($db['db_driver'], ['mysqli', 'pdo'], true)
            && $this->model_install_install->connect($db, self::TEST_TIMEOUT);

        $this->jsonResponse([
            'success' => $ok,
            'error'   => $ok ? '' : $this->model_install_install->getError(),
        ], 200, true);
    }

    /** Segundos de espera do botão "Testar conexão". */
    private const TEST_TIMEOUT = 5;

    /**
     * Servidor do banco: nome, IP, "nome:porta", "p:nome" (mysqli persistente)
     * ou caminho de socket. Sem ';', '=', espaço nem controle: no PDO o valor
     * entra no DSN, e "x;unix_socket=..." trocaria o destino.
     */
    public static function isDbHost(string $host): bool {
        return preg_match('#^[A-Za-z0-9._:/\[\]-]{1,255}$#D', $host) === 1;
    }

    /** Nome do banco: livre, menos o que quebra o DSN (';') e bytes de controle. */
    public static function isDbName(string $name): bool {
        return $name !== '' && strlen($name) <= 64 && preg_match('/[\x00-\x1f\x7f;]/', $name) !== 1;
    }

    /**
     * Executa a instalação (ADR 0091, S12). Ordem: trava → config.php → banco.
     *
     * A trava vem primeiro: se ela não puder ser gravada, nada foi alterado e
     * o erro aparece na tela. Se uma etapa seguinte falhar, o que esta
     * tentativa gravou em disco é desfeito, para permitir nova tentativa.
     *
     * @param array<string, mixed> $db
     *
     * @return array<string, string> Erros por campo (vazio = instalado)
     */
    private function install(array $db, string $prefix): array {
        $seal = \Reamur\System\Library\Security\InstallSeal::class;

        if (!$seal::seal(DIR_STORAGE)) {
            return ['config' => $this->language->get('error_lock') . ' ' . DIR_STORAGE . $seal::LOCK];
        }

        $error_config = '';

        if (!$this->writeConfigs($db, $prefix, $error_config)) {
            $this->undoInstall();

            return ['config' => $this->language->get('error_config') . ' ' . $error_config];
        }

        try {
            $this->model_install_install->importSchema($prefix);

            // Grupo Administrator já nasce com access+modify em
            // 'user/user' e 'user/user_group' (Adendo F2-6 do ADR 0005)
            $this->model_install_install->createAdministrator([
                'username'  => trim($this->postScalar('username')),
                'firstname' => trim($this->postScalar('firstname')),
                'lastname'  => trim($this->postScalar('lastname')),
                'email'     => trim($this->postScalar('email')),
                'password'  => $this->postScalar('password'),
            ], $prefix);
        } catch (\Throwable $e) {
            $this->undoInstall();

            return ['database' => $this->language->get('error_import') . ' ' . $e->getMessage()];
        }

        return [];
    }

    /** Desfaz o que a tentativa gravou em disco: os dois config.php e a trava. */
    private function undoInstall(): void {
        $seal = \Reamur\System\Library\Security\InstallSeal::class;

        foreach ($seal::configFiles(dirname(__DIR__, 3)) as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $seal::unseal(DIR_STORAGE);
    }

    // ─── internos ────────────────────────────────────────────────────────

    /**
     * @return array{db_driver: string, db_hostname: string, db_username: string, db_password: string, db_database: string, db_port: string}
     */
    private function dbCredentials(): array {
        return [
            'db_driver'   => $this->postScalar('db_driver'),
            'db_hostname' => trim($this->postScalar('db_hostname')),
            'db_username' => trim($this->postScalar('db_username')),
            'db_password' => $this->postScalar('db_password'),
            'db_database' => trim($this->postScalar('db_database')),
            'db_port'     => trim($this->postScalar('db_port', '3306')),
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
            $default = match ($field) {
                'db_driver'   => in_array('mysqli', $data['drivers'], true) ? 'mysqli' : (string)($data['drivers'][0] ?? 'mysqli'),
                'db_hostname' => 'localhost',
                'db_port'     => '3306',
                'db_prefix'   => 'rms_',
                default       => '',
            };
            $data[$field] = $this->postScalar($field, $default);
        }

        // Site URL: prefill honesto por tentativa (o usuário pode — e deve —
        // revisar). Localhost/dev sai pronto; o esquema/teste de porta obedece
        // ao request do próprio wizard. POST já traz o valor (normalizado).
        if ($this->request->server['REQUEST_METHOD'] !== 'POST' && trim((string)$data['http_server']) === '') {
            $data['http_server'] = $this->guessSiteUrl();
        }

        $data['text_http_server_hint'] = $this->language->get('text_http_server_hint');

        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['action']     = $this->url->link('install/step_3');
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

        // ── conexão ──
        if (!in_array($this->postScalar('db_driver'), ['mysqli', 'pdo'], true)) {
            $error['db_driver'] = $this->language->get('error_db_driver');
        }

        foreach (['db_hostname', 'db_username', 'db_database'] as $field) {
            if (trim($this->postScalar($field)) === '') {
                $error[$field] = $this->language->get('error_required');
            }
        }

        if (!isset($error['db_hostname']) && !self::isDbHost(trim($this->postScalar('db_hostname')))) {
            $error['db_hostname'] = $this->language->get('error_db_hostname');
        }

        if (!isset($error['db_database']) && !self::isDbName(trim($this->postScalar('db_database')))) {
            $error['db_database'] = $this->language->get('error_db_database');
        }

        if (!preg_match('/^[a-z][a-z0-9_]{0,19}$/', $this->postScalar('db_prefix'))) {
            $error['db_prefix'] = $this->language->get('error_db_prefix');
        }

        $dbPort = $this->postScalar('db_port');
        if ($this->varchar($dbPort, 1, 5) === '' || !ctype_digit($dbPort)) {
            $error['db_port'] = $this->language->get('error_db_port');
        }

        // Site URL: ajuda de digitação — vazio assume a tentativa (endereço
        // de onde o wizard responde); esquema ausente vira http:// (o valor
        // normalizado volta ao POST para repovoar o form e gravar o config
        // com o endereço FINAL). Localhost/porta dedicada vale sempre.
        $httpServer = trim($this->postScalar('http_server'));

        if ($httpServer === '') {
            $httpServer = $this->guessSiteUrl();
        }

        if (!str_contains($httpServer, '://')) {
            $httpServer = 'http://' . $httpServer;
        }

        $httpServer = rtrim(str_replace('\\', '/', $httpServer), '/'); // sem barra final aqui — writeConfigs recompõe

        $parsedScheme = parse_url($httpServer, PHP_URL_SCHEME);
        $scheme = is_string($parsedScheme) ? strtolower($parsedScheme) : '';
        if (
            $httpServer === ''
            || preg_match('/[\x00-\x20\x7f]/', $httpServer) === 1
            || !in_array($scheme, ['http', 'https'], true)
            || !filter_var($httpServer, FILTER_VALIDATE_URL)
        ) {
            $error['http_server'] = $this->language->get('error_http_server');
        } else {
            $this->request->post['http_server'] = $httpServer . '/';
        }

        // ── administração (mesmas regras do antigo s4 e do CRUD do painel) ──
        $username = trim($this->postScalar('username'));

        if (mb_strlen($username) < 3 || mb_strlen($username) > 32) {
            $error['username'] = $this->language->get('error_username');
        }

        foreach (['firstname' => 'error_firstname', 'lastname' => 'error_lastname'] as $field => $key) {
            $len = mb_strlen(trim($this->postScalar($field)));

            if ($len < 1 || $len > 32) {
                $error[$field] = $this->language->get($key);
            }
        }

        if (!filter_var($this->postScalar('email'), FILTER_VALIDATE_EMAIL)) {
            $error['email'] = $this->language->get('error_email');
        }

        $password = $this->postScalar('password');

        if (mb_strlen($password) < 8) {
            $error['password'] = $this->language->get('error_password');
        } elseif ($password !== $this->postScalar('confirm')) {
            $error['confirm'] = $this->language->get('error_confirm');
        }

        return $error;
    }

    private function varchar(string $value, int $min, int $max): string {
        $len = mb_strlen($value);

        return ($len >= $min && $len <= $max) ? $value : '';
    }

    /**
     * Tentativa de Site URL a partir do pedido do wizard. É GUESS de formulário
     * (o usuário revisa antes de gravar) — a regra de ouro do F2-5 (nunca
     * derivar base em runtime) continua intocada: o valor final é o digitado
     * e vai para o config como HTTP_SERVER.
     * Modo irmãos (Apache/XAMPP em subpasta): {projeto}/install/ ⇒ sobe um
     * nível e aponta a LOJA em {projeto}/catalog/ (admin é derivado como irmão
     * — ver adminUrl()). Docroot dedicado (php -S / vhost por app) ⇒ '/'.
     */
    private function guessSiteUrl(): string {
        $server = $this->request->server;

        // ADR 0089 (S5): mesma regra de install/index.php, num lugar só.
        $https = \Reamur\System\Library\Security\ClientAddress::looksHttps($server);

        $scheme = $https ? 'https://' : 'http://';
        $rawHost = (string)($server['HTTP_HOST'] ?? 'localhost');
        $rawHost = substr($rawHost, 0, strcspn($rawHost, "\r\n\0"));
        $host    = preg_replace('/[^a-zA-Z0-9.:\[\]-]/', '', $rawHost) ?: 'localhost';

        $script = str_replace('\\', '/', (string)($server['SCRIPT_NAME'] ?? '/index.php'));
        $dir    = rtrim((string)dirname($script), '/');

        if (basename($dir) === 'install') {
            $dir = rtrim((string)dirname($dir), '/');
        }

        $path = ($dir === '' || $dir === '.') ? '/' : $dir . '/catalog/';

        return $scheme . $host . $path;
    }

    /**
     * URL do painel derivada da URL da LOJA: layout irmãos (catalog/ e admin/
     * lado a lado) ⇒ troca o sufixo; demais layouts (docroot dedicado,
     * convenção OpenCart com admin sob a loja) ⇒ {site}admin/.
     */
    private function adminUrl(string $catalogBase): string {
        $base = rtrim($catalogBase, '/') . '/';

        if (str_ends_with($base, 'catalog/')) {
            return substr($base, 0, -strlen('catalog/')) . 'admin/';
        }

        return $base . 'admin/';
    }

    /**
     * Gera dev/catalog/config.php e dev/admin/config.php a partir do template.
     * admin usa HTTP_SERVER = {url}admin/ (S5 do plano — precedente OpenCart).
     */
    /** Escape a string safely for single-quoted PHP config literals. */
    public static function escapePhpStringLiteral(string $value): string {
        $clean = str_replace("\0", '', $value);
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $clean);
    }

    /** @param array<string, mixed> $db */
    private function writeConfigs(array $db, string $prefix, string &$error = ''): bool {
        $template = dirname(__DIR__, 2) . '/config.php.tpl'; // dev/install/config.php.tpl
        $dir_dev  = dirname(__DIR__, 3) . '/';               // dev/

        if (!is_file($template)) {
            $error = 'config.php.tpl';

            return false;
        }

        $tpl      = (string)file_get_contents($template);
        $base     = rtrim($this->postScalar('http_server'), '/') . '/';
        $adminUrl = $this->adminUrl($base); // irmão da loja (catalog/ → admin/) — ver método

        // As duas pastas são conferidas antes de gravar a primeira: falha na
        // segunda deixaria o site com meio config.php.
        foreach (['catalog', 'admin'] as $app) {
            if (!is_writable($dir_dev . $app)) {
                $error = $dir_dev . $app . '/config.php';

                return false;
            }
        }

        // ADR 0094 (S9): uma chave por instalação, a MESMA nos dois arquivos.
        $secretKey = \Reamur\System\Library\Security\SecretBox::generateKey();

        foreach (['catalog', 'admin'] as $app) {
            $output = strtr($tpl, [
                '{{HTTP_SERVER}}'  => self::escapePhpStringLiteral($app === 'admin' ? $adminUrl : $base),
                '{{HTTPS_SERVER}}' => self::escapePhpStringLiteral($app === 'admin' ? $adminUrl : $base),
                '{{DIR_REAMUR}}'   => self::escapePhpStringLiteral(dirname($dir_dev) . '/'),
                '{{APP_DIR}}'      => $app,
                '{{DB_DRIVER}}'    => self::escapePhpStringLiteral((string)$db['db_driver']),
                '{{DB_HOSTNAME}}'  => self::escapePhpStringLiteral((string)$db['db_hostname']),
                '{{DB_USERNAME}}'  => self::escapePhpStringLiteral((string)$db['db_username']),
                '{{DB_PASSWORD}}'  => self::escapePhpStringLiteral((string)$db['db_password']),
                '{{DB_DATABASE}}'  => self::escapePhpStringLiteral((string)$db['db_database']),
                '{{DB_PORT}}'      => self::escapePhpStringLiteral((string)$db['db_port']),
                '{{DB_PREFIX}}'    => self::escapePhpStringLiteral($prefix),
                '{{SECRET_KEY}}'   => $secretKey, // base64: sem aspas nem barra invertida
            ]);

            $target = $dir_dev . $app . '/config.php';

            if (!is_writable($dir_dev . $app) || file_put_contents($target, $output) === false) {
                $error = $target;

                return false;
            }
        }

        return true;
    }
}
