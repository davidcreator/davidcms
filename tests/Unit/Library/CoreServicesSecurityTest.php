<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Engine\Controller;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Cache;
use Reamur\System\Library\Cache\File as FileCache;
use Reamur\System\Library\Image;
use Reamur\System\Library\Language;
use Reamur\System\Library\Language\Detect as LanguageDetect;
use Reamur\System\Library\Session\File as FileSession;
use Reamur\System\Library\Webhook\Dispatcher;

final class CoreServicesSecurityTest extends TestCase {
    private function makeTempDir(string $prefix): string {
        $dir = sys_get_temp_dir() . '/' . $prefix . '_' . bin2hex(random_bytes(6)) . '/';
        mkdir($dir, 0755, true);
        return $dir;
    }

    private function removeDir(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '*', GLOB_MARK) ?: [] as $item) {
            if (is_file($item)) {
                @unlink($item);
            }
        }
        @rmdir($dir);
    }

    public function testFileCacheHonorsPerCallExpireRejectsTraversalAndClears(): void {
        $dir = $this->makeTempDir('reamur_cache');
        try {
            $cache = new FileCache(3600, $dir);

            self::assertTrue($cache->set('setting.config', ['site' => 'Reamur'], 120));
            self::assertTrue($cache->exists('setting.config'));
            self::assertSame(['site' => 'Reamur'], $cache->get('setting.config'));

            file_put_contents($dir . 'cache.stale_key', json_encode([
                'expire' => time() - 10,
                'data'   => 'old',
            ]));
            self::assertNull($cache->get('stale_key'));
            clearstatcache(true, $dir . 'cache.stale_key');
            self::assertFalse(is_file($dir . 'cache.stale_key'));

            self::assertFalse($cache->set('../escape', 'bad'));
            self::assertFalse($cache->set('bad', 'x', -5));
            self::assertTrue($cache->delete('setting.config'));
            self::assertFalse($cache->exists('setting.config'));

            self::assertTrue($cache->set('k1', 'v1'));
            self::assertTrue($cache->set('k2', 'v2'));
            self::assertTrue($cache->clear());
            self::assertNull($cache->get('k1'));
            self::assertNull($cache->get('k2'));
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testCacheFacadeValidationAndFileRoundtrip(): void {
        $cacheDirExisted = is_dir(DIR_STORAGE . 'cache');
        $facade = new Cache('file', 60);
        $key = 'phpunit_probe_' . bin2hex(random_bytes(4));

        self::assertSame('file', $facade->getAdaptorName());
        try {
            self::assertTrue($facade->set($key, ['ok' => true], 30));
            self::assertTrue($facade->exists($key));
            self::assertSame(['ok' => true], $facade->get($key));
            self::assertTrue($facade->delete($key));
            self::assertNull($facade->get($key));
        } finally {
            $facade->delete($key);
            if (!$cacheDirExisted && is_dir(DIR_STORAGE . 'cache')) {
                @rmdir(DIR_STORAGE . 'cache');
            }
        }

        $invalidThrown = false;
        try {
            new Cache('database');
        } catch (\InvalidArgumentException) {
            $invalidThrown = true;
        }
        self::assertTrue($invalidThrown);

        $traversalThrown = false;
        try {
            $facade->set('../secret', 'nope');
        } catch (\InvalidArgumentException) {
            $traversalThrown = true;
        }
        self::assertTrue($traversalThrown);
    }

    public function testFileSessionLifecycleAndIdSanitization(): void {
        $dir = $this->makeTempDir('reamur_sess');
        try {
            $handler = new FileSession($dir);
            $sid = 'abc123def456ghi789jkl012';

            self::assertTrue($handler->open($dir, 'RMSSESSION'));
            self::assertSame('', $handler->read($sid));
            self::assertTrue($handler->write($sid, 'user_id|i:1;'));
            self::assertSame('user_id|i:1;', $handler->read($sid));

            self::assertFalse($handler->write('../evil', 'x'));
            self::assertSame('', $handler->read('../evil'));
            self::assertFalse($handler->destroy(''));

            self::assertTrue(touch($dir . 'sess_' . $sid, time() - 7200));
            clearstatcache(true, $dir . 'sess_' . $sid);
            self::assertSame(1, $handler->gc(60));
            self::assertSame('', $handler->read($sid));
            self::assertTrue($handler->close());
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testWebhookDispatcherBlocksSsrfWithoutNetworkCalls(): void {
        $log = new class {
            /** @var array<int, string> */
            public array $messages = [];

            public function write(string $msg): void {
                $this->messages[] = $msg;
            }
        };

        $dispatcher = new Dispatcher($log);
        $results = $dispatcher->dispatch('order_add', ['order_id' => 9], [
            ['webhook_id' => 7, 'url' => 'http://127.0.0.1:9999/hook', 'secret' => 's3cr3t'],
            ['webhook_id' => 8, 'url' => 'file:///etc/passwd', 'secret' => 's3cr3t'],
        ]);

        self::assertCount(2, $results);
        foreach ($results as $entry) {
            self::assertSame(0, $entry['http_code']);
            self::assertSame(0.0, $entry['duration']);
            self::assertSame(1, $entry['attempt']);
            $decoded = json_decode($entry['payload'], true);
            self::assertIsArray($decoded);
            self::assertSame('order_add', $decoded['event']);
        }
        self::assertCount(4, $log->messages);
    }

    public function testLanguageDetectHandlesExactFallbackAndMalformedHeaders(): void {
        $detect = new LanguageDetect();
        $detect->setDefault('en-us');
        $detect->setSupportedLanguages(['en-us', 'pt-br', 'es-eu']);

        self::assertSame('pt-br', $detect->getLanguage([], 'pt-BR,pt;q=0.9,en-US;q=0.8'));
        self::assertSame('es-eu', $detect->getLanguage([], 'es-AR,es;q=0.9'));
        self::assertSame('en-us', $detect->getLanguage([], ''));
        self::assertSame('en-us', $detect->getLanguage([], ';q=0.9,fr-CA;q=0.8'));
    }

    public function testImageProcessingAndValidationBounds(): void {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('GD extension required for Image tests');
        }

        $dir = $this->makeTempDir('reamur_img');
        try {
            $src = $dir . 'source.png';
            $outPng = $dir . 'resized.png';
            $outJpg = $dir . 'cropped.jpg';

            $gd = imagecreatetruecolor(40, 20);
            self::assertInstanceOf(\GdImage::class, $gd);
            $fill = imagecolorallocate($gd, 40, 120, 200);
            self::assertIsInt($fill);
            imagefilledrectangle($gd, 0, 0, 39, 19, $fill);
            imagepng($gd, $src);
            imagedestroy($gd);

            $img = new Image($src);
            self::assertSame(40, $img->getWidth());
            self::assertSame(20, $img->getHeight());
            self::assertSame('image/png', $img->getMime());

            $img->resize(20, 10);
            self::assertSame(20, $img->getWidth());
            self::assertSame(10, $img->getHeight());

            $img->rotate(90, 'FFFFFF');
            self::assertSame(10, $img->getWidth());
            self::assertSame(20, $img->getHeight());

            $img->text('OK', 1, 1, 2, '000000');
            $img->save($outPng, 90);
            self::assertFileExists($outPng);

            $img->crop(0, 0, 8, 8);
            $img->save($outJpg, 85);
            self::assertFileExists($outJpg);

            $nulThrown = false;
            try {
                new Image($dir . "bad\0.png");
            } catch (\InvalidArgumentException) {
                $nulThrown = true;
            }
            self::assertTrue($nulThrown);
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testControllerAdminPaginationAndLabels(): void {
        $registry = new Registry();
        $language = new Language('en-us');
        $language->set('button_first_page', 'FirstBtn');
        $language->set('button_last_page', 'LastBtn');
        $language->set('button_next_page', 'NextBtn');
        $language->set('button_previous_page', 'PrevBtn');
        $language->set('text_page_first', 'FirstTxt');
        $language->set('text_page_last', 'LastTxt');
        $language->set('text_page_next', 'NextTxt');
        $language->set('text_page_prev', 'PrevTxt');
        $registry->set('language', $language);

        $controller = new class($registry) extends Controller {
            public function renderButtonStyle(): string {
                return $this->adminPagination(
                    45,
                    2,
                    10,
                    'https://example.test/admin/index.php?route=cms/page&page={page}&filter_status=1',
                    $this->paginationLabels('button')
                );
            }

            public function renderTextStyle(): string {
                return $this->adminPagination(
                    45,
                    2,
                    10,
                    'https://example.test/admin/index.php?route=sale/order&page={page}',
                    $this->paginationLabels()
                );
            }
        };

        $btnHtml = $controller->renderButtonStyle();
        self::assertStringContainsString('FirstBtn', $btnHtml);
        self::assertStringContainsString('LastBtn', $btnHtml);
        self::assertStringContainsString('pagination pagination-sm mb-0', $btnHtml);
        self::assertStringNotContainsString('&amp;amp;', $btnHtml);

        $txtHtml = $controller->renderTextStyle();
        self::assertStringContainsString('FirstTxt', $txtHtml);
        self::assertStringContainsString('NextTxt', $txtHtml);
    }
}
