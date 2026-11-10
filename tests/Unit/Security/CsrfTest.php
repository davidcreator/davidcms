<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Security\Csrf;

final class CsrfTest extends TestCase {
    public function testTokenGeneratesHexAndIsStableWithinSession(): void {
        $session = (object)['data' => []];

        $first  = Csrf::token($session);
        $second = Csrf::token($session);

        self::assertSame(64, strlen($first));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
        self::assertSame($first, $second);
        self::assertSame($first, $session->data['csrf_token']);
    }

    public function testCheckAcceptsValidTokenAndRejectsInvalid(): void {
        $session = (object)['data' => []];
        $token   = Csrf::token($session);

        self::assertTrue(Csrf::check($session, $token));
        self::assertFalse(Csrf::check($session, ''));
        self::assertFalse(Csrf::check($session, 'wrong'));
        self::assertFalse(Csrf::check($session, ['nested' => $token]));
        self::assertFalse(Csrf::check($session, null));

        $emptySession = (object)['data' => []];
        self::assertFalse(Csrf::check($emptySession, $token));
    }
}
