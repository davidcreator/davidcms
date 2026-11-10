<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\Admin\Controller\Cms\Block_pattern;
use Reamur\Catalog\Controller\Common\Three;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Loader;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Cache\APCU as ApcuCache;
use Reamur\System\Library\Language;
use Reamur\System\Library\Log;
use Reamur\System\Library\Mail\Capture;
use Reamur\System\Library\Mail\Factory as MailFactory;
use Reamur\System\Library\Mail\Mail as NativeMail;
use Reamur\System\Library\Mail\Smtp;
use Reamur\System\Library\Request;
use Reamur\System\Library\Response;
use Reamur\System\Library\Security\UrlGuard;
use Reamur\System\Library\Session\DB as SessionDb;
use Reamur\System\Library\Three\AssetManifest;

final class Step10CoreHardeningTest extends TestCase {
    public function testAutoloaderResolvesAcronymsAndSnakeCaseClasses(): void {
        self::assertTrue(class_exists(SessionDb::class));
        self::assertTrue(class_exists(ApcuCache::class));
        self::assertTrue(class_exists(UrlGuard::class));
        self::assertTrue(class_exists(Block_pattern::class));
    }

    public function testLogSanitizesFilenameCreatesDirAndWritesLine(): void {
        $dir = sys_get_temp_dir() . '/reamur_log10_' . bin2hex(random_bytes(4)) . '/nested/';
        try {
            $log = new Log('../../evil.log', $dir);
            self::assertSame($dir . 'evil.log', $log->getFilePath());

            $log->write("line one\0ok");
            self::assertFileExists($dir . 'evil.log');
            $content = (string)file_get_contents($dir . 'evil.log');
            self::assertStringContainsString(' - line oneok', $content);

            $fallback = new Log('..', $dir);
            self::assertSame($dir . 'error.log', $fallback->getFilePath());
        } finally {
            foreach (glob($dir . '*') ?: [] as $item) {
                @unlink($item);
            }
            @rmdir($dir);
            @rmdir(dirname($dir));
        }
    }

    public function testMailFactoryCreatesEnginesWithConfigDefaultsAndCaptureRejectsNul(): void {
        $config = new Config();
        $config->set('config_contact_email', 'site@example.test');
        $config->set('config_name', 'ReamurSite');

        $config->set('mail_engine', 'capture');
        $capture = MailFactory::create($config, [
            'to'      => 'user@example.test',
            'subject' => 'Hello',
            'text'    => 'Body',
        ]);
        self::assertInstanceOf(Capture::class, $capture);

        $config->set('mail_engine', 'smtp');
        $smtp = MailFactory::create($config, [
            'to'      => 'user@example.test',
            'subject' => 'Hello',
            'text'    => 'Body',
        ]);
        self::assertInstanceOf(Smtp::class, $smtp);

        $config->set('mail_engine', 'unknown_driver');
        $fallback = MailFactory::create($config, [
            'to'      => 'user@example.test',
            'subject' => 'Hello',
            'text'    => 'Body',
        ]);
        self::assertInstanceOf(NativeMail::class, $fallback);

        $this->expectException(\RuntimeException::class);
        $capture->send([
            'capture_path' => "/tmp/bad\0mail.json",
            'to'           => 'user@example.test',
            'subject'      => 'Hello',
            'text'         => 'Body',
        ]);
    }

    public function testApcuCacheGetStatsReturnsArraySafely(): void {
        $apcu = new ApcuCache(120);
        self::assertIsArray($apcu->getStats());
    }

    public function testCatalogCommonThreeIndexAndAssetDelivery(): void {
        $registry = new Registry();
        $request  = new Request();
        $response = new Response();
        $language = new Language('en-us');
        $loader   = new Loader($registry);

        $registry->set('request', $request);
        $registry->set('response', $response);
        $registry->set('language', $language);
        $registry->set('load', $loader);
        $registry->set('three_asset_manifest', new AssetManifest());

        $request->get = [];
        $controller = new Three($registry);
        $controller->index();

        self::assertContains('HTTP/1.1 404 Not Found', $response->getHeaders());
        self::assertSame('The requested Three.js runtime asset is unavailable.', $response->getOutput());

        $response2 = new Response();
        $request->get = ['asset' => 'three/core/dom.js'];
        $registry->set('response', $response2);

        $controller2 = new Three($registry);
        $controller2->asset();
        self::assertContains('X-Content-Type-Options: nosniff', $response2->getHeaders());
        self::assertContains('Content-Type: application/javascript; charset=utf-8', $response2->getHeaders());
        self::assertNotSame('', $response2->getOutput());

        $etagHeader = '';
        foreach ($response2->getHeaders() as $header) {
            if (str_starts_with($header, 'ETag: ')) {
                $etagHeader = substr($header, 6);
                break;
            }
        }
        self::assertNotSame('', $etagHeader);

        $response3 = new Response();
        $request->server['HTTP_IF_NONE_MATCH'] = $etagHeader;
        $registry->set('response', $response3);
        (new Three($registry))->asset();
        self::assertContains('HTTP/1.1 304 Not Modified', $response3->getHeaders());
        self::assertSame('', $response3->getOutput());
    }
}
