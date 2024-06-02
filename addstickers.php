<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';
$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die;
}

$theme = MP::getSettingInt('theme');
$lng = MP::initLocale();

$name = $_POST['n'] ?? $_GET['n'] ?? die;
$stickerset = ['_' => 'inputStickerSetShortName', 'short_name' => $name];
$returnurl = $_POST['u'] ?? $_GET['u'] ?? null;

header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: private, no-cache, no-store");

function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');
include 'themes.php';
Themes::setTheme($theme);
try {
	$MP = MP::getMadelineAPI($user);
	if(isset($_GET['c'])) {
		if(!$returnurl) $returnurl = '/chats.php';
		$MP->messages->installStickerSet(['stickerset' => $stickerset, 'archived' => false]);
		header("Location: $returnurl");
		die;
	}
	if(!$returnurl) $returnurl = $_SERVER['HTTP_REFERER'] ?? '';
	$r = $MP->messages->getStickerSet(['stickerset' => $stickerset]);
	echo '<head><title>'.MP::x($r['set']['title']).'</title>';
	echo Themes::head();
	echo '</head>';
	echo Themes::bodyStart();
	echo '<b>'.MP::x($r['set']['title']).'</b><br>';
	echo '<a href="addstickers.php?n='.$name.'&u='.urlencode($returnurl).'&c">'.MP::x($lng['install_stickerset']).'</a>';
	echo '<p>';
	foreach($r['documents'] as $v) {
		echo '<img src="file.php?sticker='.$v['id'].'&access_hash='.$v['access_hash'].'&p=r'.(($v['mime_type'] ?? '') == 'application/x-tgsticker' ? 'tgss&s=100' : 'sprev').'">';
	}
	echo '</p>';
	echo Themes::bodyEnd();
} catch (Exception $e) {
	echo $e;
}
