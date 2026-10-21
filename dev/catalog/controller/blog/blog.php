<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * blog/blog (CATALOG) — frente pública do blog interno (F7-02, ADR 0043):
 *  · index    → lista cronológica (/blog via alias do router);
 *  · info|N   → post completo por keyword seo_url (ex.: /meu-primeiro-post);
 *               inexistente/rascunho/agendado → 404 honesto (error/not_found).
 * Descrição é HTML do editor do admin → render raw (conteúdo confiável).
 */
namespace Reamur\Catalog\Controller\Blog;

class Blog extends \Reamur\System\Engine\Controller {

    /** Lista pública de posts publicados (mais novos primeiro) */
    public function index(): void {
        $this->load->language('blog/blog');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->setDescription($this->language->get('text_lead'));

        $this->load->model('blog/blog');

        $base = rtrim((string)$this->config->get('site_url', ''), '/');

        $data['heading_title']   = $this->language->get('heading_title');
        $data['text_lead']       = $this->language->get('text_lead');
        $data['text_no_results'] = $this->language->get('text_no_results');
        $data['text_read_more']  = $this->language->get('text_read_more');
        $data['button_back_home']= $this->language->get('button_back_home');

        $data['posts'] = [];

        foreach ($this->model_blog_blog->getPosts() as $post) {
            $post = (array)$post;

            $data['posts'][] = [
                'post_id' => (int)$post['post_id'],
                'title'   => (string)$post['title'],
                'image'   => $this->absoluteImage((string)$post['image'], $base),
                'date'    => $this->formatDate((string)$post['date_published']),
                'excerpt' => $this->excerpt((string)$post['description']),
                'href'    => $this->url->link('blog/blog.info|' . (int)$post['post_id']),
            ];
        }

        $data['home'] = $base . '/';

        $this->chrome($data, 'rms-blog');

        $this->response->setOutput($this->load->view('blog/blog_list', $data));
    }

    /** Post completo (keyword seo_url resolvida pelo router → blog_id=N) */
    public function info(string $post_id = '0'): void {
        $post_id = (int)$post_id;

        $this->load->language('blog/blog');

        $this->load->model('blog/blog');

        $post = $this->model_blog_blog->getPost($post_id);

        if (!$post) {
            $action = new \Reamur\System\Engine\Action('error/not_found');

            $action->execute($this->registry);

            return;
        }

        $this->document->setTitle((string)($post['meta_title'] !== '' ? $post['meta_title'] : $post['title']));
        $this->document->setDescription((string)$post['meta_description']);

        $base = rtrim((string)$this->config->get('site_url', ''), '/');

        $data['heading_title']     = (string)$post['title'];
        $data['text_published_on'] = $this->language->get('text_published_on');
        $data['button_back']       = $this->language->get('button_back');

        $data['post'] = [
            'post_id'      => (int)$post['post_id'],
            'title'        => (string)$post['title'],
            'description'  => (string)$post['description'],
            'image'        => $this->absoluteImage((string)$post['image'], $base),
            'date'         => $this->formatDate((string)$post['date_published']),
        ];

        $data['back_blog'] = $this->url->link('blog/blog');

        $this->chrome($data, 'rms-blog-post');

        $this->response->setOutput($this->load->view('blog/blog_info', $data));
    }

    /** Imagem relativa → absoluta pela site_url; URL externa (http/https) intocada */
    private function absoluteImage(string $image, string $base): string {
        if ($image === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $image)) {
            return $image;
        }

        return $base . '/' . ltrim($image, '/');
    }

    /** Y-m-d, idioma-neutro (formato local rico fica para polish SEO/visual) */
    private function formatDate(string $raw): string {
        $ts = strtotime($raw);

        return $ts !== false ? date('Y-m-d', $ts) : '';
    }

    /** Excerpt de 160 chars a partir do HTML do editor */
    private function excerpt(string $html): string {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');

        return mb_strlen($plain) > 160 ? mb_strimwidth($plain, 0, 160, '…', 'UTF-8') : $plain;
    }

    /** Chrome compartilhado (mesmo padrão F2-4/F3-01) */
    private function chrome(array &$data, string $body_class): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/common.css');

        $data['body_class'] = $body_class;
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
