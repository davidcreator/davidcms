<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * helper/utf8.php — operações de string multibyte (Guia-PHP8 §9: mbstring obrigatório).
 * Demais helpers (json, seo, slug...) entram junto com seus consumidores (Fase 2).
 * Carregue via: $this->load->helper('utf8')
 */

if (!extension_loaded('mbstring')) {
    throw new \Exception('ReamurCMS: extensão mbstring é obrigatória (Guia-PHP8 §9).');
}

function utf8_strlen(string $string): int {
    return mb_strlen($string, 'UTF-8');
}

function utf8_substr(string $string, int $offset, ?int $length = null): string {
    return mb_substr($string, $offset, $length, 'UTF-8');
}

function utf8_strpos(string $haystack, string $needle, int $offset = 0): int|false {
    return mb_strpos($haystack, $needle, $offset, 'UTF-8');
}

function utf8_strrpos(string $haystack, string $needle, int $offset = 0): int|false {
    return mb_strrpos($haystack, $needle, $offset, 'UTF-8');
}

function utf8_strtoupper(string $string): string {
    return mb_strtoupper($string, 'UTF-8');
}

function utf8_strtolower(string $string): string {
    return mb_strtolower($string, 'UTF-8');
}

/** Primeira letra maiúscula, resto intacto (mb-safe) */
function utf8_ucfirst(string $string): string {
    return utf8_strtoupper(utf8_substr($string, 0, 1)) . utf8_substr($string, 1);
}
