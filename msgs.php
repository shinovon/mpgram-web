<?php
if(!isset($_GET['id'])) die();
$i = intval($_GET['m']) ?? 0;
$limit = $_GET['l'] ?? 0;
include 'mp.php';
$user = MP::getUser();
if(!$user) die();
$id = $_GET['id'];
$timeoff = $_GET['t'] ?? 0;
$offset = $_GET['o'] ?? -1;
$timeout = $_GET['timeout'] ?? 30;
$longpoll = isset($_GET['l']);
$lng = MP::initLocale();

function printMsgs($MP, $msg, $update_id) {
	global $id;
	global $limit;
	global $lng;
	global $timeoff;
	$r = $MP->messages->getHistory([
	'peer' => $id,
	'offset_id' => 0,
	'offset_date' => 0,
	'add_offset' => 0,
	'limit' => 1,
	'max_id' => $msg['id']+1,
	'min_id' => $msg['id']-1,
	'hash' => 0]);
	$rm = $r['messages'];
	$info = $MP->getInfo($id);
	$name = null;
	$pm = false;
	$ch = false;
	if(isset($info['Chat'])) {
		$ch = isset($info['type']) && $info['type'] == 'channel';
		if(isset($info['Chat']['title'])) {
			$name = $info['Chat']['title'];
		}
	} else if(isset($info['User']) && isset($info['User']['first_name'])) {
		$name = $info['User']['first_name'];
		$pm = true;
	}
	$channel = isset($info['channel_id']);
	unset($info);
	//echo $rm[0]['id'].'||';
	echo $update_id.'||';
	MP::addUsers($r['users'], $r['chats']);
	MP::printMessages($MP, $rm, $id, $pm, $ch, $lng, true, $name, $timeoff, $channel, true);
	// Mark as read
	try {
		if($ch || (int)$id < 0) {
			$MP->channels->readHistory(['channel' => $id, 'max_id' => 0]);
		} else {
			$MP->messages->readHistory(['peer' => $id, 'max_id' => 0]);
		}
	} catch (Exception $e) {
	}
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-cache, no-store');
try {
	$MP = MP::getMadelineAPI($user);
	$time = microtime(true);
	if($longpoll) {
		while(true) {
			if(microtime(true) - $time >= $timeout) die();
			$updates = $MP->getUpdates(['offset' => $offset+1, 'limit' => 100, 'timeout' => 10]);
			foreach($updates as $update) {
				if($update['update_id'] == $i) continue;
				$type = $update['update']['_'];
				if($type == 'updateNewMessage' || $type == 'updateNewChannelMessage') {
					$msg = $update['update']['message'];
					if(MP::getId($MP, $msg['peer_id']) == $id) {
						if($msg['id'] < $i) continue;
						if($msg['id'] == $i) {
							$offset = $update['update_id'];
							continue;
						}
						printMsgs($MP, $msg, $update['update_id']);
						die();
					}
				}
				// TODO
				/*if($type == 'updateDeleteMessages') {
					$msgs = $update['update']['messages'];
				}*/
				$offset = $update['update_id'];
			}
		}
		return;
	}
	$r = $MP->messages->getHistory([
	'peer' => $id,
	'offset_id' => 0,
	'offset_date' => 0,
	'add_offset' => 0,
	'limit' => $limit,
	'max_id' => 0,
	'min_id' => $i,
	'hash' => 0]);
	$rm = $r['messages'];
	if(count($rm) == 0 || !isset($rm[0])) die();
	$info = $MP->getInfo($id);
	$name = null;
	$pm = false;
	$ch = false;
	if(isset($info['Chat'])) {
		$ch = isset($info['type']) && $info['type'] == 'channel';
		if(isset($info['Chat']['title'])) {
			$name = $info['Chat']['title'];
		}
	} else if(isset($info['User']) && isset($info['User']['first_name'])) {
		$name = $info['User']['first_name'];
		$pm = true;
	}
	$channel = isset($info['channel_id']);
	unset($info);
	echo $rm[0]['id'].'||';
	MP::addUsers($r['users'], $r['chats']);
	MP::printMessages($MP, $rm, $id, $pm, $ch, $lng, true, $name, $timeoff, $channel, true);
	// Mark as read
	try {
		if($ch || (int)$id < 0) {
			$MP->channels->readHistory(['channel' => $id, 'max_id' => 0]);
		} else {
			$MP->messages->readHistory(['peer' => $id, 'max_id' => 0]);
		}
	} catch (Exception $e) {
	}
	unset($rm);
	unset($r);
	MP::gc();
} catch (Exception $e) {
}
