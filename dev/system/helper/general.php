<?php
// String
function rms_strlen(string $string) {
	return mb_strlen($string);
}

function rms_strpos(string $string, string $needle, int $offset = 0) {
	return mb_strpos($string, $needle, $offset);
}

function rms_strrpos(string $string, string $needle, int $offset = 0) {
	return mb_strrpos($string, $needle, $offset);
}

function rms_substr(string $string, int $offset, ?int $length = null) {
	return mb_substr($string, $offset, $length);
}

function rms_strtoupper(string $string) {
	return mb_strtoupper($string);
}

function rms_strtolower(string $string) {
	return mb_strtolower($string);
}

// Other
function rms_token(int $length = 32): string {
	return substr(bin2hex(random_bytes($length)), 0, $length);
}