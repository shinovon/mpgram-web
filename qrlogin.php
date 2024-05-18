<?php

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$user = null;
$nouser = true;

$confirm = $_GET['confirm'] ?? $_POST['confirm'] ?? null;

$post = isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Series60/3') === false;

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

set_error_handler('exceptions_error_handler');

include 'mp.php';
MP::startSession();
$logout = isset($_GET['logout']);
if($logout) {
	$_SESSION = [];
}
$user = MP::getUser();
$nouser = $user == null || empty($user) || strlen($user) < 32 || strlen($user) > 200 || !file_exists(sessionspath.$user.'.madeline');

$theme = 0;
$ua = '';
$iev = MP::getIEVersion();
if($iev > 0 && $iev < 4) $theme = 1;
$theme = MP::getSettingInt('theme', $theme);
$lng = MP::initLocale();
MP::cookie('theme', $theme, time() + (86400 * 365));
include 'themes.php';
Themes::setTheme($theme);

function base64_encode_url($string) {
    return str_replace(['+','/','='], ['-','_',''], base64_encode($string));
}

function htmlStart() {
	global $lng;
	header("Content-Type: text/html; charset=utf-8");
	echo '<head><title>'.MP::x($lng['login']).'</title>';
	echo Themes::head();
	echo '</head>';
	echo Themes::bodyStart();
}

$MP = null;
if($logout && !$nouser) {
	$nouser = true;
	$logout = true;
	MP::delcookie('user');
	MP::delcookie('code');
	try {
		// Remove all session files
		if(file_exists(sessionspath.$user.'.madeline')) {
			try {
				if(PHP_OS_FAMILY === "Linux") {
					exec('kill -9 `ps -ef | grep -v grep | grep '.$user.'.madeline | awk \'{print $2}\'`');
				}
			} catch (Exception $e) {
			}
			MP::deleteSessionFile($user);
		}
	} catch (Exception $e) {
		echo $e;
	}
}
if($user === null || $nouser) {
	if($confirm === null || empty($confirm) || !isset($_SESSION['captcha']) || strtolower($confirm) !== $_SESSION['captcha']) {
		unset($_SESSION['captcha']);
		htmlStart();
		echo 'CAPTCHA:<br>';
		echo '<p><img src="captcha.php?r='.time().'"></p>';
		echo '<form action="qrlogin.php"'.($post?' method="post"':'').'>';
		echo '<input type="text" value="" name="confirm">';
		echo '<input type="submit">';
		echo '</form>';
		echo Themes::bodyEnd();
		die;
	} else {
		unset($_SESSION['captcha']);
		$user = 'qr_'.hash('sha384', sha1(random_bytes(32).rand(0,1000)).sha1(random_bytes(32)));
		MP::cookie('user', $user, time() + (86400 * 365));
		$MP = MP::getMadelineAPI($user, true);
	}
} else {
	$MP = MP::getMadelineAPI($user, true);
}
$qr = $MP->qrLogin();
if(!$qr) {
	if(isset($_POST['pass']) || isset($_GET['pass'])) {
		// 2fa check
		try {
			$password = null;
			if(isset($_POST['pass'])) {
				$password = $_POST['pass'];
			} elseif(isset($_GET['pass'])) {
				$password = $_GET['pass'];
			}
			$MP->complete2faLogin($password);
			unset($_SESSION['qr_token']);
			MP::cookie('code', '1', time() + (86400 * 365));
			header('Location: chats.php');
			die;
		} catch (Exception $e) {
			if(strpos($e->getMessage(), 'PASSWORD_HASH_INVALID') !== false) {
				htmlStart();
				echo MP::x($lng['pass_code']).':<br>';
				echo '<form action="qrlogin.php"'.($post?' method="post"':'').'>';
				echo '<input type="text" name="pass">';
				//if($phone !== null)
				//	echo '<input type="hidden" name="phone" value="'.$phone.'">';
				echo '<input type="submit">';
				echo '</form>';
				echo '<b>'.MP::x($lng['password_hash_invalid']).'</b><br>';
				echo Themes::bodyEnd();
				die;
			} else {
				echo '<xmp>';
				echo $e;
				echo '</xmp>';
				die;
			}
			die;
		}
	}
	if ($MP->getAuthorization() === \danog\MadelineProto\API::WAITING_PASSWORD) {
		// 2fa start
		htmlStart();
		echo MP::x($lng['pass_code']).':<br>';
		echo '<form action="qrlogin.php"'.($post?' method="post"':'').'>';
		echo '<input type="text" name="pass">';
		//if($phone !== null)
		//	echo '<input type="hidden" name="phone" value="'.$phone.'">';
		echo '<input type="submit">';
		echo '</form>';
		echo Themes::bodyEnd();
		die;
	}
	if(isset($_SESSION['qr_token'])) {
		unset($_SESSION['qr_token']);
		MP::cookie('user', $user, time() + (86400 * 365));
		MP::cookie('code', '1', time() + (86400 * 365));
	}
	header('Location: chats.php');
	die;
}
$qrtext = $qr->{'link'};
$_SESSION['qr_token'] = base64_encode($qrtext);
htmlStart();
echo $qrtext;
echo '<br><img src="qrcode.php"><br>';
echo '<a href="qrlogin.php?check">Check</a>';
echo Themes::bodyEnd();