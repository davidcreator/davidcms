<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Contrato declarativo de estrutura para Pages. Não contém Twig, HTML, PHP de
 * template ou JavaScript. Dados de Blog/Portfolio/Tools continuam nos módulos.
 */
namespace Reamur\System\Library\Cms;

final class PageLayout {
    public const PREFIX = 'REAMURCMS_PAGE_LAYOUT_V1:';
    private const SECTIONS = [
        'home' => ['hero','about','methodology','skills','services','editorial','testimonials','portfolio','tools','news','clients','contact'],
        'blog' => ['intro','editorial','posts_grid'],
        'blog_post' => ['post_header','editorial','post_content'],
        'portfolio' => ['intro','editorial','filters','portfolio_grid'],
        'portfolio_detail' => ['editorial','project_header','categories','cover','description','facts','gallery'],
        'tools' => ['intro','editorial','tools_grid'],
        'tool_detail' => ['editorial','tool_header','tool_description','contact_link'],
        'contact' => ['intro','editorial','contact_form'],
        'quote' => ['intro','editorial','quote_form'],
        'not_found' => ['not_found_content','editorial'],
    ];

    /** @return list<string> */
    public function options(string $systemKey): array {
        return self::SECTIONS[$systemKey] ?? [];
    }

    /** @return list<string> */
    public function defaults(string $systemKey, bool $hasEditorial = false): array {
        $sections = $this->options($systemKey);
        if ($sections === []) return [];
        if ($systemKey === 'home' && $hasEditorial) {
            // Preserva o comportamento legado: conteúdo Home substituía os
            // cinco primeiros partials; as seções dinâmicas permanecem visíveis.
            return ['editorial','testimonials','portfolio','tools','news','clients','contact'];
        }
        return $sections;
    }

    /** Validate a section order against the fixed route-specific registry. @return list<string>|null */
    public function normalizeSections(string $systemKey, mixed $sections): ?array {
        $allowed = $this->options($systemKey);
        if ($allowed === [] || !is_array($sections) || count($sections) > count($allowed)) return null;
        $safe = [];
        foreach ($sections as $section) {
            if (!is_string($section) || !in_array($section, $allowed, true) || in_array($section, $safe, true)) return null;
            $safe[] = $section;
        }
        return $safe;
    }

    /** @param array<string,mixed> $content @param list<string> $sections */
    public function encode(string $systemKey, array $content, array $sections): string {
        $safeSections = $this->normalizeSections($systemKey, $sections);
        if ($safeSections === null || ($content['format_version'] ?? null) !== 2 || !is_array($content['blocks'] ?? null)) {
            throw new \InvalidArgumentException('Invalid CMS page structure document.');
        }
        $document = ['format_version'=>1,'system_key'=>$systemKey,'sections'=>$safeSections,'content'=>$content];
        return self::PREFIX . (json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /** Decode the Pages-only envelope; the nested block document is validated by BlockDocument. @return array{system_key:string,sections:list<string>,content:array<string,mixed>}|null */
    public function decode(string $serialized, string $expectedSystemKey): ?array {
        if (!str_starts_with($serialized, self::PREFIX)) return null;
        $document = json_decode(substr($serialized, strlen(self::PREFIX)), true);
        if (!is_array($document) || ($document['format_version'] ?? null) !== 1 || ($document['system_key'] ?? null) !== $expectedSystemKey ||
            array_diff(array_keys($document), ['format_version','system_key','sections','content'])) return null;
        $sections = $this->normalizeSections($expectedSystemKey, $document['sections'] ?? null);
        $content = $document['content'] ?? null;
        if ($sections === null || !is_array($content) || ($content['format_version'] ?? null) !== 2 || !is_array($content['blocks'] ?? null)) return null;
        return ['system_key'=>$expectedSystemKey,'sections'=>$sections,'content'=>$content];
    }
}
