<?php
ini_set('error_reporting', E_ERROR);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('output_buffering', 0);

require_once("api_values.php");
require_once("config.php");

define("def", 1);
define("api_version", 8);
define("api_version_min", 2);

use danog\MadelineProto\Magic;
use function Amp\async;
use function Amp\Future\await;
use danog\MadelineProto\Tools;

// Setup error handler
function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');


function json($json, $headers=true) {
	if (defined("json")) return;
	global $PARAMS;
	$c = JSON_UNESCAPED_SLASHES | (isset($_SERVER['HTTP_X_MPGRAM_UNICODE']) || isset($PARAMS['utf']) ? JSON_UNESCAPED_UNICODE : 0);
	if (isset($PARAMS['pretty'])) {
		$c |= JSON_PRETTY_PRINT;
	}
	if ($headers) {
		$time = time();
		$sv = api_version;
		header("X-Server-Time: {$time}");
		header("X-Server-Api-Version: {$sv}");
		if (defined('FILE_REWRITE') && FILE_REWRITE) header("X-file-rewrite-supported: 1");
		header("Content-Type: application/json");
	}
	echo json_encode($json, $c);
	define("json", 1);
}

function removeEmoji($text) {
	if ($text === null) return null;
	if (isset($_SERVER['HTTP_X_MPGRAM_KEEP_EMOJI'])) return $text;
	return preg_replace('/\x{1F3F4}\x{E0067}\x{E0062}(?:\x{E0077}\x{E006C}\x{E0073}|\x{E0073}\x{E0063}\x{E0074}|\x{E0065}\x{E006E}\x{E0067})\x{E007F}|(?:\x{1F9D1}\x{1F3FF}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F9D1}|\x{1F469}\x{1F3FF}\x{200D}\x{1F91D}\x{200D}[\x{1F468}\x{1F469}]|\x{1FAF1}\x{1F3FF}\x{200D}\x{1FAF2})[\x{1F3FB}-\x{1F3FE}]|(?:\x{1F9D1}\x{1F3FE}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F9D1}|\x{1F469}\x{1F3FE}\x{200D}\x{1F91D}\x{200D}[\x{1F468}\x{1F469}]|\x{1FAF1}\x{1F3FE}\x{200D}\x{1FAF2})[\x{1F3FB}-\x{1F3FD}\x{1F3FF}]|(?:\x{1F9D1}\x{1F3FD}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F9D1}|\x{1F469}\x{1F3FD}\x{200D}\x{1F91D}\x{200D}[\x{1F468}\x{1F469}]|\x{1FAF1}\x{1F3FD}\x{200D}\x{1FAF2})[\x{1F3FB}\x{1F3FC}\x{1F3FE}\x{1F3FF}]|(?:\x{1F9D1}\x{1F3FC}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F9D1}|\x{1F469}\x{1F3FC}\x{200D}\x{1F91D}\x{200D}[\x{1F468}\x{1F469}]|\x{1FAF1}\x{1F3FC}\x{200D}\x{1FAF2})[\x{1F3FB}\x{1F3FD}-\x{1F3FF}]|(?:\x{1F9D1}\x{1F3FB}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F9D1}|\x{1F469}\x{1F3FB}\x{200D}\x{1F91D}\x{200D}[\x{1F468}\x{1F469}]|\x{1FAF1}\x{1F3FB}\x{200D}\x{1FAF2})[\x{1F3FC}-\x{1F3FF}]|\x{1F468}(?:\x{1F3FB}(?:\x{200D}(?:\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FF}]|\x{1F468}[\x{1F3FB}-\x{1F3FF}])|\x{200D}(?:\x{1F48B}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FF}]|\x{1F468}[\x{1F3FB}-\x{1F3FF}]))|\x{1F91D}\x{200D}\x{1F468}[\x{1F3FC}-\x{1F3FF}]|[\x{2695}\x{2696}\x{2708}]\x{FE0F}|[\x{2695}\x{2696}\x{2708}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]))?|[\x{1F3FC}-\x{1F3FF}]\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FF}]|\x{1F468}[\x{1F3FB}-\x{1F3FF}])|\x{200D}(?:\x{1F48B}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FF}]|\x{1F468}[\x{1F3FB}-\x{1F3FF}]))|\x{200D}(?:\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F468}|[\x{1F468}\x{1F469}]\x{200D}(?:\x{1F466}\x{200D}\x{1F466}|\x{1F467}\x{200D}[\x{1F466}\x{1F467}])|\x{1F466}\x{200D}\x{1F466}|\x{1F467}\x{200D}[\x{1F466}\x{1F467}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FF}\x{200D}(?:\x{1F91D}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FE}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FE}\x{200D}(?:\x{1F91D}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FD}\x{1F3FF}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FD}\x{200D}(?:\x{1F91D}\x{200D}\x{1F468}[\x{1F3FB}\x{1F3FC}\x{1F3FE}\x{1F3FF}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FC}\x{200D}(?:\x{1F91D}\x{200D}\x{1F468}[\x{1F3FB}\x{1F3FD}-\x{1F3FF}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|(?:\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{200D}[\x{2695}\x{2696}\x{2708}])\x{FE0F}|\x{200D}(?:[\x{1F468}\x{1F469}]\x{200D}[\x{1F466}\x{1F467}]|[\x{1F466}\x{1F467}])|\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FF}|\x{1F3FE}|\x{1F3FD}|\x{1F3FC}|\x{200D}[\x{2695}\x{2696}\x{2708}])?|(?:\x{1F469}(?:\x{1F3FB}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}])|\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}]))|[\x{1F3FC}-\x{1F3FF}]\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}])|\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}])))|\x{1F9D1}[\x{1F3FB}-\x{1F3FF}]\x{200D}\x{1F91D}\x{200D}\x{1F9D1})[\x{1F3FB}-\x{1F3FF}]|\x{1F469}\x{200D}\x{1F469}\x{200D}(?:\x{1F466}\x{200D}\x{1F466}|\x{1F467}\x{200D}[\x{1F466}\x{1F467}])|\x{1F469}(?:\x{200D}(?:\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}])|\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}]))|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FF}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FE}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FD}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FC}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FB}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F9D1}(?:\x{200D}(?:\x{1F91D}\x{200D}\x{1F9D1}|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FF}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FE}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FD}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FC}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FB}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F469}\x{200D}\x{1F466}\x{200D}\x{1F466}|\x{1F469}\x{200D}\x{1F469}\x{200D}[\x{1F466}\x{1F467}]|\x{1F469}\x{200D}\x{1F467}\x{200D}[\x{1F466}\x{1F467}]|(?:\x{1F441}\x{FE0F}?\x{200D}\x{1F5E8}|\x{1F9D1}(?:\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FB}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{200D}[\x{2695}\x{2696}\x{2708}])|\x{1F469}(?:\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FB}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{200D}[\x{2695}\x{2696}\x{2708}])|\x{1F636}\x{200D}\x{1F32B}|\x{1F3F3}\x{FE0F}?\x{200D}\x{26A7}|\x{1F43B}\x{200D}\x{2744}|(?:[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93D}\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}][\x{1F3FB}-\x{1F3FF}]|[\x{1F46F}\x{1F9DE}\x{1F9DF}])\x{200D}[\x{2640}\x{2642}]|[\x{26F9}\x{1F3CB}\x{1F3CC}\x{1F575}](?:[\x{FE0F}\x{1F3FB}-\x{1F3FF}]\x{200D}[\x{2640}\x{2642}]|\x{200D}[\x{2640}\x{2642}])|\x{1F3F4}\x{200D}\x{2620}|[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93C}-\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}]\x{200D}[\x{2640}\x{2642}]|[\xA9\xAE\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}\x{21AA}\x{231A}\x{231B}\x{2328}\x{23CF}\x{23ED}-\x{23EF}\x{23F1}\x{23F2}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}\x{25AB}\x{25B6}\x{25C0}\x{25FB}\x{25FC}\x{25FE}\x{2600}-\x{2604}\x{260E}\x{2611}\x{2614}\x{2615}\x{2618}\x{2620}\x{2622}\x{2623}\x{2626}\x{262A}\x{262E}\x{262F}\x{2638}-\x{263A}\x{2640}\x{2642}\x{2648}-\x{2653}\x{265F}\x{2660}\x{2663}\x{2665}\x{2666}\x{2668}\x{267B}\x{267E}\x{267F}\x{2692}\x{2694}-\x{2697}\x{2699}\x{269B}\x{269C}\x{26A0}\x{26A7}\x{26AA}\x{26B0}\x{26B1}\x{26BD}\x{26BE}\x{26C4}\x{26C8}\x{26CF}\x{26D1}\x{26D3}\x{26E9}\x{26F0}-\x{26F5}\x{26F7}\x{26F8}\x{26FA}\x{2702}\x{2708}\x{2709}\x{270F}\x{2712}\x{2714}\x{2716}\x{271D}\x{2721}\x{2733}\x{2734}\x{2744}\x{2747}\x{2763}\x{27A1}\x{2934}\x{2935}\x{2B05}-\x{2B07}\x{2B1B}\x{2B1C}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F004}\x{1F170}\x{1F171}\x{1F17E}\x{1F17F}\x{1F202}\x{1F237}\x{1F321}\x{1F324}-\x{1F32C}\x{1F336}\x{1F37D}\x{1F396}\x{1F397}\x{1F399}-\x{1F39B}\x{1F39E}\x{1F39F}\x{1F3CD}\x{1F3CE}\x{1F3D4}-\x{1F3DF}\x{1F3F5}\x{1F3F7}\x{1F43F}\x{1F4FD}\x{1F549}\x{1F54A}\x{1F56F}\x{1F570}\x{1F573}\x{1F576}-\x{1F579}\x{1F587}\x{1F58A}-\x{1F58D}\x{1F5A5}\x{1F5A8}\x{1F5B1}\x{1F5B2}\x{1F5BC}\x{1F5C2}-\x{1F5C4}\x{1F5D1}-\x{1F5D3}\x{1F5DC}-\x{1F5DE}\x{1F5E1}\x{1F5E3}\x{1F5E8}\x{1F5EF}\x{1F5F3}\x{1F5FA}\x{1F6CB}\x{1F6CD}-\x{1F6CF}\x{1F6E0}-\x{1F6E5}\x{1F6E9}\x{1F6F0}\x{1F6F3}])\x{FE0F}|\x{1F441}\x{FE0F}?\x{200D}\x{1F5E8}|\x{1F9D1}(?:\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FB}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{200D}[\x{2695}\x{2696}\x{2708}])|\x{1F469}(?:\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FB}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{200D}[\x{2695}\x{2696}\x{2708}])|\x{1F3F3}\x{FE0F}?\x{200D}\x{1F308}|\x{1F469}\x{200D}\x{1F467}|\x{1F469}\x{200D}\x{1F466}|\x{1F636}\x{200D}\x{1F32B}|\x{1F3F3}\x{FE0F}?\x{200D}\x{26A7}|\x{1F635}\x{200D}\x{1F4AB}|\x{1F62E}\x{200D}\x{1F4A8}|\x{1F415}\x{200D}\x{1F9BA}|\x{1FAF1}(?:\x{1F3FF}|\x{1F3FE}|\x{1F3FD}|\x{1F3FC}|\x{1F3FB})?|\x{1F9D1}(?:\x{1F3FF}|\x{1F3FE}|\x{1F3FD}|\x{1F3FC}|\x{1F3FB})?|\x{1F469}(?:\x{1F3FF}|\x{1F3FE}|\x{1F3FD}|\x{1F3FC}|\x{1F3FB})?|\x{1F43B}\x{200D}\x{2744}|(?:[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93D}\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}][\x{1F3FB}-\x{1F3FF}]|[\x{1F46F}\x{1F9DE}\x{1F9DF}])\x{200D}[\x{2640}\x{2642}]|[\x{26F9}\x{1F3CB}\x{1F3CC}\x{1F575}](?:[\x{FE0F}\x{1F3FB}-\x{1F3FF}]\x{200D}[\x{2640}\x{2642}]|\x{200D}[\x{2640}\x{2642}])|\x{1F3F4}\x{200D}\x{2620}|\x{1F1FD}\x{1F1F0}|\x{1F1F6}\x{1F1E6}|\x{1F1F4}\x{1F1F2}|\x{1F408}\x{200D}\x{2B1B}|\x{2764}(?:\x{FE0F}\x{200D}[\x{1F525}\x{1FA79}]|\x{200D}[\x{1F525}\x{1FA79}])|\x{1F441}\x{FE0F}?|\x{1F3F3}\x{FE0F}?|[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93C}-\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}]\x{200D}[\x{2640}\x{2642}]|\x{1F1FF}[\x{1F1E6}\x{1F1F2}\x{1F1FC}]|\x{1F1FE}[\x{1F1EA}\x{1F1F9}]|\x{1F1FC}[\x{1F1EB}\x{1F1F8}]|\x{1F1FB}[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1EE}\x{1F1F3}\x{1F1FA}]|\x{1F1FA}[\x{1F1E6}\x{1F1EC}\x{1F1F2}\x{1F1F3}\x{1F1F8}\x{1F1FE}\x{1F1FF}]|\x{1F1F9}[\x{1F1E6}\x{1F1E8}\x{1F1E9}\x{1F1EB}-\x{1F1ED}\x{1F1EF}-\x{1F1F4}\x{1F1F7}\x{1F1F9}\x{1F1FB}\x{1F1FC}\x{1F1FF}]|\x{1F1F8}[\x{1F1E6}-\x{1F1EA}\x{1F1EC}-\x{1F1F4}\x{1F1F7}-\x{1F1F9}\x{1F1FB}\x{1F1FD}-\x{1F1FF}]|\x{1F1F7}[\x{1F1EA}\x{1F1F4}\x{1F1F8}\x{1F1FA}\x{1F1FC}]|\x{1F1F5}[\x{1F1E6}\x{1F1EA}-\x{1F1ED}\x{1F1F0}-\x{1F1F3}\x{1F1F7}-\x{1F1F9}\x{1F1FC}\x{1F1FE}]|\x{1F1F3}[\x{1F1E6}\x{1F1E8}\x{1F1EA}-\x{1F1EC}\x{1F1EE}\x{1F1F1}\x{1F1F4}\x{1F1F5}\x{1F1F7}\x{1F1FA}\x{1F1FF}]|\x{1F1F2}[\x{1F1E6}\x{1F1E8}-\x{1F1ED}\x{1F1F0}-\x{1F1FF}]|\x{1F1F1}[\x{1F1E6}-\x{1F1E8}\x{1F1EE}\x{1F1F0}\x{1F1F7}-\x{1F1FB}\x{1F1FE}]|\x{1F1F0}[\x{1F1EA}\x{1F1EC}-\x{1F1EE}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F7}\x{1F1FC}\x{1F1FE}\x{1F1FF}]|\x{1F1EF}[\x{1F1EA}\x{1F1F2}\x{1F1F4}\x{1F1F5}]|\x{1F1EE}[\x{1F1E8}-\x{1F1EA}\x{1F1F1}-\x{1F1F4}\x{1F1F6}-\x{1F1F9}]|\x{1F1ED}[\x{1F1F0}\x{1F1F2}\x{1F1F3}\x{1F1F7}\x{1F1F9}\x{1F1FA}]|\x{1F1EC}[\x{1F1E6}\x{1F1E7}\x{1F1E9}-\x{1F1EE}\x{1F1F1}-\x{1F1F3}\x{1F1F5}-\x{1F1FA}\x{1F1FC}\x{1F1FE}]|\x{1F1EB}[\x{1F1EE}-\x{1F1F0}\x{1F1F2}\x{1F1F4}\x{1F1F7}]|\x{1F1EA}[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1ED}\x{1F1F7}-\x{1F1FA}]|\x{1F1E9}[\x{1F1EA}\x{1F1EC}\x{1F1EF}\x{1F1F0}\x{1F1F2}\x{1F1F4}\x{1F1FF}]|\x{1F1E8}[\x{1F1E6}\x{1F1E8}\x{1F1E9}\x{1F1EB}-\x{1F1EE}\x{1F1F0}-\x{1F1F5}\x{1F1F7}\x{1F1FA}-\x{1F1FF}]|\x{1F1E7}[\x{1F1E6}\x{1F1E7}\x{1F1E9}-\x{1F1EF}\x{1F1F1}-\x{1F1F4}\x{1F1F6}-\x{1F1F9}\x{1F1FB}\x{1F1FC}\x{1F1FE}\x{1F1FF}]|\x{1F1E6}[\x{1F1E8}-\x{1F1EC}\x{1F1EE}\x{1F1F1}\x{1F1F2}\x{1F1F4}\x{1F1F6}-\x{1F1FA}\x{1F1FC}\x{1F1FD}\x{1F1FF}]|[#\*0-9]\x{FE0F}?\x{20E3}|\x{1F93C}[\x{1F3FB}-\x{1F3FF}]|\x{2764}\x{FE0F}?|[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93D}\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}][\x{1F3FB}-\x{1F3FF}]|[\x{26F9}\x{1F3CB}\x{1F3CC}\x{1F575}][\x{FE0F}\x{1F3FB}-\x{1F3FF}]?|\x{1F3F4}|[\x{270A}\x{270B}\x{1F385}\x{1F3C2}\x{1F3C7}\x{1F442}\x{1F443}\x{1F446}-\x{1F450}\x{1F466}\x{1F467}\x{1F46B}-\x{1F46D}\x{1F472}\x{1F474}-\x{1F476}\x{1F478}\x{1F47C}\x{1F483}\x{1F485}\x{1F48F}\x{1F491}\x{1F4AA}\x{1F57A}\x{1F595}\x{1F596}\x{1F64C}\x{1F64F}\x{1F6C0}\x{1F6CC}\x{1F90C}\x{1F90F}\x{1F918}-\x{1F91F}\x{1F930}-\x{1F934}\x{1F936}\x{1F977}\x{1F9B5}\x{1F9B6}\x{1F9BB}\x{1F9D2}\x{1F9D3}\x{1F9D5}\x{1FAC3}-\x{1FAC5}\x{1FAF0}\x{1FAF2}-\x{1FAF6}][\x{1F3FB}-\x{1F3FF}]|[\x{261D}\x{270C}\x{270D}\x{1F574}\x{1F590}][\x{FE0F}\x{1F3FB}-\x{1F3FF}]|[\x{261D}\x{270A}-\x{270D}\x{1F385}\x{1F3C2}\x{1F3C7}\x{1F408}\x{1F415}\x{1F43B}\x{1F442}\x{1F443}\x{1F446}-\x{1F450}\x{1F466}\x{1F467}\x{1F46B}-\x{1F46D}\x{1F472}\x{1F474}-\x{1F476}\x{1F478}\x{1F47C}\x{1F483}\x{1F485}\x{1F48F}\x{1F491}\x{1F4AA}\x{1F574}\x{1F57A}\x{1F590}\x{1F595}\x{1F596}\x{1F62E}\x{1F635}\x{1F636}\x{1F64C}\x{1F64F}\x{1F6C0}\x{1F6CC}\x{1F90C}\x{1F90F}\x{1F918}-\x{1F91F}\x{1F930}-\x{1F934}\x{1F936}\x{1F93C}\x{1F977}\x{1F9B5}\x{1F9B6}\x{1F9BB}\x{1F9D2}\x{1F9D3}\x{1F9D5}\x{1FAC3}-\x{1FAC5}\x{1FAF0}\x{1FAF2}-\x{1FAF6}]|[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93D}\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}]|[\x{1F46F}\x{1F9DE}\x{1F9DF}]|[\xA9\xAE\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}\x{21AA}\x{231A}\x{231B}\x{2328}\x{23CF}\x{23ED}-\x{23EF}\x{23F1}\x{23F2}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}\x{25AB}\x{25B6}\x{25C0}\x{25FB}\x{25FC}\x{25FE}\x{2600}-\x{2604}\x{260E}\x{2611}\x{2614}\x{2615}\x{2618}\x{2620}\x{2622}\x{2623}\x{2626}\x{262A}\x{262E}\x{262F}\x{2638}-\x{263A}\x{2640}\x{2642}\x{2648}-\x{2653}\x{265F}\x{2660}\x{2663}\x{2665}\x{2666}\x{2668}\x{267B}\x{267E}\x{267F}\x{2692}\x{2694}-\x{2697}\x{2699}\x{269B}\x{269C}\x{26A0}\x{26A7}\x{26AA}\x{26B0}\x{26B1}\x{26BD}\x{26BE}\x{26C4}\x{26C8}\x{26CF}\x{26D1}\x{26D3}\x{26E9}\x{26F0}-\x{26F5}\x{26F7}\x{26F8}\x{26FA}\x{2702}\x{2708}\x{2709}\x{270F}\x{2712}\x{2714}\x{2716}\x{271D}\x{2721}\x{2733}\x{2734}\x{2744}\x{2747}\x{2763}\x{27A1}\x{2934}\x{2935}\x{2B05}-\x{2B07}\x{2B1B}\x{2B1C}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F004}\x{1F170}\x{1F171}\x{1F17E}\x{1F17F}\x{1F202}\x{1F237}\x{1F321}\x{1F324}-\x{1F32C}\x{1F336}\x{1F37D}\x{1F396}\x{1F397}\x{1F399}-\x{1F39B}\x{1F39E}\x{1F39F}\x{1F3CD}\x{1F3CE}\x{1F3D4}-\x{1F3DF}\x{1F3F5}\x{1F3F7}\x{1F43F}\x{1F4FD}\x{1F549}\x{1F54A}\x{1F56F}\x{1F570}\x{1F573}\x{1F576}-\x{1F579}\x{1F587}\x{1F58A}-\x{1F58D}\x{1F5A5}\x{1F5A8}\x{1F5B1}\x{1F5B2}\x{1F5BC}\x{1F5C2}-\x{1F5C4}\x{1F5D1}-\x{1F5D3}\x{1F5DC}-\x{1F5DE}\x{1F5E1}\x{1F5E3}\x{1F5E8}\x{1F5EF}\x{1F5F3}\x{1F5FA}\x{1F6CB}\x{1F6CD}-\x{1F6CF}\x{1F6E0}-\x{1F6E5}\x{1F6E9}\x{1F6F0}\x{1F6F3}]|[\x{23E9}-\x{23EC}\x{23F0}\x{23F3}\x{25FD}\x{2693}\x{26A1}\x{26AB}\x{26C5}\x{26CE}\x{26D4}\x{26EA}\x{26FD}\x{2705}\x{2728}\x{274C}\x{274E}\x{2753}-\x{2755}\x{2757}\x{2795}-\x{2797}\x{27B0}\x{27BF}\x{2B50}\x{1F0CF}\x{1F18E}\x{1F191}-\x{1F19A}\x{1F201}\x{1F21A}\x{1F22F}\x{1F232}-\x{1F236}\x{1F238}-\x{1F23A}\x{1F250}\x{1F251}\x{1F300}-\x{1F320}\x{1F32D}-\x{1F335}\x{1F337}-\x{1F37C}\x{1F37E}-\x{1F384}\x{1F386}-\x{1F393}\x{1F3A0}-\x{1F3C1}\x{1F3C5}\x{1F3C6}\x{1F3C8}\x{1F3C9}\x{1F3CF}-\x{1F3D3}\x{1F3E0}-\x{1F3F0}\x{1F3F8}-\x{1F407}\x{1F409}-\x{1F414}\x{1F416}-\x{1F43A}\x{1F43C}-\x{1F43E}\x{1F440}\x{1F444}\x{1F445}\x{1F451}-\x{1F465}\x{1F46A}\x{1F479}-\x{1F47B}\x{1F47D}-\x{1F480}\x{1F484}\x{1F488}-\x{1F48E}\x{1F490}\x{1F492}-\x{1F4A9}\x{1F4AB}-\x{1F4FC}\x{1F4FF}-\x{1F53D}\x{1F54B}-\x{1F54E}\x{1F550}-\x{1F567}\x{1F5A4}\x{1F5FB}-\x{1F62D}\x{1F62F}-\x{1F634}\x{1F637}-\x{1F644}\x{1F648}-\x{1F64A}\x{1F680}-\x{1F6A2}\x{1F6A4}-\x{1F6B3}\x{1F6B7}-\x{1F6BF}\x{1F6C1}-\x{1F6C5}\x{1F6D0}-\x{1F6D2}\x{1F6D5}-\x{1F6D7}\x{1F6DD}-\x{1F6DF}\x{1F6EB}\x{1F6EC}\x{1F6F4}-\x{1F6FC}\x{1F7E0}-\x{1F7EB}\x{1F7F0}\x{1F90D}\x{1F90E}\x{1F910}-\x{1F917}\x{1F920}-\x{1F925}\x{1F927}-\x{1F92F}\x{1F93A}\x{1F93F}-\x{1F945}\x{1F947}-\x{1F976}\x{1F978}-\x{1F9B4}\x{1F9B7}\x{1F9BA}\x{1F9BC}-\x{1F9CC}\x{1F9D0}\x{1F9E0}-\x{1F9FF}\x{1FA70}-\x{1FA74}\x{1FA78}-\x{1FA7C}\x{1FA80}-\x{1FA86}\x{1FA90}-\x{1FAAC}\x{1FAB0}-\x{1FABA}\x{1FAC0}-\x{1FAC2}\x{1FAD0}-\x{1FAD9}\x{1FAE0}-\x{1FAE7}]/u', '', $text);
}

function error($error) {
	$obj['error'] = $error;
	json($obj);
	die();
}

function checkField($field, $def = def) {
	global $PARAMS;
	if (($field == 'users' || $field == 'chats') && isset($PARAMS['exclude_peers'])) {
		return false;
	}
	if (!isset($PARAMS['fields'])) {
		return $def;
	}
	return in_array($field, explode(',',$PARAMS['fields']));
}

function checkCount($count, $def=100) {
	global $PARAMS;
	if (!isset($PARAMS['count']) || empty($PARAMS['count'])) {
		return $count < $def;
	}
	return $count < (int) $PARAMS['count'];
}

function isParamEmpty($param) {
	global $PARAMS;
	return !isset($PARAMS[$param]) || empty($PARAMS[$param]);
}

function checkParamEmpty($param) {
	if (isParamEmpty($param)) {
		error(['message'=>"Required parameter '$param' is not set"]);
	}
}

function getParam($param, $def=false) {
	global $PARAMS;
	if (!isset($PARAMS[$param])) {
		if ($def === false) {
			error(['message'=>"Required parameter '$param' is not set"]);
		}
		return $def;
	}
	
	return $PARAMS[$param];
}

function addParamToArray(&$array, $param, $type = null) {
	global $PARAMS;
	if (!isset($PARAMS[$param])) {
		return;
	}
	$value = $PARAMS[$param];
	if ($type) {
		switch($type) {
		case 'int':
			if (!is_numeric($value)) {
				error(['message'=>"Given parameter '$param' is not integer"]);
			}
			$value = intval($value);
			break;
		case 'boolean':
			$value = strtolower($value);
			if ($value == 'true' || $value == '1') {
				$value = true;
			} elseif ($value == 'false' || $value == '0') {
				$value = false;
			} else {
				error(['message'=>"Given parameter '$param' is not boolean"]);
			}
			break;
		}
	}
	$array[$param] = $value;
}

function checkAuth() {
	if (!defined('user_checked')) {
		define('user_checked', 1);
	} else return;
	global $PARAMS;
	$user = $_SERVER['HTTP_X_MPGRAM_USER'] ?? $PARAMS['user'] ?? null;
	
	if ($user == null || empty($user)
		|| strlen($user) < 32 || strlen($user) > 200
		|| strpos($user, '\\') !== false
		|| strpos($user, '/') !== false
		|| strpos($user, '.') !== false
		|| strpos($user, ';') !== false
		|| strpos($user, ':') !== false
		|| strpos($user, ' ') !== false
		|| !file_exists(sessionspath.$user.'.madeline')) {
		http_response_code(401);
		error(['message'=>'Invalid authorization']);
	}
}

function setupMadelineProto($user=null) {
	global $MP;
	global $PARAMS;
	if ($MP != null) {
		return;
	}
	if (!$user) {
		checkAuth();
		$user = $_SERVER['HTTP_X_MPGRAM_USER'] ?? $PARAMS['user'];
	}
	require_once 'vendor/autoload.php';
	$sets = new \danog\MadelineProto\Settings;
	$app = new \danog\MadelineProto\Settings\AppInfo;
	$app->setApiId(api_id);
	$app->setApiHash(api_hash);
	$app->setShowPrompt(false);
	
	$app->setAppVersion($_SERVER['HTTP_X_MPGRAM_APP_VERSION'] ?? 'api');
	if (isset($_SERVER['HTTP_X_MPGRAM_DEVICE'])) {
		$app->setDeviceModel($_SERVER['HTTP_X_MPGRAM_DEVICE']);
	}
	if (isset($_SERVER['HTTP_X_MPGRAM_SYSTEM'])) {
		$app->setSystemVersion($_SERVER['HTTP_X_MPGRAM_SYSTEM']);
	}
	$sets->setAppInfo($app);
	try {
		$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline', $sets);
	} catch (Exception $e) {
		http_response_code(401);
		error(['message' => "Failed to load session", 'stack_trace' => strval($e)]);
	}
}

function getId($a) {
	if (is_int($a)) return $a;
	return $a['user_id'] ?? (isset($a['chat_id']) ? $a['chat_id'] : (isset($a['channel_id']) ? ($a['channel_id']) : null));
}

function getAllDialogs($limit = 0, $folder_id = -1) {
	global $MP;
	$p = ['limit' => 100, 'offset_date' => 0, 'offset_id' => 0, 'offset_peer' => ['_' => 'inputPeerEmpty'], 'count' => 0, 'hash' => 0];
	if ($folder_id != -1) {
		$p['folder_id'] = $folder_id;
	}
	$t = ['dialogs' => [0], 'count' => 1];
	$r = ['dialogs' => [], 'messages' => [], 'users' => [], 'chats' => []];
	$d = [];
	while ($p['count'] < $t['count']) {
		$t = $MP->messages->getDialogs($p);
		$r['users'] = array_merge($r['users'], $t['users']);
		$r['chats'] = array_merge($r['chats'], $t['chats']);
		$r['messages'] = array_merge($r['messages'], $t['messages']);
		$last_peer = 0;
		$last_date = 0;
		$last_id = 0;
		$t['messages'] = array_reverse($t['messages'] ?? []);
		foreach (array_reverse($t['dialogs'] ?? []) as $dialog) {
			$id = $dialog['peer'];
			if (!isset($d[$id])) {
				$d[$id] = $dialog;
				array_push($r['dialogs'], $dialog);
			}
			if (!$last_date) {
				if (!$last_peer) {
					$last_peer = $id;
				}
				if (!$last_id) {
					$last_id = $dialog['top_message'];
				}
				foreach ($t['messages'] as $message) {
					if ($message['_'] !== 'messageEmpty' && $message['peer_id'] === $last_peer && $last_id === $message['id']) {
						$last_date = $message['date'];
						break;
					}
				}
			}
		}
		if ($last_date) {
			$p['offset_date'] = $last_date;
			$p['offset_peer'] = $last_peer;
			$p['offset_id'] = $last_id;
			$p['count'] = count($d);
		} else {
			break;
		}
		if (!isset($t['count'])) {
			break;
		}
	}
	return $r;
}

function utflen($str) {
	return mb_strlen($str, 'utf-8');
}

function utfsubstr($s, $offset, $length = null) {
	$s = mb_convert_encoding($s, 'UTF-16');
	return mb_convert_encoding(substr($s, $offset << 1, $length === null ? null : ($length << 1)), 'UTF-8', 'UTF-16');
}

function findPeer($id, $r) {
	if ((int) $id < 0) {
		if (!isset($r['chats'])) return null;
		foreach ($r['chats'] as $u) {
			if ($u['id'] != $id) continue;
			return $u;
		}
	} else if (isset($r['users'])) {
		foreach ($r['users'] as $u) {
			if ($u['id'] != $id) continue;
			return $u;
		}
	}
	return null;
}

function parsePeer($peer) {
	return getId($peer);
}

function parseDialog($rawDialog) {
	global $v;
	$dialog = array();
	$dialog['id'] = strval(getId($rawDialog['peer']));
	if ($rawDialog['unread_count'] ?? 0 > 0) $dialog['unread'] = $rawDialog['unread_count'];
	if ($rawDialog['unread_mentions_count'] ?? 0 > 0) $dialog['mentions'] = $rawDialog['unread_mentions_count'];
	if ($rawDialog['pinned'] ?? false) $dialog['pin'] = true;
	return $dialog;
}

function parseUser($rawUser) {
	global $v;
	if (!$rawUser) {
		return false;
	}
	$user = array();
	$user['id'] = strval($rawUser['id']);
	$user[$v < 5 ? 'first_name' : 'fn'] = removeEmoji($rawUser['first_name'] ?? null);
	$user[$v < 5 ? 'last_name' : 'ln'] = removeEmoji($rawUser['last_name'] ?? null);
	if ((isset($rawUser['username']) && $rawUser['username'] !== null) || $v < 5) $user[$v < 5 ? 'username' : 'name'] = $rawUser['username'] ?? null;
	if ($v >= 5) {
		if (isset($rawUser['photo'])) $user['p'] = true;
		if ($rawUser['contact'] ?? false) $user['k'] = true;
		if ($rawUser['bot'] ?? false) $user['b'] = true;
		if (checkField('status') && isset($rawUser['status'])) {
			$user['s'] = $rawUser['status']['_'] == 'userStatusOnline';
			$user['w'] = $rawUser['status']['was_online'] ?? 0;
		}
	}
	return $user;
}

function parseChat($rawChat) {
	global $v;
	$chat = array();
	$chat['type'] = $rawChat['_'];
	$chat['id'] = strval($rawChat['id']);
	$chat[$v < 5 ? 'title' : 't'] = removeEmoji($rawChat['title'] ?? null);
	if ((isset($rawUser['username']) && $rawUser['username'] !== null) || $v < 5) $chat[$v < 5 ? 'username' : 'name'] = $rawChat['username'] ?? null;
	if ($v >= 5) {
		if (isset($rawChat['photo'])) $chat['p'] = true;
		if ($rawChat['broadcast'] ?? false) $chat['c'] = true;
		if ($rawChat['forum'] ?? false) $chat['f'] = true;
		if ($rawChat['left'] ?? false) $chat['l'] = true;
	}
	return $chat;
}

function getCaptchaText($length) {
	$c = '0123456789abcdefghjkmnopqrstuvwxyz.#*';
	$l = strlen($c);
	$s = '';
	for ($i = 0; $i < $length; $i++) {
		$s .= $c[rand(0, $l - 1)];
	}
	return $s;
}

function parseMessage($rawMessage, $media=false, $short=false) {
	global $v;
	$message = array();
	//$message['raw'] = $rawMessage;
	$message['id'] = $rawMessage['id'] ?? null;
	$message['date'] = $rawMessage['date'] ?? null;
	if (isset($rawMessage['message'])) {
		$t = $rawMessage['message'];
		if ($short) {
			if (utflen($t) > 150) {
				$t = trim(utfsubstr($t, 0, 150)).'..';
			}
		} else if ($v >= 5 && isset($rawMessage['entities']) && count($rawMessage['entities']) != 0) {
			$message['entities'] = $rawMessage['entities'];
		}
		$message['text'] = $t;
		
	}
	if (isset($rawMessage['out'])) $message['out'] = $rawMessage['out'];
	if (isset($rawMessage['peer_id'])) {
		$message['peer_id'] = parsePeer($rawMessage['peer_id']);
	}
	if (isset($rawMessage['from_id'])) {
		$message['from_id'] = parsePeer($rawMessage['from_id']);
	}
	if (isset($rawMessage['fwd_from'])) {
		$rawFwd = $rawMessage['fwd_from'];
		$fwd = array();
		if (isset($rawFwd['from_id'])) {
			$fwd['from_id'] = parsePeer($rawFwd['from_id']);
		}
		if (isset($rawFwd['date'])) {
			$fwd['date'] = $rawFwd['date'];
		}
		if (isset($rawFwd['saved_from_msg_id']) || isset($rawFwd['channel_post'])) {
			$fwd['msg'] = $rawFwd['saved_from_msg_id'] ?? $rawFwd['channel_post'];
		}
		if (isset($rawFwd['saved_from_peer'])) {
			$fwd['peer'] = $rawFwd['saved_from_peer'];
		}
		if (isset($rawFwd['from_name'])) {
			$fwd['from_name'] = removeEmoji($rawFwd['from_name']);
		}
		if (isset($rawFwd['saved_out'])) {
			$fwd['s'] = true;
		}
		$message['fwd'] = $fwd;
	}
	if (isset($rawMessage['media'])) {
		if (!$media) {
			// media is disabled
			$message['media'] = $v < 5 ? ['_' => 0] : null;
		} else {
			$rawMedia = $rawMessage['media'];
			$media = [];
			if (isset($rawMedia['photo'])) {
				$media['type'] = 'photo';
				$media['id'] = strval($rawMedia['photo']['id']);
				$media['date'] = $rawMedia['photo']['date'] ?? null;
			} elseif (isset($rawMedia['document'])) {
				$media['type'] = 'document';
				$media['id'] = strval($rawMedia['document']['id']);
				if (isset($rawMedia['document']['date'])) $media['date'] = $rawMedia['document']['date'];
				$media['size'] = $rawMedia['document']['size'] ?? null;
				$media['mime'] = $rawMedia['document']['mime_type'] ?? null;
				$media['thumb'] = isset($rawMedia['document']['thumbs']);
				if (isset($rawMedia['document']['attributes'])) {
					foreach ($rawMedia['document']['attributes'] as $attr) {
						if ($attr['_'] == 'documentAttributeFilename') {
							$media['name'] = $attr['file_name'];
						}
						if ($attr['_'] == 'documentAttributeAudio') {
							$audio = [];
							$audio['voice'] = $attr['voice'] ?? false;
							$audio['time'] = $attr['duration'] ?? false;
							if (isset($attr['title'])) {
								$audio['title'] = $attr['title'];
								if (isset($attr['performer'])) {
									$audio['artist'] = $attr['performer'];
								}
							}
							$media['audio'] = $audio;
						}
					}
				}
			} elseif (isset($rawMedia['webpage'])) {
				$media['type'] = 'webpage';
				$media['name'] = $rawMedia['webpage']['site_name'] ?? null;
				$media['url'] = $rawMedia['webpage']['url'] ?? null;
				$media['title'] = $rawMedia['webpage']['title'] ?? null;
			} elseif (isset($rawMedia['geo'])) {
				$media['type'] = 'geo';
				$media['lat'] = str_replace(',', '.', strval($rawMedia['geo']['lat'])) ?? null;
				$media['long'] = str_replace(',', '.', strval($rawMedia['geo']['long'])) ?? null;
			} elseif (isset($rawMedia['poll'])) {
				$media['type'] = 'poll';
				$media['voted'] = $media['results']['total_voters'] ?? 0;
			} else {
				// TODO
				$media['type'] = 'undefined';
				$media['_'] = $rawMedia['_'];
			}
			$message['media'] = $media;
		}
	}
	if (isset($rawMessage['action'])) {
		$rawAction = $rawMessage['action'];
		$action = ['_' => substr($rawAction['_'], 13)];
		if (isset($rawAction['user_id']) || isset($rawAction['users'])) {
			$action['user'] = $rawAction['user_id'] ?? $rawAction['users'][0] ?? null;
		}
		if (isset($rawAction['title'])) {
			$action['t'] = $rawAction['title'];
		}
		$message['act'] = $action;
	}
	if (isset($rawMessage['reply_to'])) {
		$rawReply = $rawMessage['reply_to'];
		$reply = [];
		$reply[$v < 5 ? 'msg' : 'id'] = $rawReply['reply_to_msg_id'] ?? null;
		if (isset($rawReply['reply_to_peer_id'])) $reply['peer'] = $rawReply['reply_to_peer_id'];
		if (isset($rawReply['quote_text'])) $reply['quote'] = $rawReply['quote_text'];
		if ($v >= 5 && !$short && isset($reply['id'])) {
			$rawReplyMsg = null;
			try {
				global $MP;
				$peer = $message['peer_id'];
				if ((int) $peer < 0) {
					$rawReplyMsg = $MP->channels->getMessages(['channel' => $peer, 'id' => [$reply['id']]]);
				} else {
					$rawReplyMsg = $MP->messages->getMessages(['peer' => $peer, 'id' => [$reply['id']]]);
				}
				if ($rawReplyMsg && isset($rawReplyMsg['messages']) && isset($rawReplyMsg['messages'][0])) {
					$reply['msg'] = parseMessage($rawReplyMsg['messages'][0], false, true);
				}
			} catch (Exception) {}
		}
		$message['reply'] = $reply;
	}
	if ($v >= 5) {
		if (isset($rawMessage['grouped_id'])) $message['group'] = $rawMessage['grouped_id'];
		
		if ($media && isset($rawMessage['reply_markup'])) {
			$rows = $rawMessage['reply_markup']['rows'] ?? [];
			$markup = [];
			foreach ($rows as $row) {
				$markupRow = [];
				foreach($row['buttons'] ?? [] as $button) {
					$r = ['text' => $button['text'] ?? ''];
					if (isset($button['data'])) {
						$r['data'] = urlencode(base64_encode($button['data']));
					} else if (isset($button['url'])) {
						$r['url'] = $button['url'];
					}
					array_push($markupRow, $r);
				}
				array_push($markup, $markupRow);
			}
			
			$message['markup'] = $markup;
		}
	}
	if ($v >= 7 && isset($rawMessage['edit_date']) && !($rawMessage['edit_hide'] ?? false)) {
		$message['edit'] = $rawMessage['edit_date'];
	}
	if ($v >= 8) {
		if ($rawMessage['silent'] ?? false) {
			$message['silent'] = true;
		}
		if ($rawMessage['replies']['comments'] ?? false) {
			$comments = [];
			$comments['count'] = $rawMessage['replies']['replies'] ?? 0;
			$comments['peer'] = $rawMessage['replies']['channel_id'];
			if (isset($rawMessage['replies']['read_max_id'])) {
				$comments['read'] = $rawMessage['replies']['read_max_id'];
			}
			$message['comments'] = $comments;
		}
	}
	//$message['raw'] = $rawMessage;
	return $message;
}

try {
	if (!defined('ENABLE_API') || !ENABLE_API) {
		error(['message' => "API is disabled"]);
	}
	if (defined('INSTANCE_PASSWORD') && INSTANCE_PASSWORD !== null) {
		$ipass = $_SERVER['HTTP_X_MPGRAM_INSTANCE_PASSWORD'] ?? null;
		if ($ipass != null) {
			if ($ipass != INSTANCE_PASSWORD) {
				http_response_code(403);
				error(['message' => "Wrong instance password"]);
			}
		} else {
			http_response_code(403);
			error(['message' => "Instance password is required"]);
		}
	}
	$MP = null;
	// Parameters
	$PARAMS = array();
	if (count($_GET) > 0) {
		$PARAMS = array_merge($PARAMS, $_GET);
	}
	if (count($_POST) > 0) {
		$PARAMS = array_merge($PARAMS, $_POST);
	}
	if (count($PARAMS) == 0) {
		error(['message' => "No parameters set"]);
	}
	if (isset($_COOKIE['user'])) {
		$PARAMS['user'] = $_COOKIE['user'];
	}
	if (!isset($PARAMS['method'])) {
		error(['message' => "No method set"]);
	}
	checkParamEmpty('v');
	$v = (int) $PARAMS['v'];
	if ($v < api_version_min || $v > api_version) {
		error(['message' => "Unsupported API version"]);
	}
	$METHOD = $PARAMS['method'];
	switch ($METHOD) {
	case 'getCaptchaImg':
		if (!defined('ENABLE_LOGIN_API') || !ENABLE_LOGIN_API) {
			http_response_code(403);
			error(['message' => "Login API is disabled"]);
		}
		checkParamEmpty('captcha_id');
		session_id('API'.$PARAMS['captcha_id']);
		session_start(['use_cookies' => '0']);
		if (empty($_SESSION['captcha_key'])) {
			error('Captcha id expired');
		}
		$c = getCaptchaText(rand(6, 10));
		$_SESSION['captcha_key'] = $c;
		session_write_close();
		$img = imagecreatetruecolor(150, 50);
		imagefill($img, 0, 0, -1);
		imagestring($img, rand(4, 10), rand(0, 50), rand(0, 25), $c, 0x000000);
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header('Content-type: image/png');
		imagepng($img);
		imagedestroy($img);
		break;
	case 'initLogin':
		if (!isParamEmpty('user')) {
			error(['message' => 'Authorized']);
		}
	case 'phoneLogin':
		if ($v != api_version) {
			http_response_code(403);
			error(['message' => "Unsupported API version"]);
		}
		if (!defined('ENABLE_LOGIN_API') || !ENABLE_LOGIN_API) {
			http_response_code(403);
			error(['message' => "Login API is disabled"]);
		}
		if (!isset($PARAMS['captcha_id']) || !isset($PARAMS['captcha_key'])) {
			$id = md5(random_bytes(32));
			session_id('API'.$id);
			session_start(['use_cookies' => '0']);
			$c = getCaptchaText(rand(6, 10));
			$_SESSION['captcha_key'] = $c; 
			json(['res' => 'need_captcha', 'captcha_id' => $id]);
			session_write_close();
			die();
		}
		checkParamEmpty('phone');
		session_id('API'.$PARAMS['captcha_id']);
		session_start(['use_cookies' => '0']);
		if (!isset($_SESSION['captcha_key']) || empty($_SESSION['captcha_key'])) {
			unset($_SESSION['captcha_key']);
			$c = getCaptchaText(rand(6, 10));
			$_SESSION['captcha_key'] = $c; 
			json(['res' => 'captcha_expired', 'captcha_id' => $id]);
			session_write_close();
			die();
		}
		if (strtolower($PARAMS['captcha_key']) != $_SESSION['captcha_key']) {
			unset($_SESSION['captcha_key']);
			$c = getCaptchaText(rand(6, 10));
			$_SESSION['captcha_key'] = $c; 
			json(['res' => 'wrong_captcha', 'captcha_id' => $id]);
			session_write_close();
			die();
		}
		unset($_SESSION['captcha_key']);
		session_write_close();
		
		$phone = getParam('phone');
		$user = $_SERVER['HTTP_X_MPGRAM_USER'] ?? $PARAMS['user'] ?? null;
		// generate user id
		if ($user === null) {
			$user = rtrim(strtr(base64_encode(hash('sha384', sha1(md5($phone.rand(0,1000).random_bytes(6))).random_bytes(30), true)), '+/', '-_'), '=');
		}
		setupMadelineProto($user);
		try {
			$a = $MP->phoneLogin($phone);
			json(['user' => $user, 'res' => 'code_sent', 'phone_code_hash' => $a['phone_code_hash'] ?? null]);
		} catch (Exception $e) {
			if (strpos($e->getMessage(), 'PHONE_NUMBER_INVALID') !== false) {
				json(['user' => $user, 'res' => 'phone_number_invalid']);
			} else {
				json(['user' => $user, 'res' => 'exception', 'message' => $e->getMessage()]);
			}
		}
		break;
	case 'resendCode':
		if ($v != api_version) {
			http_response_code(403);
			error(['message' => "Unsupported API version"]);
		}
		if (!defined('ENABLE_LOGIN_API') || !ENABLE_LOGIN_API) {
			http_response_code(403);
			error(['message' => "Login API is disabled"]);
		}
		checkParamEmpty('code');
		checkAuth();
		setupMadelineProto();
		
		$MP->auth->resendCode(['phone' => $phone, 'phone_code_hash' => $hash]);
		json(['res' => 1]);
		break;
	case 'completePhoneLogin':
		if ($v != api_version) {
			http_response_code(403);
			error(['message' => "Unsupported API version"]);
		}
		if (!defined('ENABLE_LOGIN_API') || !ENABLE_LOGIN_API) {
			http_response_code(403);
			error(['message' => "Login API is disabled"]);
		}
		checkParamEmpty('code');
		checkAuth();
		setupMadelineProto();
		try {
			$a = $MP->completePhoneLogin($PARAMS['code']);
			$hash = $a['phone_code_hash'] ?? null;
			if (isset($a['_']) && $a['_'] === 'account.noPassword') {
				json(['res' => 'no_password', 'phone_code_hash' => $hash]);
			} elseif (isset($a['_']) && $a['_'] === 'account.password') {
				json(['res' => 'password', 'phone_code_hash' => $hash]);
			} elseif (isset($a['_']) && $a['_'] === 'account.needSignup') {
				json(['res' => 'need_signup', 'phone_code_hash' => $hash]);
			} else {
				json(['res' => 1, 'phone_code_hash' => $hash]);
			}
		} catch (Exception $e) {
			if (strpos($e->getMessage(), 'PHONE_CODE_INVALID') !== false) {
				json(['res' => 'phone_code_invalid']);
			} elseif (strpos($e->getMessage(), 'PHONE_CODE_EXPIRED') !== false) {
				json(['res' => 'phone_code_expired']);
			} elseif (strpos($e->getMessage(), 'AUTH_RESTART') !== false) {
				json(['res' => 'auth_restart']);
			} else {
				error(['message' => $e->getMessage()]);
			}
		}
		break;
	case 'complete2faLogin':
		// TODO password encryption
		if ($v != api_version) {
			http_response_code(403);
			error(['message' => "Unsupported API version"]);
		}
		if (!defined('ENABLE_LOGIN_API') || !ENABLE_LOGIN_API) {
			http_response_code(403);
			error(['message' => "Login API is disabled"]);
		}
		checkParamEmpty('password');
		checkAuth();
		setupMadelineProto();
		try {
			$MP->complete2faLogin($PARAMS['password']);
			json(['res' => 1]);
		} catch(Exception $e) {
			if (strpos($e->getMessage(), 'PASSWORD_HASH_INVALID') !== false) {
				json(['res' => 'password_hash_invalid']);
			} else {
				error(['message' => $e->getMessage()]);
			}
		}	
		break;
	case 'getServerTimeOffset':
		$dtz = new DateTimeZone(date_default_timezone_get());
		$t = new DateTime('now', $dtz);
		$tof = $dtz->getOffset($t);
		json(['res' => $tof]);
		break;
	// Authorized methods
	case 'completeSignup':
		//checkParamEmpty('first_name');
		//checkParamEmpty('last_name');
		//checkAuth();
		//setupMadelineProto();
		//try {
		//	$MP->completeSignup($PARAMS['first_name'], $PARAMS['last_name']);
		//	json(['result' => 1]);
		//} catch(Exception $e) {
		//	json(['result' => 'exception', 'message' => $e->getMessage()]);
		//}
		json(['res' => 0]);
		break;
	case 'checkAuth':
		checkAuth();
		setupMadelineProto();
		json(['res' => '1']);
		break;
	case 'getDialogs':
		checkAuth();
		setupMadelineProto();
		
		function cmp($a, $b) {
			global $rawData;
			$ma = $a['message'] ?? null;
			$mb = $b['message'] ?? null;
			if ($ma == null || $mb == null) {
				foreach ($rawData['messages'] as $m) {
					if (parsePeer($m['peer_id']) == ($a['id'] ?? $a['peer'])) {
						$ma = $m;
					}
					if (parsePeer($m['peer_id']) == ($b['id'] ?? $b['peer'])) {
						$mb = $m;
					}
					if ($ma !== null && $mb !== null) break;
				}
			}
			if ($ma === null || $mb === null || $ma['date'] == $mb['date']) {
				return 0;
			}
			if (($a['pinned'] ?? false) && !($b['pinned'] ?? false)) {
				return -1;
			}
			return ($ma['date'] > $mb['date']) ? -1 : 1;
		}
		
		$f = $v < 5 ? null : getParam('f', null);
		$rawData = null;
		
		$p = [];
		addParamToArray($p, 'offset_id', 'int');
		addParamToArray($p, 'offset_date', 'int');
		addParamToArray($p, 'offset_peer');
		addParamToArray($p, 'limit', 'int');
		$sort = $v < 5 || $f === null;
		if ($f !== null) {
			if ((int) $f == 1) {
				$p['folder_id'] = 1;
				$rawData = $MP->messages->getDialogs($p);
			} else if ((int) $f > 1) {
				$sort = false;
				$fid = (int) $f;
				$folders = $MP->messages->getDialogFilters();
				if (($folders['_'] ?? '') == 'messages.dialogFilters')
					$folders = $folders['filters'];
				$folder = null;
				foreach ($folders as $f) {
					if (!isset($f['id']) || $f['id'] != $fid) continue;
					$folder = $f;
					break;
				}
				if ($folder == null) {
					error(['message' => 'Folder not found']);
				}
				unset($folders);
				$rawData = getAllDialogs();
				$dialogs = [];
				$all = $rawData['dialogs'];
				foreach ($rawData['messages'] as $m) {
					foreach ($all as $k => $d) {
						if ($m['peer_id'] != $d['peer']) continue;
						$all[$k]['message'] = $m;
						break;
					}
				}
				if ($f['contacts'] || $f['non_contacts']) {
					$contacts = $MP->contacts->getContacts()['contacts'];
					foreach ($all as $d) {
						if ($d['peer'] < 0) continue;
						$found = false;
						foreach ($contacts as $c) {
							if ($d['peer'] != getId($c)) continue;
							$found = true;
							if ($f['contacts']) array_push($dialogs, $d);
							break;
						}
						if ($found || $f['non_contacts']) continue;
						if (!in_array($d, $dialogs)) array_push($dialogs, $d);
					}
					unset($contacts);
				}
				if ($f['groups']) {
					foreach ($all as $d) {
						$peer = $d['peer'];
						if ($peer > 0) continue;
						foreach ($rawData['chats'] as $c) {
							if ($c['id'] != $peer) continue;
							if (!($c['broadcast'] ?? false) && !in_array($d, $dialogs))
								array_push($dialogs, $d);
							break;
						}
					}
				}
				if ($f['broadcasts']) {
					foreach ($all as $d) {
						$peer = $d['peer'];
						if ($peer > 0) continue;
						foreach ($rawData['chats'] as $c) {
							if ($c['id'] != $peer) continue;
							if (($c['broadcast'] ?? false) && !in_array($d, $dialogs))
								array_push($dialogs, $d);
							break;
						}
					}
				}
				if ($f['bots']) {
					foreach ($all as $d) {
						$peer = $d['peer'];
						if ($peer < 0) continue;
						foreach ($rawData['users'] as $u) {
							if ($u['id'] != $peer) continue;
							if (($u['bot'] ?? false) && !in_array($d, $dialogs))
								array_push($dialogs, $d);
							break;
						}
						continue;
					}
				}
				if (count($f['exclude_peers']) > 0) {
					foreach ($f['exclude_peers'] as $p) {
						$p = getId($p);
						foreach ($dialogs as $idx => $d) {
							if ($d['peer'] != $p) continue;
							unset($dialogs[$idx]);
							break;
						}
					}
				}
				if ($f['exclude_archived']) {
					foreach ($dialogs as $idx => $d) {
						if (!isset($d['folder_id']) || $d['folder_id'] != 1) continue;
						unset($dialogs[$idx]);
					}
				}
				if ($f['exclude_read']) {
					foreach ($dialogs as $idx => $d) {
						if (!isset($d['unread_count']) || $d['unread_count'] > 0) continue;
						unset($dialogs[$idx]);
					}
				}
				if (count($f['include_peers']) > 0) {
					foreach ($f['include_peers'] as $p) {
						$p = getId($p);
						foreach ($all as $d) {
							if ($d['peer'] != $p) continue;
							if (!in_array($d, $dialogs)) array_push($dialogs, $d);
							break;
						}
					}
				}
				usort($dialogs, 'cmp');
				if (count($f['pinned_peers']) > 0) {
					$pinned = array();
					foreach ($f['pinned_peers'] as $p) {
						$p = getId($p);
						foreach ($all as $d) {
							if ($d['peer'] != $p) continue;
							if (in_array($d, $dialogs)) {
								unset($dialogs[array_search($d, $dialogs)]);
							}
							array_push($pinned, $d);
							break;
						}
					}
					$dialogs = array_merge($pinned, $dialogs);
					unset($pinned);
				}
				$rawData['dialogs'] = $dialogs;
				unset($all);
			} else {
				$p['folder_id'] = (int) $f;
				$rawData = $MP->messages->getDialogs($p);
			}
		} else {
			$rawData = $MP->messages->getDialogs($p);
		}
		$res = array();
		if (checkField('raw') === true) {
			$res['raw'] = $rawData;
		}
		if (isset($rawData['count'])) $res['count'] = $rawData['count'];
		$dialogPeers = array();
		$senderPeers = array();
		$mesages = array();
		if (checkField('dialogs', true)) {
			foreach ($rawData['messages'] as $rawMessage) {
				$message = parseMessage($rawMessage, $PARAMS['media'] ?? false, $v < 5 ? false : ($PARAMS['text'] ?? true));
				$messages[strval($message['peer_id'])] = $message;
			}
			$res['dialogs'] = array();
			foreach ($rawData['dialogs'] as $rawDialog) {
				$dialog = parseDialog($rawDialog);
				array_push($res['dialogs'], $dialog);
			}
			if ($sort) usort($res['dialogs'], 'cmp');
			for ($i = count($rawData['dialogs'])-1; $i >= 0; $i--) {
				if (!checkCount($i)) {
					unset($res['dialogs'][$i]);
				} else {
					$id = $res['dialogs'][$i]['id'];
					array_push($dialogPeers, $id);
					if (!isset($messages[$id]) || !isset($messages[$id]['from_id'])) {
						continue;
					}
					$fid = strval($messages[$id]['from_id']);
					if ($fid == $id) {
						continue;
					}
					array_push($senderPeers, $fid);
				}
			}
		}
		if (checkField('users', true)) {
			$res['users'] = array();
			$res['users']['0'] = 0;
			foreach ($rawData['users'] as $rawUser) {
				$id = strval($rawUser['id']);
				if (isset($res['users'][$id])
					|| (count($dialogPeers) != 0 && !in_array($id, $dialogPeers) && !in_array($id, $senderPeers)))
					continue;
				$res['users'][$id] = parseUser($rawUser);
			}
		}
		if (checkField('chats', true)) {
			$res['chats'] = array();
			$res['chats']['0'] = 0;
			foreach ($rawData['chats'] as $rawChat) {
				$id = strval($rawChat['id']);
				if (isset($res['chats'][$id])
					|| (count($dialogPeers) != 0 && !in_array($id, $dialogPeers) && !in_array($id, $senderPeers)))
					continue;
				$res['chats'][$id] = parseChat($rawChat);
			}
		}
		if (checkField('messages', true)) {
			if ($v < 5) {
				$res['messages'] = array();
				$res['messages']['0'] = 0;
				foreach ($messages as $message) {
					$id = $message['peer_id'];
					if (count($dialogPeers) != 0 && !in_array($id, $dialogPeers)) continue;
					$res['messages'][$id] = $message;
				}
			} else {
				$l = count($res['dialogs']);
				foreach ($messages as $message) {
					$id = $message['peer_id'];
					if (count($dialogPeers) != 0 && !in_array($id, $dialogPeers)) continue;
					$idx = null;
					for ($i = 0; $i < $l; $i++) {
						if ($res['dialogs'][$i]['id'] != strval($id)) continue;
						$idx = $i;
						break;
					}
					if ($idx === null) continue;
					unset($message['peer_id']);
					$res['dialogs'][$idx]['msg'] = $message;
				}
			}
		}
		json($res);
		break;
	case 'getAllDialogs':
		checkAuth();
		setupMadelineProto();
		$rawData = getAllDialogs();
		$res = array();
		if (checkField('raw') === true) {
			$res['raw'] = $rawData;
		}
		$dialogPeers = array();
		$senderPeers = array();
		$mesages = array();
		if (checkField('dialogs')) {
			foreach ($rawData['messages'] as $rawMessage) {
				$message = parseMessage($rawMessage);
				$messages[strval($message['peer_id'])] = $message;
			}
			$res['dialogs'] = array();
			foreach ($rawData['dialogs'] as $rawDialog) {
				$dialog = parseDialog($rawDialog);
				array_push($res['dialogs'], $dialog);
			}
			function cmp($a, $b) {
				global $rawData;
				$ma = null;
				$mb = null;
				foreach ($rawData['messages'] as $m) {
					if (parsePeer($m['peer_id']) == $a['id']) {
						$ma = $m;
					}
					if (parsePeer($m['peer_id']) == $b['id']) {
						$mb = $m;
					}
					if ($ma !== null && $mb !== null) break;
				}
				if ($ma === null || $mb === null || $ma['date'] == $mb['date']) {
					return 0;
				}
				if ($a['pinned'] && !$b['pinned']) {
					return -1;
				}
				return ($ma['date'] > $mb['date']) ? -1 : 1;
			}
			usort($res['dialogs'], 'cmp');
			for ($i = count($rawData['dialogs'])-1; $i >= 0; $i--) {
				if (!checkCount($i)) {
					unset($res['dialogs'][$i]);
				} else {
					$id = $res['dialogs'][$i]['id'];
					array_push($dialogPeers, $id);
					if (!isset($messages[$id]) || !isset($messages[$id]['from_id'])) {
						continue;
					}
					$fid = strval($messages[$id]['from_id']);
					if ($fid == $id) {
						continue;
					}
					array_push($senderPeers, $fid);
				}
			}
		}
		if (checkField('users')) {
			$res['users'] = array();
			$res['users']['0'] = 0;
			foreach ($rawData['users'] as $rawUser) {
				$id = strval($rawUser['id']);
				if (isset($res['users'][$id])
					|| (count($dialogPeers) != 0 && !in_array($id, $dialogPeers) && !in_array($id, $senderPeers)))
					continue;
				$res['users'][$id] = parseUser($rawUser);
			}
		}
		if (checkField('chats')) {
			$res['chats'] = array();
			$res['chats']['0'] = 0;
			foreach ($rawData['chats'] as $rawChat) {
				$id = strval($rawChat['id']);
				if (isset($res['chats'][$id])
					|| (count($dialogPeers) != 0 && !in_array($id, $dialogPeers) && !in_array($id, $senderPeers)))
					continue;
				$res['chats'][$id] = parseChat($rawChat);
			}
		}
		if (checkField('messages', false) && $v < 5) {
			$res['messages'] = array();
			$res['messages']['0'] = 0;
			foreach ($messages as $message) {
				$id = $message['peer_id'];
				if (count($dialogPeers) != 0 && !in_array($id, $dialogPeers)) continue;
				$res['messages'][$id] = $message;
			}
		}
		json($res);
		break;
	case 'getHistory':
	case 'searchMessages':
	case 'getMessages':
		checkParamEmpty('peer');
		checkAuth();
		setupMadelineProto();
		$p = array();
		addParamToArray($p, 'peer');
		
		$peer = getParam('peer');
		$thread = getParam('top_msg_id', null);
		
		if ($METHOD == 'getMessages') {
			$p['id'] = explode(',', getParam('id'));
			$rawData = $MP->messages->getMessages($p);
		} else {
			addParamToArray($p, 'offset_id', 'int');
			addParamToArray($p, 'offset_date', 'int');
			addParamToArray($p, 'add_offset', 'int');
			addParamToArray($p, 'limit', 'int');
			addParamToArray($p, 'max_id', 'int');
			addParamToArray($p, 'min_id', 'int');
			addParamToArray($p, 'q');
			addParamToArray($p, 'top_msg_id');
			if (!isParamEmpty('filter')) {
				$p['filter'] = ['_' => 'inputMessagesFilter'.getParam('filter')];
			}
			$rawData = $METHOD == 'searchMessages' ? $MP->messages->search($p) : $MP->messages->getHistory($p);
		}
		try {
			if (!isParamEmpty('read') && isset($rawData['messages'][0])) {
				$maxid = $rawData['messages'][0]['id'];
				if ($thread != null) {
					$MP->messages->readDiscussion(['peer' => $peer, 'read_max_id' => $maxid, 'msg_id' => (int) $thread]);
					$MP->messages->readMentions(['peer' => $peer, 'top_msg_id' => (int) $thread]);
				} else if ((int) $peer < 0 && Magic::ZERO_CHANNEL_ID >= (int) $peer) {
					$MP->channels->readHistory(['channel' => $peer, 'max_id' => $maxid]);
					$MP->messages->readMentions(['peer' => $peer]);
				} else {
					$MP->messages->readHistory(['peer' => $peer, 'max_id' => $maxid]);
					$MP->messages->readMentions(['peer' => $peer]);
				}
			}
		} catch (Exception) {}
		$res = array();
		if (isset($rawData['count'])) $res['count'] = $rawData['count'];
		if (isset($rawData['offset_id_offset'])) $res['off'] = $rawData['offset_id_offset'];
		if (checkField('messages')) {
			$res['messages'] = array();
			foreach ($rawData['messages'] as $rawMessage) {
				array_push($res['messages'], parseMessage($rawMessage, $PARAMS['media'] ?? false));
			}
		}
		if (checkField('users')) {
			$res['users'] = array();
			$res['users']['0'] = 0;
			foreach ($rawData['users'] as $rawUser) {
				$id = strval($rawUser['id']);
				if (isset($res['users'][$id])) continue;
				$res['users'][$id] = parseUser($rawUser);
			}
		}
		if (checkField('chats')) {
			$res['chats'] = array();
			$res['chats']['0'] = 0;
			foreach ($rawData['chats'] as $rawChat) {
				$id = strval($rawChat['id']);
				if (isset($res['chats'][$id])) continue;
				$res['chats'][$id] = parseChat($rawChat);
			}
		}
		json($res);
		break;
	case 'getSelf':
	case 'me':
		checkAuth();
		setupMadelineProto();
		$r = $MP->getSelf();
		if (!$r) {
			http_response_code(401);
			error(['message' => 'Could not get user info']);
		}
		if (!isParamEmpty('status')) {
			try {
				$MP->account->updateStatus(['offline' => false]);
			} catch (Exception) {}
		}
		json(parseUser($r));
		break;
	case 'getPeer':
		checkParamEmpty('id');
		checkAuth();
		setupMadelineProto();
		$r = $MP->getInfo(getParam('id'));
		if (isset($r['User']) && (!isset($PARAMS['type']) || $PARAMS['type'] == 'user')) {
			json(parseUser($r['User']));
		} elseif (isset($r['Chat']) && (!isset($PARAMS['type']) || $PARAMS['type'] == 'chat')) {
			json(parseChat($r['Chat']));
		} else {
			error(['message'=>'']);
		}
		break;
	case 'getPeers':
		checkParamEmpty('id');
		checkAuth();
		setupMadelineProto();
		$users = ['0'=>0];
		$chats = ['0'=>0];
		foreach (explode(',', getParam('id')) as $id) {
			$id = (int) trim($id);
			if ($id == 0) error(['message'=>'Invalid id']);
			$r = $MP->getInfo($PARAMS['id']);
			if (isset($r['User'])) {
				$users[strval($id)] = parseUser($r['User']);
			} elseif (isset($r['Chat'])) {
				$chats[strval($id)] = parseChat($r['Chat']);
			}
		}
		json(['users' => $users, 'chats' => $chats]);
		break;
	case 'getLastUpdate':
		checkAuth();
		setupMadelineProto();
		
		$res = null;
		
		if (isset($PARAMS['peer']) && isset($PARAMS['id'])) {
			$peer = (int) getParam('peer');
			$id = (int) getParam('id');
			$updates = $MP->getUpdates(['timeout' => 1]);
			foreach ($updates as $update) {
				$type = $update['update']['_'];
				if ($type == 'updateNewMessage' || $type == 'updateNewChannelMessage') {
					$msg = $update['update']['message'];
					if ($msg['peer_id'] != $peer && ($msg['peer_id'] < 0 || $msg['from_id'] != $peer)) {
						continue;
					}
					if ($msg['id'] < $id) continue;
					if ($msg['id'] == $id) {
						$res = $update;
						$res['exact'] = true;
						break;
					}
					$res = $update;
				}
			}
			if ($res === null) $res = end($updates);
		} else {
			$res = $MP->getUpdates(['offset' => -1]);
			$res = end($res);
		}
		json(['res' => $res]);
		break;
	case 'updates':
		checkAuth();
		setupMadelineProto();
		$timeout = (int) getParam('timeout', '10');
		if ($timeout > 120) $timeout = 120;
		$offset = (int) getParam('offset');
		$peer = (int) getParam('peer', '0');
		$message = (int) getParam('message', '0'); 
		$types = isParamEmpty('types') ? false : explode(',', getParam('types'));
		$exclude = isParamEmpty('exclude') ? false : explode(',', getParam('exclude'));
		$limit = (int) getParam('limit', '100');
		$userPeer = $peer > 0;
		$chatPeer = $peer < 0;
		$media = !isParamEmpty('media');
		$autoread = !isParamEmpty('read');
		
		$time = microtime(true);
		$so = $offset;
		$i = $message;
		$maxmsg = 0;
		$res = array();
		$selfid = strval($MP->getSelf()['id']);
		
		http_response_code(200);
		header("X-Accel-Buffering: no");
		set_time_limit(0);
		ob_implicit_flush(true);
		
		try {
			while (true) {
				echo ' ';
				flush();
				if (connection_aborted() || microtime(true) - $time >= $timeout) break;
				$updates = $MP->getUpdates(['offset' => $offset, 'limit' => $limit, 'timeout' => 1]);
				foreach ($updates as $update) {
					if ($update['update_id'] == $so) continue;
					$type = $update['update']['_'];
					$offset = $update['update_id'] + 1;
					if ($types && !in_array($type, $types)) continue;
					if ($exclude && in_array($type, $exclude)) continue;
					if ($type == 'updateNewMessage' || $type == 'updateNewChannelMessage'
					|| $type == 'updateEditMessage' || $type == 'updateEditChannelMessage') {
						$msg = $update['update']['message'];
						if ($peer) {
							if ($userPeer) {
								if (($peer == $selfid && $msg['from_id'] != $peer)
									|| ($msg['peer_id'] != $peer &&
										($msg['out'] || $msg['peer_id'] != $selfid || $msg['from_id'] != $peer))
									|| ($type != 'updateNewMessage' && $type != 'updateEditMessage'))
									continue;
							} else if (($msg['peer_id'] != $peer)
								|| ($type != 'updateNewChannelMessage' && $type != 'updateEditChannelMessage')) {
								continue;
							}
							if ($msg['id'] < $i) continue;
							if ($msg['id'] == $i) continue;
							$maxmsg = $msg['id'];
						}
						$update['update']['message'] = parseMessage($msg, $media);
						array_push($res, $update);
					}
					if (($userPeer || $peer == 0) && ($type == 'updateUserStatus' || $type == 'updateUserTyping')) {
						if ($update['update']['user_id'] != $peer) continue;
						if (isset($update['update']['from_id'])) {
							$update['update']['from_id'] = parsePeer($update['update']['from_id']);
						}
						array_push($res, $update);
					}
					if ($chatPeer || $peer == 0) {
						if ($type == 'updateDeleteChannelMessages' || $type == 'updateChannelUserTyping') {
							if ($update['update']['channel_id'] != $peer) continue;
							if (isset($update['update']['from_id'])) {
								$update['update']['from_id'] = parsePeer($update['update']['from_id']);
							}
							array_push($res, $update);
						}
						if ($type == 'updateChatUserTyping') {
							if ($update['update']['chat_id'] != $peer) continue;
							if (isset($update['update']['from_id'])) {
								$update['update']['from_id'] = parsePeer($update['update']['from_id']);
							}
							array_push($res, $update);
						}
					}
					// TODO updateDeleteMessages
					
					if ($peer) continue;
					array_push($res, $update);
				}
				if ($res) break;
			}
			if (!$res) {
				echo '{"res":[]}';
			} else {
				if ($autoread && $maxmsg != 0) {
					try {
						if ($chatPeer && Magic::ZERO_CHANNEL_ID >= (int) $peer) {
							$MP->channels->readHistory(['channel' => $peer, 'max_id' => $maxmsg]);
						} else {
							$MP->messages->readHistory(['peer' => $peer, 'max_id' => $maxmsg]);
						}
					} catch (Exception) {}
				}
				$c = JSON_UNESCAPED_SLASHES | (isset($_SERVER['HTTP_X_MPGRAM_UNICODE']) || isset($PARAMS['utf']) ? JSON_UNESCAPED_UNICODE : 0);
				echo json_encode(['res'=>$res], $c);
			}
		} catch (Exception $e) {
			$c = JSON_UNESCAPED_SLASHES | (isset($_SERVER['HTTP_X_MPGRAM_UNICODE']) || isset($PARAMS['utf']) ? JSON_UNESCAPED_UNICODE : 0);
			echo json_encode(['error' => ['message' => 'Exception', 'stack_trace' =>strval($e)]], $c);
		}
		break;
	// v5
	case 'getFullInfo':
		checkParamEmpty('id');
		checkAuth();
		setupMadelineProto();
		$r = $MP->getFullInfo(getParam('id')) ?? null;
		if ($r) {
			json($r);
		} else {
			error(['message'=>'']);
		}
		break;
	case 'getFolders':
		checkAuth();
		setupMadelineProto();
		$folders = $MP->messages->getDialogFilters();
		if (($folders['_'] ?? '') == 'messages.dialogFilters')
			$folders = $folders['filters'];
		$hasArchiveChats = count($MP->messages->getDialogs([
			'limit' => 1, 
			'exclude_pinned' => true,
			'folder_id' => 1
			])['dialogs']) > 0;
		if (count($folders) == 0 && !$hasArchiveChats) {
			json(['res' => null]);
			break;
		}
		$res = ['archive' => $hasArchiveChats];
		if (count($folders) > 0) {
			$res['res'] = [];
			foreach ($folders as $f) {
				if (($f['_'] ?? '') == 'dialogFilterDefault' || !isset($f['id'])) {
					array_push($res['res'], ['id' => 0]);
				} else {
					array_push($res['res'], ['id' => $f['id'], 't' => $f['title']]);
				}
			}
		}
		json($res);
		break;
	case 'readMessages':
		checkAuth();
		setupMadelineProto();
		
		$id = getParam('peer');
		$maxid = (int) getParam('max');
		$thread = getParam('thread', null);
		
		if ($thread != null) {
			$MP->messages->readDiscussion(['peer' => $id, 'read_max_id' => $maxid, 'msg_id' => (int) $thread]);
			$MP->messages->readMentions(['peer' => $id, 'top_msg_id' => (int) $thread]);
		} else if ((int) $id < 0 && Magic::ZERO_CHANNEL_ID >= (int) $peer) {
			$MP->channels->readHistory(['channel' => $id, 'max_id' => $maxid]);
			$MP->messages->readMentions(['peer' => $id]);
		} else {
			$MP->messages->readHistory(['peer' => $id, 'max_id' => $maxid]);
			$MP->messages->readMentions(['peer' => $id]);
		}
		break;
	case 'startBot':
		checkAuth();
		setupMadelineProto();
		
		$id = getParam('id');
		$start = getParam('start', null);
		$random = getParam('random', null);
		$MP->messages->startBot(['start_param' => $start, 'bot' => $id, 'random_id' => $random]);
	
		json(['res' => 1]);
		break;
	case 'getContacts':
		checkAuth();
		setupMadelineProto();
		
		$rawData = $MP->contacts->getContacts();
		$res = [];
		foreach ($rawData['contacts'] as $contact) {
			array_push($res, parseUser(findPeer(getId($contact), $rawData)));
		}
		json(['res' => $res]);
		break;
	case 'joinChannel':
		checkAuth();
		setupMadelineProto();
		$MP->channels->joinChannel(['channel' => getParam('id')]);
		json(['res' => 1]);
		break;
	case 'leaveChannel':
		checkAuth();
		setupMadelineProto();
		$MP->channels->leaveChannel(['channel' => getParam('id')]);
		json(['res' => 1]);
		break;
	case 'checkChatInvite':
		checkAuth();
		setupMadelineProto();
		json(['res' => $MP->messages->checkChatInvite(hash: getParam('id'))]);
		break;
	case 'importChatInvite':
		checkAuth();
		setupMadelineProto();
		$MP->messages->importChatInvite(hash: getParam('id'));
		json(['res' => 1]);
		break;
	case 'deleteMessage':
		checkParamEmpty('peer');
		checkParamEmpty('id');
		checkAuth();
		setupMadelineProto();
		$peer = getParam('peer');
		$id = getParam('id');
		if (is_numeric($peer) && (int) $peer > 0) {
			$MP->messages->deleteMessages(['id' => [(int) $id]]);
		} else {
			$MP->channels->deleteMessages(['channel' => $peer, 'id' => [(int) $id]]);
		}
		json(['res' => '1']);
		break;
	case 'resolvePhone':
		checkAuth();
		setupMadelineProto();
		json(['res' => $MP->contacts->resolvePhone(phone: getParam('phone'))]);
		break;
	case 'getParticipants':
		checkAuth();
		setupMadelineProto();
		$p = ['channel' => getParam('peer'), 'filter' => ['_' => 'channelParticipants'.getParam('filter', 'Recent')]];
		addParamToArray($p, 'offset', 'int');
		addParamToArray($p, 'limit', 'int');
		$rawData = $MP->channels->getParticipants($p);
		$res = [];
		$res['raw'] = $rawData;
		$res['users'] = [];
		foreach ($rawData['participants'] as $p) {
			$r = parseUser(findPeer(getId($p), $rawData));
			if (isset($p['admin_rights'])) $r['a'] = true;
			array_push($res['users'], $r);
		}
		if (isset($rawData['count'])) $res['count'] = $rawData['count'];
		json(['res' => $res]);
		break;
	case 'setTyping':
		checkAuth();
		setupMadelineProto();
		
		$MP->messages->setTyping(['peer' => (int) getParam('peer'),
		'action' => ['_' => 'sendMessage'.getParam('action').'Action']]);
		
		json(['res' => 1]);
		break;
	case 'updateStatus':
		checkAuth();
		setupMadelineProto();
		
		$MP->account->updateStatus(['offline' => !isParamEmpty('off')]);
		json(['res' => 1]);
		break;
	case 'sendMessage':
	case 'editMessage':
	case 'sendMedia':
		checkParamEmpty('peer');
		checkAuth();
		setupMadelineProto();
		
		if (!isParamEmpty('fwd_from')) {
			$MP->messages->forwardMessages([
			'from_peer' => (int) getParam('fwd_from'),
			'to_peer' => (int) getParam('peer'),
			'id' => [(int) getParam('id')]
			]);
			if (!isset($_FILES['file']) && isParamEmpty('text')) {
				json(['res' => '1']);
				break;
			}
		}
		
		$p = [];
		addParamToArray($p, 'peer');
		$p['message'] = getParam('text', '');
		if (!isParamEmpty('id')) addParamToArray($p, 'id');
		if (!isParamEmpty('reply')) {
			$p['reply_to_msg_id'] = getParam('reply');
		}
		if (!isParamEmpty('html')) {
			$p['parse_mode'] = 'HTML';
		}
		
		if (!isset($_FILES['file'])) {
			if ($METHOD == 'sendMedia' && (isParamEmpty('doc_id') || isParamEmpty('doc_access_hash'))) {
				json(['error' => ['message' => 'No file: '.var_export($_FILES,true)]]);
				break;
			}
		} else {
			if (($_FILES['file']['error'] ?? false) && $_FILES['file']['error'] != 4) {
				json(['error' => ['message' => 'File error: '.$_FILES['file']['error']]]);
				break;
			}
		}
		$file = $_FILES['file']['tmp_name'] ?? false;
		try {
			if ($file) {
				if ($_FILES['file']['size'] == 0) {
					json(['error' => ['message' => 'Size error']]);
					break;
				}
				$max = 20 * 1024 * 1024;
				if (defined('UPLOAD_SIZE_LIMIT')) $max = UPLOAD_SIZE_LIMIT;
				if ($_FILES['file']['size'] > $max) {
					json(['error' => ['message' => 'File is too large']]);
					break;
				}
				
				$filename = $_FILES['file']['name'];
				$extidx = strrpos($filename, '.');
				if ($extidx === false) {
					json(['error' => ['message' => 'Invalid file extension']]);
					break;
				}
				$ext = strtolower(substr($filename, $extidx+1));
				$attr = false;
				if (!isParamEmpty('uncompressed')) {
					$type = 'inputMediaUploadedDocument';
					$attr = true;
				} else {
					switch ($ext) {
						case 'jpg':
						case 'jpeg':
						case 'png':
							$newfile = $file.'.'.$ext;
							if (!move_uploaded_file($file, $newfile)) {
								json(['error' => ['message' => 'Failed to move file']]);
								break;
							}
							$type = 'inputMediaUploadedPhoto';
							$file = $newfile;
							break;
						default:
							$type = 'inputMediaUploadedDocument';
							$attr = true;
							break;
					}
				}
				$attributes = [];
				if ($attr) {
					array_push($attributes, ['_' => 'documentAttributeFilename', 'file_name' => $filename]);
				}
				$p['media'] = ['_' => $type, 'file' => $file, 'attributes' => $attributes, 'spoiler' => !isParamEmpty('spoiler')];
			} else if (!isParamEmpty('doc_id')) {
				$p['media'] = ['_' => 'document', 'id' => (int) getParam('doc_id'), 'access_hash' => getParam('doc_access_hash')];
			}
			
			if ($METHOD == 'editMessage') {
				$MP->messages->editMessage($p);
			} else if ($METHOD == 'sendMedia') {
				$MP->messages->sendMedia($p);
			} else {
				$MP->messages->sendMessage($p);
			}
		} finally {
			if ($file) {
				try {
					unlink($file);
				} catch (Exception) {}
			}
		}
		
		json(['res' => '1']);
		break;
	case 'searchChats':
		checkAuth();
		setupMadelineProto();
		
		$rawData = $MP->contacts->search(['q' => getParam('q')]);
		$res = [];
		foreach ($rawData['my_results'] as $c) {
			$r = findPeer(getId($c), $rawData);
			array_push($res, $r['id'] < 0 ? parseChat($r) : parseUser($r));
		}
		foreach ($rawData['results'] as $c) {
			$r = findPeer(getId($c), $rawData);
			array_push($res, $r['id'] < 0 ? parseChat($r) : parseUser($r));
		}
		json(['res' => $res]);
		break;
	case 'banMember':
		checkAuth();
		setupMadelineProto();
		
		$MP->channels->editBanned(['channel' => (int) getParam('peer'), 'participant' => (int) getParam('id'), 'banned_rights' => [
			'_' => 'chatBannedRights',
			'until_date' => 1,
			'view_messages' => true,
			'send_messages' => true
			]]);
			
			
		json(['res' => '1']);
		break;
	case 'getForumTopics':
		checkAuth();
		setupMadelineProto();
		
		$rawData = $MP->channels->getForumTopics(['channel' => (int) getParam('peer'), 'limit' => (int) getParam('limit', 30)]);
		$res = [];
		foreach ($rawData['topics'] as $t) {
			$r = [
			'closed' => $t['closed'] ?? false,
			'pinned' => $t['pinned'] ?? false,
			'id' => $t['id'],
			'title' => $t['title'] ?? null,
			'date' => $t['date'] ?? 0,
			'top' => $t['top_message'] ?? 0,
			'unread' => $t['unread_count'] ?? 0,
			'read_max_id' => $t['read_inbox_max_id'] ?? 0
			];
			array_push($res, $r);
		}
		json(['res' => $res]);
		break;
	case 'botCallback':
		checkAuth();
		setupMadelineProto();
		
		$timeout = (float) getParam('timeout', '0.5');
		
		$rawData = async(
			$MP->messages->getBotCallbackAnswer(...),
			['peer' => (int) getParam('peer'), 'msg_id' => (int) getParam('id'), 'data' => base64_decode(getParam('data'))]
		)->await(Tools::getTimeoutCancellation($timeout));
		
		json($rawData);
		break;
	case 'sendVote':
		checkAuth();
		setupMadelineProto();
		
		$votes = explode('vote=', $_SERVER['QUERY_STRING']);
		$options = [];
		foreach ($votes as $vote) {
			if (strpos($vote, '=') !== false) continue;
			$i = strpos($vote, '&');
			if ($i !== false) $vote = substr($vote, 0, $i);
			array_push($options, $vote);
		}
		$rawData = $MP->messages->sendVote(['peer' => getParam('peer'), 'msg_id' => getParam('id'), 'options' => $options]);
		
		json($rawData);
		break;
	case 'getStickerSets':
		checkAuth();
		setupMadelineProto();
		
		$rawData = $MP->messages->getAllStickers();
		
		$res = [];
		foreach ($rawData['sets'] as $set) {
			$r = ['id' => strval($set['id']), 'access_hash' => strval($set['access_hash']), 'title' => $set['title'] ?? ''];
			if (isset($set['short_name'])) $r['short_name'] = $set['short_name'];
			array_push($res, $r);
		}
		
		json(['res' => $res]);
		break;
	case 'getStickerSet':
		checkAuth();
		setupMadelineProto();
		
		$rawData = $MP->messages->getStickerSet(['stickerset' =>
		(isParamEmpty('slug') ? ['_' => 'inputStickerSetID',
			'id' => (int) getParam('id'),
			'access_hash' => getParam('access_hash')]
		: ['_' => 'inputStickerSetShortName', 'short_name' => getParam('slug')]
		)]);
		
		$res = [];
		$res['res'] = [];
		if (isset($rawData['set']['count'])) $res['count'] = $rawData['set']['count'];
		if (isset($rawData['set']['title'])) $res['title'] = $rawData['set']['title'];
		if (isset($rawData['set']['installed_date'])) $res['installed'] = $rawData['set']['installed_date'];
		if (isset($rawData['set']['short_name'])) $res['short_name'] = $rawData['set']['short_name'];
		$res['id'] = strval($rawData['set']['id']);
		$res['access_hash'] = strval($rawData['set']['access_hash']);
		foreach ($rawData['documents'] as $doc) {
			$r = ['id' => strval($doc['id']), 'access_hash' => strval($doc['access_hash']), 'mime' => $doc['mime_type']];
			array_push($res['res'], $r);
		}
		
		json($res);
		break;
	// v6
	case 'pinMessage':
		checkAuth();
		setupMadelineProto();
		
		$MP->messages->updatePinnedMessage([
		'silent' => ((int) getParam('silent', '1')) == 1,
		'unpin' => !isParamEmpty('unpin'),
		'peer' => (int) getParam('peer'),
		'id' => (int) getParam('id')
		]);
		
		json(['res' => '1']);
		break;
	// v7
	case 'installStickerSet':
		checkAuth();
		setupMadelineProto();
		
		$MP->messages->installStickerSet(['stickerset' =>
		(isParamEmpty('slug') ? ['_' => 'inputStickerSetID',
			'id' => (int) getParam('id'),
			'access_hash' => getParam('access_hash')]
		: ['_' => 'inputStickerSetShortName', 'short_name' => getParam('slug')]
		)]);
		
		json(['res' => '1']);
		break;
	// v8
	case 'getNotifySettings':
		checkAuth();
		setupMadelineProto();
		
		json([
			'users' => $MP->account->getNotifySettings(peer: ['_' => 'inputNotifyUsers'])['mute_until'] ?? 0,
			'chats' => $MP->account->getNotifySettings(peer: ['_' => 'inputNotifyChats'])['mute_until'] ?? 0,
			'broadcasts' => $MP->account->getNotifySettings(peer: ['_' => 'inputNotifyBroadcasts'])['mute_until'] ?? 0
		]);
		break;
	case 'notifications':
		checkAuth();
		setupMadelineProto();
		
		$offset = (int) getParam('offset');
		$media = !isParamEmpty('media');
		$peers = isParamEmpty('peers') ? false : explode(',', getParam('peers'));
		$limit = (int) getParam('limit', '1000');
		$includemuted = !isParamEmpty('include_muted');
		$users = getParam('mute_users', '0');
		$chats = getParam('mute_chats', '0');
		$broadcasts = getParam('mute_broadcasts', '0');
		
		$so = $offset;
		$res = [];
		
		$updates = $MP->getUpdates(['offset' => $offset, 'limit' => $limit, 'timeout' => 1]);
		foreach ($updates as $update) {
			if ($update['update_id'] == $so) continue;
			$type = $update['update']['_'];
			$offset = $update['update_id'] + 1;
			if ($type == 'updateNewMessage' || $type == 'updateNewChannelMessage') {
				$msg = $update['update']['message'];
				if ($peers && $type == 'updateNewChannelMessage') {
					if (array_search(strval(getId($msg['peer_id'])), $peers) === false)
						continue;
				} else {
					$info = $MP->getFullInfo($msg['peer_id']);
					if ($info['Chat']['left'] ?? false)
						continue;
					$mute = $info['full']['notify_settings']['mute_until'] ??
					(($info['Chat']['broadcast'] ?? false) ? $broadcasts : (isset($info['Chat']) ? $chats : $users));
					if ($mute != 0 && !$includemuted)
						continue;
					if ($mute) $update['update']['mute'] = true;
				}
				$update['update']['message'] = parseMessage($msg, $media, true);
				array_push($res, $update['update']);
			}
		}
		json(['res' => $res, 'offset' => $offset]);
		break;
	case 'getDiscussionMessage':
		checkAuth();
		setupMadelineProto();
		
		$r = $MP->messages->getDiscussionMessage([
		'peer' => getParam('peer'),
		'msg_id' => (int) getParam('id')
		]);
		$msg = $r['messages'][0];
		
		json([
		'id' => $msg['id'],
		'peer_id' => strval(getId($msg['peer_id'])),
		'unread' => $r['unread_count'] ?? 0,
		'read' => max($r['read_inbox_max_id'] ?? 0, $r['read_outbox_max_id'] ?? 0, $msg['id']),
		'max_id' => $r['max_id']
		]);
		break;
	case 'getInfo':
		checkParamEmpty('id');
		checkAuth();
		setupMadelineProto();
		$r = $MP->getInfo(getParam('id')) ?? null;
		if ($r) {
			json($r);
		} else {
			error(['message'=>'']);
		}
		break;
	default:
		error(['message' => "Method \"$METHOD\" is undefined"]);
	}
} catch (Throwable $e) {
	http_response_code(500);
	error(['message' => "Unhandled exception", 'stack_trace' => strval($e)]);
}
?>