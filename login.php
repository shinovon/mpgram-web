<?php

include 'redirect.php';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';

MP::startSession();

if(!defined('LOGIN_CAPTCHA')) define('LOGIN_CAPTCHA', true);

$theme = 0;
$ua = '';
$iev = MP::getIEVersion();
if($iev > 0 && $iev < 4) $theme = 1;
$theme = MP::getSettingInt('theme', $theme, true);
$post = (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Series60/3') === false) || ($iev < 4 && $iev == 0);

$lng = MP::initLocale();
//MP::cookie('theme', $theme, time() + (86400 * 365));

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

set_error_handler('exceptions_error_handler');

include 'themes.php';
Themes::setTheme($theme);

$revoked = isset($_GET['revoked']);
$logout = false;
$wrong = isset($_GET['wrong']);

$user = null;
$nouser = true;

$phone = $_GET['phone'] ?? $_POST['phone'] ?? null;

$user = MP::getUser();

$ipass = $_GET['ipass'] ?? $_POST['ipass'] ?? null;

// Check session existance
$nouser = $user == null || $user === false || empty($user) || strlen($user) < 32 || strlen($user) > 200 || !file_exists(sessionspath.$user.'.madeline');
function removeSession($logout=false) {
	global $user;
	MP::delcookie('user');
	MP::delcookie('code');
	try {
		// Remove all session files
		if(file_exists(sessionspath.$user.'.madeline')) {
			if($logout) {
				try {
					$MP = MP::getMadelineAPI($user, true);
					$MP->logout();
					unset($MP);
				} catch (Exception) {}
			}
			try {
				if(PHP_OS_FAMILY === "Linux") {
					exec('kill -9 `ps -ef | grep -v grep | grep '.$user.'.madeline | awk \'{print $2}\'`');
				}
			} catch (Exception) {}
			MP::deleteSessionFile($user);
		}
	} catch (Exception $e) {
		echo $e;
	}
}

function htmlStart() {
	global $lng;
	header("Content-Type: text/html; charset=utf-8");
	echo '<head><title>'.MP::x($lng['login']).'</title>';
	echo Themes::head();
	// определение часового пояса
	$iev = MP::getIEVersion();
	if($iev == 0 || $iev > 4) {
		$dtz = new DateTimeZone(date_default_timezone_get());
		$t = new DateTime('now', $dtz);
		$tof = $dtz->getOffset($t);
		echo '<script type="text/javascript"><!--
try {
	var d = new Date();
	var c = ((d.getTime()+'.($tof*1000).')-(d.getTime()-(d.getTimezoneOffset()*60*1000)))/1000 | 0;
	var e = new Date();
	e.setTime(e.getTime() + (365*86400*1000));
	document.cookie = "timeoff=" + c + "; expires="+e.toUTCString()+"; path=/";
} catch (e) {
}
//--></script>';
	}
	echo '</head>';
	echo Themes::bodyStart('style="margin:5px"');
	echo '<h1>MPGram Web</h1>';
}

if((isset($_GET['logout']) || $revoked || $wrong) && !$nouser) {
	$logout = true;
	$nouser = true;
	removeSession(($_GET['logout'] ?? '') == '2');
	$user = null;
}

$MP = null;
if($user != null && !$logout && !$nouser) {
	// Already logged in
	if(isset($_COOKIE['code']) && !empty($_COOKIE['code'])) {
		header('Location: chats.php');
		die;
	} else {
		$MP = MP::getMadelineAPI($user, true);
		if($MP->getAuthorization() === 3) {
			MP::cookie('code', '1', time() + (86400 * 365));
			header('Location: chats.php');
			die;
		}
		if($phone === null) {
			unset($MP);
			removeSession();
		}
	}
}
if(defined('INSTANCE_PASSWORD') && INSTANCE_PASSWORD !== null) {
	if($ipass === null || $ipass != INSTANCE_PASSWORD) {
		htmlStart();
		echo 'Instance password:<br>';
		echo '<form action="login.php"';
		if($post) echo ' method="post"';
		echo '>';
		echo '<input type="text" value="" name="ipass">';
		echo '<input type="submit">';
		echo '</form>';
		if($ipass !== null) echo '<b>Wrong password</b>';
		die;
	}
}
if($phone !== null) {
	$p = $phone;
	if(empty($p) || strlen($p) < 10 || !is_numeric(str_replace('-','',str_replace('+','', $p)))) {
		header('Location: login.php?wrong=number');
		die;
	}
	if(!isset($_SESSION['captcha_entered']) && LOGIN_CAPTCHA) {
		if(!isset($_POST['c']) && !isset($_GET['c'])) {
			htmlStart();
			echo 'CAPTCHA:<br>';
			echo '<p><img src="captcha.php?r='.time().'"></p>';
			echo '<form action="login.php"';
			if($post) echo ' method="post"';
			echo '>';
			if(isset($_GET['code']))
				echo "<input type=\"hidden\" name=\"code\" value=\"{$_GET['code']}\">";
			elseif(isset($_POST['code']))
				echo "<input type=\"hidden\" name=\"code\" value=\"{$_POST['code']}\">";
			if($phone !== null)
				echo "<input type=\"hidden\" name=\"phone\" value=\"{$phone}\">";
			if($ipass !== null)
				echo "<input type=\"hidden\" name=\"ipass\" value=\"{$ipass}\">";
			echo '<input type="text" name="c">';
			echo '<input type="submit">';
			echo '</form>';
			echo MP::x('<a href="login.php?logout=2">'.$lng['logout'].'</a>');
			echo Themes::bodyEnd();
			die;
		} else {
			$c = null;
			if(isset($_POST['c'])) {
				$c = $_POST['c'];
			} elseif(isset($_GET['c'])) {
				$c = $_GET['c'];
			}
			$b = isset($_SESSION['captcha']);
			if(!$b || strtolower($c) !== $_SESSION['captcha']) {
				htmlStart();
				if($b) unset($_SESSION['captcha']);
				echo 'CAPTCHA:<br>';
				echo '<p><img src="captcha.php"></p>';
				echo '<form action="login.php"';
				if($post) echo ' method="post"';
				echo '>';
				if(isset($_GET['code']))
					echo "<input type=\"hidden\" name=\"code\" value=\"{$_GET['code']}\">";
				elseif(isset($_POST['code']))
					echo "<input type=\"hidden\" name=\"code\" value=\"{$_POST['code']}\">";
				if($phone !== null)
					echo "<input type=\"hidden\" name=\"phone\" value=\"{$phone}\">";
				if($ipass !== null)
					echo "<input type=\"hidden\" name=\"ipass\" value=\"{$ipass}\">";
				echo '<input type="text" name="c">';
				echo '<input type="submit">';
				echo '</form>';
				if($b) echo '<b>'.MP::x($lng['wrong_captcha']).'</b>';
				echo Themes::bodyEnd();
				die;
			}
			$_SESSION['captcha_entered'] = 1;
		}
	}
	if(!isset($user) || $nouser) {
		$SESSION['user'] = $user = rtrim(strtr(base64_encode(hash('sha384', sha1(md5($phone.rand(0,1000).random_bytes(6))).random_bytes(30), true)), '+/', '-_'), '=');
		MP::cookie('user', $user, time() + (86400 * 365));
		$MP = MP::getMadelineAPI($user, true);
		htmlStart();
	} else {
		if(isset($_COOKIE['code']) && !empty($_COOKIE['code'])) {
			unset($_SESSION['captcha_entered']);
			header('Location: chats.php');
			die;
		} elseif(isset($_POST['pass']) || isset($_GET['pass'])) {
			$MP = MP::getMadelineAPI($user, true);
			try {
				$password = $_POST['pass'] ?? $_GET['pass'] ?? null;
				$MP->complete2faLogin($password);
				MP::cookie('code', '1', time() + (86400 * 365));
				header('Location: chats.php');
				die;
			} catch (Exception $e) {
				if(strpos($e->getMessage(), 'PASSWORD_HASH_INVALID') !== false) {
					htmlStart();
					echo MP::x($lng['pass_code']).':<br>';
					echo '<form action="login.php"';
					if($post) echo ' method="post"';
					echo '>';
					echo '<input type="text" name="pass">';
					if($phone !== null)
						echo "<input type=\"hidden\" name=\"phone\" value=\"{$phone}\">";
					if($ipass !== null)
						echo "<input type=\"hidden\" name=\"ipass\" value=\"{$ipass}\">";
					echo '<input type="submit">';
					echo '</form>';
					echo '<b>'.MP::x($lng['password_hash_invalid']).'</b><br>';
					echo Themes::bodyEnd();
					die;
				} elseif(strpos($e->getMessage(), 'AUTH_RESTART') !== false/* || strpos($e->getMessage(), 'I\'m not waiting') !== false*/) {
				} else {
					echo '<xmp>';
					echo $e;
					echo '</xmp>';
					die;
				}
			}
		} elseif(isset($_POST['code']) || isset($_GET['code'])) {
			$code = $_POST['code'] ?? $_GET['code'] ?? null;
			if(!empty($code) && is_numeric($code)) {
				try {
					$MP = MP::getMadelineAPI($user, true);
					$a = $MP->completePhoneLogin($code);
					$hash = null;
					if(isset($a['phone_code_hash'])) {
						$hash = $a['phone_code_hash'];
					}
					if(isset($a['_']) && $a['_'] === 'account.noPassword') {
						htmlStart();
						echo '<b>'.MP::x($lng['no_pass_code']).'</b>';
						echo Themes::bodyEnd();
						die;
					} elseif(isset($a['_']) && $a['_'] === 'account.password') {
						htmlStart();
						echo MP::x($lng['pass_code']).':<br>';
						echo '<form action="login.php"';
						if($post) echo ' method="post"';
						echo '>';
						echo '<input type="text" name="pass">';
						if($phone !== null)
							echo "<input type=\"hidden\" name=\"phone\" value=\"{$phone}\">";
						if($ipass !== null)
							echo "<input type=\"hidden\" name=\"ipass\" value=\"{$ipass}\">";
						echo '<input type="submit">';
						echo '</form>';
						echo Themes::bodyEnd();
						die;
					} elseif(isset($a['_']) && $a['_'] === 'account.needSignup') {
						htmlStart();
						echo MP::x($lng['need_signup']);
						echo Themes::bodyEnd();
						die;
					} else {
						MP::cookie('code', '1', time() + (86400 * 365));
						header('Location: chats.php');
						die;
					}
				} catch (Exception $e) {
					htmlStart();
					if(strpos($e->getMessage(), 'PHONE_CODE_INVALID') !== false) {
						echo '<b>'.MP::x($lng['phone_code_invalid']).'</b><br>';
					} elseif(strpos($e->getMessage(), 'PHONE_CODE_EXPIRED') !== false) {
						echo '<b>'.MP::x($lng['phone_code_expired']).'</b><br>';
					} elseif(strpos($e->getMessage(), 'AUTH_RESTART') !== false) {
						unset($hash);
					} else {
						echo '<b>'.MP::x($lng['error']).'</b><br>';
						echo $e->getMessage();
						echo Themes::bodyEnd();
						die;
					}
				}
			} else {
				echo MP::x($lng['phone_code']).':<br>';
				echo '<form action="login.php"';
				if($post) echo ' method="post"';
				echo '>';
				echo '<input type="text" name="code">';
				if($phone !== null)
					echo "<input type=\"hidden\" name=\"phone\" value=\"{$phone}\">";
				if($ipass !== null)
					echo "<input type=\"hidden\" name=\"ipass\" value=\"{$ipass}\">";
				echo '<input type="submit">';
				echo '</form>';
				echo Themes::bodyEnd();
				die;
			}
		} else {
			$MP = MP::getMadelineAPI($user, true);
			htmlStart();
		}
	}
	// ввод кода
	if(isset($hash)) {
		try {
			$MP->auth->resendCode(['phone' => $phone, 'phone_code_hash' => $hash]);
		} catch (Exception $e) {
			echo $e->getMessage();
			echo Themes::bodyEnd();
			die;
		}
	} else {
		try {
			$MP->phoneLogin($phone);
		} catch (Exception $e) {
			if(strpos($e->getMessage(), 'PHONE_NUMBER_INVALID') !== false) {
				header('Location: login.php?wrong=number');
				die;
			} else {
				echo $e->getMessage();
				echo Themes::bodyEnd();
				die;
			}
		}
	}
	echo MP::x($lng['phone_code']).':<br>';
	echo '<form action="login.php"';
	if($post) echo ' method="post"';
	echo '>';
	echo '<input type="text" name="code">';
	if($phone !== null)
		echo "<input type=\"hidden\" name=\"phone\" value=\"{$phone}\">";
	if($ipass !== null)
		echo "<input type=\"hidden\" name=\"ipass\" value=\"{$ipass}\">";
	echo '<input type="submit">';
	echo '</form>';
	echo Themes::bodyEnd();
} else {
	// ввод телефона
	htmlStart();
	//if($revoked) {
	//	echo MP::x('<b>Ваша сессия истекла!</b><br>');
	//}
	echo MP::x($lng['phone_number']).':<br>';
	echo '<form action="login.php"';
	if($post) echo ' method="post"';
	echo '>';
	echo '<input type="text" value="" name="phone">';
	echo '<input type="submit">';
	if($ipass !== null)
		echo '<input type="hidden" name="ipass" value="'.$ipass.'">';
	echo '</form>';
	if($wrong) {
		echo '<b>'.MP::x($lng['wrong_number_format']).'</b><br>';
	} else {
		echo '<a href="qrlogin.php">'.MP::x($lng['qr_login']).'</a> (experimental)';
	}
	echo '<br><div>';
	echo '<a href="about.php">'.MP::x($lng['about']).'</a> <a href="login.php?lang=en">English</a> <a href="login.php?lang=ru">'.MP::x('Русский').'</a>';
	//echo ' <a href="sets.php">'.$lng['settings'].'</a>';
	echo '</div>';
	echo Themes::bodyEnd();
}
