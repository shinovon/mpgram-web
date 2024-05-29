<?php
$lang = 'ru';
$autoupd = 1;
$dynupd = 1;
$updint = 10;
$theme = 0;
$chats = 15;
$sym3 = strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Symbian/3') !== false;
$reverse = $sym3 ? 1 : 0;
$autoscroll = 1;
$limit = 20;
$useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$avas = strpos($useragent, 'Chrome') || strpos($useragent, 'Symbian/3') || strpos($useragent, 'SymbOS') || strpos($useragent, 'Android') || strpos($useragent, 'Linux') ? 1 : 0;
$texttop = $sym3 ? 1 : 0;
$longpoll = strpos($useragent, 'AppleWebKit') || strpos($useragent, 'Chrome') || strpos($useragent, 'Symbian') || strpos($useragent, 'SymbOS') || strpos($useragent, 'Android') ? 1 : 0;
$status = 0;
$imgs = 1;
$pngava = 0;
$oldchat = 0;
$photosize = 180;
$bgsize = 240;
$set = isset($_GET['set']);
include 'mp.php';

MP::startSession();

if($set) {
	$autoupd = isset($_GET['autoupd']) ? 1 : 0;
	$reverse = isset($_GET['reverse']) ? 1 : 0;
	$autoscroll = isset($_GET['autoscroll']) ? 1 : 0;
	$avas = isset($_GET['avas']) ? 1 : 0;
	$idf = $dynupd == 1 ? 10 : 25;
	if(isset($_GET['lang'])) {
		$lang = $_GET['lang'];
	}
	if(isset($_GET['updint'])) {
		$updint = $_GET['updint'];
		if(!is_numeric($updint)) {
			$updint = $idf;
		} else {
			$updint = (int) $updint;
		}
		if($updint < 3 || $updint > 60) {
			$updint = $idf;
		}
	}
	if(isset($_GET['theme'])) {
		$theme = (int) $_GET['theme'];
	}
	if(isset($_GET['chats'])) {
		$chats = (int) $_GET['chats'];
		if($chats < 10) {
			$chats = 10;
		} elseif($chats > 100) {
			$chats = 100;
		}
	}
	if(isset($_GET['limit'])) {
		$limit = (int) $_GET['limit'];
		if($limit < 5) {
			$limit = 5;
		} elseif($chats > 50) {
			$limit = 50;
		}
	}
	if(isset($_GET['photosize'])) {
		$photosize = (int) $_GET['photosize'];
	}
	if(isset($_GET['bgsize'])) {
		$bgsize = (int) $_GET['bgsize'];
	}
	$texttop = isset($_GET['texttop']) ? 1 : 0;
	$longpoll = isset($_GET['longpoll']) ? 1 : 0;
	$status = isset($_GET['status']) ? 1 : 0;
	$imgs = isset($_GET['imgs']) ? 1 : 0;
	$pngava = isset($_GET['pngava']) ? 1 : 0;
	$oldchat = isset($_GET['oldchat']) ? 1 : 0;
	
	MP::cookie('lang', $lang, time() + (86400 * 365));
	MP::cookie('updint', $updint, time() + (86400 * 365));
	MP::cookie('theme', $theme, time() + (86400 * 365));
	
	$_SESSION['lang'] = $lang;
	$_SESSION['autoupd'] = $autoupd;
	$_SESSION['updint'] = $updint;
	$_SESSION['theme'] = $theme;
	$_SESSION['chats'] = $chats;
	$_SESSION['reverse'] = $reverse;
	$_SESSION['autoscroll'] = $autoscroll;
	$_SESSION['limit'] = $limit;
	$_SESSION['avas'] = $avas;
	$_SESSION['texttop'] = $texttop;
	$_SESSION['longpoll'] = $longpoll;
	$_SESSION['status'] = $status;
	$_SESSION['imgs'] = $imgs;
	$_SESSION['pngava'] = $pngava;
	$_SESSION['oldchat'] = $oldchat;
	$_SESSION['photosize'] = $photosize;
	$_SESSION['bgsize'] = $bgsize;
} else {
	if(isset($_COOKIE['lang']))
		$lang = $_COOKIE['lang'];
	if(isset($_COOKIE['autoupd']))
		$autoupd = (int)$_COOKIE['autoupd'];
	if(isset($_COOKIE['updint']))
		$updint = (int)$_COOKIE['updint'];
	if(isset($_COOKIE['theme']))
		$theme = (int)$_COOKIE['theme'];
	if(isset($_COOKIE['chats']))
		$chats = (int)$_COOKIE['chats'];
	if(isset($_COOKIE['reverse']))
		$reverse = (int)$_COOKIE['reverse'];
	if(isset($_COOKIE['autoscroll']))
		$autoscroll = (int)$_COOKIE['autoscroll'];
	if(isset($_COOKIE['limit']))
		$limit = (int)$_COOKIE['limit'];
	if(isset($_COOKIE['avas']))
		$avas = (int)$_COOKIE['avas'];
	if(isset($_COOKIE['texttop']))
		$texttop = (int)$_COOKIE['texttop'];
	if(isset($_COOKIE['longpoll']))
		$longpoll = (int)$_COOKIE['longpoll'];
	if(isset($_COOKIE['status']))
		$status = (int)$_COOKIE['status'];
	if(isset($_COOKIE['imgs']))
		$imgs = (int)$_COOKIE['imgs'];
	if(isset($_COOKIE['pngava']))
		$pngava = (int)$_COOKIE['pngava'];
	if(isset($_COOKIE['oldchat']))
		$oldchat = (int)$_COOKIE['oldchat'];
	if(isset($_COOKIE['photosize']))
		$photosize = (int)$_COOKIE['photosize'];
	if(isset($_COOKIE['bgsize']))
		$bgsize = (int)$_COOKIE['bgsize'];
	
	if(isset($_SESSION['lang']))
		$lang = $_SESSION['lang'];
	if(isset($_SESSION['autoupd']))
		$autoupd = (int)$_SESSION['autoupd'];
	if(isset($_SESSION['updint']))
		$updint = (int)$_SESSION['updint'];
	if(isset($_SESSION['theme']))
		$theme = (int)$_SESSION['theme'];
	if(isset($_SESSION['chats']))
		$chats = (int)$_SESSION['chats'];
	if(isset($_SESSION['reverse']))
		$reverse = (int)$_SESSION['reverse'];
	if(isset($_SESSION['autoscroll']))
		$autoscroll = (int)$_SESSION['autoscroll'];
	if(isset($_SESSION['limit']))
		$limit = (int)$_SESSION['limit'];
	if(isset($_SESSION['avas']))
		$avas = (int)$_SESSION['avas'];
	if(isset($_SESSION['texttop']))
		$texttop = (int)$_SESSION['texttop'];
	if(isset($_SESSION['longpoll']))
		$longpoll = (int)$_SESSION['longpoll'];
	if(isset($_SESSION['status']))
		$status = (int)$_SESSION['status'];
	if(isset($_SESSION['imgs']))
		$imgs = (int)$_SESSION['imgs'];
	if(isset($_SESSION['pngava']))
		$pngava = (int)$_SESSION['pngava'];
	if(isset($_SESSION['oldchat']))
		$oldchat = (int)$_SESSION['oldchat'];
	if(isset($_SESSION['photosize']))
		$photosize = (int)$_SESSION['photosize'];
	if(isset($_SESSION['bgsize']))
		$bgsize = (int)$_SESSION['bgsize'];
}

$lng = MP::initLocale();

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-cache, no-store');

include 'themes.php';
Themes::setTheme($theme);
echo '<head><title>'.MP::x($lng['settings']).'</title>';
echo Themes::head();
echo '</head>';
echo Themes::bodyStart();
echo '<div><a href="login.php">'.MP::x($lng['back']).'</a></div>';
echo '<form action="sets.php">';
echo '<input type="hidden" name="set" value="1">';
$langs = json_decode(file_get_contents('./locale/list.json'), true);
echo '<p><b>'.MP::x($lng['set_language']).'</b></p>';
foreach($langs as $k=>$v) {
	echo '<input type="radio" name="lang"'.($lang==$k ? ' checked' : '').' value="'.$k.'">'.MP::x($v).'<br>';
}
echo '<p><input type="submit"></p>';
echo '<p><b>'.MP::x($lng['set_chat']).'</b></p>';
echo '<p><input type="checkbox" id="autoupd" name="autoupd"'.($autoupd ? ' checked' : '').'>';
echo '<label for="autoupd">'.MP::x($lng['set_chat_autoupdate']).'</label>';
echo '<br><input type="checkbox" id="reverse" name="reverse"'.($reverse ? ' checked' : '').'>';
echo '<label for="reverse">'.MP::x($lng['set_chat_reverse_mode']).'</label>';
echo '<br><input type="checkbox" id="autoscroll" name="autoscroll"'.($autoscroll ? ' checked' : '').'>';
echo '<label for="autoscroll">'.MP::x($lng['set_chat_autoscroll']).'</label>';
echo '<br><input type="checkbox" id="avas" name="avas"'.($avas ? ' checked' : '').'>';
echo '<label for="avas">'.MP::x($lng['set_chat_avas']).'</label>';
echo '<br><input type="checkbox" id="texttop" name="texttop"'.($texttop ? ' checked' : '').'>';
echo '<label for="texttop">'.MP::x($lng['set_chat_texttop']).'</label>';
echo '<br><input type="checkbox" id="longpoll" name="longpoll"'.($longpoll ? ' checked' : '').'>';
echo '<label for="longpoll">Longpoll</label>';
echo '<br><input type="checkbox" id="status" name="status"'.($status ? ' checked' : '').'>';
echo '<label for="status">'.MP::x($lng['set_chat_status']).'</label>';
echo '<br><input type="checkbox" id="imgs" name="imgs"'.($imgs ? ' checked' : '').'>';
echo '<label for="imgs">'.MP::x($lng['set_msg_photos']).'</label>';
echo '<br><input type="checkbox" id="pngava" name="pngava"'.($pngava ? ' checked' : '').'>';
echo '<label for="pngava">'.MP::x($lng['set_png_avatar']).'</label>';
echo '<br><input type="checkbox" id="oldchat" name="oldchat"'.($oldchat ? ' checked' : '').'>';
echo '<label for="oldchat">'.MP::x($lng['set_old_chat']).'</label>';

echo '</p><p><label for="updint">'.MP::x($lng['set_chat_autoupdate_interval']).'</label>:<br>';
echo '<input type="text" size="3" id="updint" name="updint" value="'.$updint.'"><br>';
echo '<label for="limit">'.MP::x($lng['set_msgs_limit']).'</label>:<br>';
echo '<input type="text" size="3" id="limit" name="limit" value="'.$limit.'"><br>';
echo '<label for="chats">'.MP::x($lng['set_chats_count']).'</label>:<br>';
echo '<input type="text" size="3" id="chats" name="chats" value="'.$chats.'"></p>';
echo '<p><b>'.MP::x($lng['set_chat_photos_size']).'</b></p>';
$photosizes = [80, 120, 180, 240, 360];
foreach($photosizes as $v) {
	echo '<input type="radio" name="photosize"'.($photosize==$v ? ' checked' : '').' value="'.$v.'">'.MP::x($v).'<br>';
}
echo '<p><b>'.MP::x($lng['set_theme']).'</b></p>';
$themes = array(
0 => $lng['set_theme_dark'],
1 => $lng['set_theme_light'],
2 => $lng['set_theme_light_bg'],
);
foreach($themes as $k=>$v) {
	echo '<input type="radio" name="theme"'.($theme==$k ? ' checked' : '').' value="'.$k.'">'.MP::x($v).'<br>';
}
echo '<p><b>'.MP::x($lng['set_bg_size']).'</b></p>';
$bgsizes = [240, 320, 640, 720, 1000];
foreach($bgsizes as $v) {
	echo '<input type="radio" name="bgsize"'.($bgsize==$v ? ' checked' : '').' value="'.$v.'">'.MP::x($v).'<br>';
}
echo '<p><input type="submit"></p>';
echo '</form><br>';
if(MP::getUser()) {
	echo '<p><a class="bth ra" href="login.php?logout=2">'.MP::x($lng['logout']).'</a></p>';
}
echo Themes::bodyEnd();
