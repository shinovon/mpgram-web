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
$set = isset($_GET['set']);
include 'mp.php';
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
		} else if($chats > 100) {
			$chats = 100;
		}
	}
	if(isset($_GET['limit'])) {
		$limit = (int) $_GET['limit'];
		if($limit < 5) {
			$limit = 5;
		} else if($chats > 50) {
			$limit = 50;
		}
	}
	$texttop = isset($_GET['texttop']) ? 1 : 0;
	MP::cookie('lang', $lang, time() + (86400 * 365));
	MP::cookie('autoupd', $autoupd, time() + (86400 * 365));
	MP::cookie('updint', $updint, time() + (86400 * 365));
	MP::cookie('theme', $theme, time() + (86400 * 365));
	MP::cookie('chats', $chats, time() + (86400 * 365));
	MP::cookie('reverse', $reverse, time() + (86400 * 365));
	MP::cookie('autoscroll', $autoscroll, time() + (86400 * 365));
	MP::cookie('limit', $limit, time() + (86400 * 365));
	MP::cookie('avas', $avas, time() + (86400 * 365));
	MP::cookie('texttop', $texttop, time() + (86400 * 365));
	MP::cookie('longpoll', $longpoll, time() + (86400 * 365));
} else {
	if(isset($_COOKIE['lang'])) {
		$lang = $_COOKIE['lang'];
	}
	if(isset($_COOKIE['autoupd'])) {
		$autoupd = (int)$_COOKIE['autoupd'];
	}
	if(isset($_COOKIE['updint'])) {
		$updint = (int)$_COOKIE['updint'];
	}
	if(isset($_COOKIE['theme'])) {
		$theme = (int)$_COOKIE['theme'];
	}
	if(isset($_COOKIE['chats'])) {
		$chats = (int)$_COOKIE['chats'];
	}
	if(isset($_COOKIE['reverse'])) {
		$reverse = (int)$_COOKIE['reverse'];
	}
	if(isset($_COOKIE['autoscroll'])) {
		$autoscroll = (int)$_COOKIE['autoscroll'];
	}
	if(isset($_COOKIE['limit'])) {
		$limit = (int)$_COOKIE['limit'];
	}
	if(isset($_COOKIE['avas'])) {
		$avas = (int)$_COOKIE['avas'];
	}
	if(isset($_COOKIE['texttop'])) {
		$texttop = (int)$_COOKIE['texttop'];
	}
	if(isset($_COOKIE['longpoll'])) {
		$longpoll = (int)$_COOKIE['longpoll'];
	}
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
echo '</p><p><label for="updint">'.MP::x($lng['set_chat_autoupdate_interval']).'</label>:<br>';
echo '<input type="text" size="3" id="updint" name="updint" value="'.$updint.'"><br>';
echo '<label for="limit">'.MP::x($lng['set_msgs_limit']).'</label>:<br>';
echo '<input type="text" size="3" id="limit" name="limit" value="'.$limit.'"><br>';
echo '<label for="chats">'.MP::x($lng['set_chats_count']).'</label>:<br>';
echo '<input type="text" size="3" id="chats" name="chats" value="'.$chats.'"></p>';
echo '<p><b>'.MP::x($lng['set_theme']).'</b></p>';
$themes = array(
0 => $lng['set_theme_dark'],
1 => $lng['set_theme_light'],
);
foreach($themes as $k=>$v) {
	echo '<input type="radio" name="theme"'.($theme==$k ? ' checked' : '').' value="'.$k.'">'.MP::x($v).'<br>';
}
echo '<p><input type="submit"></p>';
echo '</form>';
echo Themes::bodyEnd();
