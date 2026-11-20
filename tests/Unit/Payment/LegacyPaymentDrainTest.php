<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;

/**
 * ADR 0122 (F3): a drenagem de `rms_mp_payment` é uma medição, não uma remoção.
 *
 * O que este teste protege: o script **nunca** pode sair por 0 dizendo "pode
 * apagar". O veredicto `drained` significa "não foi tocado na janela", não
 * "remova"; a saída padrão diz isso em palavras, e o teste fixa o texto para
 * que ninguém encurte a saída e transforme o aviso em autorização.
 */
final class LegacyPaymentDrainTest extends TestCase {
    private const SCRIPT = 'tools/legacy-payment-drain.php';

    public function testWithoutAConfigItFailsWithTheUsageCode(): void {
        [$code, $out, $err] = self::execute(['--config=/nao/existe/config.php']);

        self::assertSame(2, $code);
        self::assertStringContainsString('config não encontrado', $err);
        self::assertSame('', $out);
    }

    public function testItRejectsAnOutOfRangeWindow(): void {
        [$code, , $err] = self::execute(['--config=catalog/config.php', '--days=0']);

        self::assertSame(2, $code);
        self::assertStringContainsString('dias fora da faixa', $err);
    }

    public function testHelpDoesNotTouchTheDatabase(): void {
        [$code, $out] = self::execute(['--help']);

        self::assertSame(0, $code);
        self::assertStringContainsString('--days=', $out);
    }

    public function testTheScriptNeverContainsADeleteStatement(): void {
        // A regra é de código, não de disciplina: um script de drenagem que
        // aprende a apagar vira um script que alguém roda com pressa.
        $source = (string)file_get_contents(__DIR__ . '/../../../' . self::SCRIPT);

        self::assertDoesNotMatchRegularExpression('/\bDELETE\s+FROM\b/i', $source);
        self::assertDoesNotMatchRegularExpression('/\bDROP\s+TABLE\b/i', $source);
        self::assertDoesNotMatchRegularExpression('/\bTRUNCATE\b/i', $source);
    }

    public function testTheDrainedVerdictExplicitlySaysItDoesNotAuthoriseDeletion(): void {
        $source = (string)file_get_contents(__DIR__ . '/../../../' . self::SCRIPT);

        self::assertStringContainsString('Isto NÃO autoriza apagar', $source);
    }

    /** @return array{int, string, string} */
    private static function execute(array $args): array {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 3) . '/' . self::SCRIPT);

        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 3));

        if (!is_resource($process)) {
            self::fail('não foi possível executar o script');
        }

        $out = (string)stream_get_contents($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $out, $err];
    }
}
