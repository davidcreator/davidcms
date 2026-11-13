<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Classe base de todo controller (Arquitetura-MVCL §6).
 * Serviços e models chegam via propriedade mágica a partir do Registry:
 * $this->request · $this->load · $this->model_grupo_modulo · ...
 */
namespace Reamur\System\Engine;

/**
 * @property \Reamur\System\Engine\Config  $config
 * @property \Reamur\System\Library\Request $request
 * @property \Reamur\System\Library\Session $session
 * @property \Reamur\System\Library\Security\Csp $csp
 * @property \Reamur\System\Library\Security\SecretBox $secret_box
 */
class Controller {
    public function __construct(protected Registry $registry) {}

    /** Acesso preguiçoso a qualquer serviço registrado (load, request, model_*...) */
    public function __get(string $key): mixed {
        return $this->registry->get($key);
    }

    /**
     * Cifra dos segredos do banco (ADR 0094). Num Registry sem o serviço
     * (testes, instalador) devolve uma instância sem chave: grava e lê como antes.
     */
    protected function secrets(): \Reamur\System\Library\Security\SecretBox {
        $box = $this->registry->get('secret_box');

        return $box instanceof \Reamur\System\Library\Security\SecretBox
            ? $box
            : new \Reamur\System\Library\Security\SecretBox();
    }

    /** Valida o token CSRF enviado via POST contra a sessão atual. */
    protected function csrfValid(): bool {
        $session = $this->registry->get('session');
        if (!$session instanceof \Reamur\System\Library\Session) {
            return false;
        }

        $request = $this->registry->get('request');
        $token = ($request instanceof \Reamur\System\Library\Request)
            ? ($request->post['csrf_token'] ?? '')
            : '';

        return \Reamur\System\Library\Security\Csrf::check(
            $session,
            is_scalar($token) ? (string)$token : ''
        );
    }

    /** Lê um campo escalar de POST com fallback seguro (evita TypeError/Array-to-string em inputs array). */
    protected function postScalar(string $key, string $default = ''): string {
        $request = $this->registry->get('request');
        if (!$request instanceof \Reamur\System\Library\Request) {
            return $default;
        }

        $value = $request->post[$key] ?? null;

        return is_scalar($value) ? (string)$value : $default;
    }

    /**
     * Lê e limpa a mensagem flash armazenada na sessão.
     *
     * @return array{type: string, text: string}
     */
    protected function flash(): array {
        $session = $this->registry->get('session');
        $data = ['type' => '', 'text' => ''];

        if ($session instanceof \Reamur\System\Library\Session && !empty($session->data['flash'])) {
            $flash = (array)$session->data['flash'];
            $data['type'] = (string)($flash['type'] ?? 'success');
            $data['text'] = (string)($flash['text'] ?? '');
            unset($session->data['flash']);
        }

        return $data;
    }

    /** Grava mensagem flash na sessão para a próxima renderização. */
    protected function setFlash(string $text, string $type = 'success'): void {
        $session = $this->registry->get('session');

        if ($session instanceof \Reamur\System\Library\Session) {
            $session->data['flash'] = ['type' => $type, 'text' => $text];
        }
    }

    /** Lê parâmetro escalar da query string (GET) normalizado com trim. */
    protected function queryValue(string $key): string {
        $request = $this->registry->get('request');
        $value = ($request instanceof \Reamur\System\Library\Request)
            ? ($request->get[$key] ?? '')
            : '';

        return is_scalar($value) ? trim((string)$value) : '';
    }

    /** Lê um parâmetro inteiro escalar da query string (GET) com fallback seguro. */
    protected function queryInt(string $key, int $default = 0): int {
        $raw = $this->queryValue($key);
        if ($raw === '' || preg_match('/^-?\d+$/', $raw) !== 1) {
            return $default;
        }

        return (int)$raw;
    }

    /** Valida URL externa para links públicos (somente http/https, sem controle/CRLF/NUL). */
    protected function safeExternalUrl(string $url): string {
        $clean = trim($url);
        if ($clean === '' || preg_match('/[\x00-\x20\x7f]/', $clean) === 1) {
            return '';
        }

        $scheme = strtolower((string)parse_url($clean, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        return filter_var($clean, FILTER_VALIDATE_URL) !== false ? $clean : '';
    }

    /** Base URL pública do catálogo (converte sufixo admin/ em catalog/). */
    protected function catalogUrl(): string {
        $config = $this->registry->get('config');
        $base = ($config instanceof Config) ? (string)$config->get('site_url', '') : '';

        if (str_ends_with($base, 'admin/')) {
            return substr($base, 0, -strlen('admin/')) . 'catalog/';
        }

        return $base;
    }

    /** Base URL pública da pasta catalog/ (preserva /catalog/ explícito ou converte sufixo admin/). */
    protected function catalogAssetUrl(): string {
        $config = $this->registry->get('config');
        $base = ($config instanceof Config) ? (string)$config->get('site_url', '') : '';

        if (str_ends_with($base, 'admin/')) {
            return substr($base, 0, -strlen('admin/')) . 'catalog/';
        }
        if (str_ends_with($base, 'catalog/')) {
            return $base;
        }

        return rtrim($base, '/') . '/catalog/';
    }

    /**
     * Emite resposta JSON UTF-8 padronizada no objeto Response.
     *
     * @param array<string|int, mixed> $payload
     */
    protected function jsonResponse(array $payload, int $status = 200, bool $noStore = false): void {
        $response = $this->registry->get('response');
        if (!$response instanceof \Reamur\System\Library\Response) {
            return;
        }

        if ($status !== 200 && PHP_SAPI !== 'cli' && !headers_sent()) {
            http_response_code($status);
        }

        $response->addHeader('Content-Type: application/json; charset=utf-8');
        $response->addHeader('X-Content-Type-Options: nosniff');
        if ($noStore) {
            $response->addHeader('Cache-Control: no-store, private');
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $response->setOutput($json === false ? '{"success":false}' : $json);
    }

    /** Monta CSS base, classe do body e header/footer padrão do painel admin. */
    protected function adminChrome(array &$data, string $assetVersion, ?string $bodyClass = null): void {
        $config   = $this->registry->get('config');
        $document = $this->registry->get('document');
        $load     = $this->registry->get('load');

        $siteUrl = ($config instanceof Config) ? (string)$config->get('site_url', '') : '';
        $suffix  = $assetVersion !== '' ? ('?v=' . $assetVersion) : '';

        if ($document instanceof \Reamur\System\Library\Document) {
            $document->addStyle($siteUrl . 'view/css/admin.css' . $suffix);
        }

        if ($bodyClass !== null && $bodyClass !== '') {
            $data['body_class'] = $bodyClass;
        }

        if ($load instanceof Loader) {
            $data['header'] = $load->controller('common/header', $data);
            $data['footer'] = $load->controller('common/footer', $data);
        }
    }

    /** Traduz o código de erro padronizado de upload de mídia do admin. */
    protected function uploadErrorMessage(string $code, string $maxSize = '2 MB'): string {
        $language = $this->registry->get('language');
        if (!$language instanceof \Reamur\System\Library\Language) {
            return 'Upload error';
        }

        return match ($code) {
            'size'  => sprintf($language->get('error_upload_size'), $maxSize),
            'type'  => $language->get('error_upload_type'),
            'move'  => $language->get('error_upload_move'),
            default => $language->get('error_upload'),
        };
    }

    /** Instancia o serviço de upload apontando para o diretório catalog/ irmão. */
    protected function catalogMedia(): \Reamur\System\Library\MediaUpload {
        return new \Reamur\System\Library\MediaUpload(
            rtrim((string)dirname(DIR_APPLICATION), '/\\') . '/catalog/'
        );
    }

    /** Executa o endpoint JSON padrão de upload de mídia no admin (POST + ACL + CSRF). */
    protected function handleAdminUpload(string $route, string $uploadDir): void {
        $load     = $this->registry->get('load');
        $request  = $this->registry->get('request');
        $language = $this->registry->get('language');
        $user     = $this->registry->get('user');
        $response = $this->registry->get('response');

        if ($load instanceof Loader) {
            $load->language($route);
        }

        $translate = static fn(string $key, string $fallback): string => ($language instanceof \Reamur\System\Library\Language)
            ? $language->get($key)
            : $fallback;

        $ok   = false;
        $msg  = '';
        $path = '';

        $method = ($request instanceof \Reamur\System\Library\Request)
            ? (string)($request->server['REQUEST_METHOD'] ?? '')
            : '';

        if ($method !== 'POST') {
            $msg = $translate('error_upload_method', 'Method not allowed');
        } elseif (!$user instanceof \Reamur\System\Library\User || !$user->hasPermission('modify', $route)) {
            $msg = $translate('error_permission', 'Permission denied');
        } elseif (!$this->csrfValid()) {
            $msg = $translate('error_csrf', 'Invalid CSRF token');
        } else {
            $rawFile = ($request instanceof \Reamur\System\Library\Request)
                ? ($request->files['file'] ?? [])
                : [];
            /** @var array<string, mixed> $files */
            $files  = is_array($rawFile) ? $rawFile : [];
            $result = $this->catalogMedia()->store($files, $uploadDir);

            if ($result['ok']) {
                $ok   = true;
                $path = $result['path'];
            } else {
                $msg = $this->uploadErrorMessage((string)$result['error']);
            }
        }

        if ($response instanceof \Reamur\System\Library\Response) {
            $response->addHeader('Content-Type: application/json; charset=utf-8');
            $response->setOutput((string)json_encode([
                'success' => $ok,
                'path'    => $ok ? $path : '',
                'error'   => $ok ? '' : $msg,
            ]));
        }
    }

    /**
     * Renderiza paginação padrão do painel admin com labels traduzíveis opcionais.
     *
     * @param array{first?: string, last?: string, next?: string, prev?: string} $labels
     */
    protected function adminPagination(
        int $total,
        int $page,
        int $limit,
        string $url,
        array $labels = [],
        string $cssClass = 'pagination pagination-sm mb-0'
    ): string {
        if (!class_exists(\Pagination::class, false)) {
            require_once DIR_SYSTEM . 'library/pagination.php';
        }

        $pagination = new \Pagination();
        $pagination->total     = $total;
        $pagination->page      = $page;
        $pagination->limit     = $limit;
        $pagination->url       = $url;
        $pagination->css_class = $cssClass;

        if (isset($labels['first'])) {
            $pagination->text_first = $labels['first'];
        }
        if (isset($labels['last'])) {
            $pagination->text_last = $labels['last'];
        }
        if (isset($labels['next'])) {
            $pagination->text_next = $labels['next'];
        }
        if (isset($labels['prev'])) {
            $pagination->text_prev = $labels['prev'];
        }

        return $pagination->render();
    }

    /**
     * Monta os labels de paginação a partir do serviço Language (`text` ou `button`).
     *
     * @return array{first: string, last: string, next: string, prev: string}
     */
    protected function paginationLabels(string $style = 'text'): array {
        $language = $this->registry->get('language');
        if (!$language instanceof \Reamur\System\Library\Language) {
            return [
                'first' => '« First',
                'last'  => 'Last »',
                'next'  => 'Next ›',
                'prev'  => '‹ Prev',
            ];
        }

        if ($style === 'button') {
            return [
                'first' => $language->get('button_first_page'),
                'last'  => $language->get('button_last_page'),
                'next'  => $language->get('button_next_page'),
                'prev'  => $language->get('button_previous_page'),
            ];
        }

        return [
            'first' => $language->get('text_page_first'),
            'last'  => $language->get('text_page_last'),
            'next'  => $language->get('text_page_next'),
            'prev'  => $language->get('text_page_prev'),
        ];
    }

    /**
     * Monta CSS base, classe do body e header/footer padrão do catálogo público.
     *
     * @param array<string, mixed>  $data
     * @param array<int, string>    $extraStyles  caminhos relativos a site_url
     * @param array<int, string>    $extraScripts caminhos relativos a site_url
     */
    protected function catalogChrome(
        array &$data,
        string $bodyClass = '',
        array $extraStyles = [],
        array $extraScripts = []
    ): void {
        $config   = $this->registry->get('config');
        $document = $this->registry->get('document');
        $load     = $this->registry->get('load');

        $siteUrl = ($config instanceof Config) ? (string)$config->get('site_url', '') : '';

        if ($document instanceof \Reamur\System\Library\Document) {
            $document->addStyle($siteUrl . 'view/css/common.css');
            foreach ($extraStyles as $style) {
                $document->addStyle($siteUrl . $style);
            }
            foreach ($extraScripts as $script) {
                $document->addScript($siteUrl . $script);
            }
        }

        if ($bodyClass !== '') {
            $data['body_class'] = $bodyClass;
        }

        if ($load instanceof Loader) {
            $data['header'] = $load->controller('common/header', $data);
            $data['footer'] = $load->controller('common/footer', $data);
        }
    }

    /** Monta chrome padrão do wizard de instalação (header + seletor de idiomas + footer). */
    protected function installChrome(array &$data): void {
        $language = $this->registry->get('language');
        $load     = $this->registry->get('load');

        if ($language instanceof \Reamur\System\Library\Language) {
            $data['page_title'] = $language->get('heading_title');
        }

        if ($load instanceof Loader) {
            $data['header']   = $load->controller('common/header', $data);
            $data['language'] = $load->controller('common/language');
            $data['footer']   = $load->controller('common/footer');
        }
    }

    /**
     * Despacha evento de webhook para os endpoints ativos e persiste logs sem
     * propagar exceções para o fluxo de negócio.
     *
     * @param array<string, mixed>|\Closure(): array<string, mixed> $payload
     */
    protected function dispatchWebhookEvent(string $event, array|\Closure $payload, string $contextLabel = ''): void {
        $load = $this->registry->get('load');
        $log  = $this->registry->get('log');

        if (!$load instanceof Loader || !$log instanceof \Reamur\System\Library\Log) {
            return;
        }

        try {
            $load->model('setting/webhook');

            $webhookModel = $this->registry->get('model_setting_webhook');
            if (!is_object($webhookModel)) {
                return;
            }

            $getHooks = [$webhookModel, 'getHooksForEvent'];
            $addLog   = [$webhookModel, 'addLog'];
            if (!is_callable($getHooks) || !is_callable($addLog)) {
                return;
            }

            $hooks = $getHooks($event);
            if (!is_array($hooks) || $hooks === []) {
                return;
            }

            $payloadData = $payload instanceof \Closure ? $payload() : $payload;
            if (!is_array($payloadData)) {
                return;
            }

            $dispatcher = new \Reamur\System\Library\Webhook\Dispatcher($log);
            foreach ($dispatcher->dispatch($event, $payloadData, $hooks) as $r) {
                $addLog(
                    (int)$r['webhook_id'],
                    (string)$r['delivery'],
                    (string)$r['payload'],
                    (int)$r['http_code'],
                    (float)$r['duration'],
                    (int)$r['attempt']
                );
            }
        } catch (\Throwable $e) {
            $suffix = $contextLabel !== '' ? " ({$contextLabel})" : '';
            $log->write("webhook {$event}{$suffix} falhou e foi isolado: " . $e->getMessage());
        }
    }
}
