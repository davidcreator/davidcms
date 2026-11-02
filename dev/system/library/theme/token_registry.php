<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @license   https://opensource.org/licenses/GPL-3.0
 *
 * Registro de design tokens de tema (Frente B).
 *
 * Um tema publicado pode personalizar tipografia, escala, espacamento, forma,
 * densidade, largura e cores (claras e escuras) SEM escrever CSS livre.
 * Todos os valores sao allowlistados/bounded; o CSS gerado contem apenas
 * declaracoes --rms-*: valor, sem seletores, sem url(), sem @import, sem
 * expressoes e sem aspas. Contraste WCAG e medido e exigido no save.
 *
 * Nenhum eval, IO, arquivo, rede, cookie ou dado sensivel.
 */
namespace Reamur\System\Library\Theme;

final class TokenRegistry {
    /** Valores completos de fabrica; todo tema sem tokens cai aqui. */
    private const DEFAULTS = [
        'font_family'    => 'system',
        'font_size'      => '16',
        'font_scale'     => '1.250',
        'line_height'    => '1.55',
        'font_weight'    => '400',
        'heading_weight' => '700',
        'space_unit'     => '8',
        'density'        => 'comfortable',
        'content_width'  => '1200',
        'radius'         => '12',
        'radius_lg'      => '20',
        'border_width'   => '1',
        'shadow'         => '1',
        'color_text'     => '#212529',
        'color_muted'    => '#5c6773',
        'color_surface'  => '#ffffff',
        'color_primary'  => '#0a58ca',
        'focus_ring'     => '#0a58ca',
        'dark_text'      => '#dee2e6',
        'dark_muted'     => '#9aa4ae',
        'dark_surface'   => '#212529',
        'dark_primary'   => '#6ea8fe',
    ];

    /** Metadados de cada token: tipo, limites, grupo. */
    private const FIELDS = [
        'font_family'    => ['type'=>'enum','options'=>['system','serif','mono','rounded'],'group'=>'typography'],
        'font_size'      => ['type'=>'int','min'=>14,'max'=>20,'unit'=>'px','group'=>'typography'],
        'font_scale'     => ['type'=>'float','min'=>1.0,'max'=>1.5,'decimals'=>3,'group'=>'typography'],
        'line_height'    => ['type'=>'float','min'=>1.2,'max'=>2.0,'decimals'=>2,'group'=>'typography'],
        'font_weight'    => ['type'=>'int','min'=>300,'max'=>800,'group'=>'typography'],
        'heading_weight' => ['type'=>'int','min'=>400,'max'=>900,'group'=>'typography'],
        'space_unit'     => ['type'=>'int','min'=>2,'max'=>16,'unit'=>'px','group'=>'shape'],
        'density'        => ['type'=>'enum','options'=>['compact','comfortable','spacious'],'group'=>'shape'],
        'content_width'  => ['type'=>'int','min'=>800,'max'=>1600,'unit'=>'px','group'=>'shape'],
        'radius'         => ['type'=>'int','min'=>0,'max'=>32,'unit'=>'px','group'=>'shape'],
        'radius_lg'      => ['type'=>'int','min'=>0,'max'=>64,'unit'=>'px','group'=>'shape'],
        'border_width'   => ['type'=>'int','min'=>0,'max'=>4,'unit'=>'px','group'=>'shape'],
        'shadow'         => ['type'=>'int','min'=>0,'max'=>4,'group'=>'shape'],
        'color_text'     => ['type'=>'color','group'=>'light'],
        'color_muted'    => ['type'=>'color','group'=>'light'],
        'color_surface'  => ['type'=>'color','group'=>'light'],
        'color_primary'  => ['type'=>'color','group'=>'light'],
        'focus_ring'     => ['type'=>'color','group'=>'light'],
        'dark_text'      => ['type'=>'color','group'=>'dark'],
        'dark_muted'     => ['type'=>'color','group'=>'dark'],
        'dark_surface'   => ['type'=>'color','group'=>'dark'],
        'dark_primary'   => ['type'=>'color','group'=>'dark'],
    ];

    private const GROUPS = ['typography', 'shape', 'light', 'dark'];

    /** Stacks fixas, self-hosted/system: nenhuma fonte remota e carregada. */
    private const FONTS = [
        'system'  => 'system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif',
        'serif'   => 'Georgia, Cambria, Times New Roman, serif',
        'mono'    => 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
        'rounded' => 'Nunito, Quicksand, system-ui, sans-serif',
    ];

    private const SHADOWS = [
        0 => 'none',
        1 => '0 1px 2px rgba(16, 24, 40, .08)',
        2 => '0 4px 12px rgba(16, 24, 40, .12)',
        3 => '0 10px 28px rgba(16, 24, 40, .18)',
        4 => '0 18px 48px rgba(16, 24, 40, .24)',
    ];

    private const DENSITY = ['compact'=>'0.75', 'comfortable'=>'1.00', 'spacious'=>'1.35'];

    /** Pares WCAG exigidos (AA para texto normal = 4.5:1). */
    private const CONTRAST_PAIRS = [
        ['a'=>'color_text','b'=>'color_surface','code'=>'contrast_light_text'],
        ['a'=>'color_muted','b'=>'color_surface','code'=>'contrast_light_muted'],
        ['a'=>'color_primary','b'=>'color_surface','code'=>'contrast_light_primary'],
        ['a'=>'#ffffff','b'=>'color_primary','code'=>'contrast_button'],
        ['a'=>'dark_text','b'=>'dark_surface','code'=>'contrast_dark_text'],
        ['a'=>'dark_muted','b'=>'dark_surface','code'=>'contrast_dark_muted'],
        ['a'=>'dark_primary','b'=>'dark_surface','code'=>'contrast_dark_primary'],
    ];

    public const AA_MIN = 4.5;

    /** @return array<string,mixed> */
    public function defaults(): array { return self::DEFAULTS; }
    /** @return array<string,array<string,mixed>> */
    public function fields(): array { return self::FIELDS; }
    /** @return array<int,string> */
    public function groups(): array { return self::GROUPS; }

    public function spec(): array {
        return [
            'fields'=>self::FIELDS, 'groups'=>self::GROUPS, 'defaults'=>self::DEFAULTS,
            'aa_min'=>self::AA_MIN,
            'fonts'=>array_keys(self::FONTS), 'densities'=>array_keys(self::DENSITY),
            'shadows'=>[0,1,2,3,4],
        ];
    }

    /** Luminancia relativa WCAG. */
    private function luminance(string $hex): float {
        $h=ltrim($hex,'#');
        $c=[];
        for($i=0;$i<3;$i++) {
            $v=intval(substr($h,$i*2,2),16)/255;
            $c[]=$v<=0.04045 ? $v/12.92 : pow(($v+0.055)/1.055,2.4);
        }
        return 0.2126*$c[0]+0.7152*$c[1]+0.0722*$c[2];
    }
    public function contrast(string $a, string $b): float {
        $x=$this->luminance($a); $y=$this->luminance($b);
        $hi=max($x,$y); $lo=min($x,$y);
        return ($hi+0.05)/($lo+0.05);
    }

    /** @return array<int,array{code:string,a:string,b:string,ratio:float,min:float,ok:bool}> */
    public function contrastPairs(array $tokens): array {
        $out=[];
        foreach(self::CONTRAST_PAIRS as $pair) {
            $resolve=static function(string $v) use ($tokens): string {
                return str_starts_with($v,'#') ? $v : (string)($tokens[$v] ?? self::DEFAULTS[$v] ?? '#000000');
            };
            $a=$resolve($pair['a']); $b=$resolve($pair['b']);
            $ratio=$this->contrast($a,$b);
            $out[]=['code'=>$pair['code'],'a'=>$a,'b'=>$b,'ratio'=>round($ratio,2),'min'=>self::AA_MIN,'ok'=>$ratio>=self::AA_MIN];
        }
        return $out;
    }

    /** Valida um valor isolado. @return array{0:bool,1:string} */
    private function checkValue(string $key, $value): array {
        $meta=self::FIELDS[$key];
        switch($meta['type']) {
            case 'enum':
                if (!is_string($value) || !in_array($value,$meta['options'],true)) return [false,'enum_invalid'];
                return [true,$value];
            case 'int':
                $n=filter_var($value,FILTER_VALIDATE_INT);
                if ($n===false || $n<$meta['min'] || $n>$meta['max']) return [false,'range_invalid'];
                return [true,(string)$n];
            case 'float':
                if (!is_numeric($value)) return [false,'number_invalid'];
                $f=(float)$value;
                if ($f<$meta['min'] || $f>$meta['max']) return [false,'range_invalid'];
                return [true,number_format($f,$meta['decimals'],'.','')];
            case 'color':
                if (!is_string($value) || preg_match('/^#[0-9a-fA-F]{6}$/D',$value)!==1) return [false,'color_invalid'];
                return [true,$value];
        }
        return [false,'type_invalid'];
    }

    /**
     * Normaliza tokens.
     * @param bool $partial true = patch de condicao (so chaves presentes, sem defaults)
     * @return array<string,string>|null null quando invalido (fail-closed)
     */
    public function normalize(array $raw, bool $partial=false): ?array {
        [$out,$errors]=$this->build($raw,$partial);
        if ($errors!==[]) return null;
        foreach($this->contrastPairs($out) as $p) if (!$p['ok']) return null;
        return $out;
    }

    /** @return array<int,array{code:string,path:string}> */
    public function validate(array $raw, bool $partial=false): array {
        [,$errors]=$this->build($raw,$partial);
        $candidate=$partial ? array_replace(self::DEFAULTS,$raw) : array_replace(self::DEFAULTS,$raw);
        foreach($this->contrastPairs($candidate) as $p) {
            if (!$p['ok']) $errors[]=['code'=>$p['code'],'path'=>$p['a'].'/'.$p['b']];
        }
        return $errors;
    }

    /** @return array{0:array<string,string>,1:array<int,array{code:string,path:string}>} */
    private function build(array $raw, bool $partial): array {
        $out=$partial?[]:self::DEFAULTS;
        $errors=[];
        foreach($raw as $key=>$value) {
            if (!is_string($key) || !isset(self::FIELDS[$key])) { $errors[]=['code'=>'token_key_invalid','path'=>(string)$key]; continue; }
            [$ok,$normalized]=$this->checkValue($key,$value);
            if (!$ok) { $errors[]=['code'=>$normalized,'path'=>$key]; continue; }
            $out[$key]=$normalized;
        }
        return [$out,$errors];
    }

    /** Nunca deixa um valor livre chegar ao CSS. */
    private function safeCssValue(string $v): string {
        if (preg_match('/[;{}<>"\x00-\x1F]|url\s*\(|@import|expression\s*\(/i',$v)) return 'initial';
        return $v;
    }

    /**
     * Declaracoes --rms-* para o atributo style do <body>. Sem seletores:
     * o bloco escuro fica em theme.css e apenas troca as variaveis.
     */
    public function css(array $tokens): string {
        $t=array_replace(self::DEFAULTS,$tokens);
        $map=[
            '--rms-font-family'   => self::FONTS[$t['font_family']] ?? self::FONTS['system'],
            '--rms-font-size'     => ((int)$t['font_size']).'px',
            '--rms-font-scale'    => (string)$t['font_scale'],
            '--rms-line-height'   => (string)$t['line_height'],
            '--rms-font-weight'   => (string)$t['font_weight'],
            '--rms-heading-weight'=> (string)$t['heading_weight'],
            '--rms-space'         => ((int)$t['space_unit']).'px',
            '--rms-space-mult'    => self::DENSITY[$t['density']] ?? self::DENSITY['comfortable'],
            '--rms-content-width' => ((int)$t['content_width']).'px',
            '--rms-radius'        => ((int)$t['radius']).'px',
            '--rms-radius-lg'     => ((int)$t['radius_lg']).'px',
            '--rms-border-width'  => ((int)$t['border_width']).'px',
            '--rms-shadow'        => self::SHADOWS[(int)$t['shadow']] ?? self::SHADOWS[1],
            '--rms-light-text'    => (string)$t['color_text'],
            '--rms-light-muted'   => (string)$t['color_muted'],
            '--rms-light-surface' => (string)$t['color_surface'],
            '--rms-light-primary' => (string)$t['color_primary'],
            '--rms-focus-ring'    => (string)$t['focus_ring'],
            '--rms-dark-text'     => (string)$t['dark_text'],
            '--rms-dark-muted'    => (string)$t['dark_muted'],
            '--rms-dark-surface'  => (string)$t['dark_surface'],
            '--rms-dark-primary'  => (string)$t['dark_primary'],
        ];
        $parts=[];
        foreach($map as $k=>$v) $parts[]=$k.': '.$this->safeCssValue((string)$v);
        return implode('; ',$parts);
    }

    /** Campos prontos para o formulario, com label traduzido. */
    public function formFields(callable $label, array $tokens): array {
        $out=[];
        foreach(self::FIELDS as $key=>$meta) {
            $out[]=[
                'key'=>$key,'label'=>$label($key),'group'=>$meta['group'],'type'=>$meta['type'],
                'value'=>(string)($tokens[$key] ?? self::DEFAULTS[$key]),
                'options'=>$meta['options'] ?? [],
                'min'=>$meta['min'] ?? null,'max'=>$meta['max'] ?? null,
                'step'=>$meta['type']==='float' ? '0.01' : '1',
                'unit'=>$meta['unit'] ?? '',
            ];
        }
        return $out;
    }
}
