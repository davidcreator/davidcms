<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Payment\FxQuote;
use Reamur\System\Library\Payment\GatewayAdapter;
use Reamur\System\Library\Payment\GatewayAccount;
use Reamur\System\Library\Payment\GatewayCapabilities;
use Reamur\System\Library\Payment\GatewayCredentials;
use Reamur\System\Library\Payment\GatewayCustomerAction;
use Reamur\System\Library\Payment\GatewayCredentialVault;
use Reamur\System\Library\Payment\GatewayPaymentRequest;
use Reamur\System\Library\Payment\GatewayPaymentResult;
use Reamur\System\Library\Payment\GatewayPaymentStatus;
use Reamur\System\Library\Payment\GatewayRegistry;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\Money;
use Reamur\System\Library\Payment\Notification;
use Reamur\System\Library\Security\SecretBox;

final class GatewayCoreTest extends TestCase {
    public function testMoneyKeepsExactCurrencyPrecisionWithoutFloats(): void {
        $brl = new Money('00012.3', 'BRL', 2);
        $jpy = new Money('500', 'JPY', 0);
        $kwd = new Money('1.005', 'KWD', 3);

        self::assertSame('12.30', $brl->amount());
        self::assertSame('1230', $brl->minorUnits());
        self::assertSame('500', $jpy->amount());
        self::assertSame('1005', $kwd->minorUnits());
        self::assertTrue(Money::fromMinorUnits('1230', 'BRL', 2)->equals($brl));
    }

    public function testMoneyRejectsRoundingAndWrongCurrencyUnits(): void {
        try {
            new Money('1.001', 'BRL', 2);
            self::fail('no implicit currency rounding');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }

        try {
            (new Money('1.00', 'BRL', 2))->compareTo(new Money('1.00', 'USD', 2));
            self::fail('currencies cannot be compared');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
    }

    public function testGatewayCapabilitiesAreAnExplicitMethodCurrencyMatrix(): void {
        $caps = new GatewayCapabilities(
            ['pix' => ['BRL'], 'card' => ['BRL', 'USD']],
            ['BRL' => 2, 'USD' => 2],
            ['BRL' => ['USD']]
        );

        self::assertTrue($caps->supports('pix', 'BRL'));
        self::assertFalse($caps->supports('pix', 'USD'));
        self::assertSame(2, $caps->fractionDigits('USD'));
        self::assertTrue($caps->supportsFxQuote('BRL', 'USD'));
        self::assertFalse($caps->supportsFxQuote('USD', 'BRL'));
    }

    public function testProviderQuoteLocksBothExactAmountsAndExpiry(): void {
        $now = new DateTimeImmutable('2026-11-17T12:00:00+00:00');
        $quote = new FxQuote(
            'stub', 'quote-501', new Money('100.00', 'BRL', 2), new Money('18.40', 'USD', 2),
            '0.184', $now, $now->modify('+5 minutes')
        );

        self::assertSame('18.40', $quote->chargeAmount->amount());
        self::assertSame('0.184', $quote->rate);
        self::assertTrue($quote->isUsableAt($now->modify('+1 minute')));
        self::assertFalse($quote->isUsableAt($now->modify('+5 minutes')));
    }

    public function testGatewayCredentialVaultEncryptsAndBindsSecretsToGatewayField(): void {
        $vault = new GatewayCredentialVault(new SecretBox(SecretBox::generateKey()));
        $sealed = $vault->seal(7, 'stub', 'secret_key', 'sandbox-secret-value');

        self::assertTrue(SecretBox::isSealed($sealed));
        self::assertSame('sandbox-secret-value', $vault->open(7, 'stub', 'secret_key', $sealed));

        $encryptedJson = $vault->sealAll(7, 'stub', ['secret_key' => 'sandbox-secret-value', 'webhook_secret' => 'hook-value']);
        self::assertStringNotContainsString('sandbox-secret-value', $encryptedJson);
        $opened = $vault->openJson(7, 'stub', $encryptedJson);
        self::assertSame('sandbox-secret-value', $opened->get('secret_key'));
        self::assertSame('hook-value', $opened->get('webhook_secret'));

        try {
            $vault->open(7, 'stub', 'webhook_secret', $sealed);
            self::fail('ciphertext must not open in a different context');
        } catch (\RuntimeException) {
            self::assertTrue(true);
        }

        $credentials = new GatewayCredentials(['secret_key' => 'do-not-print-this']);
        self::assertStringNotContainsString('do-not-print-this', print_r($credentials, true));
    }

    public function testVaultFailsClosedWhenItCannotSeal(): void {
        $vault = new GatewayCredentialVault(new SecretBox());

        $this->expectException(\RuntimeException::class);
        $vault->seal(7, 'stub', 'secret_key', 'plain-secret');
    }

    public function testRegistryOnlyResolvesExplicitlyRegisteredProviders(): void {
        $adapter = new GatewayCoreTestAdapter();
        $registry = new GatewayRegistry([$adapter]);

        self::assertSame($adapter, $registry->get('stub'));
        self::assertCount(1, $registry->all());
        $this->expectException(\OutOfBoundsException::class);
        $registry->get('arbitrary_database_class');
    }

    public function testGatewayAccountDoesNotAllowSecretsInsidePublicConfig(): void {
        $account = new GatewayAccount(9, 'stub', 'Sandbox', 'sandbox', false, ['merchant_id' => 'm_100']);
        self::assertSame(['merchant_id' => 'm_100'], $account->publicConfig());
        self::assertStringNotContainsString('m_100', print_r($account, true));

        $this->expectException(\InvalidArgumentException::class);
        new GatewayAccount(9, 'stub', 'Sandbox', 'sandbox', false, ['api_secret' => 'do-not-store-here']);
    }

    public function testRequestAndResultRequireSafeUrlsAndQuoteAmountMatch(): void {
        $now = new DateTimeImmutable('2026-11-17T12:00:00+00:00');
        $source = new Money('100.00', 'BRL', 2);
        $quote = new FxQuote('stub', 'quote-502', $source, new Money('18.40', 'USD', 2), '0.184', $now, $now->modify('+5 minutes'));
        $request = new GatewayPaymentRequest(
            'jow_charge', 42, $source, 'USD', 'card', 'jow_charge-42-attempt-1', 'Cobrança 42',
            'https://shop.example/pay/return', 'https://shop.example/pay/notify'
        );
        $result = new GatewayPaymentResult('charge-42', GatewayCustomerAction::redirect('https://gateway.example/pay/42'), $quote->chargeAmount, GatewayPaymentStatus::PENDING, $quote, 'pay-42');

        self::assertSame('USD', $result->chargeAmount->currency);
        self::assertSame('jow_charge', $request->sourceType);

        $this->expectException(\InvalidArgumentException::class);
        new GatewayPaymentRequest(
            'order', 1, $source, 'BRL', 'card', 'short', 'Order',
            'http://insecure.example/return', 'https://shop.example/notify'
        );
    }
}

final class GatewayCoreTestAdapter implements GatewayAdapter {
    public function code(): string { return 'stub'; }
    public function label(): string { return 'Test gateway'; }
    public function capabilities(): GatewayCapabilities {
        return new GatewayCapabilities(['card' => ['BRL', 'USD']], ['BRL' => 2, 'USD' => 2], ['BRL' => ['USD']]);
    }
    public function quote(Money $sourceAmount, string $chargeCurrency, GatewayAccount $account, GatewayCredentials $credentials): ?FxQuote {
        return null;
    }
    public function createPayment(GatewayPaymentRequest $request, GatewayAccount $account, GatewayCredentials $credentials, ?FxQuote $quote): GatewayPaymentResult {
        return new GatewayPaymentResult('ref-1', GatewayCustomerAction::redirect('https://gateway.example/pay/1'), $request->sourceAmount, GatewayPaymentStatus::PENDING, $quote);
    }
    public function verifyNotification(Notification $notification, GatewayAccount $account, GatewayCredentials $credentials): GatewayWebhookResult {
        return GatewayWebhookResult::ignored('not_a_payment');
    }
}
