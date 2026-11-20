<?php declare(strict_types=1);
namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Payment\ChargeStatusConcordance;

/**
 * ADR 0121 (R1) — a comparação entre a situação digitada pelo operador e a
 * situação do provedor. Nada aqui move dinheiro: a classe só lê dois textos.
 */
final class ChargeStatusConcordanceTest extends TestCase {
    /** @return iterable<string, array{string, string, bool}> */
    public static function pairs(): iterable {
        yield 'pago nos dois lados'            => ['paid', 'paid', false];
        yield 'estornado nos dois lados'       => ['refunded', 'refunded', false];
        yield 'parcial e o operador marcou partial' => ['partial', 'partially_refunded', false];
        yield 'parcial mas o operador marcou refunded' => ['refunded', 'partially_refunded', false];
        yield 'aguardando nos dois lados'      => ['pending', 'pending', false];
        yield 'autorizado, operador já disse pago' => ['paid', 'authorized', false];
        yield 'disputa nos dois lados'         => ['disputed', 'disputed', false];

        // Divergências reais: o operador afirmou mais do que o provedor.
        yield 'operador diz pago, provedor espera'  => ['paid', 'pending', true];
        yield 'operador diz estornado, provedor diz pago' => ['refunded', 'paid', true];
        yield 'operador diz pago, provedor falhou'  => ['paid', 'failed', true];
        yield 'operador diz pago, provedor estornou' => ['paid', 'refunded', true];
        yield 'situação de provedor desconhecida'   => ['paid', 'qualquer_coisa', true];
    }

    /** @dataProvider pairs */
    public function testDivergenceIsInformationNotTranslation(string $operator, string $provider, bool $expected): void {
        self::assertSame($expected, ChargeStatusConcordance::diverges($operator, $provider));
    }

    public function testAMissingSideIsNeverADivergence(): void {
        // Cobrança manual não tem situação de provedor; cobrança de gateway
        // recém-criada pode ainda não ter nenhuma. Nenhuma das duas é erro.
        self::assertFalse(ChargeStatusConcordance::diverges('', 'paid'));
        self::assertFalse(ChargeStatusConcordance::diverges('pending', ''));
        self::assertFalse(ChargeStatusConcordance::diverges('', ''));
    }

    public function testTheScreenPrefersTheProviderStatusAndFallsBackToTheOperator(): void {
        self::assertSame('paid', ChargeStatusConcordance::displayed('refunded', 'paid'));
        self::assertSame('pending', ChargeStatusConcordance::displayed('pending', ''));
    }

    public function testAnUnknownProviderStatusNeverAgreesWithAnything(): void {
        // Lista fechada de propósito: vocabulário novo do provedor não pode
        // passar como "concorda" só porque ninguém previu a palavra.
        foreach (['pending', 'paid', 'refunded', 'disputed', 'partial', 'canceled', 'expired'] as $operator) {
            self::assertTrue(ChargeStatusConcordance::diverges($operator, 'status_novo_do_provedor'), $operator);
        }
    }
}
