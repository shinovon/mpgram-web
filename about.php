<?php

include 'redirect.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-cache, no-store');
include 'mp.php';
$theme = MP::getSettingInt('theme', 0);
include 'themes.php';
Themes::setTheme($theme);
$lng = MP::initLocale();
echo MP::x('<head><title>'.$lng['about'].'</title>');
echo Themes::head();
echo '</head>';
echo Themes::bodyStart();
echo MP::x('<div><a href="login.php">'.$lng['back'].'</a></div>');

?>
<h1>MPGram Web</h1>
<p>MPGram Web (aka MIDletPascalGram Web) is lightweight telegram web client based on MadelineProto library, for devices with internet access and basic HTML & CSS support</p>
<p>Links:<br>
<a href="https://github.com/shinovon/mpgram-web">GitHub</a><br>
<?php
if(MP::getUser()) {
	echo '<a href="chat.php?c=nnmidletschat">Discussion chat</a>';
} else {
	echo '<a href="https://t.me/nnmidletschat">Discussion chat</a>';
}
?>
<br><a href="https://nnp.nnchan.ru/mp">Page on nnproject</a><br>
</p>
<p>Developers:<br>
<b>Shinovon</b> <a href="https://github.com/shinovon">github</a>
 <a href="https://t.me/shinovon">t.me</a>
<br>
<b>MuseCat77</b></i>
 <a href="https://github.com/musecat77">github</a>
 <a href="https://t.me/musecat77">t.me</a>
</p>
<p>Idea author:<br>
<b>twsparkle</b> <a href="https://github.com/diller444">github</a>
 <a href="https://t.me/twsparkle">t.me</a>
</p>
<p>Donate:<br>
<a href="https://ko-fi.com/shinovon">ko-fi.com/shinovon</a><br>
<a href="https://boosty.to/nnproject/donate">boosty.to/nnproject/donate</a><br>
4400430278594491 Kaspi (KZ)<br>
</p>
<?php
require_once 'vendor/autoload.php';
echo '<br>Running on MadelineProto ' . \danog\MadelineProto\API::RELEASE;
echo ' PHP ' . phpversion();
echo Themes::bodyEnd();
