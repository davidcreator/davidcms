<?php declare(strict_types=1);
/**
 * F13-I — midia vetorial declarativa + modelos de pagina do tema.
 *
 * NAO e a biblioteca de midia do Blog (ADR 0049 / F8-05: filesystem, upload,
 * ACL/CSRF, fronteira Blog-only) e NAO e o BDREditor (F9-25/F9-103, pausado).
 * Aqui nao ha arquivo, upload, tabela, caminho de disco, URL externa, HTML
 * livre, custom_js, eval, call_user_func, regex do usuario, IO ou HTTP.
 *
 * Cada midia e uma CONSTANTE allowlistada que gera um SVG deterministico. O
 * data URI vai para uma variavel CSS `--rms-media-*` concatenada ao `style`
 * do `<body>` (como B/G/H): `Twig::setThemeGlobals()` aceita strings de ate
 * 256 bytes, entao os globais publicados sao APENAS mapas planos e curtos
 * (variant / tone / ratio / enabled), nunca o data URI.
 *
 * Os 6 modelos de pagina sao ATALHOS de formulario (como os presets D): nao
 * entram no config, nao escrevem no banco e apenas preenchem secoes (F),
 * componentes (G), midias (I) e um snippet Twig fixo no textarea do template.
 */
namespace Reamur\System\Library\Theme;

final class ThemeMedia {
    public const MAX_MEDIA = 12;

    /** Cores fixas: nenhum valor de cor vem do config. */
    private const TONES = ['primary'=>'#2563eb','neutral'=>'#475569','success'=>'#15803d','danger'=>'#b91c1c'];

    /** Catalogo fechado: id => grupo, variantes, tons, forma e proporcao. */
    private const CATALOG = [
        'hero-aurora'   => ['group'=>'hero',     'variants'=>['soft','bold','outline'], 'tones'=>['primary','neutral','danger'],  'shape'=>'wave',   'ratio'=>'16/9'],
        'hero-grid'     => ['group'=>'hero',     'variants'=>['soft','bold'],          'tones'=>['primary','neutral'],          'shape'=>'grid',   'ratio'=>'16/9'],
        'cover-desk'    => ['group'=>'cover',    'variants'=>['soft','bold'],          'tones'=>['primary','neutral','success'],'shape'=>'desk',   'ratio'=>'4/3'],
        'cover-code'    => ['group'=>'cover',    'variants'=>['soft','outline'],       'tones'=>['primary','neutral'],          'shape'=>'code',   'ratio'=>'4/3'],
        'portrait-a'    => ['group'=>'portrait', 'variants'=>['soft','bold'],          'tones'=>['primary','neutral'],          'shape'=>'person', 'ratio'=>'3/4'],
        'portrait-b'    => ['group'=>'portrait', 'variants'=>['soft','outline'],       'tones'=>['primary','neutral','success'],'shape'=>'person', 'ratio'=>'3/4'],
        'icon-spark'    => ['group'=>'icon',     'variants'=>['soft','bold'],          'tones'=>['primary','success'],          'shape'=>'star',   'ratio'=>'1/1'],
        'icon-shield'   => ['group'=>'icon',     'variants'=>['soft','outline'],       'tones'=>['primary','neutral'],          'shape'=>'shield', 'ratio'=>'1/1'],
        'pattern-dots'  => ['group'=>'pattern',  'variants'=>['soft','bold'],          'tones'=>['primary','neutral'],          'shape'=>'dots',   'ratio'=>'1/1'],
        'pattern-waves' => ['group'=>'pattern',  'variants'=>['soft','outline'],       'tones'=>['primary','neutral','danger'], 'shape'=>'waves',  'ratio'=>'16/9'],
        'logo-mark'     => ['group'=>'logo',     'variants'=>['soft','bold'],          'tones'=>['primary','neutral'],          'shape'=>'mark',   'ratio'=>'1/1'],
        'thumb-project' => ['group'=>'thumb',    'variants'=>['soft','bold'],          'tones'=>['primary','neutral','success'],'shape'=>'frame',  'ratio'=>'4/3'],
    ];

    /** Snippets Twig FIXOS: sem |raw, <script>, on*, javascript:, srcdoc ou formaction. */
    private const SNIPPETS = [
        'hero-aurora'   => '{% if theme_media_enabled[\'hero-aurora\']|default(0) %}<div class="rms-media rms-media--hero-aurora" style="background-image: var(--rms-media-hero-aurora)" role="img" aria-label="Hero decorativo"></div>{% endif %}',
        'hero-grid'     => '{% if theme_media_enabled[\'hero-grid\']|default(0) %}<div class="rms-media rms-media--hero-grid" style="background-image: var(--rms-media-hero-grid)" role="img" aria-label="Malha decorativa"></div>{% endif %}',
        'cover-desk'    => '{% if theme_media_enabled[\'cover-desk\']|default(0) %}<div class="rms-media rms-media--cover-desk" style="background-image: var(--rms-media-cover-desk)" role="img" aria-label="Capa de artigo"></div>{% endif %}',
        'cover-code'    => '{% if theme_media_enabled[\'cover-code\']|default(0) %}<div class="rms-media rms-media--cover-code" style="background-image: var(--rms-media-cover-code)" role="img" aria-label="Capa tecnica"></div>{% endif %}',
        'portrait-a'    => '{% if theme_media_enabled[\'portrait-a\']|default(0) %}<div class="rms-media rms-media--portrait-a" style="background-image: var(--rms-media-portrait-a)" role="img" aria-label="Retrato"></div>{% endif %}',
        'portrait-b'    => '{% if theme_media_enabled[\'portrait-b\']|default(0) %}<div class="rms-media rms-media--portrait-b" style="background-image: var(--rms-media-portrait-b)" role="img" aria-label="Retrato alternativo"></div>{% endif %}',
        'icon-spark'    => '{% if theme_media_enabled[\'icon-spark\']|default(0) %}<div class="rms-media rms-media--icon-spark" style="background-image: var(--rms-media-icon-spark)" role="img" aria-label="Icone de destaque"></div>{% endif %}',
        'icon-shield'   => '{% if theme_media_enabled[\'icon-shield\']|default(0) %}<div class="rms-media rms-media--icon-shield" style="background-image: var(--rms-media-icon-shield)" role="img" aria-label="Icone de seguranca"></div>{% endif %}',
        'pattern-dots'  => '{% if theme_media_enabled[\'pattern-dots\']|default(0) %}<div class="rms-media rms-media--pattern-dots" style="background-image: var(--rms-media-pattern-dots)" role="img" aria-label="Padrao de pontos"></div>{% endif %}',
        'pattern-waves' => '{% if theme_media_enabled[\'pattern-waves\']|default(0) %}<div class="rms-media rms-media--pattern-waves" style="background-image: var(--rms-media-pattern-waves)" role="img" aria-label="Padrao de ondas"></div>{% endif %}',
        'logo-mark'     => '{% if theme_media_enabled[\'logo-mark\']|default(0) %}<div class="rms-media rms-media--logo-mark" style="background-image: var(--rms-media-logo-mark)" role="img" aria-label="Marca"></div>{% endif %}',
        'thumb-project' => '{% if theme_media_enabled[\'thumb-project\']|default(0) %}<div class="rms-media rms-media--thumb-project" style="background-image: var(--rms-media-thumb-project)" role="img" aria-label="Miniatura de projeto"></div>{% endif %}',
    ];

    /**
     * Modelos de pagina: apenas dados allowlistados para preencher o formulario.
     * `sections` usa os IDs de PageLayout (F), `components`/`media` usam os
     * enums dos registries G/I e `template` e um snippet Twig constante.
     */
    private const MODELS = [
        'landing'   => ['thumb'=>'hero-aurora','sections'=>['hero','services','portfolio','testimonials','contact'],
            'components'=>['button'=>['enabled'=>true,'variant'=>'solid','tone'=>'primary'],'card'=>['enabled'=>true,'variant'=>'elevated','tone'=>'neutral'],'cta'=>['enabled'=>true,'variant'=>'block','tone'=>'primary']],
            'media'=>['hero-aurora'=>['enabled'=>true,'variant'=>'bold','tone'=>'primary']],
            'template'=>'{% if theme_media_enabled[\'hero-aurora\']|default(0) %}<div class="rms-media rms-media--hero-aurora" style="background-image: var(--rms-media-hero-aurora)" role="img" aria-label="Hero decorativo"></div>{% endif %}<h1>{{ heading_title|default(\'Bem-vindo\') }}</h1><p>{{ text_intro|default(\'\') }}</p>'],
        'portfolio' => ['thumb'=>'thumb-project','sections'=>['hero','portfolio','tools','clients','contact'],
            'components'=>['card'=>['enabled'=>true,'variant'=>'bordered','tone'=>'neutral'],'quote'=>['enabled'=>true,'variant'=>'plain','tone'=>'primary']],
            'media'=>['thumb-project'=>['enabled'=>true,'variant'=>'soft','tone'=>'neutral']],
            'template'=>'<h1>{{ heading_title|default(\'Portfolio\') }}</h1>{% if theme_media_enabled[\'thumb-project\']|default(0) %}<div class="rms-media rms-media--thumb-project" style="background-image: var(--rms-media-thumb-project)" role="img" aria-label="Miniatura de projeto"></div>{% endif %}'],
        'article'   => ['thumb'=>'cover-desk','sections'=>['about','editorial','news','contact'],
            'components'=>['quote'=>['enabled'=>true,'variant'=>'bordered','tone'=>'neutral'],'callout'=>['enabled'=>true,'variant'=>'info','tone'=>'primary']],
            'media'=>['cover-desk'=>['enabled'=>true,'variant'=>'soft','tone'=>'neutral']],
            'template'=>'{% if theme_media_enabled[\'cover-desk\']|default(0) %}<div class="rms-media rms-media--cover-desk" style="background-image: var(--rms-media-cover-desk)" role="img" aria-label="Capa de artigo"></div>{% endif %}<article><h1>{{ heading_title|default(\'Artigo\') }}</h1></article>'],
        'catalog'   => ['thumb'=>'cover-code','sections'=>['hero','services','tools','news'],
            'components'=>['badge'=>['enabled'=>true,'variant'=>'pill','tone'=>'primary'],'stat'=>['enabled'=>true,'variant'=>'compact','tone'=>'neutral']],
            'media'=>['cover-code'=>['enabled'=>true,'variant'=>'outline','tone'=>'primary']],
            'template'=>'<h1>{{ heading_title|default(\'Catalogo\') }}</h1>{% if theme_media_enabled[\'cover-code\']|default(0) %}<div class="rms-media rms-media--cover-code" style="background-image: var(--rms-media-cover-code)" role="img" aria-label="Capa tecnica"></div>{% endif %}'],
        'contact'   => ['thumb'=>'pattern-waves','sections'=>['hero','contact'],
            'components'=>['notice'=>['enabled'=>true,'variant'=>'box','tone'=>'primary'],'cta'=>['enabled'=>true,'variant'=>'inline','tone'=>'primary']],
            'media'=>['pattern-waves'=>['enabled'=>true,'variant'=>'soft','tone'=>'neutral']],
            'template'=>'{% if theme_media_enabled[\'pattern-waves\']|default(0) %}<div class="rms-media rms-media--pattern-waves" style="background-image: var(--rms-media-pattern-waves)" role="img" aria-label="Padrao de ondas"></div>{% endif %}<h1>{{ heading_title|default(\'Contato\') }}</h1>'],
        'blank'     => ['thumb'=>'pattern-dots','sections'=>[],'components'=>[],'media'=>[],
            'template'=>'<h1>{{ heading_title|default(\'Pagina\') }}</h1>'],
    ];

    public function ids(): array { return array_keys(self::CATALOG); }
    public function modelIds(): array { return array_keys(self::MODELS); }
    public function spec(): array { return ['max'=>self::MAX_MEDIA,'ids'=>$this->ids(),'tones'=>array_keys(self::TONES),'model_ids'=>$this->modelIds()]; }
    public function variants(string $id): ?array { return self::CATALOG[$id]['variants'] ?? null; }
    public function tones(string $id): ?array { return self::CATALOG[$id]['tones'] ?? null; }
    public function defaultVariant(string $id): string { return self::CATALOG[$id]['variants'][0] ?? 'soft'; }
    public function defaultTone(string $id): string { return self::CATALOG[$id]['tones'][0] ?? 'primary'; }
    public function toneColor(string $tone): string { return self::TONES[$tone] ?? self::TONES['neutral']; }
    public function ratio(string $id): string { return self::CATALOG[$id]['ratio'] ?? '1/1'; }
    public function snippets(): array { return self::SNIPPETS; }
    public function snippet(string $id): string { return self::SNIPPETS[$id] ?? ''; }

    /** Fail-closed: IDs/enums allowlistados, enabled booleano, no maximo 12. */
    public function normalize(mixed $raw): ?array {
        if (!is_array($raw) || count($raw) > self::MAX_MEDIA) return null;
        $out=[];
        foreach ($raw as $id=>$cfg) {
            if (!is_string($id) || !isset(self::CATALOG[$id]) || !is_array($cfg)) return null;
            if (array_key_exists('enabled',$cfg) && !is_bool($cfg['enabled']) && !in_array($cfg['enabled'],[0,1,'0','1'],true)) return null;
            foreach (['variant','tone'] as $key) {
                if (array_key_exists($key,$cfg) && !is_scalar($cfg[$key])) return null;
            }
            $variant=(string)($cfg['variant'] ?? $this->defaultVariant($id));
            $tone=(string)($cfg['tone'] ?? $this->defaultTone($id));
            if (!in_array($variant,self::CATALOG[$id]['variants'],true) || !in_array($tone,self::CATALOG[$id]['tones'],true)) return null;
            $out[$id]=['enabled'=>(bool)($cfg['enabled'] ?? false),'variant'=>$variant,'tone'=>$tone];
        }
        ksort($out);
        return $out;
    }

    public function enabled(array $normalized): array {
        $out=[]; foreach ($normalized as $id=>$cfg) if (!empty($cfg['enabled'])) $out[]=(string)$id;
        return $out;
    }

    /** 12 linhas para a galeria do Admin (sempre completas, valores seguros). */
    public function view(?array $raw): array {
        $norm=is_array($raw)?($this->normalize($raw)??[]):[];
        $rows=[];
        foreach (self::CATALOG as $id=>$c) {
            $cfg=$norm[$id] ?? ['enabled'=>false,'variant'=>$this->defaultVariant($id),'tone'=>$this->defaultTone($id)];
            $rows[]=['id'=>$id,'label_key'=>'media_label_'.$id,'help_key'=>'media_help_'.$id,
                'group'=>$c['group'],'group_key'=>'text_media_group_'.$c['group'],
                'variants'=>$c['variants'],'tones'=>$c['tones'],'shape'=>$c['shape'],'ratio'=>$c['ratio'],
                'enabled'=>(bool)$cfg['enabled'],'variant'=>$cfg['variant'],'tone'=>$cfg['tone'],
                'tone_color'=>$this->toneColor($cfg['tone']),
                'thumb'=>$this->dataUri($id,$cfg['variant'],$cfg['tone'])];
        }
        return $rows;
    }

    /** Variaveis CSS: o data URI vai AQUI (style do body), nunca em global Twig. */
    public function css(array $normalized): string {
        $out=[];
        foreach ($this->enabled($normalized) as $id) {
            $cfg=$normalized[$id];
            $out[]='--rms-media-'.$id.': url("'.$this->dataUri($id,$cfg['variant'],$cfg['tone']).'")';
            $out[]='--rms-media-'.$id.'-ratio: '.self::CATALOG[$id]['ratio'];
            $out[]='--rms-media-'.$id.'-tone: '.$this->toneColor($cfg['tone']);
        }
        return $out===[] ? '' : implode('; ',$out).';';
    }

    /** Globais PLANOS e curtos (<=256 bytes por valor), aceitos por twig.php. */
    public function globals(array $normalized): array {
        $variants=[];$tones=[];$enabled=[];$ratios=[];
        foreach ($this->enabled($normalized) as $id) {
            $variants[$id]=$normalized[$id]['variant'];
            $tones[$id]=$normalized[$id]['tone'];
            $ratios[$id]=self::CATALOG[$id]['ratio'];
            $enabled[$id]=1;
        }
        return ['theme_media'=>$variants,'theme_media_tones'=>$tones,
                'theme_media_ratios'=>$ratios,'theme_media_enabled'=>$enabled];
    }

    public function bars(array $normalized): array {
        $out=[];
        foreach ($this->enabled($normalized) as $id) {
            $out[]=['id'=>$id,'ratio'=>self::CATALOG[$id]['ratio'],
                    'tone'=>$this->toneColor($normalized[$id]['tone']),'variant'=>$normalized[$id]['variant']];
        }
        return $out;
    }

    /** SVG deterministico, sem texto/URL/script do usuario. */
    public function svg(string $id, string $variant='', string $tone=''): string {
        if (!isset(self::CATALOG[$id])) $id='pattern-dots';
        $c=self::CATALOG[$id];
        if (!in_array($variant,$c['variants'],true)) $variant=$this->defaultVariant($id);
        if (!in_array($tone,$c['tones'],true)) $tone=$this->defaultTone($id);
        $color=$this->toneColor($tone);
        $bg=$variant==='bold'?$color:'#f8fafc';
        $fg=$variant==='bold'?'#ffffff':$color;
        $stroke=$variant==='outline'?$color:'none';
        $w=160;$h=100;
        $shape='';
        switch ($c['shape']) {
            case 'wave':   $shape='<path d="M0 70 Q40 30 80 65 T160 45 L160 100 L0 100 Z" fill="'.$color.'" opacity=".55"/>'; break;
            case 'grid':   $shape='<path d="M0 25H160M0 50H160M0 75H160M40 0V100M80 0V100M120 0V100" stroke="'.$color.'" stroke-width="1" opacity=".5"/>'; break;
            case 'desk':   $shape='<rect x="20" y="42" width="120" height="42" rx="4" fill="'.$color.'" opacity=".55"/><rect x="66" y="84" width="28" height="10" fill="'.$color.'"/>'; break;
            case 'code':   $shape='<path d="M55 35L35 50L55 65M105 35L125 50L105 65" stroke="'.$color.'" stroke-width="5" fill="none" stroke-linecap="round"/>'; break;
            case 'person': $shape='<circle cx="80" cy="38" r="16" fill="'.$color.'" opacity=".7"/><path d="M50 95 Q80 58 110 95 Z" fill="'.$color.'" opacity=".7"/>'; break;
            case 'star':   $shape='<path d="M80 18L90 42H116L95 58L103 82L80 66L57 82L65 58L44 42H70 Z" fill="'.$color.'"/>'; break;
            case 'shield': $shape='<path d="M80 20L112 32V58Q112 82 80 92Q48 82 48 58V32 Z" fill="'.$color.'" opacity=".75"/>'; break;
            case 'dots':   $shape='<circle cx="40" cy="30" r="6" fill="'.$color.'"/><circle cx="80" cy="30" r="6" fill="'.$color.'"/><circle cx="120" cy="30" r="6" fill="'.$color.'"/><circle cx="40" cy="70" r="6" fill="'.$color.'"/><circle cx="80" cy="70" r="6" fill="'.$color.'"/><circle cx="120" cy="70" r="6" fill="'.$color.'"/>'; break;
            case 'waves':  $shape='<path d="M0 40 Q20 20 40 40 T80 40 T120 40 T160 40M0 65 Q20 45 40 65 T80 65 T120 65 T160 65" stroke="'.$color.'" stroke-width="4" fill="none"/>'; break;
            case 'mark':   $shape='<circle cx="80" cy="50" r="28" fill="none" stroke="'.$color.'" stroke-width="6"/><circle cx="80" cy="50" r="9" fill="'.$color.'"/>'; break;
            default:       $shape='<rect x="18" y="16" width="124" height="68" rx="6" fill="'.$color.'" opacity=".55"/>';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 160 100">'
             . '<rect width="160" height="100" fill="'.$bg.'"/>'.$shape
             . '<rect x="1" y="1" width="158" height="98" fill="none" stroke="'.$stroke.'" stroke-width="2"/>'
             . '</svg>';
    }

    public function dataUri(string $id, string $variant='', string $tone=''): string {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($id,$variant,$tone));
    }

    /** Modelo por ID (constantes; nada e interpolado a partir do config). */
    public function model(string $id): ?array { return self::MODELS[$id] ?? null; }

    /** @return list<array<string,mixed>> */
    public function models(): array {
        $out=[];
        foreach (self::MODELS as $id=>$m) {
            $out[]=['id'=>$id,'label_key'=>'model_label_'.$id,'help_key'=>'model_help_'.$id,
                    'thumb'=>$this->dataUri($m['thumb']??'pattern-dots'),'section_count'=>count($m['sections']),
                    'component_count'=>count($m['components']),'media_count'=>count($m['media'])];
        }
        return $out;
    }

    /** Payload seguro para o JS do Admin (apenas dados allowlistados). */
    public function modelPayload(): array {
        $out=[];
        foreach (self::MODELS as $id=>$m) {
            $out[$id]=['sections'=>array_values($m['sections']),'components'=>$m['components'],
                       'media'=>$m['media'],'template'=>$m['template']];
        }
        return $out;
    }
}
