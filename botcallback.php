<?php
include 'mp.php';
$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die();
}

$theme = MP::getSettingInt('theme');
$lng = MP::initLocale();

$id = null;
if(isset($_POST['c'])) {
	$id = $_POST['c'];
} else if(isset($_GET['c'])) {
	$id = $_GET['c'];
} else {
	die();
}
$msg = null;
if(isset($_POST['m'])) {
	$msg = $_POST['m'];
} else if(isset($_GET['m'])) {
	$msg = $_GET['m'];
} else {
	die();
}
$data = null;
if(isset($_POST['d'])) {
	$data = $_POST['d'];
} else if(isset($_GET['d'])) {
	$data = $_GET['d'];
} else {
	die();
}

header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: private, no-cache, no-store");

function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');

try {
	$MP = MP::getMadelineAPI($user);
	$MP->messages->getBotCallbackAnswer(['peer' => $id, 'msg_id' => $msg, 'data' => base64_decode($data)]);
} catch (Exception $e) {
}
header('Location: chat.php?c='.$id);
die();
?>