<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\Admin\Controller\Common\Logout;
use Reamur\Admin\Controller\Setting\Marketplace as AdminMarketplaceController;
use Reamur\Admin\Controller\Setting\Setting as AdminSettingController;
use Reamur\Admin\Controller\Setting\Site_info as AdminSiteInfoController;
use Reamur\Admin\Controller\Setting\Webhook as AdminWebhookController;
use Reamur\Admin\Controller\Startup\Language as AdminStartupLanguage;
use Reamur\Admin\Controller\Startup\Login as AdminStartupLogin;
use Reamur\Admin\Controller\Startup\Permission as AdminStartupPermission;
use Reamur\Admin\Controller\Startup\Router as AdminStartupRouter;
use Reamur\Admin\Controller\Startup\Setting as AdminStartupSetting;
use Reamur\Admin\Controller\User\Profile as AdminUserProfile;
use Reamur\Admin\Controller\User\User as AdminUserController;
use Reamur\Admin\Controller\User\User_group as AdminUserGroupController;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Loader;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Cache\File as FileCache;
use Reamur\System\Library\Language;
use Reamur\System\Library\Request;
use Reamur\System\Library\Response;
use Reamur\System\Library\Security\Csrf;
use Reamur\System\Library\Session;
use Reamur\System\Library\Session\File as FileSession;
use Reamur\System\Library\Url;
use Reamur\System\Library\User;

final class Step15WindowsAndAdminHardeningTest extends TestCase {
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

    public function testSessionFileAndCacheFileRoundtripAndPreserveErrorHandler(): void {
        $dir = $this->makeTempDir('reamur_step15_io');
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $severity . ':' . $message;
            return true;
        });

        try {
            $session = new FileSession($dir . 'sess/');
            $sid = 'step15session1234567890';

            self::assertTrue($session->open($dir . 'sess/', 'RMSSESSION'));
            self::assertSame('', $session->read($sid));
            self::assertTrue($session->write($sid, ''));
            self::assertSame('', $session->read($sid));
            self::assertTrue($session->write($sid, 'user_id|i:42;'));
            self::assertSame('user_id|i:42;', $session->read($sid));
            self::assertFalse($session->write('../escape', 'bad'));
            self::assertSame('', $session->read('../escape'));
            self::assertTrue($session->destroy($sid));
            self::assertSame('', $session->read($sid));

            $cache = new FileCache(120, $dir . 'cache/');
            self::assertNull($cache->get('missing'));
            self::assertFalse($cache->exists('missing'));
            self::assertTrue($cache->set('setting.config', ['mode' => 'dark'], 60));
            self::assertTrue($cache->exists('setting.config'));
            self::assertSame(['mode' => 'dark'], $cache->get('setting.config'));
            self::assertFalse($cache->set('bad_ttl', 'x', -1));
            self::assertFalse($cache->set('../escape', 'x'));
            self::assertTrue($cache->clear());
            self::assertNull($cache->get('setting.config'));

            self::assertSame([], $warnings);
        } finally {
            restore_error_handler();
            $this->removeDir($dir . 'sess/');
            $this->removeDir($dir . 'cache/');
            @rmdir($dir);
        }
    }

    public function testAdminStartupControllersResistArrayAndTraversalInputs(): void {
        $registry = new Registry();
        $config   = new Config();
        $request  = new Request();
        $response = new Response();
        $url      = new Url('https://example.test/admin/');
        $loader   = new Loader($registry);

        $config->set('site_url', 'https://example.test/admin/');
        $config->set('action_default', 'common/dashboard');

        $request->get = ['route' => ['array_attack'], 'language' => ['pt-br']];
        $request->server['REQUEST_URI'] = "/admin/login\0?x=1";

        $registry->set('config', $config);
        $registry->set('request', $request);
        $registry->set('response', $response);
        $registry->set('url', $url);
        $registry->set('load', $loader);

        (new AdminStartupLogin($registry))->index();
        self::assertContains('Location: https://example.test/admin/index.php?route=common/login', $response->getHeaders());

        // Logged-in User with empty ACL: allowed dashboard & invalid/traversal probes do not leak; real catalog route redirects to 403
        $user = new User($registry);
        (new \ReflectionProperty(User::class, 'logged'))->setValue($user, true);
        (new \ReflectionProperty(User::class, 'user_id'))->setValue($user, 1);
        $registry->set('user', $user);

        $permResponse = new Response();
        $registry->set('response', $permResponse);
        $request->get = ['route' => 'common/dashboard'];
        (new AdminStartupPermission($registry))->index();
        self::assertSame([], $permResponse->getHeaders());

        $request->get = ['route' => ['array_attack']];
        (new AdminStartupPermission($registry))->index();
        self::assertSame([], $permResponse->getHeaders());

        $request->get = ['route' => '../../etc/passwd'];
        (new AdminStartupPermission($registry))->index();
        self::assertSame([], $permResponse->getHeaders());

        $request->get = ['route' => 'common/home'];
        (new AdminStartupPermission($registry))->index();
        self::assertContains('Location: https://example.test/admin/index.php?route=error/permission', $permResponse->getHeaders());

        $router = new AdminStartupRouter($registry);
        self::assertSame('common/login', $router->index());
        self::assertSame('login?foo=bar', $router->rewrite('index.php?route=common/login&amp;foo=bar'));
        self::assertSame('index.php?route=user/user.edit|3', $router->rewrite('index.php?route=user/user.edit|3'));

        $registry->set('model_localization_language', new class {
            /** @return array<int, string> */
            public function getEnabledCodes(): array { return ['en-us', 'pt-br']; }
        });
        $registry->set('language', new Language('en-us'));
        $request->get = ['language' => ['pt-br']];
        (new AdminStartupLanguage($registry))->index();
        self::assertSame('en-us', $config->get('language_directory'));

        $registry->set('model_setting_setting', new class {
            /** @return array<string, mixed> */
            public function getSettings(): array {
                return [
                    'config_name' => 'ReamurCMS',
                    'mail_engine' => ['bad_array'],
                    'blocked_key' => 'ignored',
                ];
            }
        });
        (new AdminStartupSetting($registry))->index();
        self::assertSame('ReamurCMS', $config->get('config_name'));
        self::assertNull($config->get('mail_engine'));
        self::assertNull($config->get('blocked_key'));

        $logoutRes = new Response();
        $registry->set('response', $logoutRes);
        (new Logout($registry))->index();
        self::assertFalse($user->isLogged());
        // O $router->index() acima registrou o rewrite de URLs limpas no Url
        // compartilhado (ADR 0006), como o boot real faz antes de qualquer
        // controller: a partir dali common/login sai como o alias /login.
        self::assertContains('Location: https://example.test/admin/login', $logoutRes->getHeaders());
    }

    public function testAdminSettingAndUserControllersSanitizeArrayPayloadsAndUrls(): void {
        $registry = new Registry();
        $request  = new Request();
        $response = new Response();
        $language = new Language('en-us');
        $loader   = new Loader($registry);
        $url      = new Url('https://example.test/admin/');

        $registry->set('request', $request);
        $registry->set('response', $response);
        $registry->set('language', $language);
        $registry->set('load', $loader);
        $registry->set('url', $url);
        $registry->set('model_localization_language', new class {
            /** @return array<int, array<string, string|int>> */
            public function getLanguages(): array {
                return [['code' => 'en-us', 'status' => 1]];
            }
        });

        $profile = new AdminUserProfile($registry);
        $profileSanitize = new \ReflectionMethod($profile, 'sanitize');
        $cleanProfile = $profileSanitize->invoke($profile, [
            'email' => ['array'],
            'firstname' => '  David ',
            'lastname' => 'Creator',
            'password_current' => ['array'],
            'password_new' => ' secret123 ',
            'password_confirm' => ' secret123 ',
        ]);
        self::assertSame('', $cleanProfile['email']);
        self::assertSame('David', $cleanProfile['firstname']);
        self::assertSame('', $cleanProfile['password_current']);
        self::assertSame(' secret123 ', $cleanProfile['password_new']);

        $adminUser = new AdminUserController($registry);
        $request->post = [
            'user_group_id' => ['bad'],
            'username' => ['bad'],
            'email' => 'ok@example.test',
            'firstname' => 'David',
            'lastname' => 'Almeida',
            'password' => ['bad'],
        ];
        $payloadMethod = new \ReflectionMethod($adminUser, 'postPayload');
        $userPayload = $payloadMethod->invoke($adminUser);
        self::assertSame(0, $userPayload['user_group_id']);
        self::assertSame('', $userPayload['username']);
        self::assertSame('ok@example.test', $userPayload['email']);
        self::assertSame(0, $userPayload['status']);

        $userGroup = new AdminUserGroupController($registry);
        $allRoutesMethod = new \ReflectionMethod($userGroup, 'allRoutes');
        $routes = $allRoutesMethod->invoke($userGroup);
        self::assertIsArray($routes);
        self::assertContains('common/home', $routes);
        foreach ($routes as $route) {
            self::assertStringNotContainsString('\\', $route);
            self::assertStringNotContainsString('.php', $route);
        }

        $setting = new AdminSettingController($registry);
        $settingSanitize = new \ReflectionMethod($setting, 'sanitize');
        $cleanSetting = $settingSanitize->invoke($setting, [
            'config_name' => ' Reamur ',
            'mail_smtp_password' => ['bad'],
            'cache_engine' => ['bad'],
        ]);
        self::assertSame('Reamur', $cleanSetting['config_name']);
        self::assertSame('', $cleanSetting['mail_smtp_password']);
        self::assertSame('', $cleanSetting['cache_engine']);

        $siteInfo = new AdminSiteInfoController($registry);
        $siSanitize = new \ReflectionMethod($siteInfo, 'sanitize');
        $siValidate = new \ReflectionMethod($siteInfo, 'validate');
        $cleanSi = $siSanitize->invoke($siteInfo, [
            'config_contact_email' => 'contact@example.test',
            'config_contact_whatsapp_text' => '+55 11 99999-0000',
            'config_contact_whatsapp_href' => ['bad'],
            'config_social_facebook_href' => "https://facebook.com/ok\r\nX-Test: 1",
            'config_social_instagram_href' => 'https://instagram.com/davidcreator',
            'config_contact_address_en-us' => 'Sao Paulo, BR',
            'config_copyright_en-us' => 'Copyright %s Reamur',
        ]);
        self::assertSame('', $cleanSi['config_contact_whatsapp_href']);
        $siErrors = $siValidate->invoke($siteInfo, $cleanSi, [['code' => 'en-us']]);
        self::assertArrayHasKey('config_social_facebook_href', $siErrors);
        self::assertArrayNotHasKey('config_social_instagram_href', $siErrors);

        /** @var Session $session */
        $session = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();
        $session->data = [];
        $csrf = Csrf::token($session);
        $registry->set('session', $session);
        $registry->set('user', new class {
            public function hasPermission(string $type, string $route): bool { return true; }
        });
        $registry->set('model_setting_setting', new class {
            /** @var array<string, string> */
            public array $saved = [];
            /** @return array<string, string> */
            public function getSettings(): array { return []; }
            /** @param array<string, string> $data */
            public function editSettings(array $data): void { $this->saved = $data; }
        });

        $request->server['REQUEST_METHOD'] = 'POST';
        $request->post = [
            'csrf_token' => $csrf,
            'mp_app_id' => ['bad'],
            'mp_client_secret' => ['bad'],
            'mp_env' => ['bad'],
            'mp_redirect_uri' => "https://example.test/callback\r\nX-Bad: 1",
        ];
        (new AdminMarketplaceController($registry))->save();
        self::assertSame('danger', $session->data['flash']['type'] ?? '');
        self::assertSame([], $registry->get('model_setting_setting')->saved);

        $webhook = new AdminWebhookController($registry);
        $request->post = [
            'trigger' => ['bad'],
            'url' => 'https://hooks.example.test/events',
            'secret' => '1234567890abcdef',
            'description' => 'Order events',
            'status' => ['bad'],
        ];
        $whPayload = (new \ReflectionMethod($webhook, 'postPayload'))->invoke($webhook);
        self::assertSame('', $whPayload['trigger']);
        self::assertSame(0, $whPayload['status']);
        $whErrors = (new \ReflectionMethod($webhook, 'validateWebhook'))->invoke($webhook, 0, false);
        self::assertNotEmpty($whErrors);
    }
}
