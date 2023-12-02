<?php
include 'mp.php';
$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die;
}

$theme = MP::getSettingInt('theme');
$lng = MP::initLocale();

$id = $_POST['c'] ?? $_GET['c'] ?? die;
$msg = $_POST['m'] ?? $_GET['m'] ?? die;
$data = $_POST['d'] ?? $_GET['d'] ?? die;

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
die;
