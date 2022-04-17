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

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

set_error_handler('exceptions_error_handler');

$avas = false;
try {
	include 'madeline.php';
	$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline');
	$MP->start();
	echo "<head><title>Чаты</title></head><body>";
	
	try {
		$dialogs = $MP->getFullDialogs();
		$c = 0;
		foreach(array_reverse($dialogs) as $dialogo){
			if($c == 15) {
				break;
			} else {
				$c += 1;
			}
			if(isset($dialogo["peer"]["user_id"])){
				// лс
				$id = $dialogo["peer"]["user_id"];
				$chat = $MP->getInfo($id);
				$ava = null;
				if(isset($chat["User"]["username"])){
					if($avas) preg_match('/<meta property="og:image" content="([\s\S]+?)">/', file_get_contents('https://t.me/'.$chat["User"]["username"]), $ava);
					//$id = $chat["User"]["username"];
				}
				echo '<div></div><div><a href="chat.php?peer='.$id.'">'.
				($ava != null && $avas ? '<img width="32px" src="'.$ava[1].'"/>' : '').
				(isset($chat["User"]["first_name"]) ? $chat["User"]["first_name"] : '').
				'</a>'.
				($dialogo["unread_count"] > 0 ? ' <b>+'.$dialogo["unread_count"].'</b>' : '').
				'</div>';
			} else if(isset($dialogo["peer"]["chat_id"])){
				// чат
				$id = '-'.$dialogo["peer"]["chat_id"];
				$chat = $MP->getInfo($id);
				echo '<div><a href="chat.php?peer='.$id.'">'.
				$chat["Chat"]["title"].
				'</a>'.
				($dialogo["unread_count"] > 0 ? ' <b>+'.$dialogo["unread_count"].'</b>' : '').
				'</div>';
			} else {
				// канал
				$id = '-100'.$dialogo["peer"]["channel_id"];
				$chat = $MP->getInfo($id);
				$ava = null;
				if(isset($chat["Chat"]["username"]) && !$avas){
					if($avas) preg_match('/<meta property="og:image" content="([\s\S]+?)">/', file_get_contents('https://t.me/'.$chat["Chat"]["username"]), $ava);
					//$id = $chat["Chat"]["username"];
				}
				echo '<div></div><div><a href="chat.php?peer='.$id.'">'.
				($ava != null && $avas ? '<img width="32px" src="'.$ava[1].'"/>' : '').
				' '.$chat["Chat"]["title"].
				'</a>'.
				($dialogo["unread_count"] > 0 ? ' <b>+'.$dialogo["unread_count"].'</b>' : '').
				'</div>';
			}
		}
	} catch (Exception $e) {
		echo "<b>Ошибка!</b><br>";
		echo $e->getMessage();
		//echo str_replace("\n", "<br>", str_replace("\r", "", strval($e)));
	}
	echo "<head><title>Чаты</title></head></body>";
} catch (Exception $e) {
	echo $e->getMessage();
}