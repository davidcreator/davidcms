<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * feed/news (CATALOG) — "Latest News" do blog externo (F3-05, ADR 0002/0023):
 *  · RSS do davidcreator.com linha a linha via cURL + SimpleXML;
 *  · cache no driver nativo (cache_engine=file) — TTL do cache_expire;
 *  · resultado negativo TAMBÉM é cacheado — blog fora do ar não derruba a home;
 *  · nunca lança exceção: erro de rede/XML → [] (partial se oculta sozinha).
 */
namespace Reamur\Catalog\Model\Feed;

class News extends \Reamur\System\Engine\Model {

    /** URL canônica do feed (blog externo — ADR 0002); override por setting ocorrerá na Fase 4/5 */
    private const FEED_URL = 'https://davidcreator.com/feed/';

    /** Chave única no cache compartilhado */
    private const CACHE_KEY = 'news_feed';
    private const MAX_ITEMS_HARD_CAP = 6;
    private const EXCERPT_LEN = 160;

    /**
     * Últimos N posts normalizados [{title, url, date, excerpt, source}].
     *
     * @return array<int, array<string, string>> [] em qualquer falha — degradação silenciosa
     */
    public function getLatestNews(int $limit = 3): array {
        $limit = max(1, min(self::MAX_ITEMS_HARD_CAP, $limit));

        $cached = $this->cache->get(self::CACHE_KEY);

        if (is_array($cached)) {
            // Formato [ 'items' => [...] ] verificado em runtime (cache é compartilhado)
            $items = $cached['items'] ?? null;

            if (is_array($items)) {
                return array_slice($items, 0, $limit);
            }
        }

        $items = $this->fetchFeed();

        // Cacheia até o resultado vazio: blog fora do ar custará 1 hit por TTL, nunca por pageview
        $this->cache->set(self::CACHE_KEY, ['items' => $items]);

        return array_slice($items, 0, $limit);
    }

    /**
     * Busca + parse do RSS. Isolada para clareza; mantém o contrato "nunca lança".
     *
     * @return array<int, array<string, string>>
     */
    private function fetchFeed(): array {
        $xml = $this->httpGet(self::FEED_URL);

        if ($xml === '') {
            return [];
        }

        libxml_use_internal_errors(true);

        $rss = simplexml_load_string($xml);

        if (!$rss instanceof \SimpleXMLElement || !isset($rss->channel->item)) {
            return [];
        }

        $items = [];
        $count = 0;

        // ATENÇÃO (bug F3-05): em SimpleXML, `foreach ($rss->channel->item as $i => $item)`
        // dá $i = NOME do elemento ("item"), não índice — a checagem numérica quebraria.
        // Contador explícito é a forma segura.
        foreach ($rss->channel->item as $item) {
            if ($count >= self::MAX_ITEMS_HARD_CAP) {
                break;
            }

            $title   = $this->clean((string)$item->title);
            $url     = trim((string)$item->link);

            if ($title === '' || $url === '') {
                continue;
            }

            $items[] = [
                'title'   => $title,
                'url'     => $url,
                'date'    => $this->normalizeDate((string)$item->pubDate),
                'excerpt' => $this->excerpt((string)$item->description),
                'source'  => 'davidcreator.com',
            ];

            $count++;
        }

        return $items;
    }

    /** HTTP GET com timeouts curtos — a home jamais pode ficar refém do blog */
    private function httpGet(string $url): string {
        $ch = curl_init($url);

        if ($ch === false) {
            return '';
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 3,   // handshake
            CURLOPT_TIMEOUT        => 6,   // total
            CURLOPT_USERAGENT      => 'ReamurCMS/3.x (+news feed reader; site owner blog)',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return (is_string($body) && $code >= 200 && $code < 300) ? $body : '';
    }

    /** Remove tags/entidades duplas e comprime espaços */
    private function clean(string $text): string {
        return trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');
    }

    /** Trecho plano, cortado no espaço com reticências */
    private function excerpt(string $description): string {
        $plain = $this->clean($description);

        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($plain, 0, self::EXCERPT_LEN, '…', 'UTF-8');
        }

        return (strlen($plain) > self::EXCERPT_LEN) ? substr($plain, 0, self::EXCERPT_LEN) . '…' : $plain;
    }

    /** RFC-2822 do RSS → d/m/Y (neutro entre idiomas; títulos do feed vêm em pt) */
    private function normalizeDate(string $pubDate): string {
        $ts = strtotime($pubDate);

        return ($ts !== false) ? date('d/m/Y', $ts) : '';
    }
}
