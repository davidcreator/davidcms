<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\Admin\Controller\Blog\Blog as AdminBlogController;
use Reamur\Admin\Controller\Blog\Import as AdminBlogImportController;
use Reamur\Admin\Controller\Catalog\Category as AdminCategoryController;
use Reamur\Admin\Controller\Catalog\Project as AdminProjectController;
use Reamur\Admin\Controller\Design\Theme as AdminThemeController;
use Reamur\Admin\Controller\Marketing\Client_logo as AdminClientLogoController;
use Reamur\Admin\Controller\Marketing\Testimonial as AdminTestimonialController;
use Reamur\Admin\Controller\Sale\Order as AdminSaleOrderController;
use Reamur\Admin\Controller\Tool\Tool as AdminToolController;
use Reamur\Admin\Model\Blog\Import as AdminBlogImportModel;
use Reamur\Admin\Model\Design\Theme as AdminThemeModel;
use Reamur\Admin\Model\Localization\Language as AdminLanguageModel;
use Reamur\Catalog\Model\Catalog\Page as CatalogPageModel;
use Reamur\Catalog\Model\Catalog\Project as CatalogProjectModel;
use Reamur\Catalog\Model\Common\Theme as CatalogThemeModel;
use Reamur\Catalog\Model\Localization\Language as CatalogLanguageModel;
use Reamur\Install\Controller\Install\Step_3 as InstallStep3;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Loader;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Image;
use Reamur\System\Library\Language;
use Reamur\System\Library\MediaUpload;
use Reamur\System\Library\Request;
use Reamur\System\Library\Response;
use Reamur\System\Library\Url;

final class Step16ModelAndAdminAuditTest extends TestCase {
    private function makeTempDir(string $prefix): string {
        $dir = sys_get_temp_dir() . '/' . $prefix . '_' . bin2hex(random_bytes(6)) . '/';
        mkdir($dir, 0755, true);
        return $dir;
    }

    private function removeTree(string $dir): void {
        if (is_link(rtrim($dir, '/'))) {
            @unlink(rtrim($dir, '/'));
            return;
        }
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = rtrim($dir, '/') . '/' . $item;
                if (is_link($path) || is_file($path)) {
                    @unlink($path);
                } elseif (is_dir($path)) {
                    $this->removeTree($path . '/');
                }
            }
        }
        @rmdir($dir);
    }

    public function testMediaUploadAndImageRejectTraversalControlCharsAndSymlinks(): void {
        $dir = $this->makeTempDir('reamur_step16_media');
        $outsideFile = $dir . 'outside.png';
        $uploadDir   = $dir . 'catalog/view/image/test/';
        $realFile    = $uploadDir . 'sample.png';

        try {
            mkdir($uploadDir, 0755, true);
            $tinyPng = base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=',
                true
            );
            self::assertIsString($tinyPng);
            file_put_contents($outsideFile, $tinyPng);
            file_put_contents($realFile, $tinyPng);

            $media = new MediaUpload($dir . 'catalog/');
            self::assertSame('view/image/test/sample.png', $media->guard('view/image/test/sample.png', 'view/image/test/'));
            self::assertSame('', $media->guard('../outside.png', 'view/image/test/'));
            self::assertSame('', $media->guard("view/image/test/sample.png\0", 'view/image/test/'));
            self::assertSame('', $media->guard('view/image/other/sample.png', 'view/image/test/'));
            self::assertSame('', $media->absolute('/etc/passwd'));
            self::assertSame('', $media->absolute("view/image/test/\x07sample.png"));
            self::assertSame($dir . 'catalog/view/image/test/sample.png', $media->absolute('view/image/test/sample.png'));

            $badTargetThrown = false;
            try {
                $media->store([], '/abs/path/');
            } catch (\InvalidArgumentException) {
                $badTargetThrown = true;
            }
            self::assertTrue($badTargetThrown);

            $linkPath = $uploadDir . 'sym.png';
            if (function_exists('symlink') && @symlink($outsideFile, $linkPath)) {
                self::assertSame('', $media->absolute('view/image/test/sym.png'));
                self::assertSame('', $media->guard('view/image/test/sym.png', 'view/image/test/'));

                if (extension_loaded('gd')) {
                    $imgLinkThrown = false;
                    try {
                        new Image($linkPath);
                    } catch (\InvalidArgumentException) {
                        $imgLinkThrown = true;
                    }
                    self::assertTrue($imgLinkThrown);
                }
            }
        } finally {
            $this->removeTree($dir);
        }
    }

    public function testCatalogAndAdminModelsEnforceStrictTypesAndSafeFallbacks(): void {
        $rows = [
            ['code' => 'en-us'],
            ['code' => '../secret'],
            ['code' => 'pt-br'],
            ['code' => 'invalid_code'],
        ];
        $db = new class($rows) {
            /** @var array<int, array<string, mixed>> */
            private array $rows;
            public bool $returnNonObject = false;
            /** @param array<int, array<string, mixed>> $rows */
            public function __construct(array $rows) { $this->rows = $rows; }
            public function escape(string $value): string { return addslashes($value); }
            public function getLastId(): string { return '42'; }
            public function query(string $sql): mixed {
                if ($this->returnNonObject) {
                    return false;
                }
                $res = new \stdClass();
                $res->rows = $this->rows;
                $res->row  = $this->rows[0] ?? [];
                $res->num_rows = count($this->rows);
                return $res;
            }
        };

        $registry = new Registry();
        $registry->set('db', $db);
        $registry->set('config', new Config());

        $catalogLang = new CatalogLanguageModel($registry);
        self::assertSame(['en-us', 'pt-br'], $catalogLang->getEnabledCodes());
        $db->returnNonObject = true;
        self::assertSame([], $catalogLang->getEnabledCodes());
        $db->returnNonObject = false;

        $adminLang = new AdminLanguageModel($registry);
        self::assertSame(['en-us', 'pt-br'], $adminLang->getEnabledCodes());

        $themeDb = new class {
            public function query(string $sql): \stdClass {
                $res = new \stdClass();
                $res->row = [
                    'slug'             => 'valid-theme',
                    'config'           => ['not-a-string'],
                    'published_config' => '{corrupt',
                    'draft_config'     => null,
                ];
                $res->rows = [$res->row, ['slug' => '../evil', 'config' => '{}']];
                $res->num_rows = 2;
                return $res;
            }
        };
        $themeReg = new Registry();
        $themeReg->set('db', $themeDb);
        $catalogTheme = new CatalogThemeModel($themeReg);
        self::assertSame([], $catalogTheme->getPublishedTheme());
        $effective = $catalogTheme->getEffectiveTheme();
        self::assertSame([], $effective['published']);
        self::assertSame([], $effective['draft']);
        self::assertSame('valid-theme', $effective['slug']);
        self::assertSame([], $catalogTheme->getThemeMap());

        $adminTheme = new AdminThemeModel($themeReg);
        self::assertFalse($adminTheme->restoreRevision(1, 1, 1));

        // Non-positive IDs must short-circuit before hitting DB
        $failDb = new class {
            public function query(string $sql): never {
                throw new \RuntimeException('DB must not be queried for non-positive ID');
            }
        };
        $failReg = new Registry();
        $failReg->set('db', $failDb);
        $failReg->set('config', new Config());
        self::assertSame([], (new CatalogPageModel($failReg))->getPage(0));
        self::assertSame([], (new CatalogProjectModel($failReg))->getProject(-5));

        $importModel = new AdminBlogImportModel($registry);
        $safeName = new \ReflectionMethod($importModel, 'safeName');
        self::assertSame('helloworld.jpg', $safeName->invoke($importModel, 'hello-world', ''));
        self::assertSame('helloworld.png', $safeName->invoke($importModel, 'hello-world', '/uploads/hero.png'));
        self::assertSame('https://example.test/catalog/', $importModel->defaultPublicUrl());
    }

    public function testAdminBlogControllerResolvesInheritanceCollisionAndSanitizesArrayInputs(): void {
        $registry = new Registry();
        $request  = new Request();
        $language = new Language('en-us');
        $language->set('error_title', 'Title required');
        $language->set('error_date_published', 'Invalid date');

        $request->post = [
            'blog_description' => ['not-an-array', 1 => ['title' => ['array_attack'], 'keyword' => ['bad']]],
            'date_published'   => ['array_attack'],
            'image'            => ['array_attack'],
            'status'           => '1',
        ];
        $registry->set('request', $request);
        $registry->set('language', $language);
        $registry->set('model_blog_blog', new class {
            public function keywordTaken(string $k, int $lid, int $pid): bool { return false; }
        });

        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $severity . ':' . $message;
            return true;
        });

        try {
            $blog = new AdminBlogController($registry);
            $payload = (new \ReflectionMethod($blog, 'postPayload'))->invoke($blog);
            $errors  = (new \ReflectionMethod($blog, 'validatePost'))->invoke($blog, 0);

            self::assertSame('', $payload['image']);
            self::assertSame(1, $payload['status']);
            self::assertArrayHasKey(1, $payload['descriptions']);
            self::assertSame('', $payload['descriptions'][1]['title']);
            self::assertSame('', $payload['descriptions'][1]['keyword']);
            self::assertContains('Title required', $errors);
            self::assertSame([], $warnings);
        } finally {
            restore_error_handler();
        }
    }

    public function testAdminCatalogMarketingOrderToolThemeAndInstallResistMalformedPayloads(): void {
        $registry = new Registry();
        $request  = new Request();
        $response = new Response();
        $config   = new Config();
        $language = new Language('en-us');
        $loader   = new Loader($registry);
        $url      = new Url('https://example.test/admin/');

        $language->set('error_name', 'Name required');
        $language->set('error_url', 'Invalid URL');
        $language->set('error_firstname', 'Firstname required');
        $language->set('error_email', 'Invalid email');
        $language->set('error_status', 'Invalid status');
        $language->set('error_base_url', 'Invalid base URL');
        $language->set('error_validation', 'Validation error');

        $registry->set('request', $request);
        $registry->set('response', $response);
        $registry->set('config', $config);
        $registry->set('language', $language);
        $registry->set('load', $loader);
        $registry->set('url', $url);
        $registry->set('user', new class {
            public function hasPermission(string $type, string $route): bool { return true; }
        });
        $registry->set('model_catalog_category', new class {
            public function keywordTaken(string $k, int $lid, int $cid): bool { return false; }
            /** @return array<int, array<string, int>> */
            public function getParentOptions(int $cid): array { return [['category_id' => 2]]; }
        });
        $registry->set('model_catalog_project', new class {
            public function keywordTaken(string $k, int $lid, int $pid): bool { return false; }
        });
        $registry->set('model_sale_order', new class {
            /** @return array<int, array<string, int|string>> */
            public function getOrderStatusOptions(): array { return [['order_status_id' => 1, 'name' => 'Pending']]; }
        });
        $registry->set('model_design_theme', new class {
            /** @return array<string, mixed> */
            public function getTheme(int $id): array { return ['theme_id' => $id, 'draft_config' => '{"templates":{"common/home":"<p>ok</p>"}}']; }
        });

        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $severity . ':' . $message;
            return true;
        });

        try {
            // Project: blocks unsafe URL and array inputs
            $request->post = [
                'project_description' => [1 => ['name' => 'Demo Project', 'keyword' => ['bad']]],
                'project_category'    => ['bad', '5', '0'],
                'url'                 => 'javascript:alert(1)',
                'sort_order'          => ['bad'],
            ];
            $project = new AdminProjectController($registry);
            $projPayload = (new \ReflectionMethod($project, 'postPayload'))->invoke($project);
            $projErrors  = (new \ReflectionMethod($project, 'validateProject'))->invoke($project, 0);
            self::assertSame([5], $projPayload['categories']);
            self::assertSame(0, $projPayload['sort_order']);
            self::assertContains('Invalid URL', $projErrors);

            $request->post['url'] = 'https://example.test/project';
            self::assertSame([], (new \ReflectionMethod($project, 'validateProject'))->invoke($project, 0));

            // Category: handles array parent_id and malformed descriptions
            $request->post = [
                'category_description' => ['bad'],
                'parent_id'            => ['bad'],
                'sort_order'           => ['bad'],
            ];
            $category = new AdminCategoryController($registry);
            $catPayload = (new \ReflectionMethod($category, 'postPayload'))->invoke($category);
            $catErrors  = (new \ReflectionMethod($category, 'validateCategory'))->invoke($category, 0);
            self::assertSame(0, $catPayload['parent_id']);
            self::assertContains('Name required', $catErrors);

            // Client logo + Tool + Testimonial
            $request->post = ['name' => 'Acme', 'url' => "https://acme.test/\r\nBad: 1", 'sort_order' => ['bad']];
            $logo = new AdminClientLogoController($registry);
            $logoErrors = (new \ReflectionMethod($logo, 'validateClientLogo'))->invoke($logo);
            self::assertContains('Invalid URL', $logoErrors);

            $request->post = ['tool_description' => [1 => ['name' => 'CLI']], 'url' => 'data:text/html,<script>', 'sort_order' => ['bad']];
            $tool = new AdminToolController($registry);
            $toolErrors = (new \ReflectionMethod($tool, 'validateTool'))->invoke($tool);
            self::assertContains('Invalid URL', $toolErrors);

            $request->post = ['testimonial_description' => ['bad'], 'sort_order' => ['bad']];
            $testimonial = new AdminTestimonialController($registry);
            $testPayload = (new \ReflectionMethod($testimonial, 'postPayload'))->invoke($testimonial);
            self::assertSame(0, $testPayload['sort_order']);

            // Sale Order: toDecimal & array order_product
            $order = new AdminSaleOrderController($registry);
            $toDec = new \ReflectionMethod($order, 'toDecimal');
            self::assertSame(0.0, $toDec->invoke($order, ['bad']));
            self::assertSame(1234.56, $toDec->invoke($order, ' 1234,56 '));
            $request->post = [
                'firstname'       => ['bad'],
                'email'           => 'buyer@example.test',
                'order_status_id' => ['bad'],
                'order_product'   => ['bad', ['name' => ['bad'], 'quantity' => ['bad'], 'price' => ['bad']]],
            ];
            $orderPayload = (new \ReflectionMethod($order, 'postPayload'))->invoke($order);
            self::assertSame(1, $orderPayload['order_status_id']);
            self::assertSame([], $orderPayload['products']);

            // Blog Import: rejects invalid base_url
            $request->post = ['base_url' => 'javascript:alert(1)', 'page' => ['bad']];
            $import = new AdminBlogImportController($registry);
            $importData = ['error' => '', 'form' => []];
            (new \ReflectionMethod($import, 'handle'))->invokeArgs($import, [&$importData, false]);
            self::assertSame('Invalid base URL', $importData['error']);

            // Design Theme: preview rejects invalid template & buildDraftConfig handles array fields
            $request->get = ['template' => '../../etc/passwd'];
            $theme = new AdminThemeController($registry);
            $theme->preview('1');
            self::assertSame('No saved source is available for this allowlisted preview.', $response->getOutput());
            $draftRes = (new \ReflectionMethod($theme, 'buildDraftConfig'))->invoke($theme, [
                'name' => ['bad'],
                'slug' => ['bad'],
            ]);
            self::assertSame('Validation error', $draftRes['error']);

            // Install Step 3: handles array POST parameters safely
            $request->post = [
                'db_driver'   => ['bad'],
                'db_hostname' => ['bad'],
                'db_username' => ['bad'],
                'db_database' => ['bad'],
                'db_prefix'   => ['bad'],
                'db_port'     => ['bad'],
                'http_server' => ['bad'],
                'username'    => ['bad'],
                'firstname'   => ['bad'],
                'lastname'    => ['bad'],
                'email'       => ['bad'],
                'password'    => ['bad'],
                'confirm'     => ['bad'],
            ];
            $step3 = new InstallStep3($registry);
            $creds = (new \ReflectionMethod($step3, 'dbCredentials'))->invoke($step3);
            $s3Errors = (new \ReflectionMethod($step3, 'validate'))->invoke($step3);
            self::assertSame('', $creds['db_driver']);
            self::assertArrayHasKey('db_driver', $s3Errors);
            self::assertArrayHasKey('db_prefix', $s3Errors);
            self::assertArrayHasKey('email', $s3Errors);

            self::assertSame([], $warnings);
        } finally {
            restore_error_handler();
        }
    }
}
