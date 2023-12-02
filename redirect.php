<?php
include 'config.php';
if(!isset($_SERVER['HTTPS']) && (FORCE_HTTPS || (CHROME_HTTPS && isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== false))) {
	$s = 'https://' . $_SERVER['SERVER_NAME'];
	if(isset($_SERVER['REQUEST_URI'])) {
		$s .= $_SERVER['REQUEST_URI'];
	} else {
		$s .= '/';
	}
	header('Location: ' . $s);
	die;
}