<?php

include 'redirect.php';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
if(isset($_GET['logout'])) {
	$_SESSION = array();
}

include 'mp.php';

$theme = 0;
$ua = '';
$iev = MP::getIEVersion();
if($iev > 0 && $iev < 4) $theme = 1;
$theme = MP::getSettingInt('theme', $theme);
$post = isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Series60/3') === false;

$lng = MP::initLocale();
MP::cookie('theme', $theme, time() + (86400 * 365));

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

$phone = null;
if(isset($_GET['phone'])) {
	$phone = $_GET['phone'];
} else if(isset($_POST['phone'])) {
	$phone = $_POST['phone'];
}

if(isset($_COOKIE['user']))
	$user = $_COOKIE['user'];
else if(isset($_SESSION['user']))
	$user = $_SESSION['user'];
// Check session existance
$nouser = $user == null || empty($user) || strlen($user) != 32 || !file_exists(sessionspath.$user.'.madeline');
if((isset($_GET['logout']) || $revoked || $wrong) && !$nouser) {
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
function htmlStart() {
	header("Content-Type: text/html; charset=utf-8");
	echo '<head><title>'.MP::x(Locale::$lng['login']).'</title>';
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
	echo Themes::bodyStart();
	echo '<h1>MPGram Web</h1>';
}
$MP = null;
if($user != null
	&& isset($_COOKIE['code'])
	&& !empty($_COOKIE['code'])
	&& !$logout
	&& !$nouser
	) {
	// уже авторизован
	header('Location: chats.php');
	die();
} else if($phone !== null) {
	$p = $phone;
	if(empty($p) || strlen($p) < 10 || !is_numeric(str_replace('-','',str_replace('+','', $p)))) {
		header('Location: login.php?wrong=number');
		die();
	}
	if(!isset($_SESSION['captcha_entered'])) {
		if(!isset($_POST['c']) && !isset($_GET['c'])) {
			htmlStart();
			echo 'CAPTCHA:<br>';
			echo '<p><img src="captcha.php?r='.time().'"></p>';
			echo '<form action="login.php"'.($post?' method="post"':'').'>';
			if(isset($_GET['code']))
				echo '<input type="hidden" name="code" value="'.$_GET['code'].'">';
			else if(isset($_POST['code']))
				echo '<input type="hidden" name="code" value="'.$_POST['code'].'">';
			if($phone !== null)
				echo '<input type="hidden" name="phone" value="'.$phone.'">';
			echo '<input type="text" name="c">';
			echo '<input type="submit">';
			echo '</form>';
			echo Themes::bodyEnd();
			die();
		} else {
			$c = null;
			if(isset($_POST['c'])) {
				$c = $_POST['c'];
			} else if(isset($_GET['c'])) {
				$c = $_GET['c'];
			}
			$b = isset($_SESSION['captcha']);
			if(!$b || strtolower($c) !== $_SESSION['captcha']) {
				htmlStart();
				if($b) unset($_SESSION['captcha']);
				echo 'CAPTCHA:<br>';
				echo '<p><img src="captcha.php"></p>';
				echo '<form action="login.php"'.($post?' method="post"':'').'>';
				if(isset($_GET['code']))
					echo '<input type="hidden" name="code" value="'.$_GET['code'].'">';
				else if(isset($_POST['code']))
					echo '<input type="hidden" name="code" value="'.$_POST['code'].'">';
				if($phone !== null)
					echo '<input type="hidden" name="phone" value="'.$phone.'">';
				echo '<input type="text" name="c">';
				echo '<input type="submit">';
				echo '</form>';
				if($b) echo '<b>Wrong!</b>';
				echo Themes::bodyEnd();
				die();
			}
			$_SESSION['captcha_entered'] = 1;
		}
	}
	if(!isset($user) || $nouser) {
		$user = md5($phone.rand(0,1000));
		MP::cookie('user', $user, time() + (86400 * 365));
		$MP = MP::getMadelineAPI($user, true);
		htmlStart();
	} else {
		if(isset($_COOKIE['code']) && !empty($_COOKIE['code'])) {
			unset($_SESSION['captcha_entered']);
			header('Location: chats.php');
			die();
		} else if(isset($_POST['pass']) || isset($_GET['pass'])) {
			$MP = MP::getMadelineAPI($user);
			try {
				$p = null;
				if(isset($_POST['pass'])) {
					$p = $_POST['pass'];
				} else if(isset($_GET['pass'])) {
					$p = $_GET['pass'];
				}
				$a = $MP->complete2falogin($p);
				MP::cookie('code', '1', time() + (86400 * 365));
				header('Location: chats.php');
				die();
			} catch (Exception $e) {
				echo '<xmp>';
				echo $e;
				echo '</xmp>';
			}
			die();
		} else if(isset($_POST['code']) || isset($_GET['code'])) {
			$code = null;
			if(isset($_POST['code'])) {
				$code = $_POST['code'];
			} else if(isset($_GET['code'])) {
				$code = $_GET['code'];
			}
			if(!empty($code) && is_numeric($code)) {
				try {
					$MP = MP::getMadelineAPI($user);
					$a = $MP->complete_phone_login((int)$code);
					$hash = null;
					if(isset($a['phone_code_hash'])) {
						$hash = $a['phone_code_hash'];
					}
					//TODO: ошибки
					if(isset($a['_']) && $a['_'] === 'account.noPassword') {
						htmlStart();
						echo '<b>No pass-code set!</b>';
						echo Themes::bodyEnd();
						die();
					} else if(isset($a['_']) && $a['_'] === 'account.password') {
						htmlStart();
						echo 'Pass-code:<br>';
						echo '<form action="login.php"'.($post?' method="post"':'').'>';
						echo '<input type="text" name="pass">';
						if($phone !== null)
							echo '<input type="hidden" name="phone" value="'.$phone.'">';
						echo '<input type="submit">';
						echo '</form>';
						echo Themes::bodyEnd();
						die();
					} else if(isset($a['_']) && $a['_'] === 'account.needSignup') {
						htmlStart();
						/*
						echo 'REGISTER:<br>';
						echo '<form action="reg.php" method="post">';
						echo '<b>First name</b><br>';
						echo '<input type="text" name="first_name">';
						echo '<b>Last name</b><br>';
						echo '<input type="text" name="last_name">';
						echo '<input type="submit">';
						echo '</form>';
						*/
						echo 'account.needSignup';
						echo Themes::bodyEnd();
						die();
					} else {
						MP::cookie('code', '1', time() + (86400 * 365));
						header('Location: chats.php');
						die();
					}
				} catch (Exception $e) {
					htmlStart();
					if(strpos($e->getMessage(), 'PHONE_CODE_INVALID') !== false) {
						echo MP::x('<b>'.MP::x($lng['phone_code_invalid']).'</b><br>');
					} else if(strpos($e->getMessage(), 'PHONE_CODE_EXPIRED') !== false) {
						echo MP::x('<b>'.MP::x($lng['phone_code_expired']).'</b><br>');
					} else if(strpos($e->getMessage(), 'AUTH_RESTART') !== false) {
						unset($hash);
					} else {
						echo MP::x('<b>'.MP::x($lng['error']).'</b><br>');
						echo $e->getMessage();
						echo Themes::bodyEnd();
						die();
					}
				}
			}
		} else {
			$MP = MP::getMadelineAPI($user);
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
			die();
		}
	} else {
		try {
			$MP->phone_login($phone);
		} catch (Exception $e) {
			if(strpos($e->getMessage(), 'PHONE_NUMBER_INVALID') !== false) {
				header('Location: login.php?wrong=number');
				die();
			} else {
				echo $e->getMessage();
				echo Themes::bodyEnd();
				die();
			}
		}
	}
	echo MP::x($lng['phone_code']).':<br>';
	echo '<form action="login.php"'.($post?' method="post"':'').'>';
	echo '<input type="text" name="code">';
	if($phone !== null)
		echo '<input type="hidden" name="phone" value="'.$phone.'">';
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
	echo '<form action="login.php"'.($post?' method="post"':'').'>';
	echo '<input type="text" value="" name="phone">';
	echo '<input type="submit">';
	echo '</form>';
	if($wrong) {
		echo MP::x('<b>Wrong number format</b><br>');
	}
	echo '<br><div>';
	echo MP::x('<a href="about.php">'.$lng['about'].'</a> <a href="login.php?lang=en">English</a> <a href="login.php?lang=ru">Русский</a>');
	//echo ' <a href="sets.php">'.$lng['settings'].'</a>';
	echo '</div>';
	echo Themes::bodyEnd();
}
?>
