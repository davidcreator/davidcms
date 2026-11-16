<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @license   https://opensource.org/licenses/GPL-3.0
 *
 * KZ-19 (ADR 0112) — textos proprios do tema, por idioma.
 *
 * O Catalog ja resolve o idioma da requisicao (LanguageNegotiator) e ja guarda
 * os textos do SISTEMA em `catalog/language/<idioma>/` e o CONTEUDO em tabelas
 * `*_description`. Faltava o terceiro lugar: o texto que so existe no tema
 * ("Iniciar missao", "Fase 01"). Ele ficava digitado na fonte Twig, em um
 * idioma so, e nao mudava com o idioma do visitante.
 *
 * Contrato (`config.strings` do pacote `reamurcms-theme`):
 *
 *     "strings": {
 *       "hero_start": { "en-us": "Start mission", "pt-br": "Iniciar missão", "es-eu": "Iniciar misión" }
 *     }
 *
 * A fonte do tema imprime `{{ theme_text.hero_start }}`. O valor entregue e o
 * do idioma da requisicao; se faltar, o do idioma de recuo (`en-us`); se
 * faltar tambem, o primeiro idioma preenchido. Assim um texto nunca some por
 * falta de traducao: ele aparece em outro idioma.
 *
 * Regras:
 *  - PURA: nenhum banco, sessao, request, rede, arquivo ou escrita.
 *  - Texto simples. Nao e HTML: o Twig do tema escapa na saida (autoescape).
 *  - Gramaticas fechadas para chave e idioma; limites de quantidade e tamanho.
 *  - Fail-closed: valor fora do contrato reprova o pacote inteiro em
 *    ThemePackage::validateConfig(); na resolucao, entrada invalida e ignorada.
 */
namespace Reamur\System\Library\Theme;

final class ThemeStrings {
    /** Nome da variavel entregue as fontes do tema. */
    public const GLOBAL = 'theme_text';
    /** Nome da variavel com o idioma efetivo (ex.: `pt-br`). */
    public const GLOBAL_LANGUAGE = 'theme_lang';
    /** Idioma de recuo: o mesmo padrao do Catalog. */
    public const FALLBACK = 'en-us';
    public const MAX_KEYS = 600;
    public const MAX_LANGUAGES = 12;
    /** Caracteres (nao bytes) por texto. */
    public const MAX_LENGTH = 2000;
    /** Teto do JSON inteiro no formulario do editor: metade do teto do pacote (ThemePackage::MAX_BYTES),
        para que um tema salvo sempre caiba no arquivo de exportacao junto com as fontes. */
    public const MAX_JSON_BYTES = 262144;
    private const KEY = '/^[a-z][a-z0-9_]{0,63}$/D';
    private const LANGUAGE = '/^[a-z]{2}-[a-z]{2}$/D';

    public static function isKey(mixed $key): bool {
        return is_string($key) && preg_match(self::KEY, $key) === 1;
    }

    public static function isLanguage(mixed $code): bool {
        return is_string($code) && preg_match(self::LANGUAGE, $code) === 1;
    }

    /** Texto aceito: UTF-8 valido, sem caracteres de controle (so `\n`), dentro do limite. */
    public static function isText(mixed $text): bool {
        if (!is_string($text) || $text === '' || preg_match('//u', $text) !== 1) return false;
        if (preg_match('/[\x00-\x09\x0B-\x1F\x7F]/', $text) === 1) return false;
        return self::length($text) <= self::MAX_LENGTH;
    }

    /** @return list<string> Codigos de erro estaveis; [] = valido. */
    public static function validate(mixed $strings): array {
        if (!is_array($strings)) return ['strings_type'];
        if (count($strings) > self::MAX_KEYS) return ['strings_count'];
        foreach ($strings as $key => $byLanguage) {
            if (!self::isKey($key)) return ['strings_key'];
            if (!is_array($byLanguage) || $byLanguage === [] || count($byLanguage) > self::MAX_LANGUAGES) return ['strings_languages'];
            foreach ($byLanguage as $code => $text) {
                if (!self::isLanguage($code)) return ['strings_language'];
                if (!self::isText($text)) return ['strings_text'];
            }
        }
        return [];
    }

    /**
     * Forma canonica (chaves e idiomas em ordem) ou null quando invalido.
     * @return array<string, array<string, string>>|null
     */
    public static function normalize(mixed $strings): ?array {
        if (self::validate($strings) !== []) return null;
        $out = [];
        foreach ($strings as $key => $byLanguage) {
            $row = [];
            foreach ($byLanguage as $code => $text) $row[(string)$code] = (string)$text;
            ksort($row, SORT_STRING);
            $out[(string)$key] = $row;
        }
        ksort($out, SORT_STRING);
        return $out;
    }

    /**
     * Mapa plano chave => texto para UM idioma, com recuo.
     * Entrada invalida (linha ou valor) e ignorada: nunca lanca.
     * @return array<string, string>
     */
    public static function resolve(mixed $strings, string $language, string $fallback = self::FALLBACK): array {
        if (!is_array($strings)) return [];
        $out = [];
        foreach ($strings as $key => $byLanguage) {
            if (count($out) >= self::MAX_KEYS) break;
            if (!self::isKey($key) || !is_array($byLanguage)) continue;
            $text = null;
            foreach ([$language, $fallback] as $code) {
                if (isset($byLanguage[$code]) && self::isText($byLanguage[$code])) { $text = $byLanguage[$code]; break; }
            }
            if ($text === null) {
                foreach ($byLanguage as $code => $candidate) {
                    if (self::isLanguage($code) && self::isText($candidate)) { $text = $candidate; break; }
                }
            }
            if ($text !== null) $out[$key] = $text;
        }
        return $out;
    }

    /**
     * Chaves sem texto em algum dos idiomas pedidos (para o painel avisar).
     * @param list<string> $languages
     * @return array<string, list<string>> idioma => chaves que faltam
     */
    public static function missing(mixed $strings, array $languages): array {
        $out = [];
        foreach ($languages as $code) {
            if (!self::isLanguage($code)) continue;
            $out[$code] = [];
            if (!is_array($strings)) continue;
            foreach ($strings as $key => $byLanguage) {
                if (!self::isKey($key) || !is_array($byLanguage)) continue;
                if (!isset($byLanguage[$code]) || !self::isText($byLanguage[$code])) $out[$code][] = $key;
            }
        }
        return $out;
    }

    /** @return array{max_keys:int,max_languages:int,max_length:int,max_json_bytes:int,fallback:string,global:string} */
    public static function spec(): array {
        return ['max_keys' => self::MAX_KEYS, 'max_languages' => self::MAX_LANGUAGES, 'max_length' => self::MAX_LENGTH,
                'max_json_bytes' => self::MAX_JSON_BYTES,
                'fallback' => self::FALLBACK, 'global' => self::GLOBAL];
    }

    private static function length(string $text): int {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : (int)preg_match_all('/./us', $text);
    }
}
