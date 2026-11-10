<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Security\UrlGuard;

final class UrlGuardTest extends TestCase {
    public function testPublicAndPrivateIps(): void {
        self::assertTrue(UrlGuard::isPublicIp('8.8.8.8'));
        self::assertTrue(UrlGuard::isPublicIp('1.1.1.1'));
        self::assertTrue(UrlGuard::isPublicIp('2606:4700:4700::1111'));

        self::assertFalse(UrlGuard::isPublicIp('127.0.0.1'));
        self::assertFalse(UrlGuard::isPublicIp('10.0.0.1'));
        self::assertFalse(UrlGuard::isPublicIp('172.16.0.1'));
        self::assertFalse(UrlGuard::isPublicIp('192.168.1.1'));
        self::assertFalse(UrlGuard::isPublicIp('169.254.169.254'));
        self::assertFalse(UrlGuard::isPublicIp('100.64.0.1'));
        self::assertFalse(UrlGuard::isPublicIp('0.0.0.0'));
        self::assertFalse(UrlGuard::isPublicIp('::1'));
        self::assertFalse(UrlGuard::isPublicIp('fe80::1'));
        self::assertFalse(UrlGuard::isPublicIp('fc00::1'));
        self::assertFalse(UrlGuard::isPublicIp('::ffff:127.0.0.1'));
    }

    public function testSafeExternalUrlBlocksSsrfVectors(): void {
        self::assertTrue(UrlGuard::isSafeExternalUrl('https://example.org/webhook', true, false));
        self::assertTrue(UrlGuard::isSafeExternalUrl('http://8.8.8.8/wp-json', false, true));

        self::assertFalse(UrlGuard::isSafeExternalUrl('http://example.org/webhook', true, false));
        self::assertFalse(UrlGuard::isSafeExternalUrl('file:///etc/passwd', false, false));
        self::assertFalse(UrlGuard::isSafeExternalUrl('gopher://127.0.0.1:6379/', false, false));
        self::assertFalse(UrlGuard::isSafeExternalUrl('https://user:pass@example.org/', true, false));
        self::assertFalse(UrlGuard::isSafeExternalUrl('https://localhost/admin', true, true));
        self::assertFalse(UrlGuard::isSafeExternalUrl('https://meta.internal/latest', true, true));
        self::assertFalse(UrlGuard::isSafeExternalUrl('https://127.0.0.1:8080/hook', true, true));
        self::assertFalse(UrlGuard::isSafeExternalUrl('http://169.254.169.254/latest/meta-data/', false, true));
        self::assertFalse(UrlGuard::isSafeExternalUrl('https://[::1]/hook', true, true));
        self::assertFalse(UrlGuard::isSafeExternalUrl("https://example.org/a\nb", true, false));
    }

    public function testResolveRedirectHandlesRelativeAndAbsoluteTargets(): void {
        self::assertSame(
            'https://cdn.example.org/img/a.png',
            UrlGuard::resolveRedirect('https://example.org/blog/post', 'https://cdn.example.org/img/a.png')
        );
        self::assertSame(
            'https://cdn.example.org/img/a.png',
            UrlGuard::resolveRedirect('https://example.org/blog/post', '//cdn.example.org/img/a.png')
        );
        self::assertSame(
            'https://example.org/wp-json/wp/v2/posts',
            UrlGuard::resolveRedirect('https://example.org/blog/post', '/wp-json/wp/v2/posts')
        );
        self::assertSame(
            'https://example.org/blog/next',
            UrlGuard::resolveRedirect('https://example.org/blog/post', 'next')
        );
        self::assertSame('', UrlGuard::resolveRedirect('https://example.org/blog', "bad\nlocation"));
    }
}
