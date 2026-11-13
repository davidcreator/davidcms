<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * extension/extension (EX-01, ADR 0095) — tela "Extensões": o que há na pasta
 * dev/extension/, ligar, desligar, configurar e apagar os dados gravados.
 *
 * Esta tela NUNCA carrega o PHP de uma extensão: tudo o que mostra vem do
 * extension.json (Library\Extension\Manifest), inclusive o formulário de
 * configuração. E a pré-ação startup/extension não põe nenhuma extensão para
 * rodar nesta rota: se uma extensão quebrar o admin, é aqui que se desliga.
 *
 * A instalação é pela pasta no servidor (FTP/deploy). Não há envio de arquivo.
 *
 * Rotas: /extension/extension · .edit|codigo (GET formulário, POST grava)
 * · .enable|codigo · .disable|codigo · .purge|codigo (as três só POST).
 * Toda alteração exige permissão de modificar em 'extension/extension' e o
 * token CSRF.
 */
namespace Reamur\Admin\Controller\Extension;

use Reamur\System\Library\Extension\Manager;
use Reamur\System\Library\Extension\Manifest;
use Reamur\System\Library\Extension\ManifestException;
use Reamur\System\Library\Security\SecretBox;

class Extension extends \Reamur\System\Engine\Controller {

    private const ROUTE = 'extension/extension';

    /** Lista do que está na pasta, com o estado de cada extensão. */
    public function index(): void {
        $this->load->language(self::ROUTE);
        $this->document->setTitle($this->language->get('heading_title'));

        $manager  = $this->manager();
        $state    = $manager->store()->all();
        $language = $this->languageCode();

        $data = $this->texts([
            'heading_title', 'text_list', 'text_install_hint', 'text_trust_hint', 'text_no_results', 'text_close',
            'text_enabled', 'text_disabled', 'text_invalid', 'text_incompatible', 'text_folder_missing',
            'text_runs_in', 'text_app_catalog', 'text_app_admin', 'text_events', 'text_no_events',
            'text_sources', 'text_no_sources', 'text_problems', 'text_blockers', 'text_details',
            'text_confirm_enable', 'text_confirm_purge', 'text_off_by_config', 'text_no_folder',
            'text_version', 'text_author', 'text_folder_missing_hint', 'text_secret_locked',
            'column_extension', 'column_status', 'column_action',
            'button_enable', 'button_disable', 'button_configure', 'button_purge',
        ]);

        $data['text_flash']  = $this->flash();
        $data['csrf_token']  = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['can_modify']  = $this->user->hasPermission('modify', self::ROUTE);
        $data['off']         = $manager->isOff();
        $data['folder']      = $manager->directory();
        $data['folder_ok']   = $manager->directory() !== '' && is_dir($manager->directory());
        $data['extensions']  = [];

        foreach ($manager->discover() as $code => $found) {
            $code     = (string)$code;
            $row      = $state[$code] ?? ['enabled' => false, 'config' => [], 'secrets' => []];
            $manifest = $found['manifest'];
            unset($state[$code]);

            $entry = [
                'code'        => $code,
                'name'        => $code,
                'description' => '',
                'version'     => '',
                'author'      => '',
                'link'        => '',
                'enabled'     => $row['enabled'],
                'status'      => 'invalid',
                'problems'    => $found['problems'],
                'blockers'    => [],
                'apps'        => [],
                'events'      => [],
                'sources'     => [],
                'has_settings' => false,
                'has_data'    => $row['config'] !== [] || $row['secrets'] !== [],
                'locked'      => false,
                'can_enable'  => false,
                'missing'     => false,
            ] + $this->links($code, Manifest::isCode($code));

            if ($manifest instanceof Manifest) {
                $secretsSet = array_keys($row['secrets']);
                $blockers   = $manager->blockers($manifest, $row['config'], $secretsSet);
                $compat     = $manifest->incompatibilities($manager->cmsVersion(), $manager->phpVersion());

                $entry['name']         = $manifest->name($language);
                $entry['description']  = $manifest->description($language);
                $entry['version']      = $manifest->version();
                $entry['author']       = $manifest->author();
                $entry['link']         = $manifest->link();
                $entry['apps']         = $manifest->apps();
                $entry['has_settings'] = $manifest->settings() !== [];
                $entry['blockers']     = $blockers;
                $entry['can_enable']   = $blockers === [];
                $entry['status']       = $compat !== [] ? 'incompatible' : ($row['enabled'] ? 'enabled' : 'disabled');
                $entry['locked']       = $this->anyLocked($code, $manifest, $row['secrets']);

                // Dois ouvintes do mesmo evento aparecem uma vez só.
                $entry['events'] = array_values(array_unique(array_column($manifest->events(), 'trigger')));

                foreach ($manifest->csp() as $directive => $sources) {
                    $entry['sources'][] = $directive . ' ' . implode(' ', $sources);
                }
            }

            $data['extensions'][] = $entry;
        }

        // Estado gravado de uma extensão cuja pasta não está mais no servidor.
        foreach ($state as $code => $row) {
            $code = (string)$code;

            $data['extensions'][] = [
                'code'        => $code,
                'name'        => $code,
                'description' => '',
                'version'     => '',
                'author'      => '',
                'link'        => '',
                'enabled'     => $row['enabled'],
                'status'      => 'missing',
                'problems'    => [],
                'blockers'    => [],
                'apps'        => [],
                'events'      => [],
                'sources'     => [],
                'has_settings' => false,
                'has_data'    => true,
                'locked'      => false,
                'can_enable'  => false,
                'missing'     => true,
            ] + $this->links($code, true);
        }

        $this->adminChrome($data, \Reamur\Admin\Controller\Common\Header::ASSET_V, 'rms-dash');
        $this->response->setOutput($this->load->view('extension/extension_list', $data));
    }

    /** GET: formulário gerado a partir do manifesto · POST: confere e grava. */
    public function edit(string $code = ''): void {
        $this->load->language(self::ROUTE);
        $this->document->setTitle($this->language->get('heading_title'));

        $isPost = ($this->request->server['REQUEST_METHOD'] ?? 'GET') === 'POST';

        if ($isPost && !$this->user->hasPermission('modify', self::ROUTE)) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $manifest = $this->manifestOrBack($code);

        if ($manifest === null) {
            return;
        }

        if ($manifest->settings() === []) {
            $this->setFlash($this->language->get('error_no_settings'), 'error');
            $this->response->redirect($this->url->link(self::ROUTE));

            return;
        }

        $manager    = $this->manager();
        $row        = $manager->store()->all()[$code] ?? ['enabled' => false, 'config' => [], 'secrets' => []];
        $secretsSet = array_keys($row['secrets']);
        $values     = $manifest->resolve($row['config']);
        $errors     = [];
        $error      = '';

        if ($isPost) {
            if (!$this->csrfValid()) {
                $error = $this->language->get('error_csrf');
            } else {
                $result = $manifest->validate((array)$this->request->post, $secretsSet);
                $values = $result['values'];
                $errors = $result['errors'];

                if ($errors === []) {
                    $store = $manager->store();
                    $store->saveConfig($code, $result['values']);

                    foreach ($result['secrets'] as $field => $secret) {
                        if ($secret === null) {
                            $store->deleteSecret($code, (string)$field);
                        } else {
                            $store->saveSecret($code, (string)$field, $secret);
                        }
                    }

                    $this->setFlash(sprintf($this->language->get('text_success_save'), $manifest->name($this->languageCode())));
                    $this->response->redirect($this->url->link(self::ROUTE));

                    return;
                }

                $error = $this->language->get('error_form');
            }
        }

        $language = $this->languageCode();

        $data = $this->texts([
            'heading_title', 'text_form', 'text_secret_set', 'text_secret_unset', 'text_secret_hint',
            'text_secret_locked', 'text_secret_clear', 'text_secret_clear_help', 'text_secret_no_key',
            'text_select_none', 'text_required', 'button_save', 'button_cancel',
        ]);

        $data['error']       = $error;
        $data['csrf_token']  = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['can_modify']  = $this->user->hasPermission('modify', self::ROUTE);
        $data['action']      = $this->url->link(self::ROUTE . '.edit|' . $code);
        $data['cancel']      = $this->url->link(self::ROUTE);
        $data['name']        = $manifest->name($language);
        $data['code']        = $code;
        $data['version']     = $manifest->version();
        $data['description'] = $manifest->description($language);
        $data['enabled']     = $row['enabled'];
        $data['can_seal']    = $this->secrets()->canSeal();
        $data['has_secret']  = $manifest->secretKeys() !== [];
        $data['fields']      = [];

        foreach ($manifest->settings() as $field) {
            $key     = $field['key'];
            $options = [];

            foreach ($field['options'] as $option) {
                $options[] = ['value' => $option['value'], 'label' => Manifest::pick($option['label'], $language)];
            }

            $stored = (string)($row['secrets'][$key] ?? '');

            $data['fields'][] = [
                'key'         => $key,
                'type'        => $field['type'],
                'label'       => Manifest::pick($field['label'], $language),
                'help'        => Manifest::pick($field['help'], $language),
                'placeholder' => $field['placeholder'],
                'required'    => $field['required'],
                'max'         => $field['max'],
                'options'     => $options,
                // O valor de um segredo nunca volta para a tela.
                'value'       => $field['type'] === 'secret' ? '' : ($values[$key] ?? $field['default']),
                'error'       => isset($errors[$key]) ? $this->fieldError($errors[$key], $field['max']) : '',
                'secret_set'  => $field['type'] === 'secret' && $stored !== '',
                'secret_locked' => $field['type'] === 'secret' && $stored !== ''
                    && $this->secrets()->locked($stored, SecretBox::extensionContext($code, $key)),
            ];
        }

        $this->adminChrome($data, \Reamur\Admin\Controller\Common\Header::ASSET_V, 'rms-dash');
        $this->response->setOutput($this->load->view('extension/extension_form', $data));
    }

    /** POST: liga a extensão, se nada a impede. */
    public function enable(string $code = ''): void {
        if (!$this->guardPost()) {
            return;
        }

        $manifest = $this->manifestOrBack($code);

        if ($manifest === null) {
            return;
        }

        $manager  = $this->manager();
        $row      = $manager->store()->all()[$code] ?? ['enabled' => false, 'config' => [], 'secrets' => []];
        $blockers = $manager->blockers($manifest, $row['config'], array_keys($row['secrets']));
        $name     = $manifest->name($this->languageCode());

        if ($blockers !== []) {
            $this->setFlash(sprintf($this->language->get('error_blocked'), $name, implode('; ', $blockers)), 'error');
        } else {
            $manager->store()->setEnabled($code, true);
            $this->log->write('extension ' . $code . ' ' . $manifest->version() . ': enabled by ' . $this->user->getUsername());
            $this->setFlash(sprintf($this->language->get('text_success_enable'), $name));
        }

        $this->response->redirect($this->url->link(self::ROUTE));
    }

    /** POST: desliga. Vale também para uma extensão cuja pasta sumiu ou cujo manifesto ficou inválido. */
    public function disable(string $code = ''): void {
        if (!$this->guardPost()) {
            return;
        }

        if (!Manifest::isCode($code)) {
            $this->setFlash($this->language->get('error_not_found'), 'error');
        } else {
            $this->manager()->store()->setEnabled($code, false);
            $this->log->write('extension ' . $code . ': disabled by ' . $this->user->getUsername());

            // Desligar não depende do manifesto; o nome é só para a mensagem.
            try {
                $name = $this->manager()->manifest($code)->name($this->languageCode());
            } catch (ManifestException $e) {
                $name = $code;
            }

            $this->setFlash(sprintf($this->language->get('text_success_disable'), $name));
        }

        $this->response->redirect($this->url->link(self::ROUTE));
    }

    /** POST: apaga o que a extensão gravou (estado, configuração, segredos). Só com ela desligada. */
    public function purge(string $code = ''): void {
        if (!$this->guardPost()) {
            return;
        }

        $store = $this->manager()->store();

        if (!Manifest::isCode($code)) {
            $this->setFlash($this->language->get('error_not_found'), 'error');
        } elseif (!empty($store->all()[$code]['enabled'])) {
            $this->setFlash($this->language->get('error_purge_enabled'), 'error');
        } else {
            $store->purge($code);
            $this->log->write('extension ' . $code . ': stored data removed by ' . $this->user->getUsername());
            $this->setFlash(sprintf($this->language->get('text_success_purge'), $code));
        }

        $this->response->redirect($this->url->link(self::ROUTE));
    }

    // ── apoio ───────────────────────────────────────────────────────────────

    private function manager(): Manager {
        $manager = $this->registry->get('extensions');

        if ($manager instanceof Manager) {
            return $manager;
        }

        return new Manager(
            $this->registry,
            defined('DIR_EXTENSION') ? (string)DIR_EXTENSION : '',
            defined('REAMUR_VERSION') ? (string)REAMUR_VERSION : '0.0.0'
        );
    }

    private function languageCode(): string {
        return strtolower((string)$this->config->get('language_directory', 'en-us')) ?: 'en-us';
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private function texts(array $keys): array {
        $out = [];

        foreach ($keys as $key) {
            $out[$key] = $this->language->get($key);
        }

        return $out;
    }

    /** @return array{edit: string, enable: string, disable: string, purge: string} */
    private function links(string $code, bool $valid): array {
        $out = ['edit' => '', 'enable' => '', 'disable' => '', 'purge' => ''];

        if ($valid) {
            foreach (array_keys($out) as $action) {
                $out[$action] = $this->url->link(self::ROUTE . '.' . $action . '|' . $code);
            }
        }

        return $out;
    }

    /** Alteração por POST: método, permissão e CSRF. Falhou → volta à lista (ou à tela de permissão). */
    private function guardPost(): bool {
        $this->load->language(self::ROUTE);

        if (($this->request->server['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->response->redirect($this->url->link(self::ROUTE));

            return false;
        }

        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            $this->response->redirect($this->url->link('error/permission'));

            return false;
        }

        if (!$this->csrfValid()) {
            $this->setFlash($this->language->get('error_csrf'), 'error');
            $this->response->redirect($this->url->link(self::ROUTE));

            return false;
        }

        return true;
    }

    /** Manifesto da extensão pedida; se não há ou é inválido, avisa e volta à lista. */
    private function manifestOrBack(string $code): ?Manifest {
        try {
            return $this->manager()->manifest($code);
        } catch (ManifestException $e) {
            $this->setFlash($this->language->get('error_not_found'), 'error');
            $this->response->redirect($this->url->link(self::ROUTE));

            return null;
        }
    }

    /** @param array<string, string> $secrets valores como estão no banco */
    private function anyLocked(string $code, Manifest $manifest, array $secrets): bool {
        foreach ($manifest->secretKeys() as $key) {
            $stored = (string)($secrets[$key] ?? '');

            if ($stored !== '' && $this->secrets()->locked($stored, SecretBox::extensionContext($code, $key))) {
                return true;
            }
        }

        return false;
    }

    private function fieldError(string $problem, int $max): string {
        return match ($problem) {
            'required' => $this->language->get('error_required'),
            'too_long' => sprintf($this->language->get('error_too_long'), $max),
            'option'   => $this->language->get('error_option'),
            default    => $this->language->get('error_pattern'),
        };
    }
}
