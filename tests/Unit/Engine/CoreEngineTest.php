<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Document;
use Reamur\System\Library\Language;
use Reamur\System\Library\Url;

final class CoreEngineTest extends TestCase {
    public function testRegistryAndConfigLifecycle(): void {
        $registry = new Registry();
        $config   = new Config();
        $config->set('site_url', 'https://example.test/');

        $registry->set('config', $config);

        self::assertTrue($registry->has('config'));
        self::assertFalse($registry->has('missing'));
        self::assertSame($config, $registry->get('config'));
        self::assertSame('https://example.test/', $config->get('site_url'));
        self::assertSame('fallback', $config->get('missing_key', 'fallback'));
        self::assertTrue($config->has('site_url'));
    }

    public function testUrlLinkAndRewrite(): void {
        $url = new Url('https://example.test/');
        self::assertSame(
            'https://example.test/index.php?route=blog/blog.info&post_id=7',
            $url->link('blog/blog.info', ['post_id' => 7])
        );

        $url->addRewrite(static fn(string $u): string => str_replace('index.php?route=blog/blog', 'blog', $u));
        self::assertSame('https://example.test/blog', $url->link('blog/blog'));
    }

    public function testDocumentAndLanguageServices(): void {
        $doc = new Document();
        $doc->setTitle('ReamurCMS');
        $doc->addStyle('view/css/common.css');
        $doc->addScript('view/js/common.js');

        self::assertSame('ReamurCMS', $doc->getTitle());
        self::assertArrayHasKey('view/css/common.css', $doc->getStyles());
        self::assertArrayHasKey('view/js/common.js', $doc->getScripts());

        $lang = new Language('en-us');
        $lang->set('heading_title', 'Blog');
        self::assertSame('Blog', $lang->get('heading_title'));
        self::assertSame('missing_key', $lang->get('missing_key'));
    }
}
