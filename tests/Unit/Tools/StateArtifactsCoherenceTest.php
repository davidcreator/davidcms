<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;

/**
 * ADR 0130 — os artefatos de ESTADO de `tools/` não podem repetir contagem de
 * decisão nem número de contratos congelados: quem transcreve um número
 * cristaliza o dia em que ele foi verdade.
 *
 * Isto não é limpeza de texto. O guarda que imprime a frase estava falso havia
 * 4 dias (a matriz tem 34 `DECIDED` / 0 `PENDING_DECISION` desde 17/11), o
 * JSON de evidência repetia a mesma frase, e a auditoria de drift errava o
 * total de contratos por 19 — e os três passavam no CI, porque nenhum deles é
 * verificado contra a matriz.
 */
final class StateArtifactsCoherenceTest extends TestCase {
    private const ROOT = __DIR__ . '/../../..';

    private static function python(): ?string {
        foreach (['python3', 'python'] as $bin) {
            $out = [];
            $rc = 0;
            exec(escapeshellarg($bin) . ' -c "import sys;print(1)" 2>&1', $out, $rc);
            if ($rc === 0 && trim($out[0] ?? '') === '1') {
                return $bin;
            }
        }

        return null;
    }

    /** O estado atual do repositório: nada reescrito, e o número impresso vem da matriz. */
    public function testNoStateArtifactRestatesTheMatrixCounts(): void {
        $python = self::python();
        if ($python === null) {
            self::markTestSkipped('nenhum interpretador Python real — o guarda não roda (SKIP não é aprovação)');
        }

        $cmd = sprintf('%s %s 2>&1; echo "EXIT:$?"', escapeshellarg($python),
            escapeshellarg(self::ROOT . '/tools/verify-evidence-refs.py'));
        $out = trim((string)shell_exec($cmd));

        self::assertStringContainsString('contagem de decisao reescrita em artefato de estado: 0', $out,
            'artefato de estado voltando a transcrever contagem:\n' . $out);
        self::assertStringContainsString('lido agora', $out,
            'a linha de contagem do guarda tem de ser derivada da matriz, não impressa de memória');
        self::assertStringEndsWith('EXIT:0', $out, 'o guarda tem de sair 0 no estado atual');
        self::assertMatchesRegularExpression(
            '/decision-matrix\.json lido agora: \d+ DECIDED \/ \d+ PENDING_DECISION \/ \d+ contratos congelados/',
            $out, 'o guarda tem de imprimir os números que ele acabou de ler, para o log do CI ser confrontável');
    }

    /**
     * E o guarda reprova quando a contagem transcrita volta — provado contra
     * uma árvore candidata, não contra uma reimplementação em PHP.
     */
    public function testTheGuardRejectsARestatedCount(): void {
        $python = self::python();
        if ($python === null) {
            $this->markTestSkipped('nenhum interpretador Python real — o guarda não roda');
        }

        $candidata = sys_get_temp_dir() . '/estado-' . bin2hex(random_bytes(6));
        if (!mkdir($candidata . '/tools', 0777, true)) {
            $this->markTestSkipped('sem /tmp gravável para a árvore candidata');
        }

        foreach (glob(self::ROOT . '/tools/*.{json,py}', GLOB_BRACE) ?: [] as $arquivo) {
            copy($arquivo, $candidata . '/tools/' . basename($arquivo));
        }

        // O dossiê de evidência aponta para documentos do repositório: na árvore
        // candidata eles entram por link, para a falha ser a contagem, não o caminho.
        foreach (['docs', 'agents-ia'] as $dir) {
            @symlink(self::ROOT . '/' . $dir, $candidata . '/' . $dir);
        }

        $auditoria = $candidata . '/tools/contract-drift-audit.json';
        $dados = json_decode((string)file_get_contents($auditoria), true);
        $dados['policy'] = 'Nenhuma decisao tomada. decision-matrix.json segue com 10 PENDING_DECISION; 11 contratos congelados.';
        file_put_contents($auditoria, json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $comando = sprintf('%s %s %s 2>&1; echo "EXIT:$?"', escapeshellarg($python),
            escapeshellarg(self::ROOT . '/tools/verify-evidence-refs.py'), escapeshellarg($candidata));
        $out = trim((string)shell_exec($comando));

        self::assertStringContainsString('tools/contract-drift-audit.json: "10 PENDING_DECISION"', $out,
            "contagem inventada tem de ser apontada por arquivo e valor esperado:\n" . $out);
        self::assertStringContainsString('a matriz diz 0 PENDING_DECISION', $out,
            'a reprovação tem de dizer o que a matriz diz agora, senão a correção vira adivinhação');
        self::assertStringContainsString('"11 contratos congelados" — a matriz lista 30', $out);
        self::assertStringContainsString('RESULTADO: REPROVADO', $out);
        self::assertStringEndsWith('EXIT:1', $out, 'contagem inventada tem de sair em exit 1 — aviso que não bloqueia é o que produziu este bug');

        self::cleanup($candidata);
    }

    private static function cleanup(string $dir): void {
        foreach (glob($dir . '/{,tools/}*', GLOB_BRACE) ?: [] as $p) {
            if (is_link($p) || is_file($p)) {
                unlink($p);
            }
        }
        @rmdir($dir . '/tools');
        @rmdir($dir);
    }
}
