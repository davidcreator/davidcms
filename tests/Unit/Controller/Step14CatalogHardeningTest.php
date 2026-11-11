<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Reamur\Catalog\Controller\Blog\Blog;
use Reamur\Catalog\Controller\Common\Header;
use Reamur\Catalog\Controller\Common\Home;
use Reamur\Catalog\Controller\Startup\Router;
use Reamur\Catalog\Controller\Tool\Tool;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Controller;
use Reamur\System\Engine\Loader;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Request;
use Reamur\System\Library\Url;

final class Step14CatalogHardeningTest extends TestCase {
    public function testControllerQueryIntAndSafeExternalUrlFilterUnsafeInputs(): void {
        $registry = new Registry();
        $request  = new Request();
        $request->get = [
            'category_id' => ['array_attack'],
            'page'        => ' 12 ',
            'neg'         => '-5',
            'bad'         => '12abc',
        ];
        $registry->set('request', $request);

        $probe = new class($registry) extends Controller {
            public function qInt(string $key, int $default = 0): int {
                return $this->queryInt($key, $default);
            }

            public function safeUrl(string $url): string {
                return $this->safeExternalUrl($url);
            }
        };

        self::assertSame(0, $probe->qInt('category_id'));
        self::assertSame(7, $probe->qInt('category_id', 7));
        self::assertSame(12, $probe->qInt('page'));
        self::assertSame(-5, $probe->qInt('neg'));
        self::assertSame(0, $probe->qInt('bad'));
        self::assertSame(0, $probe->qInt('missing'));

        self::assertSame('https://hiperiongamer.com/', $probe->safeUrl('https://hiperiongamer.com/'));
        self::assertSame(
            'https://www.google.com/search?q=David+Creator',
            $probe->safeUrl(' https://www.google.com/search?q=David+Creator ')
        );
        self::assertSame('', $probe->safeUrl('javascript:alert(1)'));
        self::assertSame('', $probe->safeUrl('data:text/html,<script>'));
        self::assertSame('', $probe->safeUrl("https://example.test/\r\nX-Injected: 1"));
        self::assertSame('', $probe->safeUrl(''));
    }

    public function testHomeToolAndBlogBlockUnsafeExternalUrlsWhilePreservingValidLinks(): void {
        $registry = new Registry();
        $registry->set('url', new Url('https://example.test/'));

        $home = new Home($registry);
        $projectHref = new \ReflectionMethod($home, 'projectHref');
        self::assertSame('https://hiperiongamer.com/', $projectHref->invoke($home, [
            'project_id' => 5,
            'url'        => 'https://hiperiongamer.com/',
        ]));
        self::assertSame('https://example.test/index.php?route=catalog/project.info|5', $projectHref->invoke($home, [
            'project_id' => 5,
            'url'        => 'javascript:alert(1)',
        ]));
        self::assertSame('https://example.test/index.php?route=catalog/project.info|5', $projectHref->invoke($home, [
            'project_id' => 5,
            'url'        => '',
        ]));

        $tool = new Tool($registry);
        $hydrate = new \ReflectionMethod($tool, 'hydrate');
        $unsafeTool = $hydrate->invoke($tool, [
            'tool_id'     => 3,
            'name'        => 'Unsafe',
            'vendor'      => 'Acme',
            'description' => 'Desc',
            'image'       => 'view/img/t.png',
            'url'         => 'javascript:alert(1)',
        ], 'https://example.test');
        self::assertSame('', $unsafeTool['url']);
        self::assertFalse($unsafeTool['external']);
        self::assertSame('https://example.test/view/img/t.png', $unsafeTool['image']);
        self::assertSame('https://example.test/index.php?route=tool/tool.info|3', $unsafeTool['href']);

        $safeTool = $hydrate->invoke($tool, [
            'tool_id'     => 4,
            'name'        => 'Safe',
            'vendor'      => 'Acme',
            'description' => 'Desc',
            'image'       => '',
            'url'         => 'https://fluxa.example.test/',
        ], 'https://example.test');
        self::assertSame('https://fluxa.example.test/', $safeTool['url']);
        self::assertTrue($safeTool['external']);

        $blog = new Blog($registry);
        $absoluteImage = new \ReflectionMethod($blog, 'absoluteImage');
        self::assertSame(
            'https://example.test/view/img/post.png',
            $absoluteImage->invoke($blog, 'view/img/post.png', 'https://example.test')
        );
        self::assertSame(
            'https://cdn.example.test/post.png',
            $absoluteImage->invoke($blog, 'https://cdn.example.test/post.png', 'https://example.test')
        );
        self::assertSame('', $absoluteImage->invoke($blog, 'javascript:alert(1)', 'https://example.test'));
        self::assertSame('', $absoluteImage->invoke($blog, "view/img/bad\r\n.png", 'https://example.test'));
    }

    public function testHeaderRequestOriginAndStartupRouterRewriteWorkSafely(): void {
        $registry = new Registry();
        $config   = new Config();
        $config->set('site_url', 'https://davidalmeida.xyz:8443/catalog/');
        $registry->set('config', $config);
        $registry->set('load', new Loader($registry));
        $registry->set('model_design_seo_url', new class {
            public function getKeywordByQuery(string $query): ?string {
                return $query === 'project_id=7' ? 'jwa' : null;
            }
        });

        $header = new Header($registry);
        $requestOrigin = new \ReflectionMethod($header, 'requestOrigin');
        self::assertSame('https://davidalmeida.xyz:8443', $requestOrigin->invoke($header));

        $config->set('site_url', 'ftp://files.example.test/catalog/');
        self::assertSame('http://files.example.test', $requestOrigin->invoke($header));

        $router = new Router($registry);
        self::assertSame(
            'https://example.test/jwa?language=pt-br',
            $router->rewrite('https://example.test/index.php?route=catalog/project.info|7&amp;language=pt-br')
        );
        self::assertSame(
            'https://example.test/blog',
            $router->rewrite('https://example.test/index.php?route=blog/blog')
        );
        self::assertSame(
            'https://example.test/index.php?route=catalog/project.info|99',
            $router->rewrite('https://example.test/index.php?route=catalog/project.info|99')
        );
    }
}
