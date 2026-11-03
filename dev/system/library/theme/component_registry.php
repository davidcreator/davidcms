<?php declare(strict_types=1);
/**
 * F13-G — biblioteca de componentes declarativos do tema.
 *
 * Nao e o BDREditor (F9-25/F9-103): aqui nao ha documento de blocos, banco,
 * upload, custom_js nem HTML livre. Cada componente e uma CONSTANTE allow-
 * listada com opcoes enum, uma miniatura SVG deterministica, um snippet Twig
 * fixo (sem |raw, sem <script>, sem event handlers) e variaveis CSS seguras.
 * O config do tema so escolhe QUAIS componentes habilitar e suas variantes.
 *
 * Sem IO, eval, call_user_func, regex de usuario, SQL, HTTP ou escrita.
 */
namespace Reamur\System\Library\Theme;

final class ComponentRegistry {
    public const MAX_COMPONENTS = 10;

    /** Tons fixos: so hex allowlistado entra no CSS/SVG. */
    private const TONES = [
        'primary' => '#2563eb',
        'neutral' => '#475569',
        'success' => '#15803d',
        'danger'  => '#b91c1c',
    ];

    /**
     * Catalogo fechado. `snippet` e Twig constante consumido pelos globais
     * planos publicados no Catalog (theme_components / theme_component_tones /
     * theme_component_enabled); nada e interpolado a partir do config.
     */
    private const CATALOG = [
        'button'  => ['group'=>'action',   'variants'=>['solid','outline','ghost'],       'tones'=>['primary','neutral','danger'],  'shape'=>'pill'],
        'badge'   => ['group'=>'display',  'variants'=>['pill','square'],                'tones'=>['primary','neutral','success'], 'shape'=>'tag'],
        'card'    => ['group'=>'display',  'variants'=>['flat','elevated','bordered'],   'tones'=>['primary','neutral'],          'shape'=>'box'],
        'callout' => ['group'=>'feedback', 'variants'=>['info','warning','success'],     'tones'=>['primary','neutral','danger'], 'shape'=>'bar'],
        'stat'    => ['group'=>'display',  'variants'=>['compact','wide'],               'tones'=>['primary','neutral','success'], 'shape'=>'metric'],
        'quote'   => ['group'=>'display',  'variants'=>['plain','bordered'],             'tones'=>['primary','neutral'],          'shape'=>'quote'],
        'divider' => ['group'=>'display',  'variants'=>['solid','dashed'],               'tones'=>['primary','neutral'],          'shape'=>'line'],
        'feature' => ['group'=>'display',  'variants'=>['icon','number'],                'tones'=>['primary','neutral','success'], 'shape'=>'tile'],
        'cta'     => ['group'=>'action',   'variants'=>['inline','block'],               'tones'=>['primary','neutral'],          'shape'=>'banner'],
        'notice'  => ['group'=>'feedback', 'variants'=>['bar','box'],                    'tones'=>['primary','neutral','danger'], 'shape'=>'bar'],
    ];

    /** Snippets Twig fixos. Nenhum dado do config e interpolado como HTML. */
    private const SNIPPETS = [
        'button'  => '{% if theme_component_enabled.button|default(0) %}<a class="rms-cmp-btn rms-cmp-btn--{{ theme_components.button|default(\'solid\') }} rms-cmp-tone--{{ theme_component_tones.button|default(\'primary\') }}" href="#">{{ text_component_button|default(\'Saiba mais\') }}</a>{% endif %}',
        'badge'   => '{% if theme_component_enabled.badge|default(0) %}<span class="rms-cmp-badge rms-cmp-badge--{{ theme_components.badge|default(\'pill\') }} rms-cmp-tone--{{ theme_component_tones.badge|default(\'primary\') }}">{{ text_component_badge|default(\'Novo\') }}</span>{% endif %}',
        'card'    => '{% if theme_component_enabled.card|default(0) %}<div class="rms-cmp-card rms-cmp-card--{{ theme_components.card|default(\'flat\') }} rms-cmp-tone--{{ theme_component_tones.card|default(\'primary\') }}"><h3>{{ text_component_card_title|default(\'Titulo\') }}</h3><p>{{ text_component_card_body|default(\'Descricao do componente.\') }}</p></div>{% endif %}',
        'callout' => '{% if theme_component_enabled.callout|default(0) %}<aside class="rms-cmp-callout rms-cmp-callout--{{ theme_components.callout|default(\'info\') }} rms-cmp-tone--{{ theme_component_tones.callout|default(\'primary\') }}">{{ text_component_callout|default(\'Informacao importante.\') }}</aside>{% endif %}',
        'stat'    => '{% if theme_component_enabled.stat|default(0) %}<div class="rms-cmp-stat rms-cmp-stat--{{ theme_components.stat|default(\'compact\') }} rms-cmp-tone--{{ theme_component_tones.stat|default(\'primary\') }}"><strong>{{ text_component_stat_value|default(\'100%\') }}</strong><span>{{ text_component_stat_label|default(\'Resultado\') }}</span></div>{% endif %}',
        'quote'   => '{% if theme_component_enabled.quote|default(0) %}<blockquote class="rms-cmp-quote rms-cmp-quote--{{ theme_components.quote|default(\'plain\') }} rms-cmp-tone--{{ theme_component_tones.quote|default(\'primary\') }}">{{ text_component_quote|default(\'Depoimento de exemplo.\') }}</blockquote>{% endif %}',
        'divider' => '{% if theme_component_enabled.divider|default(0) %}<hr class="rms-cmp-divider rms-cmp-divider--{{ theme_components.divider|default(\'solid\') }} rms-cmp-tone--{{ theme_component_tones.divider|default(\'neutral\') }}">{% endif %}',
        'feature' => '{% if theme_component_enabled.feature|default(0) %}<div class="rms-cmp-feature rms-cmp-feature--{{ theme_components.feature|default(\'icon\') }} rms-cmp-tone--{{ theme_component_tones.feature|default(\'primary\') }}"><strong>{{ text_component_feature_title|default(\'Recurso\') }}</strong><p>{{ text_component_feature_body|default(\'Descricao do recurso.\') }}</p></div>{% endif %}',
        'cta'     => '{% if theme_component_enabled.cta|default(0) %}<section class="rms-cmp-cta rms-cmp-cta--{{ theme_components.cta|default(\'inline\') }} rms-cmp-tone--{{ theme_component_tones.cta|default(\'primary\') }}"><h3>{{ text_component_cta_title|default(\'Vamos comecar?\') }}</h3><a href="#">{{ text_component_cta_button|default(\'Falar comigo\') }}</a></section>{% endif %}',
        'notice'  => '{% if theme_component_enabled.notice|default(0) %}<div class="rms-cmp-notice rms-cmp-notice--{{ theme_components.notice|default(\'bar\') }} rms-cmp-tone--{{ theme_component_tones.notice|default(\'neutral\') }}" role="note">{{ text_component_notice|default(\'Aviso do tema.\') }}</div>{% endif %}',
    ];

    /** @return list<string> */
    public function ids(): array { return array_keys(self::CATALOG); }

    /** @return array<string,mixed> */
    public function spec(): array {
        $out=[];
        foreach (self::CATALOG as $id=>$c) $out[$id]=['group'=>$c['group'],'variants'=>$c['variants'],'tones'=>$c['tones']];
        return ['max'=>self::MAX_COMPONENTS,'tones'=>array_keys(self::TONES),'components'=>$out];
    }

    /** @return list<string>|null */
    public function variants(string $id): ?array { return self::CATALOG[$id]['variants'] ?? null; }
    /** @return list<string>|null */
    public function tones(string $id): ?array { return self::CATALOG[$id]['tones'] ?? null; }

    public function defaultVariant(string $id): string { return $this->variants($id)[0] ?? ''; }
    public function defaultTone(string $id): string { return $this->tones($id)[0] ?? 'primary'; }
    public function toneColor(string $tone): string { return self::TONES[$tone] ?? self::TONES['neutral']; }
    public function snippet(string $id): string { return self::SNIPPETS[$id] ?? ''; }
    /** @return array<string,string> */
    public function snippets(): array { return self::SNIPPETS; }

    /**
     * Fail-closed: so ids do catalogo, enums allowlistados e enabled booleano.
     * `null` = config invalido; `[]` = nenhum componente (override explicito).
     * @return array<string,array{enabled:bool,variant:string,tone:string}>|null
     */
    public function normalize(mixed $raw): ?array {
        if (!is_array($raw) || count($raw) > self::MAX_COMPONENTS) return null;
        $out=[];
        foreach ($raw as $id=>$cfg) {
            if (!is_string($id) || !isset(self::CATALOG[$id]) || !is_array($cfg)) return null;
            if (array_key_exists('enabled',$cfg) && !is_bool($cfg['enabled']) && !in_array($cfg['enabled'],[0,1,'0','1'],true)) return null;
            $enabled = (bool)($cfg['enabled'] ?? false);
            $variant = (string)($cfg['variant'] ?? $this->defaultVariant($id));
            $tone    = (string)($cfg['tone'] ?? $this->defaultTone($id));
            if (!in_array($variant,$this->variants($id),true) || !in_array($tone,$this->tones($id),true)) return null;
            $out[$id]=['enabled'=>$enabled,'variant'=>$variant,'tone'=>$tone];
        }
        ksort($out);
        return $out;
    }

    /** @return list<string> Componentes efetivamente habilitados. */
    public function enabled(array $normalized): array {
        $out=[];
        foreach ($normalized as $id=>$cfg) if (!empty($cfg['enabled'])) $out[]=(string)$id;
        return $out;
    }

    /** Estado para o formulario Admin (sempre os 10, com valores seguros). */
    public function view(?array $raw): array {
        $norm = is_array($raw) ? ($this->normalize($raw) ?? []) : [];
        $rows=[];
        foreach (self::CATALOG as $id=>$c) {
            $cfg=$norm[$id] ?? ['enabled'=>false,'variant'=>$this->defaultVariant($id),'tone'=>$this->defaultTone($id)];
            $rows[]=[
                'id'=>$id,'label_key'=>'component_label_'.$id,'help_key'=>'component_help_'.$id,
                'group'=>$c['group'],'group_key'=>'text_component_group_'.$c['group'],
                'variants'=>$c['variants'],'tones'=>$c['tones'],
                'enabled'=>(bool)$cfg['enabled'],'variant'=>$cfg['variant'],'tone'=>$cfg['tone'],
                'shape'=>$c['shape'],'tone_color'=>$this->toneColor($cfg['tone']),
            ];
        }
        return $rows;
    }

    /** Variaveis CSS allowlistadas; nenhum caractere livre do config. */
    public function css(array $normalized): string {
        $out=[];
        foreach ($this->enabled($normalized) as $id) {
            $cfg=$normalized[$id];
            $out[]='--rms-cmp-'.$id.'-variant: '.$cfg['variant'];
            $out[]='--rms-cmp-'.$id.'-tone: '.$this->toneColor($cfg['tone']);
        }
        return $out===[] ? '' : implode('; ',$out).';';
    }

    /** Globais PLANOS (compatíveis com Twig::setThemeGlobals, sem aninhamento). */
    public function globals(array $normalized): array {
        $variants=[];$tones=[];$enabled=[];
        foreach ($this->enabled($normalized) as $id) {
            $variants[$id]=$normalized[$id]['variant'];
            $tones[$id]=$normalized[$id]['tone'];
            $enabled[$id]=1;
        }
        return ['theme_components'=>$variants,'theme_component_tones'=>$tones,'theme_component_enabled'=>$enabled];
    }

    /** Miniatura SVG deterministica: sem texto/script/URL do usuario. */
    public function svg(string $id, string $variant = '', string $tone = ''): string {
        if (!isset(self::CATALOG[$id])) $id='card';
        $c=self::CATALOG[$id];
        $v=in_array($variant,$c['variants'],true)?$variant:$this->defaultVariant($id);
        $t=in_array($tone,$c['tones'],true)?$tone:$this->defaultTone($id);
        $color=$this->toneColor($t);
        $vi=array_search($v,$c['variants'],true);
        $radius=(int)(6+($vi*7));
        $stroke=($v==='outline'||$v==='bordered'||$v==='dashed')?2:0;
        $dash=($v==='dashed')?' stroke-dasharray="5 4"':'';
        $extra='';
        if ($c['shape']==='pill'||$c['shape']==='tag'||$c['shape']==='bar'||$c['shape']==='line') {
            $extra='<rect x="18" y="62" width="88" height="7" rx="3" fill="#cbd5e1"/>';
        } else {
            $extra='<rect x="18" y="58" width="104" height="6" rx="3" fill="#cbd5e1"/>'
                 .'<rect x="18" y="70" width="72" height="6" rx="3" fill="#e2e8f0"/>';
        }
        $accent=($vi%2===1)?'<circle cx="126" cy="26" r="9" fill="'.$color.'" opacity=".45"/>':'';
        return '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="100" viewBox="0 0 160 100" role="img">'
            .'<rect width="160" height="100" fill="#f8fafc"/>'
            .'<rect x="10" y="10" width="140" height="80" rx="'.$radius.'" fill="#ffffff" stroke="#cbd5e1" stroke-width="1"/>'
            .'<rect x="18" y="18" width="'.(70+($vi*14)).'" height="'.(22+($vi*4)).'" rx="'.$radius.'" fill="'.$color.'" opacity="'.($stroke?'0.28':'0.85').'" stroke="'.$color.'" stroke-width="'.$stroke.'"'.$dash.'/>'
            .$accent.$extra.'</svg>';
    }

    public function dataUri(string $id, string $variant = '', string $tone = ''): string {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($id,$variant,$tone));
    }
}
