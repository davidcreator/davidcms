<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\Admin\Controller\Cms\Block_pattern;
use Reamur\Admin\Model\Design\Theme as AdminThemeModel;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Controller;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Response;
use Reamur\System\Library\Session\DB as SessionDb;
use Reamur\System\Library\Session\File as SessionFile;

final class Step9ThemeSessionControllerTest extends TestCase {
    public function testThemeModelPreventsDuplicateSlugInsertAndCollision(): void {
        $db = new class {
            /** @var array<int, string> */
            public array $queries = [];
            /** @var array<int, array<string, mixed>> */
            public array $themes = [
                7 => [
                    'theme_id'         => 7,
                    'name'             => 'David Universe 3D',
                    'slug'             => 'david_universe_3d',
                    'config'           => '{}',
                    'draft_config'     => '{}',
                    'published_config' => '{}',
                    'status'           => 1,
                ],
            ];
            public int $lastId = 7;

            public function escape(string $value): string {
                return str_replace("'", "\\'", $value);
            }

            public function query(string $sql): \stdClass|bool {
                $this->queries[] = $sql;
                $res = new \stdClass();
                $res->rows = [];
                $res->row = [];
                $res->num_rows = 0;

                if (preg_match("/FROM `[^`]+design_theme` WHERE `slug`='([^']+)'(?: AND `theme_id`!='(\d+)')?/i", $sql, $m)) {
                    $exclude = isset($m[2]) ? (int)$m[2] : 0;
                    foreach ($this->themes as $row) {
                        if ($row['slug'] === $m[1] && (int)$row['theme_id'] !== $exclude) {
                            $res->rows[] = $row;
                        }
                    }
                    $res->row = $res->rows[0] ?? [];
                    $res->num_rows = count($res->rows);
                    return $res;
                }

                if (preg_match("/FROM `[^`]+design_theme` WHERE `theme_id`='(\d+)'/i", $sql, $m)) {
                    $id = (int)$m[1];
                    if (isset($this->themes[$id])) {
                        $res->rows = [$this->themes[$id]];
                        $res->row = $this->themes[$id];
                        $res->num_rows = 1;
                    }
                    return $res;
                }

                if (stripos($sql, 'INSERT INTO') !== false && stripos($sql, 'design_theme`') !== false) {
                    $this->lastId++;
                    return true;
                }

                return true;
            }

            public function getLastId(): int {
                return $this->lastId;
            }
        };

        $registry = new Registry();
        $registry->set('db', $db);

        $model = new AdminThemeModel($registry);

        $existing = $model->getThemeBySlug('david_universe_3d');
        self::assertSame(7, (int)($existing['theme_id'] ?? 0));
        self::assertTrue($model->slugExists('david_universe_3d'));
        self::assertFalse($model->slugExists('david_universe_3d', 7));
        self::assertFalse($model->slugExists(''));

        // Inserting a duplicate slug (id=0) must fail closed instead of running INSERT
        $insertResult = $model->save(0, [
            'name'   => 'David Universe 3D',
            'slug'   => 'david_universe_3d',
            'config' => ['primary' => '#2563eb'],
        ], 1);
        self::assertSame(0, $insertResult);

        foreach ($db->queries as $sql) {
            self::assertStringNotContainsString('INSERT INTO `rms_design_theme`', $sql);
        }

        // Updating the matching existing theme ID writes a revision + draft update safely
        $updateResult = $model->save(7, [
            'name'   => 'David Universe 3D',
            'slug'   => 'david_universe_3d',
            'config' => ['primary' => '#8a4fff'],
        ], 1);
        self::assertSame(7, $updateResult);
    }

    public function testControllerJsonResponseAndCatalogAssetUrl(): void {
        $registry = new Registry();
        $config = new Config();
        $config->set('site_url', 'https://example.test/admin/');
        $response = new Response();

        $registry->set('config', $config);
        $registry->set('response', $response);

        $controller = new class($registry) extends Controller {
            public function resolveAssetBase(): string {
                return $this->catalogAssetUrl();
            }

            /** @param array<string|int, mixed> $payload */
            public function emitJson(array $payload, int $status = 200, bool $noStore = false): void {
                $this->jsonResponse($payload, $status, $noStore);
            }
        };

        self::assertSame('https://example.test/catalog/', $controller->resolveAssetBase());

        $config->set('site_url', 'https://example.test/');
        self::assertSame('https://example.test/catalog/', $controller->resolveAssetBase());

        $config->set('site_url', 'https://example.test/catalog/');
        self::assertSame('https://example.test/catalog/', $controller->resolveAssetBase());

        $controller->emitJson(['ok' => true, 'url' => 'https://example.test/x'], 200, true);
        $headers = $response->getHeaders();
        self::assertContains('Content-Type: application/json; charset=utf-8', $headers);
        self::assertContains('X-Content-Type-Options: nosniff', $headers);
        self::assertContains('Cache-Control: no-store, private', $headers);
        self::assertSame('{"ok":true,"url":"https://example.test/x"}', $response->getOutput());
    }

    public function testBlockPatternIndexRespondsCleanlyWithoutThrowing(): void {
        $registry = new Registry();
        $response = new Response();
        $user = new class {
            public function hasPermission(string $action, string $route): bool {
                return true;
            }
        };

        $registry->set('response', $response);
        $registry->set('user', $user);

        $controller = new Block_pattern($registry);
        $controller->index();

        $decoded = json_decode($response->getOutput(), true);
        self::assertIsArray($decoded);
        self::assertFalse($decoded['success']);
        self::assertSame('method_not_allowed', $decoded['error']);
        self::assertContains('Cache-Control: no-store, private', $response->getHeaders());

        $deniedRegistry = new Registry();
        $deniedResponse = new Response();
        $deniedUser = new class {
            public function hasPermission(string $action, string $route): bool {
                return false;
            }
        };
        $deniedRegistry->set('response', $deniedResponse);
        $deniedRegistry->set('user', $deniedUser);

        $deniedController = new Block_pattern($deniedRegistry);
        $deniedController->index();
        $denied = json_decode($deniedResponse->getOutput(), true);
        self::assertIsArray($denied);
        self::assertFalse($denied['success']);
        self::assertSame('permission_denied', $denied['error']);
    }

    public function testSessionDbRejectsInvalidSessionIds(): void {
        $ref = new \ReflectionClass(SessionDb::class);
        /** @var SessionDb $instance */
        $instance = $ref->newInstanceWithoutConstructor();

        self::assertSame('', $instance->read(''));
        self::assertSame('', $instance->read('../etc/passwd'));
        self::assertFalse($instance->write('', 'x'));
        self::assertFalse($instance->write("bad' OR 1=1", 'x'));
        self::assertFalse($instance->destroy(''));
        self::assertFalse($instance->destroy('../evil'));
    }

    public function testFileSessionLockSafeWriteAndGcKeepsFreshFiles(): void {
        $dir = sys_get_temp_dir() . '/reamur_sess9_' . bin2hex(random_bytes(4)) . '/';
        mkdir($dir, 0750, true);

        try {
            $handler = new SessionFile($dir);
            $fresh = 'fresh1234567890abcdef';
            $stale = 'stale1234567890abcdef';

            self::assertTrue($handler->write($fresh, 'role|s:5:"admin";'));
            self::assertTrue($handler->write($stale, 'role|s:5:"guest";'));
            self::assertSame('role|s:5:"admin";', $handler->read($fresh));

            self::assertTrue(touch($dir . 'sess_' . $stale, time() - 7200));
            clearstatcache(true, $dir . 'sess_' . $stale);

            self::assertSame(1, $handler->gc(60));
            self::assertSame('', $handler->read($stale));
            self::assertSame('role|s:5:"admin";', $handler->read($fresh));
            self::assertTrue($handler->destroy($fresh));
            self::assertSame('', $handler->read($fresh));
        } finally {
            foreach (glob($dir . '*') ?: [] as $item) {
                @unlink($item);
            }
            @rmdir($dir);
        }
    }
}
