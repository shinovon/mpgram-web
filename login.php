<?php
define('sessionspath', '');

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
header("Content-Type: text/html; charset=utf-8");

$lang = 'ru';

if(isset($_GET['lang'])) {
	$lang = $_GET['lang'];
	setcookie('lang', $lang, time() + (86400 * 365));
} else if(isset($_COOKIE['lang'])) {
	$lang = $_COOKIE['lang'];
}
try {
	include 'locale_'.$lang.'.php';
} catch (Exception $e) {
	$lang = 'ru';
	include 'locale_'.$lang.'.php';
}

$logout = false;
if(isset($_GET['logout']) && isset($_COOKIE['user'])) {
	$logout = true;
	setcookie('user', time() - 3600);
	setcookie('code', time() - 3600);
	try {
		include 'madeline.php';
		$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline');
		$MP->stop();
		$MP->logout();
	} catch (Exception $e) {
	}
}

$user = null;
if(isset($_COOKIE['user']) && !$logout)
	$user = $_COOKIE['user'];
if($user != null && isset($_COOKIE['code']) && !empty($_COOKIE['code']) && !$logout) {
	// уже авторизован
	header('Location: chats.php');
	die();
} else if(!isset($_GET['phone']) && $user == null){
	// ввод телефона
	echo '<head><title>'.$lng['login'].'</title></head>';
	echo '<body>'.$lng['phone_number'].':<br>';
	echo '<form action="">';
	echo '<input type="text" name="phone">';
	echo '<input type="submit">';
	echo '</form></body>';
} else {
	if (!file_exists('madeline.php')) {
		copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
	}
	include 'madeline.php';
	include 'api_settings.php';
	$MP = null;
	if(!isset($user) || empty($user)) {
		$user = md5($_GET['phone'].rand(0,1000));
		setcookie('user', $user, time() + (86400 * 365));
		$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline', getSettings());
		echo '<head><title>'.$lng['login'].'</title></head><body>';
	} else {
		if(isset($_GET['code'])) {
			// завершить авторизацию
			try {
				$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline', getSettings());
				$MP->complete_phone_login((int)$_GET["code"]);
				$err = null;
				//TODO: ошибки
				if($err == null) {
					setcookie('code', '1', time() + (86400 * 365));
					header('Location: chats.php');
					die();
				} else {
					echo '<head><title>'.$lng['login'].'</title></head><body>';
					echo '<b>'.$lng['error'].'</b><br>';
					echo $err;
					echo '</body>';
					die();
				}
			} catch (Exception $e) {
				if(strpos($e->getMessage(), 'PHONE_CODE_INVALID') !== false) {
					echo '<head><title>'.$lng['login'].'</title></head><body>';
					echo '<b>Неправильный код!</b><br>';
				} else if(strpos($e->getMessage(), 'PHONE_CODE_EXPIRED') !== false) {
					echo '<head><title>'.$lng['login'].'</title></head><body>';
					echo '<b>Код истек!</b><br>';
				} else {
					echo '<head><title>'.$lng['login'].'</title></head><body>';
					echo '<b>'.$lng['error'].'</b><br>';
					echo $e->getMessage();
					echo '</body>';
					die();
				}
			}
		} else {
			$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline', getSettings());
			echo '<head><title>'.$lng['login'].'</title></head><body>';
		}
	}
	// ввод кода
	$MP->phone_login($_GET["phone"]);
	echo $lng['phone_code'].':<br>';
	echo '<form action="">';
	echo '<input type="hidden" name="phone" value="'.$_GET["phone"].'">';
	echo '<input type="text" name="code">';
	echo '<input type="submit">';
	echo '</form>';
	echo '</body>';
}
?>
