<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Engine\Controller;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Language;
use Reamur\System\Library\MercadopagoMarketplace;
use Reamur\System\Library\Request;
use Reamur\System\Library\Response;
use Reamur\System\Library\Security\UrlGuard;

require_once dirname(__DIR__, 3) . '/dev/system/library/pagination.php';

final class PaginationAndMarketplaceTest extends TestCase {
    public function testPaginationReturnsEmptyWhenNotNeeded(): void {
        $pagination = new \Pagination();
        $pagination->total = 10;
        $pagination->limit = 20;
        $pagination->url = 'https://example.test/index.php?route=blog/blog&page={page}';

        self::assertSame('', $pagination->render());

        $pagination->total = 0;
        self::assertSame('', $pagination->render());
        $info = $pagination->getInfo();
        self::assertSame(0, $info['num_pages']);
        self::assertFalse($info['has_next']);
    }

    public function testPaginationDoesNotDoubleEscapeAmpersandsOrBreakPageOneQuery(): void {
        $pagination = new \Pagination();
        $pagination->total = 50;
        $pagination->page = 2;
        $pagination->limit = 10;
        $pagination->url = 'https://example.test/index.php?route=tool/tool&page={page}&filter_name=seo';

        $html = $pagination->render();

        self::assertStringNotContainsString('&amp;amp;', $html);
        self::assertStringContainsString(
            'href="https://example.test/index.php?route=tool/tool&amp;filter_name=seo"',
            $html
        );
        self::assertStringContainsString(
            'href="https://example.test/index.php?route=tool/tool&amp;page=3&amp;filter_name=seo"',
            $html
        );

        // Pre-escaped &amp; URLs and leading ?page={page}& queries also normalize cleanly
        $pagination2 = new \Pagination();
        $pagination2->setConfig([
            'total' => 40,
            'page'  => 2,
            'limit' => 10,
            'url'   => 'https://example.test/posts?page=%7Bpage%7D&amp;sort=asc',
        ]);
        $html2 = $pagination2->render();

        self::assertStringNotContainsString('&amp;amp;', $html2);
        self::assertStringContainsString('href="https://example.test/posts?sort=asc"', $html2);
        self::assertStringContainsString('href="https://example.test/posts?page=3&amp;sort=asc"', $html2);
    }

    public function testPaginationSanitizesUnsafeSchemesAndXssPayloads(): void {
        $pagination = new \Pagination();
        $pagination->total = 30;
        $pagination->page = 1;
        $pagination->limit = 10;
        $pagination->url = 'javascript:alert(1)?page={page}';

        $html = $pagination->render();
        self::assertStringContainsString('href="#"', $html);
        self::assertStringNotContainsString('javascript:', $html);

        $pagination->url = "https://example.test/items?q=\"><script>alert(1)</script>&page={page}\r\nX:1";
        $xssHtml = $pagination->render();
        self::assertStringNotContainsString('<script>', $xssHtml);
        self::assertStringNotContainsString("\r", $xssHtml);
        self::assertStringContainsString('&quot;&gt;&lt;script&gt;', $xssHtml);
    }

    public function testMercadopagoMarketplaceConfigurationAndValidation(): void {
        self::assertTrue(MercadopagoMarketplace::isHttpUrl('https://api.mercadopago.com/oauth/token'));
        self::assertTrue(MercadopagoMarketplace::isHttpUrl('http://127.0.0.1:8912/oauth/token'));
        self::assertFalse(MercadopagoMarketplace::isHttpUrl('javascript:alert(1)'));
        self::assertFalse(MercadopagoMarketplace::isHttpUrl('file:///etc/passwd'));
        self::assertFalse(MercadopagoMarketplace::isHttpUrl("https://api.mercadopago.com\r\nX: 1"));

        $invalidMp = new MercadopagoMarketplace([
            'app_id'        => '123456',
            'client_secret' => 'secret',
            'redirect_uri'  => 'javascript:alert(1)',
        ]);
        self::assertFalse($invalidMp->isConfigured());
        self::assertSame('', $invalidMp->authorizationUrl('state-1'));

        $mp = new MercadopagoMarketplace([
            'app_id'        => '123456',
            'client_secret' => 'secret',
            'redirect_uri'  => 'https://shop.example.test/admin/callback',
        ]);
        self::assertTrue($mp->isConfigured());

        $authUrl = $mp->authorizationUrl('csrf-xyz');
        self::assertStringStartsWith('https://auth.mercadopago.com.br/authorization?', $authUrl);
        self::assertStringContainsString('client_id=123456', $authUrl);
        self::assertStringContainsString('state=csrf-xyz', $authUrl);

        self::assertSame(
            'Missing Mercado Pago authorization code.',
            $mp->exchangeAuthorizationCode('   ')['error'] ?? ''
        );
        self::assertSame(
            'Missing Mercado Pago refresh token.',
            $mp->refreshSellerToken('')['error'] ?? ''
        );

        $invalidPayment = $mp->getPayment(0, 'token');
        self::assertSame(0, $invalidPayment['_http_status']);
        self::assertSame(['nickname' => '', 'email' => ''], $mp->getUserInfo('  '));

        self::assertSame('Bad token', $mp->extractErrorMessage(['message' => 'Bad token', 'error' => 'invalid']));
        self::assertSame(
            'Mercado Pago OAuth request returned HTTP 401.',
            $mp->extractErrorMessage(['_http_status' => 401])
        );
        if (defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            self::assertSame((int)CURLPROTO_HTTP | (int)CURLPROTO_HTTPS, UrlGuard::allowedCurlProtocols());
        }
    }

    public function testControllerHandleAdminUploadRejectsNonPostAndUnauthorizedRequests(): void {
        $registry = new Registry();
        $request  = new Request();
        $language = new Language('en-us');
        $response = new Response();

        $language->set('error_upload_method', 'Method must be POST');
        $language->set('error_permission', 'Forbidden');

        $registry->set('request', $request);
        $registry->set('language', $language);
        $registry->set('response', $response);

        $controller = new class($registry) extends Controller {
            public function runUpload(): void {
                $this->handleAdminUpload('tool/tool', 'view/image/tool/');
            }
        };

        $request->server['REQUEST_METHOD'] = 'GET';
        $controller->runUpload();

        self::assertContains('Content-Type: application/json; charset=utf-8', $response->getHeaders());
        self::assertSame(
            ['success' => false, 'path' => '', 'error' => 'Method must be POST'],
            json_decode($response->getOutput(), true)
        );

        $request->server['REQUEST_METHOD'] = 'POST';
        $controller->runUpload();
        self::assertSame(
            ['success' => false, 'path' => '', 'error' => 'Forbidden'],
            json_decode($response->getOutput(), true)
        );
    }
}
