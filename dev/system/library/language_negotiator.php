<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
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
}
