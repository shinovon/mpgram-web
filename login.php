<?php
define('sessionspath', '');

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
header("Content-Type: text/html; charset=utf-8");

if (!file_exists('madeline.php')) {
    copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}

$settings['app_info']['api_id']=1488323;
$settings['app_info']['api_hash'] = '2005074e61d3fd226313e87c667453ef';
$user = null;
if(isset($_COOKIE['user']))
	$user = $_COOKIE['user'];
if($user != null && isset($_COOKIE['code']) && !empty($_COOKIE['code'])) {
	// уже авторизован
	header('Location: chats.php');
	die();
} else if(!isset($_GET['phone']) && $user == null ){
	// ввод телефона
	echo "<head><title>Логинизация</title></head>".
	'<body>Номер:<br>'.
	'<form action="">'.
	'<input type="text" name="phone">'.
	'<input type="submit">'.
	'</form></body>';
} else {
	include 'madeline.php';
	$MP = null;
	if(!isset($user) || empty($user)) {
		$user = md5($_GET['phone'].rand(0,1000));
		setcookie('user', $user, time() + (86400 * 365));
		$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline', $settings);
		echo '<head><title>Логинизация</title></head><body>';
	} else {
		if(isset($_GET['code'])) {
			// завершить авторизацию и открыть чаты
			try {
				$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline', $settings);
				$authorization = $MP->complete_phone_login((int)$_GET["code"]);
				$err = null;
				//TODO: ошибки
				if($err == null) {
					setcookie('code', '1', time() + (86400 * 365));
					header('Location: chats.php');
					die();
				} else {
					echo '<head><title>Логинизация</title></head><body>'.
					'<b>Ошибка</b><br>'.
					$err.
					'</body>';
					die();
				}
			} catch (Exception $e) {
				if(strpos($e->getMessage(), 'PHONE_CODE_INVALID') !== false) {
					echo '<head><title>Логинизация</title></head><body>'.
					'<b>Неправильный код!</b><br>';
				} else if(strpos($e->getMessage(), 'PHONE_CODE_EXPIDER') !== false) {
					echo '<head><title>Логинизация</title></head><body>';
					'<b>Код истек!</b><br>';
				} else {
					echo '<head><title>Логинизация</title></head><body>'.
					'<b>Ошибка</b><br>'.
					$e->getMessage().
					'</body>';
					die();
				}
			}
		} else {
			$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline', $settings);
			echo '<head><title>Логинизация</title></head><body>';
		}
	}
	// ввод кода
	$MP->phone_login($_GET["phone"]);
	echo 'Код:<br>'.
	'<form action="">'.
	'<input type="hidden" name="phone" value="'.$_GET["phone"].'">'.
	'<input type="text" name="code">'.
	'<input type="submit">'.
	'</form>'.
	'</body>';
}
?>