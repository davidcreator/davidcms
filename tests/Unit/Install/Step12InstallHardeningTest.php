<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Install;

use PHPUnit\Framework\TestCase;
use Reamur\Install\Controller\Install\Step_3;
use Reamur\Install\Model\Install\Install as InstallModel;
use Reamur\System\Engine\Registry;

final class Step12InstallHardeningTest extends TestCase {
    public function testEscapePhpStringLiteralNeutralizesQuotesBackslashesAndNul(): void {
        $raw = "p@ss'\\word\0'; system('id'); //";
        $escaped = Step_3::escapePhpStringLiteral($raw);

        self::assertStringNotContainsString("\0", $escaped);
        self::assertSame("p@ss\'\\\\word\'; system(\'id\'); //", $escaped);

        // Generated config line must tokenize as a single string literal (no syntax breakout)
        $snippet = "<?php define('DB_PASSWORD', '" . $escaped . "');";
        $tokens = token_get_all($snippet);
        $stringTokens = array_values(array_filter(
            $tokens,
            static fn($t): bool => is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING
        ));
        self::assertCount(2, $stringTokens);
        self::assertSame("'DB_PASSWORD'", $stringTokens[0][1]);
        self::assertSame("'" . $escaped . "'", $stringTokens[1][1]);
    }

    public function testInstallModelRequiresInitializedConnection(): void {
        $model = new InstallModel(new Registry());

        $this->expectException(\RuntimeException::class);
        $model->hasTables('rms_');
    }

    public function testInstallModelRejectsInvalidTablePrefix(): void {
        $model = new InstallModel(new Registry());

        $this->expectException(\InvalidArgumentException::class);
        $model->importSchema('rms_; DROP TABLE users; --');
    }

    public function testInstallModelCreateAdministratorRejectsInvalidPrefix(): void {
        $model = new InstallModel(new Registry());

        $this->expectException(\InvalidArgumentException::class);
        $model->createAdministrator([
            'username'  => 'admin',
            'firstname' => 'Admin',
            'lastname'  => 'User',
            'email'     => 'admin@example.test',
            'password'  => 'StrongPass123!',
        ], '1bad_prefix');
    }

    public function testGuessSiteUrlSanitizesMalformedHttpHostHeader(): void {
        $registry = new Registry();
        $request = new \Reamur\System\Library\Request();
        $request->server['HTTPS'] = 'on';
        $request->server['HTTP_HOST'] = "evil.example:8443\r\nX-Injected: 1";
        $request->server['SCRIPT_NAME'] = '/cms/install/index.php';
        $registry->set('request', $request);

        $controller = new Step_3($registry);
        $method = new \ReflectionMethod($controller, 'guessSiteUrl');
        $method->setAccessible(true);

        self::assertSame('https://evil.example:8443X-Injected1/cms/catalog/', $method->invoke($controller));
    }
}
