<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Reamur\Admin\Controller\Custom_field\Definition as CustomFieldDefinition;
use Reamur\Catalog\Controller\Checkout\Payment;
use Reamur\Catalog\Controller\Checkout\Quote;
use Reamur\Catalog\Controller\Common\Contact;
use Reamur\System\Engine\Controller;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Request;
use Reamur\System\Library\Security\Csrf;
use Reamur\System\Library\Session;

final class Step13ControllerSecurityTest extends TestCase {
    private function makeSession(): Session {
        $ref = new \ReflectionClass(Session::class);
        /** @var Session $session */
        $session = $ref->newInstanceWithoutConstructor();
        $session->data = [];

        return $session;
    }

    public function testControllerPostScalarAndCsrfValidHandleArrayPayloadsWithoutWarnings(): void {
        $registry = new Registry();
        $request  = new Request();
        $session  = $this->makeSession();
        $validToken = Csrf::token($session);

        $request->post = [
            'csrf_token' => ['array_attack'],
            'username'   => ['nested' => 'evil'],
            'company'    => ['bot_array'],
            'count'      => 42,
            'flag'       => true,
        ];
        $registry->set('request', $request);
        $registry->set('session', $session);

        $probe = new class($registry) extends Controller {
            public function scalar(string $k, string $d = ''): string { return $this->postScalar($k, $d); }
            public function checkCsrf(): bool { return $this->csrfValid(); }
        };

        self::assertFalse($probe->checkCsrf());
        self::assertSame('fallback', $probe->scalar('username', 'fallback'));
        self::assertSame('42', $probe->scalar('count'));
        self::assertSame('1', $probe->scalar('flag'));
        self::assertSame('', $probe->scalar('missing'));

        $customField = new class($registry) extends CustomFieldDefinition {
            public function checkCsrf(): bool { return $this->csrfValid(); }
        };
        self::assertFalse($customField->checkCsrf());

        // Honeypot checks must never emit Array-to-string conversion warnings
        $contact = new Contact($registry);
        $quote   = new Quote($registry);
        $contactBot = new \ReflectionMethod($contact, 'isBot');
        $quoteBot   = new \ReflectionMethod($quote, 'isBot');
        self::assertFalse($contactBot->invoke($contact));
        self::assertFalse($quoteBot->invoke($quote));

        $request->post['company']    = 'Acme Bot';
        $request->post['csrf_token'] = $validToken;
        self::assertTrue($contactBot->invoke($contact));
        self::assertTrue($quoteBot->invoke($quote));
        self::assertTrue($probe->checkCsrf());
        self::assertTrue($customField->checkCsrf());
    }

    public function testCheckoutPaymentVerifyWebhookSignatureValidatesManifestAndFormat(): void {
        $secret    = 'mp-webhook-secret-123456';
        $paymentId = 987654321;
        $requestId = 'req-abc-123';
        $ts        = '1731200000';
        $manifest  = "id:{$paymentId};request-id:{$requestId};ts:{$ts};";
        $sig       = hash_hmac('sha256', $manifest, $secret);

        self::assertTrue(Payment::verifyWebhookSignature($secret, $paymentId, "ts={$ts},v1={$sig}", $requestId));
        self::assertTrue(Payment::verifyWebhookSignature($secret, $paymentId, "ts={$ts}, v1=" . strtoupper($sig), $requestId));
        self::assertTrue(Payment::verifyWebhookSignature('', $paymentId, '', ''));

        self::assertFalse(Payment::verifyWebhookSignature($secret, $paymentId, "ts=not-digits,v1={$sig}", $requestId));
        self::assertFalse(Payment::verifyWebhookSignature($secret, $paymentId, "ts={$ts},v1=short", $requestId));
        self::assertFalse(Payment::verifyWebhookSignature($secret, $paymentId, "ts={$ts},v1=" . str_repeat('a', 64), $requestId));
        self::assertFalse(Payment::verifyWebhookSignature($secret, $paymentId, "ts={$ts},v1={$sig}", "req\r\nevil"));
    }
}
