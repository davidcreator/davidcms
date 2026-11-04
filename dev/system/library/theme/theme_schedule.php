<?php declare(strict_types=1);
/**
 * F13-O — escopo POR SITE (IDs allowlistados de ThemeSites, frente J).
 * F13-H — agendamento e rollout DECLARATIVO de temas.
 *
 * O rascunho pode declarar uma janela (from/until), um publico (scope) e um
 * percentual (percent). Em cada requisicao o Catalog decide, sem escrever nada,
 * se aplica o config PUBLICADO ou o RASCUNHO. Nao ha cron, migration, coluna
 * nova, fila, cookie novo, IO, sessao gravada, eval, call_user_func, regex do
 * usuario, <script> ou |raw. O identificador do visitante e usado APENAS como
 * semente de um hash SHA-256 -> bucket 0..99; nunca e renderizado ou gravado.
 *
 * Formato de tempo: 'YYYY-MM-DDTHH:MM' em UTC (ordem lexicografica = ordem
 * cronologica, porque o formato tem largura fixa). Validado por
 * DateTimeImmutable::createFromFormat + round-trip, nunca por regex do usuario.
 */
namespace Reamur\System\Library\Theme;

final class ThemeSchedule {
    public const KEYS = ['enabled','from','until','percent','scope','sites'];
    public const SCOPES = ['all','logged_in','anonymous'];
    public const REASONS = ['disabled','pending','expired','scope','site','zero','outside_bucket','active'];
    private const FORMAT = '!Y-m-d\TH:i';

    /** @return array{keys:list<string>,scopes:list<string>,reasons:list<string>,format:string} */
    public function spec(): array {
        return ['keys'=>self::KEYS,'scopes'=>self::SCOPES,'reasons'=>self::REASONS,
                'site_ids'=>ThemeSites::IDS,
                'format'=>'YYYY-MM-DDTHH:MM (UTC)','max_percent'=>100];
    }

    /** Formato exato, sem regex. '' e invalido (use from/until separados). */
    public function parseTime(string $value): ?string {
        if (strlen($value) !== 16) return null;
        $dt = \DateTimeImmutable::createFromFormat(self::FORMAT, $value, new \DateTimeZone('UTC'));
        if ($dt === false) return null;
        $errors = \DateTimeImmutable::getLastErrors();
        if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) return null;
        return $dt->format('Y-m-d\TH:i') === $value ? $value : null;
    }

    public function now(): string { return gmdate('Y-m-d\TH:i'); }

    /** @return array{enabled:bool,from:string,until:string,percent:int,scope:string}|null */
    public function normalize(mixed $value): ?array {
        if ($value === null || $value === [] || $value === '') {
            return ['enabled'=>false,'from'=>'','until'=>'','percent'=>0,'scope'=>'all','sites'=>[]];
        }
        if (!is_array($value)) return null;
        foreach (array_keys($value) as $k) {
            if (!in_array((string)$k, self::KEYS, true)) return null;
        }
        // F13-O: `sites` e o unico valor array permitido (IDs allowlistados).
        foreach ($value as $k => $v) {
            if ((string)$k === 'sites') continue;
            if (is_array($v) || is_object($v) || is_resource($v)) return null;
        }
        $rawSites = $value['sites'] ?? [];
        if (!is_array($rawSites)) return null;
        $sites = [];
        foreach ($rawSites as $sid) {
            if (!is_string($sid) || !in_array($sid, ThemeSites::IDS, true)) return null;
            if (!in_array($sid, $sites, true)) $sites[] = $sid;
        }
        $rawEnabled = $value['enabled'] ?? false;
        if (is_bool($rawEnabled)) $enabled = $rawEnabled;
        elseif (is_int($rawEnabled) || is_string($rawEnabled)) $enabled = in_array((string)$rawEnabled, ['1','true','on','yes'], true);
        else return null;

        $from = (string)($value['from'] ?? '');
        $until = (string)($value['until'] ?? '');
        if ($enabled) {
            if ($this->parseTime($from) === null) return null;
            if ($until !== '' && $this->parseTime($until) === null) return null;
            if ($until !== '' && strcmp($until, $from) < 0) return null;
        } else {
            if ($from !== '' && $this->parseTime($from) === null) return null;
            if ($until !== '' && $this->parseTime($until) === null) return null;
        }

        $rawPercent = $value['percent'] ?? 0;
        $percent = filter_var($rawPercent, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>100]]);
        if ($percent === false) return null;

        $scope = (string)($value['scope'] ?? 'all');
        if (!in_array($scope, self::SCOPES, true)) return null;

        return ['enabled'=>$enabled,'from'=>$from,'until'=>$until,
                'percent'=>(int)$percent,'scope'=>$scope,'sites'=>$sites];
    }

    /** Bucket estavel 0..99. Apenas um hash; nada e persistido ou exibido. */
    public function bucket(string $visitorSeed): int {
        return (int)(hexdec(substr(hash('sha256', $visitorSeed), 0, 8)) % 100);
    }

    /**
     * Decisao pura. `$now` precisa estar no formato aceito por parseTime()
     * (use now()); valor invalido => decisao fail-closed 'published'.
     * F13-O: $siteId e opcional (default 'primary') para nao quebrar A-N.
     * @return array{enabled:bool,from:string,until:string,percent:int,scope:string,
     *               sites:list<string>,site:string,effective:string,reason:string,
     *               bucket:int,active:bool,now:string}
     */
    public function decide(?array $schedule, string $now, string $visitorSeed, bool $loggedIn, string $siteId = ''): array {
        $s = $schedule ?? $this->normalize(null);
        $bucket = $this->bucket($visitorSeed);
        $rawSites = (array)($s['sites'] ?? []);
        $sites = [];
        foreach ($rawSites as $sid) {
            if (is_string($sid) && in_array($sid, ThemeSites::IDS, true)
                && !in_array($sid, $sites, true)) $sites[] = $sid;
        }
        $site = in_array($siteId, ThemeSites::IDS, true) ? $siteId : 'primary';
        $base = ['enabled'=>(bool)($s['enabled'] ?? false),'from'=>(string)($s['from'] ?? ''),
                 'until'=>(string)($s['until'] ?? ''),'percent'=>(int)($s['percent'] ?? 0),
                 'scope'=>(string)($s['scope'] ?? 'all'),'sites'=>$sites,'site'=>$site,
                 'effective'=>'published','reason'=>'disabled','bucket'=>$bucket,'active'=>false,
                 'now'=>$this->parseTime($now) ?? $this->now()];
        if ($base['percent'] < 0 || $base['percent'] > 100
            || !in_array($base['scope'], self::SCOPES, true)
            || $this->parseTime($now) === null) return $base;
        if (!$base['enabled']) { $base['reason']='disabled'; return $base; }
        if ($base['from'] === '' || strcmp($base['now'], $base['from']) < 0) { $base['reason']='pending'; return $base; }
        if ($base['until'] !== '' && strcmp($base['now'], $base['until']) > 0) { $base['reason']='expired'; return $base; }
        if ($base['scope'] === 'logged_in' && !$loggedIn) { $base['reason']='scope'; return $base; }
        if ($base['scope'] === 'anonymous' && $loggedIn) { $base['reason']='scope'; return $base; }
        // F13-O: lista vazia = todos os sites (identico a A-N). Lista nao
        // vazia = somente os IDs allowlistados de ThemeSites entram no rollout.
        if ($sites !== [] && !in_array($site, $sites, true)) { $base['reason']='site'; return $base; }
        if ($base['percent'] === 0) { $base['reason']='zero'; return $base; }
        if ($bucket >= $base['percent']) { $base['reason']='outside_bucket'; return $base; }
        $base['effective']='draft'; $base['active']=true; $base['reason']='active';
        return $base;
    }

    /** Estado completo (5 chaves + decisao), pronto para snapshot/config. */
    public function state(?array $schedule, string $now, string $visitorSeed, bool $loggedIn, string $siteId = ''): array {
        return $this->decide($schedule, $now, $visitorSeed, $loggedIn, $siteId);
    }

    /** Variaveis CSS planas; nenhum valor vem de texto livre do usuario. */
    public function css(array $state): string {
        $percent = max(0, min(100, (int)($state['percent'] ?? 0)));
        $bucket = max(0, min(99, (int)($state['bucket'] ?? 0)));
        $siteCount = count(array_filter((array)($state['sites'] ?? []),
            static fn($v): bool => is_string($v) && in_array($v, ThemeSites::IDS, true)));
        return '--rms-sched-percent:' . $percent . ';'
             . '--rms-sched-bucket:' . $bucket . ';'
             . '--rms-sched-sites:' . $siteCount . ';'
             . '--rms-sched-active:' . (!empty($state['active']) ? '1' : '0') . ';';
    }

    /** Globais TWIG planos (twig.php descarta arrays aninhados). */
    public function globals(array $state): array {
        return [
            'theme_schedule_effective' => (string)($state['effective'] ?? 'published'),
            'theme_schedule_state'      => (string)($state['reason'] ?? 'disabled'),
            'theme_schedule_percent'    => (int)($state['percent'] ?? 0),
            'theme_schedule_bucket'     => (int)($state['bucket'] ?? 0),
            'theme_schedule_scope'      => (string)($state['scope'] ?? 'all'),
            'theme_schedule_site'       => (string)($state['site'] ?? 'primary'),
            'theme_schedule_site_count' => (int)count((array)($state['sites'] ?? [])),
        ];
    }

    /** Dados ja seguros para a fita da previa (sem HTML, sem texto livre). */
    public function bars(array $state): array {
        return [
            'window'  => ['from'=>(string)($state['from'] ?? ''),'until'=>(string)($state['until'] ?? '')],
            'rollout' => ['percent'=>max(0,min(100,(int)($state['percent'] ?? 0))),
                          'bucket'=>max(0,min(99,(int)($state['bucket'] ?? 0)))],
            'scope'   => (string)($state['scope'] ?? 'all'),
            // F13-O: so IDs allowlistados, planos, para a fita da previa.
            'sites'   => array_values(array_filter((array)($state['sites'] ?? []),
                          static fn($v): bool => is_string($v) && in_array($v, ThemeSites::IDS, true))),
            'site'    => in_array((string)($state['site'] ?? ''), ThemeSites::IDS, true)
                          ? (string)$state['site'] : 'primary',
            'reason'  => (string)($state['reason'] ?? 'disabled'),
            'active'  => !empty($state['active']),
            'now'     => (string)($state['now'] ?? ''),
        ];
    }
}
