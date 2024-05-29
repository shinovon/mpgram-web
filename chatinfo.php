<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';
$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die;
}

$theme = MP::getSettingInt('theme', 0);
$pngava = MP::getSettingInt('pngava', 0);

$id = $_POST['c'] ?? $_GET['c'] ?? die;

header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: private, no-cache, no-store");

function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');

try {
	$MP = MP::getMadelineAPI($user);

	$lng = MP::initLocale();

	header('Content-Type: text/html; charset=utf-8');
	header('Cache-Control: private, no-cache, no-store');

	include 'themes.php';
	Themes::setTheme($theme);

	$chat = $MP->getPwrChat($id);
	
	$name = $chat['title'] ?? (isset($chat['first_name']) ? $chat['first_name'] . (isset($chat['last_name']) ? ' '.$chat['last_name'] : '') : null) ?? 'Deleted Account';
	$type = $chat['type'];

	$desc = null;
	
	$fullinfo = $MP->getFullInfo($id)['full'] ?? null;
	$pin = $fullinfo['pinned_msg_id'] ?? false;

	if($type != 'user') {
		$desc = $fullinfo['about'] ?? null;
		$members = $chat['participants'] ?? null;
		$memberscount = $chat['participants_count'] ?? false;
		$onlines = 0;

		if($members) {
			foreach($members as $i => $m) {
				if(isset($m['kicked_by'])) {
					unset($members[$i]);
				} elseif(isset($m['user']) && isset($m['user']['status']) && $m['user']['status']['_'] == 'userStatusOnline') {
					$onlines ++;
				}
			}
		}
	}

	echo '<head><title>'.MP::dehtml($name).'</title>';
	echo Themes::head();
	echo '</head>';
	echo Themes::bodyStart();
	echo '<div>';
	echo '<div class="chr"><small><a href="chat.php?c='.$id.'">'.MP::x($lng['back']).'</a></small></div>';
	echo '<div class="cava"><img class="ri" src="ava.php?c='.$id.'&p='.($pngava?'rc':'r').'48"></div>';
	echo '<div>';
	echo MP::dehtml($name);
	echo '</div>';
	echo '<div>';
	if($type != 'user' && !empty($members)) {
		echo MP::x(MPLocale::number($type == 'chat' ? 'members' : 'subscribers', $memberscount !== false ? $memberscount : count($members)));
		if($onlines > 0) {
			echo ', ' . strval($onlines) . ' ' . MP::x($lng['online']);
		}
	}
	echo '</div>';
	echo '</div>';
	if($desc) {
		echo '<p>'.MP::x($lng['chat_about']).':<br>'.MP::dehtml($desc).'</p>';
	}
	if($type == 'user') {
		$desc = $MP->getFullInfo($id)['full']['about'] ?? null;
		if(isset($chat['phone'])) {
			echo '<p>'.MP::x($lng['chat_phone']).':<br>+'.MP::dehtml($chat['phone']).'</p>';
		}
		if($desc) {
			echo '<p>'.MP::x($lng['chat_bio']).':<br>'.MP::dehtml($desc).'</p>';
		}
		if(isset($chat['username'])) {
			echo '<p>'.MP::x($lng['chat_username']).':<br>'.MP::dehtml($chat['username']).'</p>';
		}
	} elseif(isset($chat['username'])) {
		echo '<p>'.MP::x($lng['chat_link']).':<br>t.me/'.MP::dehtml($chat['username']).'</p>';
	}
	if($pin) {
		try {
			$msg = $MP->messages->getHistory([
				'peer' => $id,
				'offset_id' => $pin,
				'offset_date' => 0,
				'add_offset' => -1,
				'limit' => 1,
				'hash' => 0])['messages'];
			echo '<p>';
			MP::printMessages($MP, $msg, $id, false, $type == 'channel', $lng, false, $name, MP::getSettingInt('timeoff'), false, false, null, true, false, 0, false);
			echo '</p>';
		} catch (Exception $e) {
			echo $e;
		}
	}
	echo '<p><a href="chatsearch.php?c='.$id.'">'.MP::x($lng['search_messages']).'</a> <a href="chatmedia.php?c='.$id.'">'.MP::x($lng['chat_media']).'</a></p>';
	if($type != 'user') echo '<p><a href="chat.php?c='.$id.'&leave">'.MP::x($lng['leave_chat']).'</a></p>';
	if($type != 'user' && $members) {
		$avas = MP::getSettingInt('avas', 0);
		echo MP::x($lng['chat_members']).':';
		echo '<table class="cl">';
		$i = 0;
		foreach($members as $m) {
			$i ++;
			if($i > 100) {
				echo '<tr>...</tr>';
				break;
			}
			echo '<tr class="c">';
			$u = $m['user'] ?? $m['chat'] ?? $m['channel'];
			$id = $u['id'];

			$un = $u['title'] ?? (isset($u['first_name']) ? $u['first_name'] . (isset($u['last_name']) ? ' '.$u['last_name'] : '') : null) ?? 'Deleted Account';
			$status = null;
			if(isset($u['status'])) {
				$status = $u['status']['_'] == 'userStatusOnline';
				$last = $u['status']['was_online'] ?? 0;
			}
			$rank = null;
			if(isset($m['rank'])) {
				$rank = $m['rank'];
			} elseif(isset($m['role'])) {
				$role = $m['role'];
				if($role == 'creator') {
					$rank = MP::x($lng['owner']);
				} elseif($role == 'admin') {
					$rank = MP::x($lng['admin']);
				}
			}
			if($avas) {
				echo '<td class="cava cbd"><img class="ri" src="ava.php?c='.$u['id'].'&p='.($pngava?'rc':'r').'36"></td>';
			}
			echo '<td class="ctext cbd">';
			if($rank) {
				echo '<div class="chr ml">'.MP::dehtml($rank).'</div>';
			}
			echo '<a href="chat.php?c='.$id.'">'.MP::dehtml($un).'</a>';
			echo '<div class="ml">'. ($status !== null ? ($status ? MP::x($lng['online']) : '') : '&nbsp;').'</div>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}

	echo Themes::bodyEnd();
} catch (Exception $e) {
	echo '<xmp>';
	echo $e;
	echo '</xmp>';
}
