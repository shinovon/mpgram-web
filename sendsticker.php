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

$theme = MP::getSettingInt('theme');
$lng = MP::initLocale();

$id = $_POST['c'] ?? $_GET['c'] ?? die;
$reply_to = $_POST['reply_to'] ?? $_GET['reply_to'] ?? null;

header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: private, no-cache, no-store");

function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');
include 'themes.php';
Themes::setTheme($theme);
try {
	$MP = MP::getMadelineAPI($user);
	echo '<head><title>'.MP::x($lng['send_message']).'</title>';
	echo Themes::head();
	echo '</head>';
	echo Themes::bodyStart();
	if(isset($_GET['id'])) {
		$MP = MP::getMadelineAPI($user);
		$params = ['peer' => $id, 'message' => ''];
		if($reply_to) {
			$params['reply_to_msg_id'] = $reply_to;
		}
		$params['media'] = ['_' => 'document', 'id' => (int) $_GET['id'], 'access_hash' => (int) $_GET['access_hash']];
		$MP->messages->sendMedia($params);
		header('Location: chat.php?c='.$id);
		die;
	} elseif(isset($_GET['set'])) {
		echo '<div><a href="sendsticker.php?c='.$id.($reply_to?'&reply_to='.$reply_to:'').'">'.MP::x($lng['back']).'</a></div><br>';
		$set = $_GET['set'];
		$sets = $MP->messages->getAllStickers()['sets'];
		$s2 = null;
		foreach($sets as $v) {
			if(strval($v['id']) == $set) {
				$s2 = $v;
				break;
			}
		}
		$documents = $MP->messages->getStickerSet(['stickerset' => ['_' => 'inputStickerSetID', 'id' => $s2['id'], 'access_hash' => $s2['access_hash']]])['documents'];
		echo '<b>'.MP::dehtml($s2['title']).'</b><br>';
		foreach($documents as $v) {
			echo '<a href="sendsticker.php?c='.$id.'&id='.$v['id'].'&access_hash='.$v['access_hash'].($reply_to?'&reply_to='.$reply_to:'').'"><img src="file.php?sticker='.$v['id'].'&access_hash='.$v['access_hash'].'&p=r'.(($v['mime_type'] ?? '') == 'application/x-tgsticker' ? 'tgss&s=100' : 'sprev').'"></a>';
		}
	} else {
	echo '<div><a href="chat.php?c='.$id.'">'.MP::x($lng['back']).'</a></div><br>';
		$sets = $MP->messages->getAllStickers()['sets'];
		foreach($sets as $v) {
			echo '<a href="sendsticker.php?c='.$id.'&set='.$v['id'].($reply_to?'&reply_to='.$reply_to:'').'">'.MP::x($v['title']).'</a><br>';
		}
	}
	echo Themes::bodyEnd();
} catch (Exception $e) {
	echo $e;
}
