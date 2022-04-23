<?php
define('sessionspath', '');

header('Content-Type: text/html; charset=utf-8');
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$lang = 'ru';

if(isset($_GET['lang'])) {
	$lang = $_GET['lang'];
	setcookie('lang', $lang, time() + (86400 * 365));
} else if(isset($_COOKIE['lang'])) {
	$lang = $_COOKIE['lang'];
}

include 'locale_'.$lang.'.php';

$user = null;
if(isset($_COOKIE['user']))
	$user = $_COOKIE['user'];
if(!isset($user) || empty($user)) {
	//не авторизирован, отправить в логинизацию
	header('Location: login.php');
	die();
}
header('Cache-Control: private, no-cache, no-store');

if (!file_exists('madeline.php')) {
	copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}

function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

set_error_handler('exceptions_error_handler');

include 'util.php';

$avas = false;
try {
	include 'madeline.php';
	include 'api_settings.php';
	$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline', getSettings());
	$MP->start();
	echo '<head><title>'.$lng['chats'].'</title></head><body>';
	echo '<b>'.Utils::dehtml(Utils::getSelfName($MP)).'</b><br>';
	echo '<a href="login.php?logout=1">'.$lng['logout'].'</a>';
	echo ' <a href="chats.php?upd=1">'.$lng['refresh'].'</a><br><br>';
	
	try {
		$dialogs = $MP->getFullDialogs();
		$c = 0;
		foreach(array_reverse($dialogs) as $d){
			if($c == 15) {
				break;
			} else {
				$c += 1;
			}
			$id = Utils::getId($MP, $d['peer']);
			echo '<div><a href="chat.php?c='.$id.'">';
			echo Utils::dehtml(Utils::getNameFromId($MP, $id, true)).'</a>';
			if($d["unread_count"] > 0) {
				echo ' <b>+'.$d["unread_count"].'</b>';
			}
			echo '</div>';
		}
	} catch (Exception $e) {
		echo '<b>'.$lng['error'].'!</b><br>';
		echo "<xmp>$e</xmp>";
	}
	echo '</body>';
} catch (Exception $e) {
	echo '<b>'.$lng['error'].'!</b><br>';
	echo "<xmp>$e</xmp>";
}
