<?php declare(strict_types=1);
namespace Reamur\System\Library\Language;

/**
 * Class Detect
 * @package Reamur\System\Library\Language
 */
class Detect {
    /** @var string */
    private string $default_language = 'en-gb';

    /** @var array<int, string> */
    private array $supported_languages = ['en-gb', 'pt-br'];

    /**
     * Get language based on browser settings
     *
     * @param array<int, string> $available_languages Available language codes
     * @param string|null        $acceptLanguage      Optional Accept-Language header override
     * @return string Language code
     */
    public function getLanguage(array $available_languages = [], ?string $acceptLanguage = null): string {
        $available_languages = $available_languages ?: $this->supported_languages;
        $header = $acceptLanguage ?? (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');

        if (trim($header) === '') {
            return $this->default_language;
        }

        $browser_languages = explode(',', $header);
        foreach ($browser_languages as $browser_language) {
            $parts = explode(';', $browser_language);
            $lang = strtolower(trim($parts[0]));
            if ($lang === '') {
                continue;
            }

            $lang_parts = explode('-', $lang);
            $primary_lang = $lang_parts[0];
            if ($primary_lang === '') {
                continue;
            }

            if ($primary_lang === 'pt' && isset($lang_parts[1]) && $lang_parts[1] === 'br') {
                if (in_array('pt-br', $available_languages, true)) {
                    return 'pt-br';
                }
            }

            if (in_array($lang, $available_languages, true)) {
                return $lang;
            }

            foreach ($available_languages as $available_language) {
                if (str_starts_with(strtolower($available_language), $primary_lang)) {
                    return $available_language;
                }
            }
        }
        return $this->default_language;
    }

    public function setDefault(string $language): void {
        if ($language !== '') {
            $this->default_language = $language;
        }
    }

    /** @param array<int, string> $languages */
    public function setSupportedLanguages(array $languages): void {
        if ($languages !== []) {
            $this->supported_languages = $languages;
        }
    }
}
