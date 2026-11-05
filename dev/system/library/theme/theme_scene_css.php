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

    /** Frente AF — regras ESTATICAS da cena para ambientes de previa que nao
     *  carregam o theme.css do Catalogo (compare view e preview de template).
     *  Nenhum dado do usuario: allowlist literal, sem url(), @import, expressao
     *  ou tag. Consome as mesmas variaveis --rms-scene-* do site publicado. */
    public static function previewCss(): string {
        /* Espelha dev/catalog/view/css/theme.css (.rms-scene*) — inclusive o
         * container-type e a @container, sem os quais o texto fica CORTADO em
         * paineis estreitos (o font-size em px nao escala com o stage).
         * O limite da @container sobe de 620px para 1200px APENAS na previa:
         * o catalogo chega a 1200px, mas cada painel de comparacao tem ~666px
         * em desktop e o texto em 32px nao caberia na altura da camada. */
        return '.rms-scene{width:100%;margin:0;padding:24px 0 8px;background:var(--bg,#fff)}'
             . '.rms-scene__stage{position:relative;width:100%;max-width:var(--rms-content-width,1200px);'
             . 'margin:0 auto;overflow:hidden;border-radius:var(--rms-radius-lg,20px);'
             . 'background:var(--theme-surface,#ffffff);'
             . 'background-image:radial-gradient(circle,rgba(127,127,127,.18) 1px,transparent 1px);'
             . 'background-size:16px 16px;container-type:inline-size}'
             . '.rms-scene__layer{position:absolute;box-sizing:border-box;overflow:hidden;'
             . 'display:flex;align-items:center;justify-content:center;padding:.35em .6em;'
             . 'line-height:1.25;text-align:center;color:var(--rms-color-text,#212529);'
             . 'word-break:break-word;border:1px solid rgba(127,127,127,.22)}'
             . '.rms-scene__layer--rect,.rms-scene__layer--card{color:transparent}'
             . '.rms-scene__layer--text{background:transparent!important;border-color:transparent}'
             . '.rms-scene__layer--button{color:var(--rms-scene-btn-ink,#ffffff);font-weight:600;'
             . 'border:0;box-shadow:var(--rms-shadow,none)}'
             . '.rms-scene__layer--section{align-items:flex-start;justify-content:flex-start}'
             . '.rms-scene__role--primary{background-color:var(--theme-primary,#6d28d9)!important}'
             . '.rms-scene__role--surface{background-color:var(--theme-surface,#ffffff)!important}'
             . '@container(max-width:1200px){.rms-scene__layer{font-size:max(10px,2.4cqw)!important}}'
             . '@media(max-width:720px){.rms-scene{padding:12px 0 4px}'
             . '.rms-scene__stage{border-radius:0}'
             . '.rms-scene__layer{font-size:max(10px,2.4vw)!important}}'
             . '@media(forced-colors:active){.rms-scene__layer{border:2px solid CanvasText;'
             . 'forced-color-adjust:none}}';
    }

    private function text(string $s): string {
        $s = (string)(preg_replace('/[\x00-\x1F\x7F]/',' ',$s) ?? '');
        return function_exists('mb_substr') ? mb_substr($s,0,self::MAX_TEXT) : substr($s,0,self::MAX_TEXT);
    }
    private function empty(): array {
        return ['enabled'=>false,'css'=>'','layers'=>[],'canvas'=>['w'=>960,'h'=>540]];
    }
}
