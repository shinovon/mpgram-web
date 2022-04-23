<?php
define('sessionspath', '');

header('Content-Type: text/html; charset=utf-8');
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
$msglimit = 20;
$msgoffset = 0;

if(isset($_GET['limit'])) {
	$msglimit = (int) $_GET['limit'];
}
if(isset($_GET['off'])) {
	$msgoffset = (int) $_GET['off'];
}

$lang = 'ru';
if(isset($_GET['lang'])) {
	$lang = $_GET['lang'];
	setcookie('lang', $lang, time() + (86400 * 365));
} else if(isset($_COOKIE['lang'])) {
	$lang = $_COOKIE['lang'];
}

try {
	include 'locale_'.$lang.'.php';
} catch (Exception $e) {
	$lang = 'ru';
	include 'locale_'.$lang.'.php';
}

$user = null;
if(isset($_COOKIE['user']))
	$user = $_COOKIE['user'];
if(!isset($user) || empty($user)) {
	//не авторизирован, отправить в логинизацию
	header('Location: login.php');
	die();
}
header('Cache-Control: private, no-cache, no-store');
if (!file_exists('madeline.php')) {
	copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}
$id = null;
if(isset($_GET['peer'])) {
	$id = $_GET['peer'];
} else if(!isset($_GET['c'])) {
	die();
} else {
	$id = $_GET['c'];
}

function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

set_error_handler('exceptions_error_handler');

include 'util.php';
$imgs = true;
try {
	include 'madeline.php';
	include 'api_settings.php';
	$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline', getSettings());
	$MP->start();
	$info = $MP->getInfo($id);
	//echo '<xmp>'.var_export($info, true).'</xmp>';
	$un = null;
	$lid = null;
	$name = null;
	$pm = false;
	$ch = false;
	if(isset($info['Chat'])) {
		$ch = isset($info['type']) && $info['type'] == 'channel';
		if(isset($info['Chat']['title'])) {
			$name = $info['Chat']['title'];
		}
		if(isset($info['Chat']['username'])) {
			$un = $info['Chat']['username'];
		}
		if(isset($info['Chat']['id'])) {
			$lid = $info['Chat']['id'];
		}
	} else if(isset($info['User']) && isset($info['User']['first_name'])) {
		$name = $info['User']['first_name'];
		$pm = true;
		if(isset($info['User']['username'])) {
			$un = $info['User']['username'];
		}
		if(isset($info['User']['id'])) {
			$lid = $info['User']['id'];
		}
	}
	
	echo '<head><title>'.Utils::dehtml($name).'</title></head><body>';
	echo '<a href="chats.php">'.$lng['back'].'</a>';
	if(!$ch) echo ' <a href="write.php?c='.$id.'">'.$lng['write_msg'].'</a>';
	echo ' <a href="chat.php?c='.$id.'&upd=1">'.$lng['refresh'].'</a><br>';
	echo '<h2>'.Utils::dehtml($name).'</h2>';
	if($msgoffset > 0) {
		$i = $msgoffset - $msglimit;
		if($i < 0) {
			$i = 0;
		}
		echo '<p><a href="chat.php?c='.$id.'&off='.$i.'&limit='.$msglimit.'">'.$lng['history_up'].'</a></p>';
	}
	try {
		if($ch) {
			$MP->channels->readHistory(['channel' => $id, 'max_id' => 0]);
		} else {
			$MP->messages->readHistory(['peer' => $id, 'max_id' => 0]);
		}
	} catch (Exception $e) {
	}
	$r = $MP->messages->getHistory([
	'peer' => $id,
	'offset_id' => 0,
	'offset_date' => 0,
	'add_offset' => $msgoffset,
	'limit' => $msglimit,
	'max_id' => 0,
	'min_id' => 0,
	'hash' => 0]);
	
	$rm = $r['messages'];
	foreach($rm as $m) {
		try {
			$mname = null;
			$uid = null;
			$l = false;
			if($m['out'] == true) {
				$uid = Utils::getSelfId($MP);
				$mname = 'Вы';
			} else if($pm || $ch) {
				$uid = $id;
				$mname = $name;
			} else {
				$l = true;
				$uid = Utils::getId($MP,$m['from_id']);
				$mname = Utils::getNameFromId($MP, $uid);
			}
			$fwid = null;
			$fwname = null;
			$fwid = null;
			if(isset($m['fwd_from'])) {
				if(isset($m['fwd_from']['from_name'])) {
					$fwname = $m['fwd_from']['from_name'];
				} else if(isset($m['fwd_from']['from_id'])){
					$fwid = Utils::getId($MP, $m['fwd_from']['from_id']);
					$fwname = Utils::getNameFromId($MP, $fwid, true);
				}
			}
			echo '<div>';
			if(!$pm && $uid != null && $l) {
				echo '<b><a href="chat.php?c='.$uid.'">'.Utils::dehtml($mname).'</a></b>';
			} else {
				echo '<b>'.Utils::dehtml($mname).'</b>';
			}
			echo ' ('.date("H:i:s", $m['date']).'):';
			if($fwname != null) {
				echo '<br>'.$lng['fwd_from'].' <b>'.Utils::dehtml($fwname).'</b>';
			}
			if(isset($m['message']) && strlen($m['message']) > 0) {
				echo '<br>';
				echo str_replace("\n", "<br>", Utils::dehtml($m['message'])).'';
			}
			if(isset($m['media'])) {
				$media = $m['media'];
				if(isset($media['photo'])) {
					$load = false;
					if($imgs && isset($un)) {
						try {
							$iur = 'https://t.me/'.$un.'/'.$m['id'].'?embed=1';
							$iur = 'i.php?u='.urlencode($iur).'&p=t';
							echo '<br><a href="'.$iur.'orig"><img alt="'.$lng['media_att'].'" src="'.$iur.'prev"></img></a>';
							$load = true;
						} catch (Exception $e) {
						}
					}
					if(!$load) {
						echo '<br><img alt="'.$lng['media_att'].'"></img>';
					}
				} else {
					echo '<br><i>'.$lng['media_att'].'</i>';
				}
			}
			echo '</div>';
		} catch (Exception $e) {
			echo "<xmp>$e</xmp>";
		}
	}
	echo '<p><a href="chat.php?c='.$id.'&off='.($msgoffset+20).'&limit='.$msglimit.'">'.$lng['history_down'].'</a></p>';
	echo '</body>';
} catch (Exception $e) {
	echo '<b>'.$lng['error'].'!</b><br>';
	echo "<xmp>$e</xmp>";
}
?>
