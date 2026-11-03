<?php declare(strict_types=1);
/**
 * F13-J — multisite declarativo (hosts allowlistados -> temas por slug).
 *
 * Nenhuma migration, coluna, tabela, vhost, DNS, cron, cookie novo ou escrita.
 * O mapa de sites vive no JSON do tema (CONFIG_KEYS de ThemePackage); a decisao
 * e feita em cada requisicao pelo startup/theme.php sobre o `themeMap` que a
 * frente H JA carrega com getThemeMap() — nenhuma consulta extra ao banco.
 *
 * Seguranca: HTTP_HOST e dado controlado pelo cliente. NUNCA e refletido em
 * HTML, global Twig, CSS ou log; apenas comparado (minusculas, porta removida)
 * contra hosts allowlistados do config. Host desconhecido/invalido cai no site
 * padrao (fail-closed). Nenhum texto livre do request entra no resultado.
 *
 * Puro: sem IO, eval, call_user_func, regex do usuario, sessao, tempo, rede,
 * HTML, <script> ou |raw. Globais Twig sao mapas PLANOS e curtos (<=256 bytes).
 */
namespace Reamur\System\Library\Theme;

final class ThemeSites {
    public const MAX_SITES = 8;
    /** IDs estaveis e allowlistados: nunca sao texto livre do usuario. */
    public const IDS = ['primary','shop','blog','docs','landing','partner','legacy','staging'];
    public const KEYS = ['host','slug','language','enabled'];
    public const LANGUAGES = ['pt-br','en-us','es-eu'];
    private const REASONS = ['exact','default','empty'];

    /** Rotulos de UI (resolvidos pelo Language do Admin). */
    private const LABELS = [
        'primary'=>['label'=>'text_site_primary','help'=>'text_site_primary_help'],
        'shop'   =>['label'=>'text_site_shop','help'=>'text_site_shop_help'],
        'blog'   =>['label'=>'text_site_blog','help'=>'text_site_blog_help'],
        'docs'   =>['label'=>'text_site_docs','help'=>'text_site_docs_help'],
        'landing'=>['label'=>'text_site_landing','help'=>'text_site_landing_help'],
        'partner'=>['label'=>'text_site_partner','help'=>'text_site_partner_help'],
        'legacy' =>['label'=>'text_site_legacy','help'=>'text_site_legacy_help'],
        'staging'=>['label'=>'text_site_staging','help'=>'text_site_staging_help'],
    ];

    public function spec(): array {
        return ['max'=>self::MAX_SITES,'ids'=>array_values(self::IDS),'keys'=>array_values(self::KEYS),
                'languages'=>array_values(self::LANGUAGES),'reasons'=>array_values(self::REASONS)];
    }

    public function ids(): array { return array_values(self::IDS); }
    public function languages(): array { return array_values(self::LANGUAGES); }

    /** @return array<string,array{label_key:string,help_key:string}> */
    public function labels(): array { return self::LABELS; }

    /** Host allowlistado: minusculas, sem scheme/porta/caminho/underline. */
    private static function validHost(string $host): bool {
        if ($host === '' || strlen($host) > 253) return false;
        if (preg_match('/^[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?$/D',$host) !== 1) return false;
        return !str_contains($host,'..') && !str_contains($host,'.-') && !str_contains($host,'-.');
    }

    /** Normaliza APENAS para comparacao; o resultado nunca e publicado. */
    public function hostKey(mixed $raw): string {
        if (!is_string($raw)) return '';
        $host = strtolower(trim($raw));
        if ($host === '' || str_contains($host,'/') || str_contains($host,'\\') || str_contains($host,'@')) return '';
        if (preg_match('/^([a-z0-9.-]+):\d{1,5}$/D',$host,$m) === 1) $host = $m[1];
        return self::validHost($host) ? $host : '';
    }

    /**
     * Fail-closed: so IDs allowlistados, 4 chaves escalares, host/slug/idioma
     * validados. Qualquer divergencia => null => nenhum site (comportamento I).
     * @return array<string,array{host:string,slug:string,language:string,enabled:bool}>|null
     */
    public function normalize(mixed $raw): ?array {
        if ($raw === null || $raw === []) return [];
        if (!is_array($raw) || count($raw) > self::MAX_SITES) return null;
        $out=[];
        foreach ($raw as $id=>$cfg) {
            if (!is_string($id) || !in_array($id,self::IDS,true) || !is_array($cfg)) return null;
            foreach (array_keys($cfg) as $k) if (!is_string($k) || !in_array($k,self::KEYS,true)) return null;
            foreach (self::KEYS as $k) if (array_key_exists($k,$cfg) && !is_scalar($cfg[$k])) return null;
            if (array_key_exists('enabled',$cfg) && !is_bool($cfg['enabled'])
                && !in_array($cfg['enabled'],[0,1,'0','1'],true)) return null;
            $host = strtolower(trim((string)($cfg['host'] ?? '')));
            $slug = strtolower(trim((string)($cfg['slug'] ?? '')));
            $lang = (string)($cfg['language'] ?? 'pt-br');
            if (!self::validHost($host)) return null;
            // slug vazio = "usar o tema ativo" (opcao explicita do formulario).
            if ($slug !== '' && preg_match('/^[a-z0-9_-]{1,48}$/D',$slug) !== 1) return null;
            if (!in_array($lang,self::LANGUAGES,true)) return null;
            $out[$id]=['host'=>$host,'slug'=>$slug,'language'=>$lang,
                       'enabled'=>(bool)($cfg['enabled'] ?? false)];
        }
        ksort($out);
        return $out;
    }

    /** Site vazio: nenhuma politica configurada (compatibilidade A-I). */
    public function emptySite(): array {
        return ['id'=>'','host'=>'','slug'=>'','language'=>'','enabled'=>false,
                'reason'=>'empty','active'=>false];
    }

    /** Primeiro site habilitado na ordem allowlistada (fallback deterministico). */
    public function defaultSite(array $sites): array {
        foreach (self::IDS as $id) {
            if (isset($sites[$id]) && $sites[$id]['enabled']) {
                return ['id'=>$id,'host'=>$sites[$id]['host'],'slug'=>$sites[$id]['slug'],
                        'language'=>$sites[$id]['language'],'enabled'=>true,
                        'reason'=>'default','active'=>true];
            }
        }
        return $this->emptySite();
    }

    /**
     * Decisao por requisicao. Host exato allowlistado => aquele site;
     * desconhecido/invalido => default. NUNCA devolve o host do request.
     */
    public function match(array $sites, mixed $rawHost): array {
        if ($sites === []) return $this->emptySite();
        $key = $this->hostKey($rawHost);
        if ($key !== '') {
            foreach (self::IDS as $id) {
                if (!isset($sites[$id]) || !$sites[$id]['enabled']) continue;
                if (hash_equals($sites[$id]['host'],$key)) {
                    return ['id'=>$id,'host'=>$sites[$id]['host'],'slug'=>$sites[$id]['slug'],
                            'language'=>$sites[$id]['language'],'enabled'=>true,
                            'reason'=>'exact','active'=>true];
                }
            }
        }
        return $this->defaultSite($sites);
    }

    /**
     * Escolhe o config raiz do site sobre o mapa de temas ja carregado.
     * Slug ausente no mapa => fallback (fail-closed, sem erro e sem query).
     * @return array{slug:string,config:?array,fallback:bool}
     */
    public function resolve(array $active, array $themeMap, string $fallbackSlug): array {
        $slug = (string)($active['slug'] ?? '');
        if ($slug !== '' && isset($themeMap[$slug]) && is_array($themeMap[$slug])) {
            return ['slug'=>$slug,'config'=>$themeMap[$slug],'fallback'=>false];
        }
        return ['slug'=>$fallbackSlug,'config'=>null,'fallback'=>true];
    }

    /** @return list<array{id:string,slug:string,language:string,host:string,active:bool}> */
    public function bars(array $sites, array $active): array {
        $out=[];
        foreach (self::IDS as $id) {
            if (!isset($sites[$id]) || !$sites[$id]['enabled']) continue;
            $out[]=['id'=>$id,'host'=>$sites[$id]['host'],'slug'=>$sites[$id]['slug'],
                    'language'=>$sites[$id]['language'],
                    'active'=>($active['id'] ?? '')===$id];
        }
        return $out;
    }

    /** CSS so com inteiros allowlistados (nenhum texto livre do request). */
    public function css(array $active): string {
        $index = array_search((string)($active['id'] ?? ''),self::IDS,true);
        if ($index === false || !($active['active'] ?? false)) return '';
        return '--rms-site-index: '.(int)$index.';--rms-site-active: 1;';
    }

    /** Globais Twig PLANOS e curtos; nenhum host do request e publicado. */
    public function globals(array $sites, array $active): array {
        $map=[]; $enabled=[];
        foreach ($sites as $id=>$cfg) {
            $map[$id]=(string)$cfg['slug'];
            if ($cfg['enabled']) $enabled[$id]=1;
        }
        return [
            'theme_site'=>['id'=>(string)($active['id'] ?? ''),
                           'slug'=>(string)($active['slug'] ?? ''),
                           'language'=>(string)($active['language'] ?? ''),
                           'reason'=>(string)($active['reason'] ?? 'empty')],
            'theme_sites'=>$map,
            'theme_site_enabled'=>$enabled,
        ];
    }

    /** Linhas do formulario Admin (8 IDs estaveis, rotulos resolvidos depois). */
    public function view(?array $raw, ?array $active = null): array {
        $sites = $this->normalize($raw) ?? [];
        $out=[];
        foreach (self::IDS as $id) {
            $cfg = $sites[$id] ?? ['host'=>'','slug'=>'','language'=>'pt-br','enabled'=>false];
            $out[]=['id'=>$id,'label_key'=>self::LABELS[$id]['label'],'help_key'=>self::LABELS[$id]['help'],
                    'host'=>$cfg['host'],'slug'=>$cfg['slug'],'language'=>$cfg['language'],
                    'enabled'=>$cfg['enabled'],
                    'active'=>($active['id'] ?? '')===$id];
        }
        return $out;
    }
}
