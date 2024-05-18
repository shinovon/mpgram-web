<?php
if(!isset($_GET['id'])) die;
$i = intval($_GET['m'] ?? 0);
$limit = $_GET['l'] ?? 0;
include 'mp.php';
$user = MP::getUser();
if(!$user) die;
$id = $_GET['id'];
$timeoff = $_GET['t'] ?? 0;
$offset = $_GET['o'] ?? -1;
$timeout = $_GET['timeout'] ?? 30;
$longpoll = isset($_GET['l']);
$old = isset($_GET['ol']);
$photosize = $_GET['ps'] ?? 0;
$lng = MP::initLocale();

function printMsgs($MP, $minmsg, $maxmsg, $minoffset, $maxoffset) {
	global $id;
	global $limit;
	global $lng;
	global $timeoff;
	$r = $MP->messages->getHistory([
	'peer' => $id,
	'offset_id' => 0,
	'offset_date' => 0,
	'add_offset' => 0,
	'limit' => 20,
	'max_id' => $maxmsg['id']+1,
	'min_id' => $minmsg['id']-1,
	'hash' => 0]);
	$rm = $r['messages'];
	$info = $MP->getInfo($id);
	$name = null;
	$pm = false;
	$ch = false;
	$ar = null;
	if(isset($info['Chat'])) {
		$ch = isset($info['type']) && $info['type'] == 'channel';
		$name = $info['Chat']['title'] ?? null;
		$ar = $info['Chat']['admin_rights'] ?? null;
	} elseif(isset($info['User'])) {
		$name = MP::removeEmoji($info['User']['first_name'] ?? $info['User']['last_name'] ?? null);
		$pm = true;
	}
	$channel = isset($info['channel_id']);
	unset($info);
	//echo $rm[0]['id'].'||';
	echo $maxoffset.'||';
	MP::addUsers($r['users'], $r['chats']);
	MP::printMessages($MP, $rm, $id, $pm, $ch, $lng, true, $name, $timeoff, $channel, true, $ar);
	// Mark as read
	try {
		if($ch || (int)$id < 0) {
			$MP->channels->readHistory(['channel' => $id, 'max_id' => $maxmsg['id']]);
		} else {
			$MP->messages->readHistory(['peer' => $id, 'max_id' => $maxmsg['id']]);
		}
	} catch (Exception $e) {
	}
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-cache, no-store');
header("Access-Control-Allow-Origin: *", true);

try {
	$MP = MP::getMadelineAPI($user);
	$time = microtime(true);
	if($longpoll) {
		$so = $offset;
		while(true) {
			if(microtime(true) - $time >= $timeout) die;
			$updates = $MP->getUpdates(['offset' => $offset+1, 'limit' => 100, 'timeout' => 10]);
			$minid = 0;
			$maxid = 0;
			$minmsg = null;
			$maxmsg = null;
			foreach($updates as $update) {
				if($update['update_id'] == $so) continue;
				$type = $update['update']['_'];
				$offset = $update['update_id'];
				if($type == 'updateNewMessage' || $type == 'updateNewChannelMessage') {
					$msg = $update['update']['message'];
					if($msg['peer_id'] != $id) continue;
					if($msg['id'] < $i) continue;
					if($msg['id'] == $i) continue;
					if($minid == 0) {
						$minid = $update['update_id'];
						$minmsg = $msg;
					}
					$maxid = $update['update_id'];
					$maxmsg = $msg;
				}
				// TODO
				/*if($type == 'updateDeleteMessages') {
					$msgs = $update['update']['messages'];
				}*/
			}
			if($minid != 0) {
				printMsgs($MP, $minmsg, $maxmsg, $minid, $maxid);
				die;
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
	if(count($rm) == 0 || !isset($rm[0])) die;
	$info = $MP->getInfo($id);
	$name = null;
	$pm = false;
	$ch = false;
	if(isset($info['Chat'])) {
		$ch = isset($info['type']) && $info['type'] == 'channel';
		if(isset($info['Chat']['title'])) {
			$name = $info['Chat']['title'];
		}
	} elseif(isset($info['User']) && isset($info['User']['first_name'])) {
		$name = $info['User']['first_name'];
		$pm = true;
	}
	$channel = isset($info['channel_id']);
	unset($info);
	echo $rm[0]['id'].'||';
	MP::addUsers($r['users'], $r['chats']);
	MP::printMessages($MP, $rm, $id, $pm, $ch, $lng, true, $name, $timeoff, $channel, true, false, false, $old, $photosize);
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
