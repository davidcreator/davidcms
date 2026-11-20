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
        self::assertGreaterThan(80, count($json['documentos_verificados']), 'a varredura tem de olhar TODO documento operacional, não uma amostra escolhida a dedo');
        self::assertArrayHasKey('documentos_historicos', $json);
        self::assertArrayHasKey('caminhos_gerados', $json);
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

    /**
     * ADR 0127 — o escopo é DERIVADO, não escrito à mão.
     *
     * O guarda da ADR 0126 tinha uma lista de 27 documentos escolhidos a dedo.
     * Em duas semanas ela já estava incompleta: `docs/PROJECT-HANDOFF.md` e os
     * vinte guias de tema nunca foram verificados. O teste que vem abaixo fixa
     * o sintoma: se alguém voltar a enumerar arquivos, estes dois deixam de
     * estar no escopo e o teste falha.
     */
    public function testOperationalScopeIsDerivedAndNotAHandWrittenList(): void {
        $escopo = self::escopo();

        self::assertContains('docs/PROJECT-HANDOFF.md', $escopo['operacionais'], 'handoff é instrução: um caminho errado ali manda a próxima sessão pelo lugar errado');
        self::assertContains('docs/temas/guias/GUIA-TEMAS-CONDICOES-THREEJS.md', $escopo['operacionais'], 'guia de tema é instrução');
        self::assertContains('agents-ia/decisoes/0126-indice-de-adrs-e-caminhos-citados.md', $escopo['historicos'], 'ADR é registro: caminho removido ali está correto');
        self::assertContains('docs/fases/f9/BDREditor-Complete-Reference-and-Closure.md', $escopo['historicos'], 'fase é registro');
        self::assertContains('RELATORIO-KZ64.md', $escopo['historicos'], 'relatório datado da raiz é registro');

        self::assertGreaterThan(250, count($escopo['historicos']) + count($escopo['operacionais']), 'a classificação tem de cobrir todo .md versionado');
    }

    /**
     * Toda página em `docs/` é alcançável a partir do índice `docs/README.md`
     * (um salto, ou dois quando passa por um `README.md` de pasta).
     *
     * Foi assim que a documentação de pagamento envelheceu: nove documentos
     * existiam, estavam corretos e **não estavam no índice** — ninguém os lia
     * para notar que descreviam o fluxo antigo. Um documento que ninguém acha é
     * um documento que ninguém corrige.
     */
    public function testEveryPageUnderDocsIsReachableFromTheDocsIndex(): void {
        $escopo = self::escopo();
        $paginas = array_values(array_filter(
            array_merge($escopo['operacionais'], $escopo['historicos']),
            static fn (string $p): bool => str_starts_with($p, 'docs/') && $p !== 'docs/README.md'
        ));

        $alcancaveis = self::alcancaveis('docs/README.md');
        $fora = array_values(array_filter($paginas, static fn (string $p): bool => !isset($alcancaveis[$p])));

        self::assertGreaterThan(90, count($paginas), 'o índice tem de ser conferido contra a árvore real');
        self::assertSame([], $fora, 'página em docs/ fora do índice: ' . implode(', ', $fora));
    }

    /** @return array<string, true> páginas alcançáveis, normalizadas como caminho from-root */
    private static function alcancaveis(string $indice): array {
        $alcance = [];
        $fila = [$indice];

        while ($fila !== []) {
            $atual = array_pop($fila);

            if (isset($alcance[$atual]) || !is_file(self::ROOT . '/' . $atual)) {
                continue;
            }

            $alcance[$atual] = true;
            $texto = (string)file_get_contents(self::ROOT . '/' . $atual);

            preg_match_all('/\]\(([^)#]+\.md)\)/', $texto, $m);

            foreach ($m[1] as $alvo) {
                $destino = self::normaliza(dirname($atual) . '/' . $alvo);

                // Só segue por README de pasta: guia não é índice de guia.
                if (basename($destino) === 'README.md') {
                    $fila[] = $destino;
                } else {
                    $alcance[$destino] = true;
                }
            }
        }

        return $alcance;
    }

    private static function normaliza(string $caminho): string {
        $partes = [];

        foreach (explode('/', $caminho) as $parte) {
            if ($parte === '..') {
                array_pop($partes);
            } elseif ($parte !== '.' && $parte !== '') {
                $partes[] = $parte;
            }
        }

        return implode('/', $partes);
    }

    /** @return array{operacionais: list<string>, historicos: list<string>, caminhos_gerados: list<string>} */
    private static function escopo(): array {
        $command = 'node ' . escapeshellarg(self::ROOT . '/tools/verify-docs-index.js') . ' --json';
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, self::ROOT);

        if (!is_resource($process)) {
            self::fail('não foi possível executar a classificação de documentos');
        }

        $out = (string)stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $dados = json_decode($out, true);

        self::assertIsArray($dados, 'tools/verify-docs-index.js --json devolveu saída inválida');

        return $dados;
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
