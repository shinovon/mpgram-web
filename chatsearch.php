<?php

include 'redirect.php';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';

$timeoff = MP::getSettingInt('timeoff');
$theme = MP::getSettingInt('theme');
$lng = MP::initLocale();

$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die;
}

$c = $_GET['c'] ?? null;

header('Content-Type: text/html; charset='.MP::$enc);
header('Cache-Control: private, no-cache, no-store, must-revalidate');

include 'themes.php';
Themes::setTheme($theme);

echo '<head><title>'.MP::x($lng['search']).'</title>';
echo Themes::head();
echo '</head>';
echo Themes::bodyStart();

echo '<div><a href="login.php">'.MP::x($lng['back']).'</a></div>';

echo '<form action="chat'.($c ? '' : 'select').'.php">';
echo '<p><input type="text" name="q" id="q"><br>';
if($c) {
	echo '<input type="hidden" name="c" value="'.$c.'">';
} else {
	echo '<input type="checkbox" name="g" id="g">';
	echo '<label for="g">'.MP::x(MP::x($lng['global_search'])).'</label>';
}
echo '</p><input type="submit">';
echo '</form>';

echo Themes::bodyEnd();
