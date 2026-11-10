<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\Admin\Controller\Design\Theme as AdminThemeController;
use Reamur\Admin\Model\Setting\Setting as AdminSettingModel;
use Reamur\Catalog\Model\Setting\Setting as CatalogSettingModel;
use Reamur\Install\Controller\Install\Step_3 as InstallStep3;
use Reamur\System\Engine\Loader;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Blog\BlockDocument;
use Reamur\System\Library\Language;
use Reamur\System\Library\Mail\Smtp;
use Reamur\System\Library\MercadopagoMarketplace;
use Reamur\System\Library\Request;
use Reamur\System\Library\Response;

require_once dirname(__DIR__, 3) . '/dev/system/library/pagination.php';

final class Step11StrictAndSettingsTest extends TestCase {
    public function testPaginationSetConfigCastsMixedTypesUnderStrictTypes(): void {
        $pagination = new \Pagination();
        $pagination->setConfig([
            'total'           => '45',
            'page'            => '2',
            'limit'           => '10',
            'num_links'       => '5',
            'show_first_last' => 0,
            'show_prev_next'  => '1',
            'url'             => '/blog?page={page}',
            'num_pages'       => 999,
        ]);

        $info = $pagination->getInfo();
        self::assertSame(45, $info['total']);
        self::assertSame(2, $info['page']);
        self::assertSame(10, $info['limit']);
        self::assertSame(5, $info['num_pages']);
        self::assertFalse($pagination->show_first_last);
        self::assertTrue($pagination->show_prev_next);

        $html = $pagination->render();
        self::assertStringContainsString('href="/blog?page=3"', $html);
        self::assertStringNotContainsString('First', $html);
    }

    public function testSmtpHelpersSanitizeHeadersAndApplyDotStuffing(): void {
        self::assertSame('Order #42Bcc: evil@test', Smtp::sanitizeHeaderValue("  Order #42\r\nBcc: evil@test\0 "));
        self::assertSame('user@example.com', Smtp::validateEmailAddress('  user@example.com '));

        $lines = Smtp::prepareDataLines("Hello\r\n.hidden\n" . str_repeat('a', 1005));
        self::assertSame('Hello', $lines[0]);
        self::assertSame('..hidden', $lines[1]);
        self::assertSame(998, strlen($lines[2]));
        self::assertSame(7, strlen($lines[3]));

        $this->expectException(\InvalidArgumentException::class);
        Smtp::validateEmailAddress("evil@example.com\r\nBcc: victim@example.com", 'to');
    }

    public function testBlockDocumentEmbedTargetIgnoresArrayQueryAndConvertsHtml(): void {
        $doc = new BlockDocument();
        self::assertNull($doc->embedTarget('https://www.youtube.com/watch?v[]=123456'));
        self::assertSame(
            ['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'],
            $doc->embedTarget('https://youtu.be/dQw4w9WgXcQ')
        );

        $converted = $doc->convertLegacy('<h2>Hello</h2><p>World</p>');
        self::assertSame('converted', $converted['status']);
        self::assertCount(2, $converted['document']['blocks']);
    }

    public function testMercadopagoMarketplaceUrlValidationAndAuthLink(): void {
        self::assertFalse(MercadopagoMarketplace::isHttpUrl('javascript:alert(1)'));
        self::assertFalse(MercadopagoMarketplace::isHttpUrl("https://example.test/cb\n"));
        self::assertTrue(MercadopagoMarketplace::isHttpUrl('https://example.test/oauth/callback'));

        $mp = new MercadopagoMarketplace([
            'app_id'        => '12345',
            'client_secret' => 'secret',
            'redirect_uri'  => 'https://example.test/oauth/callback',
        ]);
        self::assertTrue($mp->isConfigured());
        $authUrl = $mp->authorizationUrl('state-xyz');
        self::assertStringContainsString('client_id=12345', $authUrl);
        self::assertStringContainsString('state=state-xyz', $authUrl);
    }

    public function testSettingModelsDecodeSerializedScalarsArraysAndCorruptPayloadsSafely(): void {
        $rows = [
            (object)['key' => 'site_name', 'value' => 'Reamur', 'serialized' => 0],
            (object)['key' => 'scalar_ser', 'value' => serialize('ok'), 'serialized' => 1],
            (object)['key' => 'bool_false', 'value' => serialize(false), 'serialized' => 1],
            (object)['key' => 'array_ser', 'value' => serialize(['a' => 1, 'b' => 'x']), 'serialized' => 1],
            (object)['key' => 'object_ser', 'value' => 'O:8:"StdClass":0:{}', 'serialized' => 1],
            (object)['key' => 'corrupt_ser', 'value' => 'a:2:{broken', 'serialized' => 1],
            (object)['key' => 'plain_corrupt', 'value' => 'not-serialized', 'serialized' => 1],
        ];

        $db = new class($rows) {
            /** @var array<int, object> */
            private array $rows;
            /** @param array<int, object> $rows */
            public function __construct(array $rows) { $this->rows = $rows; }
            public function escape(string $value): string { return addslashes($value); }
            public function query(string $sql): \stdClass {
                $res = new \stdClass();
                $res->rows = $this->rows;
                $res->num_rows = count($this->rows);
                return $res;
            }
        };

        $cache = new class {
            /** @var array<string, mixed> */
            public array $store = [];
            public function get(string $key): mixed { return $this->store[$key] ?? null; }
            public function set(string $key, mixed $value, int $expire = 0): bool {
                $this->store[$key] = $value;
                return true;
            }
            public function delete(string $key): bool { unset($this->store[$key]); return true; }
        };

        $registry = new Registry();
        $registry->set('db', $db);
        $registry->set('cache', $cache);

        $expected = [
            'site_name'     => 'Reamur',
            'scalar_ser'    => 'ok',
            'bool_false'    => '0',
            'array_ser'     => '{"a":1,"b":"x"}',
            'object_ser'    => '',
            'corrupt_ser'   => '',
            'plain_corrupt' => '',
        ];

        $adminModel = new AdminSettingModel($registry);
        self::assertSame($expected, $adminModel->getSettings());

        $catalogModel = new CatalogSettingModel($registry);
        self::assertSame($expected, $catalogModel->getSettings());
    }

    public function testThemeSimulateAndInstallStep3TestEmitStandardJsonResponses(): void {
        $registry = new Registry();
        $request  = new Request();
        $response = new Response();
        $language = new Language('en-us');
        $loader   = new Loader($registry);

        $request->server['REQUEST_METHOD'] = 'GET';
        $user = new class {
            public function hasPermission(string $type, string $route): bool { return false; }
        };

        $registry->set('request', $request);
        $registry->set('response', $response);
        $registry->set('language', $language);
        $registry->set('load', $loader);
        $registry->set('user', $user);

        (new AdminThemeController($registry))->simulate();
        $simDecoded = json_decode($response->getOutput(), true);
        self::assertIsArray($simDecoded);
        self::assertFalse($simDecoded['ok']);
        self::assertSame('forbidden', $simDecoded['errors'][0]['code'] ?? '');
        self::assertContains('X-Content-Type-Options: nosniff', $response->getHeaders());

        $response2 = new Response();
        $registry->set('response', $response2);
        (new InstallStep3($registry))->test();
        $s3Decoded = json_decode($response2->getOutput(), true);
        self::assertIsArray($s3Decoded);
        self::assertFalse($s3Decoded['success']);
        self::assertContains('Content-Type: application/json; charset=utf-8', $response2->getHeaders());
        self::assertContains('Cache-Control: no-store, private', $response2->getHeaders());
    }
}
