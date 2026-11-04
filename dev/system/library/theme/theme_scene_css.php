<?php declare(strict_types=1);
/**
 * Frente AB — compila `kaztheme-scene/v1` em CSS allowlistado + camadas para Twig.
 *
 * O editor desenha a cena; ESTA classe e a ponte que faz o desenho virar tema
 * publicado. Nao ha HTML, script, `|raw`, upload, rede ou execucao de codigo:
 *  - `css` contem APENAS declaracoes `--rms-scene-*` (inteiros, hex e px);
 *    vai para o atributo style do body, ja escapado por |e('html_attr').
 *  - `layers` e um array de escalares prontos para o Twig, que itera com
 *    autoescape (o texto do usuario NUNCA e marcado como seguro).
 * Validacao reutiliza ThemePackage::normalizeScene(): fail-closed.
 */
namespace Reamur\System\Library\Theme;

final class ThemeSceneCss {
    private const KINDS  = ['rect','text','button','card','section'];
    private const ROLES  = ['none','primary','surface','section','component'];
    private const MAX_TEXT   = 80;
    private const MAX_LAYERS = 48;

    /** @return array{enabled:bool,css:string,layers:array<int,array<string,scalar>>,canvas:array{w:int,h:int}} */
    public function compile(mixed $raw): array {
        $scene = ThemePackage::normalizeScene($raw);
        if ($scene === null) return $this->empty();
        $w = (int)$scene['canvas']['w']; $h = (int)$scene['canvas']['h'];
        if ($w < 1 || $h < 1) return $this->empty();
        $css = '--rms-scene-w:'.$w.';--rms-scene-h:'.$h.';--rms-scene-grid:8;';
        $layers = []; $n = 0;
        foreach ($scene['layers'] as $l) {
            if (empty($l['visible'])) continue;
            if (++$n > self::MAX_LAYERS) { $n--; break; }
            $kind   = in_array((string)$l['kind'], self::KINDS, true) ? (string)$l['kind'] : 'rect';
            $role   = in_array((string)$l['role'], self::ROLES, true) ? (string)$l['role'] : 'none';
            $fill   = preg_match('/^#[0-9a-fA-F]{6}$/D', (string)$l['fill']) === 1 ? (string)$l['fill'] : '#ffffff';
            $radius = max(0, min(64, (int)$l['radius']));
            $font   = max(8, min(72, (int)$l['fontSize']));
            $text   = $this->text((string)$l['text']);
            $id     = substr((string)preg_replace('/[^A-Za-z0-9_-]/','',(string)$l['id']),0,40);
            $pct = static function (float $v, float $base): string {
                return number_format(max(0.0,min(100.0,$v/$base*100.0)),3,'.','');
            };
            $css .= '--rms-scene-'.$n.'-fill:'.$fill
                  . ';--rms-scene-'.$n.'-radius:'.$radius.'px'
                  . ';--rms-scene-'.$n.'-font:'.$font.'px;';
            $layers[] = [
                'n'=>$n,'id'=>$id,'kind'=>$kind,'role'=>$role,
                'left'=>$pct((float)(int)$l['x'],(float)$w),
                'top'=>$pct((float)(int)$l['y'],(float)$h),
                'width'=>$pct((float)(int)$l['w'],(float)$w),
                'height'=>$pct((float)(int)$l['h'],(float)$h),
                'fill'=>$fill,'radius'=>$radius,'font'=>$font,'text'=>$text,
            ];
        }
        $css .= '--rms-scene-count:'.count($layers).';';
        /* Whitelist final: sem { } < > ; url( expression \ ou aspas. */
        if (preg_match('/[^A-Za-z0-9#:.;\-]/',$css) === 1) return $this->empty();
        return ['enabled'=>$layers !== [], 'css'=>$css, 'layers'=>$layers,
                'canvas'=>['w'=>$w,'h'=>$h]];
    }

    private function text(string $s): string {
        $s = (string)(preg_replace('/[\x00-\x1F\x7F]/',' ',$s) ?? '');
        return function_exists('mb_substr') ? mb_substr($s,0,self::MAX_TEXT) : substr($s,0,self::MAX_TEXT);
    }
    private function empty(): array {
        return ['enabled'=>false,'css'=>'','layers'=>[],'canvas'=>['w'=>960,'h'=>540]];
    }
}
