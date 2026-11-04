<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * F13-S: alignSite() realinha o idioma do site ao config EFETIVO.
 * F13-P — negociacao de idioma PURA e sem IO. Uma unica fonte de verdade
 * para as pre-acoes startup/theme (contexto das condicoes, frente L) e
 * startup/language (servico Language, frente K), que antes decidiam em
 * momentos diferentes e por isso divergiam.
 *
 * Precedencia preservada da K: ?language= > sessao > idioma do site > default.
 * F13-P: a sessao so prevalece quando pertence ao MESMO site; trocar de site
 * descarta o idioma herdado de outro host (limitacao declarada na K).
 *
 * Nenhum banco, sessao, request, cookie, arquivo, tempo, rede, regex do
 * usuario, HTML, <script> ou |raw. O chamador fornece valores ja allowlistados.
 */
namespace Reamur\System\Library;

final class LanguageNegotiator {
    /** Codigos do core (ADR 0001). Nada de texto livre entra na decisao. */
    public const CODES = ['pt-br', 'en-us', 'es-eu'];
    public const SOURCES = ['request', 'session', 'site', 'default'];
    public const SESSION_KEY = 'language';
    public const SESSION_SITE_KEY = 'language_site';

    /** Normaliza exatamente como o legado F2-3 (minusculas, so letras e hifen). */
    public static function clean(string $raw): string {
        return strtolower((string)preg_replace('/[^a-zA-Z-]/', '', $raw));
    }

    public static function isCode(string $code): bool {
        return in_array($code, self::CODES, true);
    }

    /**
     * Decisao pura e fail-closed.
     * @param array<string,mixed> $in enabled, default, requested, stored,
     *        stored_site, site, site_language
     * @return array{code:string,source:string,site:string,
     *               site_changed:bool,store:bool}
     */
    public static function resolve(array $in): array {
        $enabled = [];
        foreach ((array)($in['enabled'] ?? []) as $c) {
            if (is_string($c) && self::isCode($c) && !in_array($c, $enabled, true)) $enabled[] = $c;
        }
        $default = self::clean((string)($in['default'] ?? 'en-us'));
        if (!in_array($default, $enabled, true)) $default = $enabled[0] ?? 'en-us';

        $requested = self::clean((string)($in['requested'] ?? ''));
        $stored    = self::clean((string)($in['stored'] ?? ''));
        $storedSite = (string)($in['stored_site'] ?? '');
        $site       = (string)($in['site'] ?? '');
        $siteLanguage = self::clean((string)($in['site_language'] ?? ''));

        $siteOk = $siteLanguage !== '' && self::isCode($siteLanguage)
                  && in_array($siteLanguage, $enabled, true);
        // Sem site registrado na sessao (instalacoes A-O) a sessao continua
        // valendo exatamente como antes: nenhuma mudanca de comportamento.
        $siteChanged = $storedSite !== '' && $site !== '' && $storedSite !== $site;
        $sessionOk = !$siteChanged && $stored !== '' && self::isCode($stored)
                     && in_array($stored, $enabled, true);

        if ($requested !== '' && self::isCode($requested) && in_array($requested, $enabled, true)) {
            $code = $requested; $source = 'request';
        } elseif ($sessionOk) {
            $code = $stored; $source = 'session';
        } elseif ($siteOk) {
            $code = $siteLanguage; $source = 'site';
        } else {
            $code = $default; $source = 'default';
        }

        return [
            'code' => $code,
            'source' => $source,
            'site' => $site,
            'site_changed' => $siteChanged,
            'store' => $stored !== $code || $storedSite !== $site,
        ];
    }

    /**
     * F13-S — realinha o idioma do site ao config EFETIVO, DEPOIS da decisao
     * de rollout. A frente P negocia antes da decisao (para nao criar ciclo)
     * e por isso usa a politica PUBLICADA; se o rascunho mudou
     * `sites[shop].language`, o visitante abria no idioma antigo enquanto o
     * snapshot publicava o novo. A frente R ja alinhou o ID do site; aqui
     * fechamos a ultima divergencia da P, lendo o idioma do MESMO ID no
     * config efetivo.
     *
     * SOBERANIA PRESERVADA: apenas `source === 'site'` pode ser realinhado.
     * `?language=`, sessao e default do painel continuam INTACTOS. Codigo
     * fora da allowlist, nao habilitado ou igual ao atual => sem mudanca.
     * Continua PURO: sem IO, sem request, sem sessao. O chamador decide se
     * regrava a sessao/config comparando `code` antes e depois.
     *
     * @param array{code:string,source:string,site:string,
     *              site_changed:bool,store:bool} $resolved
     * @param mixed $effectiveSiteLanguage idioma do site no config efetivo
     * @param list<string> $enabled codigos habilitados (getEnabledCodes)
     * @return array<string,mixed> MESMO formato de resolve()
     */
    public static function alignSite(array $resolved, mixed $effectiveSiteLanguage, array $enabled): array {
        if (($resolved['source'] ?? '') !== 'site') return $resolved;
        $code = self::clean(is_string($effectiveSiteLanguage) ? $effectiveSiteLanguage : '');
        if (!self::isCode($code) || !in_array($code, $enabled, true)) return $resolved;
        if ($code === (string)($resolved['code'] ?? '')) return $resolved;
        $out = $resolved;
        $out['code'] = $code;
        $out['store'] = true;
        return $out;
    }
}
