<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';
MP::startSession();
$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die;
}

$id = $_POST['c'] ?? $_GET['c'] ?? die;
$msg = $_POST["msg"] ?? $_GET["msg"] ?? null;
$random = $_POST['r'] ?? $_GET['r'] ?? null;

header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: private, no-cache, no-store");

function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');
if($msg !== null) {
	if(isset($random)) {
		if(isset($_SESSION['random']) && $_SESSION['random'] == $random) {
			header('Location: chat.php?c='.$id);
			die;
		}
		$_SESSION['random'] = $random;
	}
	try {
		$MP = MP::getMadelineAPI($user);
		if(isset($_GET["format"]) || isset($_POST["format"])) {
			$MP->messages->sendMessage(['peer' => $id, 'message' => $msg, 'parse_mode' => 'HTML']);
		} else {
			$MP->messages->sendMessage(['peer' => $id, 'message' => $msg]);
		}
	} catch (Exception $e) {
	//	echo $e->getMessage();
	}
	header('Location: chat.php?c='.$id);
	die;
}

$name = $_GET['n'] ?? null;
echo '<body>';
echo '<form action="write.php" method="post">';
echo '<input type="hidden" name="c" value="'.$id.'">';
echo '<input type="text" value="" name="msg"><br>';
echo '<input type="hidden" name="r" value="'. \base64_encode(random_bytes(16)).'">';
echo '<input type="submit">';
echo '</form>';
if($name) {
	echo '<b>'.$name.'</b><br>';
}
echo '<a href="chat.php?c='.$id.'">Back</a>';
echo '</body>';
