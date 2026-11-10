<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Mail;
use Reamur\System\Library\Mail\Capture;
use Reamur\System\Library\Mail\Mail as NativeMailAdaptor;
use Reamur\System\Library\Mail\Smtp;
use Reamur\System\Library\Response;

final class ResponseAndMailTest extends TestCase {
    public function testResponseHeaderAndRedirectSanitization(): void {
        $response = new Response();
        $response->addHeader("X-Test: ok\r\nSet-Cookie: evil=1\0");
        $response->addHeader("X-Test: okSet-Cookie: evil=1");
        $response->addHeader("\r\n\0");

        self::assertSame(['X-Test: okSet-Cookie: evil=1'], $response->getHeaders());

        self::assertSame(
            'https://example.test/index.php?route=common/home&a=1',
            Response::sanitizeRedirectTarget("https://example.test/index.php?route=common/home&amp;a=1\r\nX-Inject: 1\0")
        );
        self::assertSame('index.php', Response::sanitizeRedirectTarget('javascript:alert(1)'));
        self::assertSame('index.php', Response::sanitizeRedirectTarget('data:text/html,<script>alert(1)</script>'));
        self::assertSame('index.php', Response::sanitizeRedirectTarget('   '));
        self::assertSame('index.php?route=error/not_found', Response::sanitizeRedirectTarget('index.php?route=error/not_found'));

        $response->redirect('javascript:alert(1)');
        self::assertContains('Location: index.php', $response->getHeaders());
    }

    public function testMailFacadeRejectsHeaderInjectionAndInvalidEmails(): void {
        $captureFile = sys_get_temp_dir() . '/reamur_mail_test_' . uniqid('', true) . '.json';
        $mail = new Mail('capture', ['capture_path' => $captureFile]);

        $mail->setTo('dest@example.test');
        $mail->setFrom('noreply@example.test');
        $mail->setSender('ReamurCMS');
        $mail->setReplyTo('reply@example.test');
        $mail->setSubject("Hello\r\nBcc: attacker@evil.test\0");
        $mail->setText('Plain text body');

        self::assertTrue($mail->send());
        $payload = json_decode((string)file_get_contents($captureFile), true);
        @unlink($captureFile);

        self::assertIsArray($payload);
        self::assertSame('HelloBcc: attacker@evil.test', $payload['subject']);

        $this->expectException(\InvalidArgumentException::class);
        $mail->setFrom("evil@example.test\r\nBcc: victim@example.test");
    }

    public function testMailRejectsCrlfInSenderAndReplyTo(): void {
        $mail = new Mail('capture', []);

        $caught = false;
        try {
            $mail->setSender("Admin\r\nBcc: x@y.z");
        } catch (\InvalidArgumentException) {
            $caught = true;
        }
        self::assertTrue($caught);

        $this->expectException(\InvalidArgumentException::class);
        $mail->setReplyTo("a@example.test\nCc: b@example.test");
    }

    public function testNativeAndSmtpValidatorsAndDotStuffing(): void {
        self::assertSame('clean header', NativeMailAdaptor::sanitizeHeaderValue(" clean\r\n header\0 "));
        self::assertSame('user@example.test', Smtp::validateEmailAddress(' user@example.test ', 'from'));

        $lines = Smtp::prepareDataLines("Line 1\r\n.\r\n.hidden\r\nLine 4");
        self::assertSame(['Line 1', '..', '..hidden', 'Line 4'], $lines);

        $this->expectException(\InvalidArgumentException::class);
        Smtp::validateEmailAddress("attacker@example.test\r\nRCPT TO:<victim@example.test>", 'from');
    }

    public function testCaptureMailAdaptorWritesJson(): void {
        $captureFile = sys_get_temp_dir() . '/reamur_capture_' . uniqid('', true) . '.json';
        $capture = new Capture();
        $ok = $capture->send([
            'capture_path' => $captureFile,
            'to'           => ['a@example.test'],
            'from'         => 'noreply@example.test',
            'sender'       => 'Site',
            'subject'      => 'Test',
            'text'         => 'Body',
        ]);
        self::assertTrue($ok);
        $decoded = json_decode((string)file_get_contents($captureFile), true);
        @unlink($captureFile);
        self::assertIsArray($decoded);
        self::assertSame(['a@example.test'], $decoded['to']);
    }
}
