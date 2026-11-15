<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;

/**
 * KZ-11 (ADR 0104): a moldura do editor de tema (barra de cima, barra da
 * tela, abas dos paineis laterais, navegacao dos modulos e rodape) le os
 * textos dos arquivos de idioma, como o resto do admin. Antes estavam escritos
 * em portugues no Twig e no script.
 *
 * Aqui fica a parte do contrato que mora no PHP: as tres linguas tem as mesmas
 * chaves, todas preenchidas, e o script recebe so chaves que existem. A
 * parte do Twig e do script e conferida por `tests/check_kaz_frame_text.js`.
 */
final class KazFrameTextTest extends TestCase {
    private const CHECKER = 'tests/check_kaz_frame_text.js';
    private const CONTROLLER = 'dev/admin/controller/design/theme.php';
    private const LANGUAGES = ['pt-br', 'en-us', 'es-eu'];

    private function repo(string $path): string {
        $source = file_get_contents(DIR_REAMUR . $path);
        self::assertIsString($source, $path);

        return $source;
    }

    /**
     * Frases `text_kaz_*` de um idioma, sem o prefixo.
     *
     * @return array<string,string>
     */
    private function frame(string $code): array {
        $_ = [];
        require DIR_REAMUR . 'dev/admin/language/' . $code . '/design/theme.php';
        $out = [];
        foreach ($_ as $key => $text) {
            if (!str_starts_with((string)$key, 'text_kaz_')) continue;
            self::assertIsString($text, $code . ' ' . $key);
            $out[substr((string)$key, 9)] = $text;
        }

        return $out;
    }

    /**
     * Chaves de uma constante do controller.
     *
     * @return list<string>
     */
    private function keys(string $constant): array {
        self::assertSame(1, preg_match('/private const ' . $constant . ' = \[(.*?)\];/s', $this->repo(self::CONTROLLER), $m), $constant);
        preg_match_all("/'([a-z0-9_]+)'/", $m[1], $found);

        return $found[1];
    }

    public function testTheThreeLanguagesHaveTheSameFrameKeysAllFilled(): void {
        $pt = $this->frame('pt-br');
        self::assertCount(109, $pt, 'a quantidade so muda junto com o Twig e os tres idiomas');
        foreach (self::LANGUAGES as $code) {
            $texts = $this->frame($code);
            self::assertSame(array_keys($pt), array_keys($texts), $code . ': mesmas chaves, na mesma ordem');
            foreach ($texts as $key => $text) {
                self::assertSame(trim($text), $text, $code . ' ' . $key . ': sem espaco nas pontas');
                self::assertNotSame('', $text, $code . ' ' . $key);
                self::assertStringNotContainsString('text_kaz_', $text, $code . ' ' . $key);
                self::assertSame(substr_count($pt[$key], '%s'), substr_count($text, '%s'), $code . ' ' . $key . ': mesma quantidade de %s');
                self::assertSame(0, preg_match('/[<>"]/', $text), $code . ' ' . $key . ': frase simples, sem marcacao');
            }
        }
    }

    public function testTheOtherLanguagesAreReallyTranslated(): void {
        $pt = $this->frame('pt-br');
        // Palavras que so existem em portugues: nao podem sobrar nas outras linguas.
        $portuguese = '/\b(não|salvo|tela|camadas|ativos|histórico|protótipo|herança|condições|prévia|seções|mídia|agendamento|recolher|painel|botão|cartão|mão|retângulo|excluir|desfazer|refazer|largura|amplo)\b/iu';
        foreach (['en-us', 'es-eu'] as $code) {
            $texts = $this->frame($code);
            $same = 0;
            foreach ($texts as $key => $text) {
                if ($text === $pt[$key]) $same++;
                self::assertSame(0, preg_match($portuguese, $text), $code . ' ' . $key . ': "' . $text . '" ficou em portugues');
                if ($code === 'en-us') self::assertSame(0, preg_match('/[^\x20-\x7E·]/u', $text), $code . ' ' . $key . ': "' . $text . '" tem letra acentuada');
            }
            // Algumas palavras sao iguais de verdade (Tokens, Normal, Mover...), mas nunca a maioria.
            self::assertLessThan($code === 'en-us' ? 12 : 40, $same, $code . ': frases iguais as do pt-br');
        }
        self::assertSame('Saved', $this->frame('en-us')['saved']);
        self::assertSame('Guardado', $this->frame('es-eu')['saved']);
        self::assertSame('Salvo', $pt['saved']);
    }

    public function testTheScriptReceivesOnlyKeysThatExist(): void {
        $keys = array_keys($this->frame('pt-br'));
        $script = $this->keys('KAZ_FRAME_SCRIPT');
        self::assertCount(24, $script);
        self::assertSame(array_values(array_unique($script)), $script, 'sem chave repetida');
        self::assertSame([], array_values(array_diff($script, $keys)), 'o script so recebe chaves que existem nos idiomas');
        self::assertSame(array_values(array_intersect($keys, $script)), $script, 'KAZ_FRAME_SCRIPT na mesma ordem dos arquivos de idioma');

        $source = $this->repo(self::CONTROLLER);
        // O Twig recebe todas as chaves do idioma por esta linha de edit(); nao ha lista a parte.
        self::assertStringContainsString('$data=array_replace($this->language->all(),$data);', $source);
        self::assertStringContainsString("foreach(self::KAZ_FRAME_SCRIPT as \$kazKey) \$kazLabels[\$kazKey]=\$this->language->get('text_kaz_'.\$kazKey);", $source);
        // O script mudou: o endereco dele muda junto, para o navegador nao usar a copia antiga.
        self::assertMatchesRegularExpression("/'-kaz-theme-editor-kz(1[1-9]|[2-9]\\d)'/", $source); // KZ-11 em diante
    }

    public function testTheScreenAndTheScriptAskOnlyForTheseKeys(): void {
        $node = trim((string)shell_exec('command -v node 2>/dev/null'));
        if ($node === '') self::markTestSkipped('node ausente: rode `node ' . self::CHECKER . '` à mão.');

        $output = [];
        exec(escapeshellarg($node) . ' ' . escapeshellarg(DIR_REAMUR . self::CHECKER) . ' 2>&1', $output, $status);
        $last = (string)end($output);
        self::assertSame(0, $status, implode("\n", array_slice($output, 0, 12)));
        self::assertMatchesRegularExpression('/^Frame text checker: \d{3,} PASS \(109 chaves, 24 no script, 89 no Twig\)$/', $last);
    }
}
