<?php
define('sessionspath', '');

header("Content-Type: text/html; charset=utf-8");
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$user = null;
if(isset($_COOKIE['user']))
	$user = $_COOKIE['user'];
if(!isset($user) || empty($user)) {
	//не авторизирован, отправить в логинизацию
	header('Location: login.php');
	die();
}

if(!isset($_GET["peer"])) {
	die();
}
$id = $_GET["peer"];

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

set_error_handler('exceptions_error_handler');

if(isset($_GET["msg"])) {
	try {
		if (!file_exists('madeline.php')) {
			copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
		}
		include 'madeline.php';
		$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline');
		$MP->start();
		$MP->messages->sendMessage(['peer' => $id, 'message' => $_GET["msg"]]);
		header('Location: chat.php?peer='.$id);
	} catch (Exception $e) {
		echo $e->getMessage();
	}
	die();
}

echo '<head><title>Письмо</title></head><body>';
echo '<a href="chat.php?peer='.$id.'">Назад</a>';
echo '<h2>Письмо</h2>';
echo '<form action="">';
echo '<input type="hidden" name="peer" value="'.$id.'">';
echo '<textarea name="msg" cols="25" rows="2"></textarea></p>';
echo '<input type="submit">';
echo '</form>';
echo '</body>';
?>