<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Docs;

use PHPUnit\Framework\TestCase;

/**
 * ADR 0126 — o índice de ADRs e os caminhos citados nos documentos.
 *
 * O que se prova aqui é o estado do repositório, não a ferramenta: toda ADR tem
 * linha no índice e nenhum documento operacional manda executar caminho que não
 * existe. É o tipo de defeito que passa em revisão de código porque ninguém abre
 * o índice para conferir.
 */
final class DocsIndexTest extends TestCase {
    private const ROOT = __DIR__ . '/../../..';

    /** @return list<string> */
    private static function adrs(): array {
        $arqs = glob(self::ROOT . '/agents-ia/decisoes/[0-9][0-9][0-9][0-9]-*.md') ?: [];
        sort($arqs);

        return $arqs;
    }

    private static function indice(): string {
        return (string)file_get_contents(self::ROOT . '/agents-ia/decisoes/README.md');
    }

    /** Toda ADR no disco tem linha no índice — inclusive as antigas. */
    public function testEveryAdrOnDiskHasALineInTheIndex(): void {
        $indice = self::indice();
        $fora = [];

        foreach (self::adrs() as $arquivo) {
            $numero = substr(basename($arquivo), 0, 4);

            if (preg_match('/\[' . $numero . '\]\(/', $indice) !== 1) {
                $fora[] = $numero;
            }
        }

        self::assertSame([], $fora, 'ADR sem linha no índice: ' . implode(', ', $fora));
    }

    /** E nenhuma linha do índice aponta para arquivo que não existe. */
    public function testNoIndexEntryPointsAtAMissingFile(): void {
        preg_match_all('/\[(\d{4})\]\(([^)]+)\)/', self::indice(), $m, PREG_SET_ORDER);

        $quebradas = [];

        foreach ($m as $achado) {
            if (!is_file(self::ROOT . '/agents-ia/decisoes/' . $achado[2])) {
                $quebradas[] = $achado[1] . ' → ' . $achado[2];
            }
        }

        self::assertSame([], $quebradas);
    }

    /**
     * Os documentos operacionais não podem citar caminho versionado que não
     * existe. A varredura é do verificador — duplicá-la aqui criaria duas
     * listas de exceção para manter em sincronia.
     */
    public function testOperationalDocumentsDoNotCiteMissingPaths(): void {
        [$code, $out] = self::execute(['--json']);

        self::assertSame(0, $code);

        $json = json_decode($out, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([], $json['caminhos_citados_inexistentes']);
        self::assertGreaterThan(10, count($json['documentos_verificados']), 'a varredura tem de olhar um conjunto real de documentos');
    }

    /**
     * Caminho gerado (vendor, docs/api) não é erro: são os lugares para onde
     * `composer install` e o gerador de documentação escrevem. O verificador
     * precisa ignorá-los, senão reprova num clone limpo o que aprova numa
     * máquina que já instalou.
     */
    public function testGeneratedPathsAreNotTreatedAsMissing(): void {
        $fonte = (string)file_get_contents(self::ROOT . '/tools/verify-docs-index.php');

        self::assertStringContainsString('CAMINHOS_GERADOS', $fonte);
        self::assertStringContainsString('dev/system/storage/vendor/', $fonte);
        self::assertStringContainsString('ehCaminhoGerado', $fonte);
    }

    /** A ferramenta roda e concorda com os testes acima. */
    public function testTheVerifierAgreesWithTheRepository(): void {
        [$code, $out] = self::execute(['--json']);

        self::assertSame(0, $code);

        $json = json_decode($out, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('ok', $json['veredicto']);
        self::assertSame([], $json['adrs_fora_do_indice']);
        self::assertSame([], $json['entradas_do_indice_quebradas']);
        self::assertSame([], $json['caminhos_citados_inexistentes']);
    }

    /** `--strict` transforma caminho inexistente em erro — e hoje não há nenhum. */
    public function testStrictModeAlsoPassesToday(): void {
        [$code] = self::execute(['--strict']);

        self::assertSame(0, $code);
    }

    /** @return array{int, string} */
    private static function execute(array $args): array {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(self::ROOT . '/tools/verify-docs-index.php');

        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, self::ROOT);

        if (!is_resource($process)) {
            self::fail('não foi possível executar o verificador');
        }

        $out = (string)stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $out];
    }
}
