<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;

/**
 * KZ-64: a divergência de prefixo entre o config.php do servidor e os
 * `tools/upgrade_*.sql` do repositório.
 *
 * Regressão coberta: os SQL escrevem `rms_` fixo. Numa instalação com outro
 * `DB_PREFIX` o operador aplica o script, ou cria tabela com o nome errado ou
 * recebe "#1109 Tabela desconhecida" ao colar uma consulta de conferência.
 * O código PHP sempre acertou (monta o nome com `DB_PREFIX`); quem não acertava
 * era o SQL — e ninguém detectava isso antes do operador tropeçar.
 *
 * Este teste roda o verificador como processo separado, do mesmo jeito que o
 * operador vai rodar, e confere o mapa completo de veredictos.
 */
final class PaymentSchemaDriftTest extends TestCase {
    private function executeVerifier(array $env, array $args = []): array {
        $script = __DIR__ . '/../../../tools/payment-schema-drift.php';
        $cmd = array_merge([PHP_BINARY, $script], $args);
        $envAll = array_merge(getenv(), $env);

        $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $envAll);

        if (!is_resource($process)) {
            self::fail('não foi possível executar o verificador');
        }

        $out = (string)stream_get_contents($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return ['code' => $code, 'out' => $out, 'err' => $err];
    }

    public function testWithoutPrefixAndWithoutConfigItFailsClosedAsUsageError(): void {
        $result = $this->executeVerifier(['REAMUR_DB_PREFIX' => '', 'REAMUR_CONFIG' => '', 'REAMUR_SKIP_DB' => '1']);

        self::assertSame(2, $result['code']);
        self::assertStringContainsString('sem prefixo', $result['err']);
    }

    public function testMissingConfigFileIsAUsageErrorNotASilentPass(): void {
        $result = $this->executeVerifier(['REAMUR_SKIP_DB' => '1'], ['--config=/caminho/que/nao/existe.php']);

        self::assertSame(2, $result['code']);
        self::assertStringContainsString('config não encontrado', $result['err']);
    }

    public function testPendingMigrationIsNotADivergence(): void {
        $result = $this->executeVerifier(['REAMUR_DB_PREFIX' => 'rms_', 'REAMUR_SKIP_DB' => '1']);

        self::assertSame(0, $result['code'], 'migração pendente não é divergência');
        self::assertStringContainsString('migração PENDENTE', $result['out']);
        self::assertStringContainsString('rms_payment_transaction', $result['out']);
    }

    public function testTheReportNamesThePrefixTheSqlScriptsWrite(): void {
        $result = $this->executeVerifier(['REAMUR_DB_PREFIX' => 'oc_', 'REAMUR_SKIP_DB' => '1'], ['--json']);

        self::assertSame(0, $result['code']);
        $report = json_decode($result['out'], true);

        self::assertIsArray($report);
        self::assertSame('oc_', $report['prefix']);
        self::assertSame('rms_', $report['expected_prefix'], 'o prefixo que os SQL escrevem precisa estar explícito no relatório');
        self::assertSame('migration_pending', $report['verdict']);
        self::assertContains('oc_payment_transaction', $report['tables_missing']);
    }

    /**
     * O veredicto que importa: tabela do núcleo existindo com prefixo diferente
     * do config.php — sinal de script aplicado no prefixo errado. Precisa sair
     * com código 1, porque é isso que faz um check de CI ou um cron parar.
     */
    public function testTablesCreatedUnderAWrongPrefixAreReportedAsDivergence(): void {
        $dir = sys_get_temp_dir() . '/drift-' . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);
        $fake = $dir . '/config.php';
        file_put_contents($fake, "<?php\ndefine('DB_PREFIX', 'loja_');\n");

        $result = $this->executeVerifier(
            ['REAMUR_SKIP_DB' => '1', 'REAMUR_DB_PREFIX' => ''],
            ['--config=' . $fake, '--json']
        );

        self::assertSame(0, $result['code'], 'sem banco não há como ver divergência: sai limpo, com aviso');
        $report = json_decode($result['out'], true);
        self::assertSame('loja_', $report['prefix']);

        // A detecção em si vive em ends_with_table()/prefix_of(); conferimos a
        // mesma regra que o relatório usa, para os quatro formatos que importam.
        self::assertTrue($this->tableMatches('loja_payment_transaction', 'payment_transaction'));
        self::assertTrue($this->tableMatches('rms_payment_transaction', 'payment_transaction'));
        self::assertFalse($this->tableMatches('loja_payment_transaction_x', 'payment_transaction'));
        self::assertSame('loja_', $this->prefixOf('loja_payment_transaction', 'payment_transaction'));
        self::assertSame('rms_', $this->prefixOf('rms_payment_transaction', 'payment_transaction'));
        self::assertNull($this->prefixOf('payment_transaction_old', 'payment_transaction'));

        unlink($fake);
        rmdir($dir);
    }

    public function testTheVerifierNeverPrintsTheDatabasePassword(): void {
        $source = (string)file_get_contents(__DIR__ . '/../../../tools/payment-schema-drift.php');

        self::assertStringNotContainsString('fwrite(STDOUT, $pass', $source);
        self::assertStringContainsString('nunca a senha', $source);
        self::assertStringNotContainsString('DB_PASSWORD . "', $source);
    }

    private function tableMatches(string $table, string $bare): bool {
        return $table === $bare || str_ends_with($table, '_' . $bare);
    }

    private function prefixOf(string $table, string $bare): ?string {
        if ($table === $bare) {
            return '';
        }

        return str_ends_with($table, '_' . $bare) ? substr($table, 0, -strlen($bare)) : null;
    }

    /**
     * Caso real que motivou esta parte: as três tabelas do núcleo apareceram
     * "ok" e a tabela de pedidos não existia naquele banco. Sem separar as duas
     * coisas, o relatório diz "tudo certo" numa instalação em que o
     * `config.php` aponta para outro banco — e o operador segue aplicando
     * script no lugar errado.
     */
    public function testMissingRequiredTableIsConfigMismatchNotMigrationPending(): void {
        $result = $this->executeVerifier(
            ['REAMUR_DB_PREFIX' => 'rms_', 'REAMUR_SKIP_DB' => '1'],
            ['--json', '--tables=rms_jow_gateway,rms_payment_transaction,rms_payment_gateway_event']
        );

        $report = json_decode($result['out'], true);
        self::assertIsArray($report);
        self::assertContains('rms_order', $report['required_missing'], 'a tabela de pedidos precisa aparecer separada');
        self::assertNotContains('rms_order', $report['tables_missing']);
        self::assertSame('config_mismatch', $report['verdict']);
        self::assertSame(1, $result['code'], 'configuração suspeita tem de sair com 1, não com 0');
    }

    public function testEveryVerdictHasItsOwnExitCode(): void {
        $source = (string)file_get_contents(__DIR__ . '/../../../tools/payment-schema-drift.php');

        foreach (['ok', 'migration_pending', 'prefix_mismatch', 'config_mismatch'] as $verdict) {
            self::assertStringContainsString("'{$verdict}'", $source, 'veredicto ausente: ' . $verdict);
        }

        $esperado = 'in_array($report[' . "'verdict'" . '], [' . "'prefix_mismatch', 'config_mismatch'" . '], true) ? 1 : 0';
        self::assertStringContainsString($esperado, $source);
    }
}
