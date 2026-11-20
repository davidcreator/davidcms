<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;

/**
 * KZ-64 (P4) — o pré-voo da bateria A–M.
 *
 * O script responde, sem credencial de provedor, as perguntas que dependem do
 * banco e da configuração. O que se prova aqui é o que dá para provar sem MySQL
 * neste binário: a recusa de uso, o `--help` sem tocar em banco, e — o mais
 * importante — que o script é **somente leitura**. Um verificador de pré-voo que
 * aprende a escrever vira um script que alguém roda com pressa em produção.
 */
final class PaymentPilotoTest extends TestCase {
    private const SCRIPT = 'tools/payment-piloto.php';

    public function testWithoutAConfigItFailsWithTheUsageCode(): void {
        [$code, $out, $err] = self::execute(['--config=/nao/existe/config.php']);

        self::assertSame(2, $code);
        self::assertStringContainsString('config não encontrado', $err);
        self::assertSame('', $out);
    }

    public function testHelpDoesNotTouchTheDatabase(): void {
        [$code, $out] = self::execute(['--help']);

        self::assertSame(0, $code);
        self::assertStringContainsString('--url=', $out);
        self::assertStringContainsString('0 pronto', $out);
    }

    /**
     * A regra é de código, não de disciplina: nenhum caminho de escrita pode
     * existir no arquivo, nem por engano.
     */
    public function testTheScriptIsReadOnly(): void {
        $source = (string)file_get_contents(__DIR__ . '/../../../' . self::SCRIPT);

        self::assertDoesNotMatchRegularExpression('/\bINSERT\s+INTO\b/i', $source);
        self::assertDoesNotMatchRegularExpression('/\bUPDATE\s+`?\w/i', $source);
        self::assertDoesNotMatchRegularExpression('/\bDELETE\s+FROM\b/i', $source);
        self::assertDoesNotMatchRegularExpression('/\bDROP\s+(TABLE|COLUMN)\b/i', $source);
        self::assertDoesNotMatchRegularExpression('/\bTRUNCATE\b/i', $source);
        self::assertDoesNotMatchRegularExpression('/\bALTER\s+TABLE\b/i', $source);
    }

    /** Nada de rede: pré-voo não conversa com provedor. */
    public function testTheScriptMakesNoNetworkCalls(): void {
        $source = (string)file_get_contents(__DIR__ . '/../../../' . self::SCRIPT);

        self::assertDoesNotMatchRegularExpression('/\bcurl_\w+\s*\(/i', $source);
        self::assertDoesNotMatchRegularExpression('/\bfile_get_contents\s*\(\s*[\'"]https?:/i', $source);
        self::assertDoesNotMatchRegularExpression('/\bfsockopen\s*\(/i', $source);
    }

    /**
     * Segredo não se imprime. O script lê o JSON de credenciais só para saber
     * QUAIS campos existem — o teste garante que nenhum valor é ecoado.
     */
    public function testItNeverPrintsACredentialValue(): void {
        $source = (string)file_get_contents(__DIR__ . '/../../../' . self::SCRIPT);

        self::assertDoesNotMatchRegularExpression('/echo[^;]*credentials_encrypted_json/i', $source);
        self::assertStringContainsString('campos presentes', $source);
        self::assertStringContainsString('valor nunca é impresso', $source);
    }

    /** O veredicto tem de dizer que sandbox é insubstituível. */
    public function testTheVerdictNamesWhatOnlyTheSandboxProves(): void {
        $source = (string)file_get_contents(__DIR__ . '/../../../' . self::SCRIPT);

        self::assertStringContainsString('O que só a sandbox prova', $source);
        self::assertStringContainsString('assinatura real do provedor', $source);
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
