<?php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-cache, no-store');
include 'mp.php';
$theme = MP::getSettingInt('theme', 0);
$lang = MP::getSetting('lang');
include 'themes.php';
Themes::setTheme($theme);
try {
	include 'locale_'.$lang.'.php';
} catch (Exception $e) {
	$lang = 'ru';
	include 'locale_'.$lang.'.php';
}

echo MP::x('<head><title>'.$lng['about'].'</title>');
echo Themes::head();
echo '</head>';
echo Themes::bodyStart();
echo MP::x('<div><a href="login.php">'.$lng['back'].'</a></div>');
echo '<h1>MPGram Web</h1>';
echo MP::x('<p>MPGram Web (aka MIDletPascalGram Web) is lightweight telegram web client based on MadelineProto library, for devices with internet access and basic HTML & CSS support</p>');
echo MP::x('<p>Links:<br>');
echo '<a href="https://github.com/shinovon/mpgram-web">GitHub</a><br>';
echo '<a href="https://vk.com/mpgram">VK</a>';
echo '</p>';
echo MP::x('<p>Developers:<br>');
echo '<b>Shinovon</b> <a href="https://github.com/shinovon">github</a>';
echo ' <a href="https://t.me/shinovon">t.me</a>';
echo '<br>';
echo MP::x('<b>MuseCat77</b></i>');
echo ' <a href="https://github.com/musecat77">github</a>';
echo ' <a href="https://t.me/musecat77">t.me</a>';
echo '</p>';
echo MP::x('<p>Idea author:<br>');
echo '<b>twsparkle</b> <a href="https://github.com/diller444">github</a>';
echo ' <a href="https://t.me/twsparkle">t.me</a>';
echo '</p>';
echo Themes::bodyEnd();