<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * catalog/project — CRUD do portfólio (F2-8, ADR 0008) — padrão-ouro de módulo.
 * Rotas: /catalog/project (lista) · ?route=catalog/project.add
 * · ?route=catalog/project.edit|N · ?route=catalog/project.delete|N (POST)
 * · ?route=catalog/project.upload (POST multipart → JSON).
 * Args de método via gramática 'rota.metodo|arg1' do Action — NUNCA via
 * querystring. ACL (Adendo F2-6 do ADR 0005): granularidade base-route —
 * 'catalog/project' cobre TODOS os métodos; POST exige direito 'modify'.
 */
namespace Reamur\Admin\Controller\Catalog;

class Project extends \Reamur\System\Engine\Controller {

    /** Diretório (rel. ao catalog) onde os uploads de imagem moram */
    private const UPLOAD_DIR  = 'view/image/project/';
    /** MIMEs aceitos + teto de tamanho */
    private const UPLOAD_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const UPLOAD_MAX  = 2097152; // 2 MB

    /** Lista projetos (200) */
    public function index(): void {
        $this->load->language('catalog/project');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/project');

        $data['heading_title']       = $this->language->get('heading_title');
        $data['text_list']           = $this->language->get('text_list');
        $data['text_no_results']     = $this->language->get('text_no_results');
        $data['text_confirm_delete'] = $this->language->get('text_confirm_delete');
        $data['text_enabled']        = $this->language->get('text_enabled');
        $data['text_disabled']       = $this->language->get('text_disabled');
        $data['text_yes']            = $this->language->get('text_yes');
        $data['text_no']             = $this->language->get('text_no');
        $data['text_placeholder']    = $this->language->get('text_placeholder');
        $data['column_image']        = $this->language->get('column_image');
        $data['column_name']         = $this->language->get('column_name');
        $data['column_client']       = $this->language->get('column_client');
        $data['column_featured']     = $this->language->get('column_featured');
        $data['column_status']       = $this->language->get('column_status');
        $data['column_sort_order']   = $this->language->get('column_sort_order');
        $data['column_date_modified'] = $this->language->get('column_date_modified');
        $data['column_action']       = $this->language->get('column_action');
        $data['button_add']          = $this->language->get('button_add');
        $data['button_edit']         = $this->language->get('button_edit');
        $data['button_delete']       = $this->language->get('button_delete');
        $data['text_flash']          = $this->flash();

        $catalog = $this->catalogUrl();

        $data['projects'] = [];

        foreach ($this->model_catalog_project->getProjects() as $project) {
            $project = (array)$project;
            $pid     = (int)$project['project_id'];
            $image   = (string)$project['image'];

            $data['projects'][] = [
                'project_id'    => $pid,
                'thumb'         => $image !== '' ? $catalog . $image : '',
                'name'          => (string)$project['name'],
                'client'        => (string)$project['client'],
                'featured'      => (int)$project['featured'],
                'status'        => (int)$project['status'],
                'sort_order'    => (int)$project['sort_order'],
                'date_modified' => (string)$project['date_modified'],
                'edit'          => $this->url->link('catalog/project.edit|' . $pid),
                'delete'        => $this->url->link('catalog/project.delete|' . $pid),
            ];
        }

        $data['add'] = $this->url->link('catalog/project.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('catalog/project_list', $data));
    }

    /** GET: formulário de criação · POST: valida e persiste; sucesso → lista + flash */
    public function add(): void {
        if (!$this->user->hasPermission('modify', 'catalog/project') && $this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('catalog/project');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/project');

        $data = $this->formData([]);

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validateProject(0);

            if ($errors === []) {
                $project_id = $this->model_catalog_project->addProject($this->postPayload());

                if ($project_id) {
                    $this->setFlash($this->language->get('text_success_add'));

                    $this->response->redirect($this->url->link('catalog/project'));

                    return;
                }
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('catalog/project.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('catalog/project_form', $data));
    }

    /** GET: formulário de edição · POST: valida e persiste (args do Action são strings) */
    public function edit(string $project_id = '0'): void {
        $project_id = (int)$project_id;

        $this->load->language('catalog/project');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/project');

        $project = $this->model_catalog_project->getProject($project_id);

        if (!$project) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $data          = $this->formData($project);
        $data['error'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'catalog/project')) {
                $this->response->redirect($this->url->link('error/permission'));

                return;
            }

            $errors = $this->validateProject($project_id);

            if ($errors === []) {
                $this->model_catalog_project->editProject($project_id, $this->postPayload());

                $this->setFlash($this->language->get('text_success_edit'));

                $this->response->redirect($this->url->link('catalog/project'));

                return;
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('catalog/project.edit|' . $project_id);

        $this->chrome($data);

        $this->response->setOutput($this->load->view('catalog/project_form', $data));
    }

    /** Exclui via POST (cascata manual; GET jamais muta) */
    public function delete(string $project_id = '0'): void {
        $project_id = (int)$project_id;

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'catalog/project')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('catalog/project');

        $this->load->model('catalog/project');

        $project = $this->model_catalog_project->getProject($project_id);

        if ($project) {
            // Limpa arquivos físicos das imagens (best-effort — DB manda)
            $files = [(string)$project['image']];

            foreach ($this->model_catalog_project->getProjectImages($project_id) as $img) {
                $files[] = (string)$img['image'];
            }

            foreach ($files as $file) {
                if ($file !== '') {
                    $path = $this->imagePath($file);

                    if ($path !== '' && is_file($path)) {
                        @unlink($path);
                    }
                }
            }

            $this->model_catalog_project->deleteProject($project_id);

            $this->setFlash($this->language->get('text_success_delete'));
        }

        $this->response->redirect($this->url->link('catalog/project'));
    }

    /**
     * Upload de imagem (capa/galeria) — POST multipart campo 'file'.
     * Valida MIME real (finfo) + tamanho, nome aleatório, retorna JSON
     * {success, path} — o form guarda o path em input oculto até o save.
     */
    public function upload(): void {
        $this->load->language('catalog/project');

        $ok   = false;
        $msg  = '';
        $path = '';

        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $msg = $this->language->get('error_upload_method');
        } elseif (!$this->user->hasPermission('modify', 'catalog/project')) {
            $msg = $this->language->get('error_permission');
        } else {
            $f = (array)($this->request->files['file'] ?? []);

            $tmp  = (string)($f['tmp_name'] ?? '');
            $size = (int)($f['size'] ?? 0);

            if ($tmp === '' || (int)($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                $msg = $this->language->get('error_upload');
            } elseif ($size <= 0 || $size > self::UPLOAD_MAX) {
                $msg = sprintf($this->language->get('error_upload_size'), '2 MB');
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = $finfo ? (string)(finfo_file($finfo, $tmp) ?: '') : '';

                if ($finfo) {
                    finfo_close($finfo);
                }

                if (!in_array($mime, self::UPLOAD_MIME, true)) {
                    $msg = $this->language->get('error_upload_type');
                } else {
                    $ext = match ($mime) {
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                        default      => 'gif',
                    };

                    $dir = rtrim((string)dirname(DIR_APPLICATION), '/') . '/catalog/' . self::UPLOAD_DIR;

                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }

                    $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;

                    if (@move_uploaded_file($tmp, $dir . $filename)) {
                        $ok   = true;
                        $path = self::UPLOAD_DIR . $filename;
                    } else {
                        $msg = $this->language->get('error_upload_move');
                    }
                }
            }
        }

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');

        $this->response->setOutput((string)json_encode([
            'success' => $ok,
            'path'    => $ok ? $path : '',
            'error'   => $ok ? '' : $msg,
        ]));
    }

    // ─── internos ────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $project Linha do banco ou [] (create)
     * @return array<string, mixed> Dados do formulário (GET) ou do POST
     */
    private function formData(array $project): array {
        $this->load->language('catalog/project');

        foreach ([
            'heading_title', 'text_edit', 'text_add', 'text_general', 'text_data',
            'text_languages_note', 'text_image_cover', 'text_gallery', 'text_upload_hint',
            'entry_name', 'entry_summary', 'entry_description', 'entry_tag',
            'entry_meta_title', 'entry_meta_description', 'entry_keyword',
            'entry_client', 'entry_url', 'entry_image', 'entry_category',
            'entry_featured', 'entry_status', 'entry_sort_order',
            'entry_alt', 'button_save', 'button_cancel', 'button_upload',
            'button_remove', 'button_add_image', 'text_placeholder',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['error']      = '';
        $data['text_flash'] = '';

        $pid          = (int)($project['project_id'] ?? 0);
        $isPost       = $this->request->server['REQUEST_METHOD'] === 'POST';
        $post         = $this->request->post;
        $descriptions = $pid ? $this->model_catalog_project->getProjectDescriptions($pid) : [];
        $keywords     = $pid ? $this->model_catalog_project->getProjectKeywords($pid) : [];

        // Abas por idioma: sempre TODAS as línguas cadastradas
        $data['languages'] = [];

        foreach ($this->model_catalog_project->getLanguages() as $language) {
            $language = (array)$language;
            $lid      = (int)$language['language_id'];
            $saved    = (array)($descriptions[$lid] ?? []);

            $fields = [];

            foreach (['name', 'summary', 'description', 'tag', 'meta_title', 'meta_description'] as $f) {
                $fields[$f] = (string)($post['project_description'][$lid][$f] ?? ($saved[$f] ?? ''));
            }

            $fields['keyword'] = (string)($post['project_description'][$lid]['keyword'] ?? ($keywords[$lid] ?? ''));

            $data['languages'][] = [
                'language_id' => $lid,
                'name'        => (string)$language['name'],
                'code'        => (string)$language['code'],
                'fields'      => $fields,
            ];
        }

        $data['project_id'] = $pid;
        $data['client']     = (string)($post['client'] ?? ($project['client'] ?? ''));
        $data['url']        = (string)($post['url'] ?? ($project['url'] ?? ''));
        $data['image']      = (string)($post['image'] ?? ($project['image'] ?? ''));
        $data['sort_order'] = (string)($post['sort_order'] ?? (string)($project['sort_order'] ?? '0'));
        $data['featured']   = isset($post['featured']) ? '1' : ($isPost ? '0' : (string)(int)($project['featured'] ?? 0));
        $data['status']     = isset($post['status']) ? '1' : ($isPost ? '0' : (string)(int)($project['status'] ?? 0));

        // Categorias (checkbox N:N)
        $selected = $isPost
            ? array_map('intval', (array)($post['project_category'] ?? []))
            : ($pid ? $this->model_catalog_project->getProjectCategories($pid) : []);

        $data['categories'] = [];

        foreach ($this->model_catalog_project->getCategories() as $cat) {
            $cat = (array)$cat;
            $cid = (int)$cat['category_id'];

            $data['categories'][] = [
                'category_id' => $cid,
                'name'        => (string)$cat['name'],
                'checked'     => in_array($cid, $selected, true),
            ];
        }

        // Galeria: POST ou banco
        $data['images'] = [];

        $rawImages = $isPost
            ? (array)($post['project_image'] ?? [])
            : $this->model_catalog_project->getProjectImages($pid);

        foreach ($rawImages as $img) {
            $img = (array)$img;

            if (trim((string)($img['image'] ?? '')) === '') {
                continue;
            }

            $data['images'][] = [
                'image'      => (string)$img['image'],
                'alt'        => (string)($img['alt'] ?? ''),
                'sort_order' => (string)($img['sort_order'] ?? '0'),
            ];
        }

        $data['upload']     = $this->url->link('catalog/project.upload');
        $data['catalog']    = $this->catalogUrl();
        $data['cancel']     = $this->url->link('catalog/project');
        $data['text_error_upload_js'] = $this->language->get('error_upload');

        return $data;
    }

    /**
     * Payload normalizado do POST para o model (chaves SEMPRE presentes — lição F2).
     *
     * @return array{image: string, client: string, url: string, status: int, featured: int, sort_order: int, descriptions: array<int, array<string, string>>, images: array<int, array<string, mixed>>, categories: array<int, int>}
     */
    private function postPayload(): array {
        $post = $this->request->post;

        $descriptions = [];

        foreach ((array)($post['project_description'] ?? []) as $lid => $d) {
            $d = (array)$d;

            $descriptions[(int)$lid] = [
                'name'             => mb_substr(trim((string)($d['name'] ?? '')), 0, 255),
                'summary'          => mb_substr(trim((string)($d['summary'] ?? '')), 0, 255),
                'description'      => trim((string)($d['description'] ?? '')),
                'tag'              => mb_substr(trim((string)($d['tag'] ?? '')), 0, 255),
                'meta_title'       => mb_substr(trim((string)($d['meta_title'] ?? '')), 0, 255),
                'meta_description' => mb_substr(trim((string)($d['meta_description'] ?? '')), 0, 255),
                'keyword'          => mb_substr(trim((string)($d['keyword'] ?? '')), 0, 64),
            ];
        }

        $images = [];

        foreach ((array)($post['project_image'] ?? []) as $img) {
            $img = (array)$img;

            $images[] = [
                'image'      => $this->imageGuard((string)($img['image'] ?? '')),
                'alt'        => mb_substr(trim((string)($img['alt'] ?? '')), 0, 255),
                'sort_order' => (int)($img['sort_order'] ?? 0),
            ];
        }

        $categories = array_values(array_filter(array_map('intval', (array)($post['project_category'] ?? []))));

        return [
            'image'        => $this->imageGuard((string)($post['image'] ?? '')),
            'client'       => mb_substr(trim((string)($post['client'] ?? '')), 0, 128),
            'url'          => mb_substr(trim((string)($post['url'] ?? '')), 0, 255),
            'status'       => isset($post['status']) ? 1 : 0,
            'featured'     => isset($post['featured']) ? 1 : 0,
            'sort_order'   => (int)($post['sort_order'] ?? 0),
            'descriptions' => $descriptions,
            'images'       => $images,
            'categories'   => $categories,
        ];
    }

    /**
     * Validação espelhada no que o model persiste.
     *
     * @return array<int, string> Mensagens de erro (vazio = válido)
     */
    private function validateProject(int $project_id): array {
        $errors = [];
        $post   = $this->request->post;
        $desp   = (array)($post['project_description'] ?? []);

        // Título no idioma padrão (language_id=1, en-US — ADR 0001) é o mínimo
        $nameEn = trim((string)($desp[1]['name'] ?? ''));

        if ($nameEn === '' || mb_strlen($nameEn) > 255) {
            $errors[] = $this->language->get('error_name');
        }

        $url = trim((string)($post['url'] ?? ''));

        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = $this->language->get('error_url');
        }

        // keyword SEO: formato + unicidade por idioma (o UNIQUE do seo_url é a rede final)
        foreach ($desp as $lid => $d) {
            $keyword = trim((string)(((array)$d)['keyword'] ?? ''));

            if ($keyword === '') {
                continue;
            }

            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $keyword)) {
                $errors[] = sprintf($this->language->get('error_keyword_format'), (int)$lid);
            } elseif ($this->model_catalog_project->keywordTaken($keyword, (int)$lid, $project_id)) {
                $errors[] = sprintf($this->language->get('error_keyword_taken'), $keyword);
            }
        }

        // Capa: só dentro do diretório de uploads do módulo
        $cover = (string)($post['image'] ?? '');

        if ($cover !== '' && $this->imageGuard($cover) === '') {
            $errors[] = $this->language->get('error_image_path');
        }

        return $errors;
    }

    /** Só vale path relativo dentro de view/image/project/ e arquivo existente */
    private function imageGuard(string $path): string {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || !str_starts_with($path, self::UPLOAD_DIR) || str_contains($path, '..')) {
            return '';
        }

        return $this->imagePath($path) !== '' ? $path : '';
    }

    /** Resolve o path físico no catalog (irmão do admin); '' se não existir */
    private function imagePath(string $relative): string {
        $path = rtrim((string)dirname(DIR_APPLICATION), '/') . '/catalog/' . $relative;

        return is_file($path) ? $path : '';
    }

    /**
     * Base pública do CATALOG derivada do site_url do admin (irmão catalog/ —
     * ADR 0008). Usada só para MONTAR <img src>. HTTP_CATALOG formal fica
     * para quando o Contrato-Bootstrap ganhar a constante.
     */
    private function catalogUrl(): string {
        $base = (string)$this->config->get('site_url', '');

        if (str_ends_with($base, 'admin/')) {
            return substr($base, 0, -strlen('admin/')) . 'catalog/';
        }

        return $base;
    }

    /** Leia-uma-vez (consume) do flash da sessão */
    private function flash(): array {
        $session = $this->session;

        $data = ['type' => '', 'text' => ''];

        if ($session instanceof \Reamur\System\Library\Session && !empty($session->data['flash'])) {
            $data         = (array)$session->data['flash'];
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

    /** Chrome compartilhado + estilo do módulo */
    private function chrome(array &$data): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css');

        $data['body_class'] = 'rms-dash';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
