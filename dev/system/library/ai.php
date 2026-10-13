<?php
namespace Reamur\System\Library;

/**
 * Lightweight AI facade.
 *
 * - Uses configured provider if available (config ai_endpoint / ai_api_key / ai_model).
 * - Falls back to deterministic heuristics so UI keeps working offline.
 */
class Ai {
    /** @var \Reamur\System\Engine\Registry */
    private $registry;
    /** @var \Reamur\System\Library\Config */
    private $config;
    /** @var \Reamur\System\Library\Log */
    private $log;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->config   = $registry->get('config');
        $this->log      = $registry->get('log');
    }

    private function providerEnabled(): bool {
        return (bool)$this->config->get('ai_endpoint') && (bool)$this->config->get('ai_api_key');
    }

    /**
     * Draft a blog post (title, excerpt, content, meta).
     */
    public function draftBlogPost(array $seed): array {
        // Heuristic fallback
        $topic   = trim($seed['topic'] ?? $seed['title'] ?? 'New post');
        $keyword = trim($seed['keyword'] ?? '');
        $title   = $topic !== '' ? $this->titleCase($topic) : 'Design insights';
        $excerpt = "Resumo rápido sobre {$title}. Pontos-chave e boas práticas.";
        $content = "<h2>Introdução</h2><p>{$title} em foco. Contextualize o problema e o impacto.</p>"
                 . "<h2>3 passos</h2><ol><li>Defina objetivos claros.</li><li>Escolha métricas.</li><li>Itere com feedback.</li></ol>"
                 . "<h2>Conclusão</h2><p>Próximos passos e referências para continuar.</p>";

        if ($this->providerEnabled()) {
            $prompt = "Write a blog draft about '{$topic}'" . ($keyword ? " focusing on {$keyword}" : '') . ".";
            $llm = $this->callProvider($prompt, 750);
            $content = $llm['text'] ?? $content;
            $excerpt = $llm['summary'] ?? $excerpt;
            $title   = $llm['title'] ?? $title;
        }

        return [
            'title'             => $title,
            'excerpt'           => $excerpt,
            'content'           => $content,
            'meta_title'        => $this->trimLen($title, 60),
            'meta_description'  => $this->trimLen(strip_tags($excerpt), 155),
            'meta_keywords'     => $keyword,
            'slug'              => $this->slugify($title),
            'reading_time'      => max(1, (int)ceil(strlen(strip_tags($content)) / 900)),
        ];
    }

    /**
     * SEO helper for a page: meta, schema, links.
     */
    public function seoAutopilot(array $page): array {
        $title   = $page['title'] ?? 'Untitled';
        $descSrc = $page['excerpt'] ?? strip_tags($page['content'] ?? '');
        $metaTitle = $this->trimLen($title, 60);
        $metaDescription = $this->trimLen($descSrc, 155);

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => $page['type'] ?? 'Article',
            'headline' => $metaTitle,
            'description' => $metaDescription,
            'datePublished' => $page['published_at'] ?? date('c'),
            'author' => $page['author'] ?? ($this->config->get('config_name') ?: 'Reamur')
        ];

        return [
            'meta_title'        => $metaTitle,
            'meta_description'  => $metaDescription,
            'schema_json'       => json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'slug'              => $page['slug'] ?? $this->slugify($title),
        ];
    }

    /**
     * QA check for HTML content.
     */
    public function qaContent(string $html, array $meta = []): array {
        $issues = [];
        $text = strip_tags($html);
        $wordCount = str_word_count($text, 0, 'àáãâéêíóôõúçÁÂÃÀÉÊÍÓÔÕÚÇ');
        if ($wordCount < 200) {
            $issues[] = ['level' => 'warn', 'msg' => 'Conteúdo curto (menos de 200 palavras).'];
        }
        if (preg_match_all('/<img[^>]*>/i', $html, $imgs)) {
            foreach ($imgs[0] as $img) {
                if (!preg_match('/alt=/', $img)) {
                    $issues[] = ['level' => 'error', 'msg' => 'Imagem sem atributo alt.'];
                    break;
                }
            }
        }
        if ($meta) {
            if (empty($meta['meta_title'])) {
                $issues[] = ['level' => 'warn', 'msg' => 'Meta title vazio.'];
            } elseif (strlen($meta['meta_title']) > 65) {
                $issues[] = ['level' => 'warn', 'msg' => 'Meta title longo (>65).'];
            }
            if (empty($meta['meta_description'])) {
                $issues[] = ['level' => 'warn', 'msg' => 'Meta description vazia.'];
            }
        }
        $readTime = max(1, (int)ceil($wordCount / 180));
        return [
            'word_count'  => $wordCount,
            'reading_min' => $readTime,
            'issues'      => $issues
        ];
    }

    /**
     * Landing page skeleton generator.
     */
    public function draftLanding(array $seed): array {
        $product = $seed['product'] ?? 'Produto';
        $offer = $seed['offer'] ?? 'Oferta especial';
        $hero = [
            'headline' => "{$product}: {$offer}",
            'subhead'  => "Benefícios claros em duas linhas. Chamada para ação direta.",
            'cta'      => 'Começar agora'
        ];
        $sections = [
            ['title' => 'Prova social', 'body' => 'Depoimentos curtos de clientes.'],
            ['title' => 'Benefícios', 'body' => 'Três bullets focados em resultado.'],
            ['title' => 'FAQ', 'body' => 'Principais dúvidas com respostas objetivas.']
        ];
        return [
            'hero' => $hero,
            'sections' => $sections,
            'slug' => $this->slugify($product . '-' . ($seed['campaign'] ?? 'landing')),
        ];
    }

    /**
     * Draft product copy (name, description, meta).
     */
    public function draftProduct(array $seed): array {
        $name = $seed['name'] ?? ($seed['keyword'] ?? 'Novo produto');
        $benefits = $seed['benefits'] ?? ['Benefício 1', 'Benefício 2', 'Benefício 3'];
        if (!is_array($benefits)) {
            $benefits = explode(',', (string)$benefits);
        }
        $desc = "<p>{$name} resolve o problema principal do cliente com rapidez.</p><ul>";
        foreach ($benefits as $b) {
            $b = trim($b);
            if ($b !== '') $desc .= "<li>{$b}</li>";
        }
        $desc .= "</ul><p>Inclua prova social e garantia.</p>";

        if ($this->providerEnabled()) {
            $prompt = "Create ecommerce copy for '{$name}' with 3 bullet benefits and a short pitch.";
            $llm = $this->callProvider($prompt, 350);
            $desc = $llm['text'] ?? $desc;
        }

        return [
            'name' => $this->titleCase($name),
            'description' => $desc,
            'meta_title' => $this->trimLen($name, 60),
            'meta_description' => $this->trimLen(strip_tags($desc), 155)
        ];
    }

    /**
     * Draft quiz questions.
     */
    public function draftQuiz(array $seed): array {
        $topic = $seed['topic'] ?? 'Fundamentals';
        $questions = [
            ['question' => "What is {$topic}?", 'type' => 'single', 'options' => ["Definition", "Tool", "Metric"], 'answer' => "Definition"],
            ['question' => "Give one practical example of {$topic}.", 'type' => 'text', 'options' => [], 'answer' => "Open"]
        ];
        if ($this->providerEnabled()) {
            $prompt = "Create 5 short quiz questions about {$topic} with type (single/multiple/true_false/text) and answers.";
            $llm = $this->callProvider($prompt, 500);
            if (!empty($llm['text'])) {
                $questions[0]['question'] = $llm['text'];
            }
        }
        return ['questions' => $questions];
    }

    /**
     * Generic provider call (OpenAI-compatible). Safe fallback if offline.
     */
    private function callProvider(string $prompt, int $maxTokens = 400): array {
        $endpoint = rtrim($this->config->get('ai_endpoint'), '/');
        $apiKey   = $this->config->get('ai_api_key');
        $model    = $this->config->get('ai_model') ?: 'gpt-4.1';

        $payload = [
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $maxTokens,
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 15
        ]);

        $raw = curl_exec($ch);
        if (curl_errno($ch)) {
            $this->log->write('AI provider error: ' . curl_error($ch));
            curl_close($ch);
            return [];
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 300) {
            $this->log->write('AI provider HTTP ' . $code . ': ' . $raw);
            return [];
        }
        $decoded = json_decode($raw, true);
        $text = $decoded['choices'][0]['message']['content'] ?? '';
        return ['text' => $text];
    }

    private function slugify(string $text): string {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        return strtolower($text ?: 'post');
    }

    private function trimLen(string $text, int $len): string {
        return mb_strlen($text, 'UTF-8') > $len ? mb_substr($text, 0, $len - 3, 'UTF-8') . '…' : $text;
    }

    private function titleCase(string $text): string {
        return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }
}
