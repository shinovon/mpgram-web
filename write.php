<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';
$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die();
}
$id = null;
if(isset($_POST['c'])) {
	$id = $_POST['c'];
} else if(isset($_GET['c'])) {
	$id = $_GET['c'];
} else {
	die();
}
$id = $_POST["c"];

header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: private, no-cache, no-store");

function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');
$msg = null;
if(isset($_POST["msg"])) {
	$msg = $_POST["msg"];
} else if(isset($_GET["msg"])) {
	$msg = $_GET["msg"];
}
if($msg !== null) {
	try {
		$MP = MP::getMadelineAPI($user);
		$MP->messages->sendMessage(['peer' => $id, 'message' => $msg]);
		header('Location: chat.php?c='.$id);
	} catch (Exception $e) {
		echo $e->getMessage();
	}
	die();
}

$name = null;
if(isset($_GET['n'])) {
	$name = $_GET['n'];
}
echo '<head><title>Письмо</title></head><body>';
//echo '<h2>Написать '.$name.'</h2>';
//echo '<h2>Письмо</h2>';
echo '<form action="write.php" method="post">';
echo '<input type="hidden" name="c" value="'.$id.'">';
echo '<input type="text" value="" name="msg"><br>';
echo '<input type="submit">';
echo '</form>';
if($name) {
	echo '<b>'.$name.'</b><br>';
}
echo '<a href="chat.php?c='.$id.'">Назад</a>';
echo '</body>';
//} catch (Exception $e) {
//	echo $e->getMessage();
//}
?>