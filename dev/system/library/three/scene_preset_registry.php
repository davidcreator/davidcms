<?php declare(strict_types=1);
/**
 * F13-D — presets e miniaturas das cenas Three.js NATIVAS do tema.
 *
 * Nao cria cenas, nao aceita codigo, GLB, JavaScript de cena, upload, regex
 * de usuario, IO ou escrita. Apenas combinacoes allowlistadas dos mesmos
 * parametros que ConditionEngine::normalizeThree() ja aceita (scene/slot/
 * count/color/speed/enabled). A miniatura e um SVG estatico e deterministico,
 * servido como imagem (data URI ou endpoint), nunca como HTML inline.
 *
 * Separado de proposito do BDREditor/threejs-thumb (ADR 0072, F9-103): o
 * recurso de thumbs declarativas de Blocos e outro subsistema e nao e tocado.
 */
namespace Reamur\System\Library\Three;

final class ScenePresetRegistry {
    private const SCENES = ['', 'hero-particles', 'portfolio-3d', 'tools-3d', 'not-found'];
    private const SLOTS  = ['hero', 'home_top', 'footer_top'];
    private const LIMITS = ['count'=>[50,800], 'speed'=>[0.01,0.20]];

    /** @var array<int,array<string,mixed>> */
    private const PRESETS = [
        ['id'=>'aurora',      'scene'=>'hero-particles', 'slot'=>'hero',       'count'=>400, 'color'=>'#8a4fff', 'speed'=>0.05, 'enabled'=>true,  'label'=>'scene_preset_aurora',      'help'=>'scene_preset_aurora_help'],
        ['id'=>'nebula',      'scene'=>'hero-particles', 'slot'=>'hero',       'count'=>800, 'color'=>'#6ea8fe', 'speed'=>0.12, 'enabled'=>true,  'label'=>'scene_preset_nebula',      'help'=>'scene_preset_nebula_help'],
        ['id'=>'geometric',   'scene'=>'portfolio-3d',  'slot'=>'home_top',   'count'=>500, 'color'=>'#22d3ee', 'speed'=>0.06, 'enabled'=>true,  'label'=>'scene_preset_geometric',   'help'=>'scene_preset_geometric_help'],
        ['id'=>'workshop',    'scene'=>'tools-3d',      'slot'=>'home_top',   'count'=>350, 'color'=>'#f59e0b', 'speed'=>0.08, 'enabled'=>true,  'label'=>'scene_preset_workshop',    'help'=>'scene_preset_workshop_help'],
        ['id'=>'lost',        'scene'=>'not-found',     'slot'=>'footer_top', 'count'=>200, 'color'=>'#94a3b8', 'speed'=>0.04, 'enabled'=>true,  'label'=>'scene_preset_lost',        'help'=>'scene_preset_lost_help'],
        ['id'=>'off',         'scene'=>'',              'slot'=>'hero',       'count'=>400, 'color'=>'#8a4fff', 'speed'=>0.05, 'enabled'=>false, 'label'=>'scene_preset_off',         'help'=>'scene_preset_off_help'],
    ];

    /** @return array<int,array<string,mixed>> */
    public function all(): array { return self::PRESETS; }
    /** @return array<int,string> */
    public function ids(): array { return array_column(self::PRESETS, 'id'); }
    public function scenes(): array { return self::SCENES; }
    public function slots(): array { return self::SLOTS; }

    /** @return array<string,mixed>|null */
    public function find(string $id): ?array {
        foreach (self::PRESETS as $p) if ($p['id'] === $id) return $p;
        return null;
    }

    /** Campos de POST aceitos pelo formulario (nenhuma chave nova no config). */
    public function apply(string $id): ?array {
        $p=$this->find($id); if ($p===null) return null;
        return [
            'three_enabled'=>$p['enabled'] ? '1' : '0',
            'three_scene'=>$p['scene'], 'three_slot'=>$p['slot'],
            'three_count'=>(string)$p['count'], 'three_color'=>$p['color'],
            'three_speed'=>number_format($p['speed'],2,'.',''),
        ];
    }

    /** @param array<string,mixed> $raw @return list<string> Codigos estaveis. */
    public function validate(array $raw): array {
        $e=[];
        $scene=(string)($raw['scene']??'');
        if (!in_array($scene,self::SCENES,true)) $e[]='scene_invalid';
        $slot=(string)($raw['slot']??'hero');
        if (!in_array($slot,self::SLOTS,true)) $e[]='slot_invalid';
        $count=filter_var($raw['count']??null,FILTER_VALIDATE_INT);
        if ($count===false || $count<self::LIMITS['count'][0] || $count>self::LIMITS['count'][1]) $e[]='count_out_of_range';
        $color=(string)($raw['color']??'');
        if (preg_match('/^#[0-9a-fA-F]{6}$/D',$color)!==1) $e[]='color_invalid';
        $speed=$raw['speed']??null;
        if (!is_numeric($speed) || (float)$speed<self::LIMITS['speed'][0] || (float)$speed>self::LIMITS['speed'][1]) $e[]='speed_out_of_range';
        $enabled=$raw['enabled']??'0';
        if (!in_array((string)$enabled,['0','1','true','false','on',''],true)) $e[]='enabled_invalid';
        return $e;
    }

    /** Fail-closed: qualquer entrada fora da allowlist volta ao default seguro. */
    public function normalize(array $raw): array {
        $scene=(string)($raw['scene']??'');
        if (!in_array($scene,self::SCENES,true)) $scene='';
        $slot=(string)($raw['slot']??'hero');
        if (!in_array($slot,self::SLOTS,true)) $slot='hero';
        $count=filter_var($raw['count']??null,FILTER_VALIDATE_INT);
        if ($count===false || $count<50 || $count>800) $count=400;
        $color=(string)($raw['color']??'');
        if (preg_match('/^#[0-9a-fA-F]{6}$/D',$color)!==1) $color='#8a4fff';
        $speed=$raw['speed']??null;
        if (!is_numeric($speed) || (float)$speed<0.01 || (float)$speed>0.20) $speed=0.05;
        $enabled=in_array((string)($raw['enabled']??'0'),['1','true','on'],true);
        return ['scene'=>$scene,'slot'=>$slot,'count'=>(int)$count,'color'=>$color,
                'speed'=>number_format((float)$speed,2,'.',''),'enabled'=>$enabled];
    }

    /** Qual preset corresponde exatamente a estes valores ('' = personalizado). */
    public function matchingPreset(array $cfg): string {
        $n=$this->normalize($cfg);
        foreach (self::PRESETS as $p) {
            if ($n['scene']===$p['scene'] && $n['slot']===$p['slot'] && $n['count']===$p['count']
                && strtolower($n['color'])===strtolower($p['color'])
                && abs((float)$n['speed']-(float)$p['speed'])<0.0001
                && $n['enabled']===$p['enabled']) return (string)$p['id'];
        }
        return '';
    }

    public function dataUri(array $cfg): string {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($cfg));
    }

    /**
     * Miniatura SVG ESTATICA. Sem <script>, sem on*, sem url(, sem <style>,
     * sem foreignObject, sem href/xlink e sem qualquer texto livre do usuario:
     * so cores hex validadas e numeros formatados. Deterministico (sem rand).
     */
    public function svg(array $cfg): string {
        $c=$this->normalize($cfg);
        $color=$c['color'];
        $o='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 180" width="320" height="180" role="img" aria-label="Scene preview">'
          .'<rect width="320" height="180" rx="8" fill="#101827"/>'
          .'<rect x="1" y="1" width="318" height="178" rx="7" fill="none" stroke="#334155" stroke-width="1"/>';
        if (!$c['enabled'] || $c['scene']==='') {
            $o.='<circle cx="160" cy="84" r="30" fill="none" stroke="#64748b" stroke-width="2" stroke-dasharray="5 5"/>'
               .'<line x1="139" y1="105" x2="181" y2="63" stroke="#64748b" stroke-width="2"/>'
               .'<text x="160" y="145" text-anchor="middle" font-family="system-ui,sans-serif" font-size="11" fill="#94a3b8">off</text>';
            return $o.'</svg>';
        }
        if ($c['scene']==='hero-particles') {
            $n=max(12,min(70,(int)round($c['count']/12)));
            for ($i=0;$i<$n;$i++) {
                $x=22+(($i*73)%276); $y=20+(($i*47)%140);
                $r=sprintf('%.1f',1.4+(($i*7)%5)*0.7);
                $op=sprintf('%.2f',0.30+(($i*3)%6)*0.11);
                $o.='<circle cx="'.$x.'" cy="'.$y.'" r="'.$r.'" fill="'.$color.'" opacity="'.$op.'"/>';
            }
            $o.='<circle cx="160" cy="90" r="54" fill="'.$color.'" opacity="0.07"/>';
        } elseif ($c['scene']==='portfolio-3d') {
            for ($i=0;$i<4;$i++) {
                $cx=64+$i*64; $cy=90+(($i%2===0)?-14:14);
                $o.='<polygon points="'.($cx).','.($cy-30).' '.($cx+34).','.($cy).' '.($cx).','.($cy+30).' '.($cx-34).','.($cy).'" fill="'.$color.'" opacity="'.sprintf('%.2f',0.20+$i*0.14).'" stroke="'.$color.'" stroke-width="1"/>';
            }
        } elseif ($c['scene']==='tools-3d') {
            foreach ([54,40,26] as $k=>$r) $o.='<circle cx="160" cy="90" r="'.$r.'" fill="none" stroke="'.$color.'" stroke-width="2" opacity="'.sprintf('%.2f',0.45+$k*0.2).'"/>';
            for ($i=0;$i<8;$i++) {
                $a=2*M_PI*$i/8;
                $x1=sprintf('%.1f',160+54*cos($a)); $y1=sprintf('%.1f',90+54*sin($a));
                $x2=sprintf('%.1f',160+68*cos($a)); $y2=sprintf('%.1f',90+68*sin($a));
                $o.='<line x1="'.$x1.'" y1="'.$y1.'" x2="'.$x2.'" y2="'.$y2.'" stroke="'.$color.'" stroke-width="3" opacity="0.75"/>';
            }
            $o.='<circle cx="160" cy="90" r="9" fill="'.$color.'"/>';
        } elseif ($c['scene']==='not-found') {
            for ($i=0;$i<18;$i++) {
                $x=18+(($i*89)%284); $y=18+(($i*53)%144);
                $o.='<circle cx="'.$x.'" cy="'.$y.'" r="2" fill="'.$color.'" opacity="0.5"/>';
            }
            $o.='<text x="160" y="108" text-anchor="middle" font-family="system-ui,sans-serif" font-size="52" font-weight="700" fill="'.$color.'">404</text>';
        }
        $o.='<text x="12" y="170" font-family="system-ui,sans-serif" font-size="10" fill="#94a3b8">'
           .htmlspecialchars($c['scene'].' · '.$c['count'].' · '.$c['speed'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</text>';
        return $o.'</svg>';
    }
}
