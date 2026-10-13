<?php
namespace Reamur\System\Library\Language;

/**
 * Class Detect
 * @package Reamur\System\Library\Language
 */
class Detect {
    /** @var string */
    private string $default_language = 'en-gb';

    /** @var array */
    private array $supported_languages = ['en-gb', 'pt-br'];

    /**
     * Get language based on browser settings
     *
     * @param array $available_languages Available language codes
     * @return string Language code
     */
    public function getLanguage(array $available_languages = []): string {
        $available_languages = $available_languages ?: $this->supported_languages;

        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return $this->default_language;
        }

        $browser_languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        foreach ($browser_languages as $browser_language) {
            $parts = explode(';', $browser_language);
            $lang = strtolower(trim($parts[0]));
            $lang_parts = explode('-', $lang);
            $primary_lang = $lang_parts[0];

            // Special handling for Brazilian Portuguese
            if ($primary_lang === 'pt' && isset($lang_parts[1]) && $lang_parts[1] === 'br') {
                if (in_array('pt-br', $available_languages, true)) {
                    return 'pt-br';
                }
            }

            // Check for exact match
            if (in_array($lang, $available_languages, true)) {
                return $lang;
            }

            // Check for primary language match
            foreach ($available_languages as $available_language) {
                if (strpos($available_language, $primary_lang) === 0) {
                    return $available_language;
                }
            }
        }
        return $this->default_language;
    }

    /**
     * Set default language
     *
     * @param string $language
     * @return void
     */
    public function setDefault(string $language): void {
        if (!empty($language)) {
            $this->default_language = $language;
        }
    }

    /**
     * Set supported languages
     *
     * @param array $languages
     * @return void
     */
    public function setSupportedLanguages(array $languages): void {
        if (!empty($languages)) {
            $this->supported_languages = $languages;
        }
    }
}