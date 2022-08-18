<?php
$lang = 'ru';
$autoupd = 1;
$dynupd = 1;
$updint = 10;
$theme = 0;
$set = isset($_GET['set']);
include 'mp.php';
if($set) {
	$autoupd = isset($_GET['autoupd']);
	if($autoupd == 'on') $autoupd = 1;
	else $autoupd = 0;
	
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
	MP::cookie('lang', $lang, time() + (86400 * 365));
	MP::cookie('autoupd', $autoupd, time() + (86400 * 365));
	MP::cookie('updint', $updint, time() + (86400 * 365));
	MP::cookie('theme', $theme, time() + (86400 * 365));
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
}

try {
	include 'locale_'.$lang.'.php';
} catch (Exception $e) {
	$lang = 'ru';
	include 'locale_'.$lang.'.php';
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-cache, no-store');

include 'themes.php';
Themes::setTheme($theme);
echo '<head><title>'.MP::x($lng['settings']).'</title>';
echo Themes::head();
echo '</head>';
echo Themes::bodyStart();
echo '<div><a href="login.php">'.MP::x($lng['back']).'</a></div>';
//echo '<div><b>Язык</b>:<br>';
//echo '<a href="sets.php?lang=en">English</a> <a href="sets.php?lang=ru">Русский</a></div>';
echo '<form action="sets.php">';
echo '<input type="hidden" name="set" value="1">';
$langs = array(
'ru' => 'Русский',
'en' => 'English',
);
echo '<p><b>'.MP::x($lng['set_language']).'</b></p>';
foreach($langs as $k=>$v) {
	echo '<input type="radio" name="lang"'.($lang==$k ? ' checked' : '').' value="'.$k.'">'.MP::x($v).'<br>';
}
echo '<p><b>'.MP::x($lng['set_chat']).'</b></p>';
echo '<p><input type="checkbox" id="autoupd" name="autoupd"'.($autoupd ? ' checked' : '').'>';
echo '<label for="autoupd">'.MP::x($lng['set_chat_autoupdate']).'</label><br>';
//echo '<p><input type="checkbox" id="dynupd" name="dynupd"'.($autoupd ? ' checked' : '').'>';
//echo '<label for="autoupd">Авто-обновление чата</label><br>';
echo '<p><label for="updint">'.MP::x($lng['set_chat_autoupdate_interval']).'</label>:<br>';
echo '<input type="text" size="3" id="updint" name="updint" value="'.$updint.'"></p>';
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