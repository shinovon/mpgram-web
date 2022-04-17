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
if (!file_exists('madeline.php')) {
    copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}

if(!isset($_GET["peer"])) {
	die();
}
$id = $_GET["peer"];

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

set_error_handler('exceptions_error_handler');

try {
	include 'madeline.php';
	$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline');
	$MP->start();
	$info=$MP->getInfo($id);
	$name = null;
	$pm = false;
	
	if(isset($info['Chat']) && isset($info['Chat']['title'])) {
		$name = $info['Chat']['title'];
	} else if(isset($info['User']) && isset($info['User']['first_name'])) {
		$name = $info['User']['first_name'];
		$pm = true;
	}
	
	echo '<head><title>'.$name.'</title></head><body>';
	echo '<a href="chats.php">Назад</a>';
	echo ' <a href="chat.php?peer='.$id.'&upd=1">Обновить</a><br>';
	echo '<h2>'.$name.'</h2>';
	
	$r = $MP->messages->getHistory([
	'peer' => $id,
	'offset_id' => 0,
	'offset_date' => 0,
	'add_offset' => 0,
	'limit' => 20,
	'max_id' => 0,
	'min_id' => 0,
	'hash' => 0]);
	
	$rm = $r['messages'];
	foreach($rm as $m) {
		try {
			$date = date("H:i:s", $m['date']);
			$mname = null;
			if($m['out'] == true) {
				$mname = 'Вы';
			} else if($pm) {
				$mname = $name;
			//TODO: имена в чате
			} else if(isset($m['from_id']['user_id'])) {
				$mname = $m['from_id']['user_id'];
			} else if(isset($m['from_id']['chat_id'])) {
				$mname = $m['from_id']['chat_id'];
			} else if(isset($m['from_id']['channel_id'])) {
				$mname = $m['from_id']['channel_id'];
			} else {
				$mname = var_export($m['from_id'], true);
			}
			echo '<div>';
			echo '<b>'.$mname.'</b> ('.$date.'):';
			if(isset($m['message'])) {
				echo '<p>'.str_replace("\n", "<br>", $m['message']).'</p>';
			}
			//TODO: файлы
			echo '</div>';
		} catch (Exception $e) {
			echo $e->getMessage();
		}
	}
	echo '</body>';
} catch (Exception $e) {
	echo $e->getMessage();
}
?>