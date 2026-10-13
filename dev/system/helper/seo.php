<?php
class SeoHelper {
    public static function generateSlug($title, $existing_slugs = []) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $original_slug = $slug;
        $counter = 1;
        
        while (in_array($slug, $existing_slugs)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    public static function generateMetaDescription($content, $length = 160) {
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length - 3) . '...';
    }

    public static function generateSitemap($posts, $categories, $base_url) {
        // Generate XML sitemap for blog content
    }

    public static function generateStructuredData($post) {
        // Generate JSON-LD structured data for blog posts
    }
}