<?php
include 'redirect.php';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';

$iev = MP::getIEVersion();
$timeoff = MP::getSettingInt('timeoff');
$theme = MP::getSettingInt('theme');
$autoupd = MP::getSettingInt('autoupd', ($iev == 0 || $iev > 4) ? 1 : 0);
$updint = MP::getSettingInt('updint', 10);
$dynupd = MP::getSettingInt('dynupd', 1);
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$sym3 = strpos($ua, 'Symbian/3') !== false ? 1 : 0;
$reverse = MP::getSettingInt('reverse', $sym3) == 1;
$autoscroll = MP::getSettingInt('autoscroll', 1) == 1;
$full = MP::getSettingInt('full', 0) == 1;
$texttop = MP::getSettingInt('texttop', $sym3) == 1;
$imgs = MP::getSettingInt('imgs', 1) == 1;
$longpoll = MP::getSettingInt('longpoll', strpos($ua, 'AppleWebKit') || strpos($ua, 'Chrome') || strpos($ua, 'Symbian') || strpos($ua, 'SymbOS') || strpos($ua, 'Android')) == 1;
$pngava = MP::getSettingInt('pngava', 0);
$old = MP::getSettingInt('oldchat', 0);
$photosize = MP::getSettingInt('photosize', 0);

$lng = MP::initLocale();

$msglimit = MP::getSettingInt('limit', 20);
$msgoffset = 0;
$msgoffsetid = 0;
$msgmaxid = 0;
if(isset($_GET['offset'])) {
	$msgoffset = (int) $_GET['offset'];
}
if(isset($_GET['offset_from'])) {
	$msgoffsetid = (int) $_GET['offset_from'];
} elseif(isset($_GET['m'])) {
	$msgoffsetid = (int) $_GET['m'];
	$msgoffset = -1;
}
if(isset($_GET['max_id'])) {
	$msgmaxid = (int) $_GET['max_id'];
}
$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die;
}

header('Content-Type: text/html; charset='.MP::$enc);
header('Cache-Control: private, no-cache, no-store');

$id = $_GET['c'] ?? $_GET['peer'] ?? die;

$start = $_GET['start'] ?? null;
$file = htmlentities($_SERVER['PHP_SELF']);

$query = $_GET['q'] ?? null;
$forum = $_GET['f'] ?? null;

function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');

include 'themes.php';
Themes::setChatTheme($theme);

try {
	$MP = MP::getMadelineAPI($user);
	$info = $MP->getInfo($id);
	$name = null;
	$pm = false;
	$ch = false;
	$left = false;
	if(!is_numeric($id)) {
		$id = MP::getId($info);
	}
	$canpost = false;
	$ar = null;
	if(isset($info['Chat'])) {
		$ch = isset($info['type']) && $info['type'] == 'channel';
		$name = $info['Chat']['title'] ?? null;
		$ar = $info['Chat']['admin_rights'] ?? null;
		$canpost = $ar !== null && $ar['post_messages'] ?? false;
		$left = $info['Chat']['left'] ?? false;
	} elseif(isset($info['User'])) {
		$pm = true;
		$name = MP::removeEmoji($info['User']['first_name'] ?? $info['User']['last_name'] ?? null);
	}
	$channel = isset($info['channel_id']);
	if($left && isset($_GET['join'])) {
		$MP->channels->joinChannel(['channel' => $id]);
		$left = false;
	} elseif(!$left && isset($_GET['leave'])) {
		$MP->channels->leaveChannel(['channel' => $id]);
		$left = true;
	}
	if($start !== null) {
		$MP->messages->startBot(['start_param' => $start, 'bot' => $id, 'random_id' => $_GET['rnd']]);
	}
	function printInputField() {
		global $full;
		global $left;
		global $ch;
		global $id;
		global $lng;
		global $reverse;
		global $canpost;
		global $iev;
		global $texttop;
		global $ua;
		global $file;
		echo '<div class="in'.($reverse?' t':'').($texttop?' cb':'').'" id="text">';
		if($left) {
			echo '<form action="'.$file.'">';
			echo '<input type="hidden" name="c" value="'.$id.'">';
			echo '<input type="hidden" name="join" value="1">';
			echo '<input type="hidden" name="r" value="'. \base64_encode(random_bytes(16)).'">';
			echo '<input type="submit" value="'.MP::x($lng['join']).'">';
			echo '</form>';
		} elseif(!$ch || $canpost) {
			$post = strpos($ua, 'Series60/3') === false;
			$opera = strpos($ua, 'Opera') !== false || ($iev != 0 && $iev <= 7);
			$watchos = strpos($ua, 'Watch OS') !== false;
			echo '<form action="write.php"'.($post ? ' method="post"' : '').' class="in">';
			echo '<input type="hidden" name="c" value="'.$id.'">';
			echo '<input type="hidden" name="r" value="'. \base64_encode(random_bytes(16)).'">';
			if($watchos) {
				echo '<input required name="msg" value="" style="width: 100%; height: 2em"><br>';
			} else {
				echo '<textarea required name="msg" value="" class="cta"></textarea><br>';
			}
			echo '<input type="submit" value="'.MP::x($lng['send']).'">';
			//echo '<input type="checkbox" id="format" name="format">';
			//echo '<label for="format">'.MP::x($lng['html_formatting']).'</label>';
			echo '</form>';
			echo '<form action="msg.php" style="margin: 0" class="in'.((!$opera) ? 'r' : '').'">';
			echo '<input type="hidden" name="c" value="'.$id.'">';
			echo '<input type="submit" value="'.MP::x($lng['send_file']).'">';
			echo '</form>';
		}
		/*
		if($reverse) {
			echo '<div><a href="chats.php">'.MP::x($lng['back']).'</a>';
			echo ' <a href="chat.php?c='.$id.'&upd=1">'.MP::x($lng['refresh']).'</a></div>';
		}
		*/
		echo '</div>';
	}
	$r = null;
	if($query !== null) {
		$r = $MP->messages->search([
		'peer' => $id,
		'offset_id' => $msgoffsetid,
		'offset_date' => 0,
		'add_offset' => $msgoffset,
		'limit' => $msglimit,
		'max_id' => $msgmaxid,
		'min_id' => 0,
		'hash' => 0,
		'q' => $query
		]);
	} else {
		$r = $MP->messages->getHistory([
		'peer' => $id,
		'offset_id' => $msgoffsetid,
		'offset_date' => 0,
		'add_offset' => $msgoffset,
		'limit' => $msglimit,
		'max_id' => $msgmaxid,
		'min_id' => 0,
		'hash' => 0]);
	}
	MP::addUsers($r['users'], $r['chats']);
	$id_offset = null;
	if(isset($r['offset_id_offset'])) {
		$id_offset = $r['offset_id_offset'];
		if($msgoffset < 0) {
			$id_offset = $id_offset+$msgoffset+1;
		}
	}
	$endReached = $id_offset === 0 || ($id_offset === null && $msgoffset <= 0);
	$hasOffset = $msgoffset > 0 || $msgoffsetid > 0;
	$dir = $_GET['d'] ?? null;
	$rm = $r['messages'];
	echo '<head><title>'.MP::dehtml($name).'</title>';
	echo Themes::head();
	echo '<script type="text/javascript"><!--
var reverse = '.($reverse&&$texttop?'true':'false').';';
echo file_get_contents('chatscroll.js');
echo '
//--></script>';
	if((!$hasOffset || $endReached) && $autoupd == 1 && count($rm) > 0 && $query === null) {
		$ii = $rm[0]['id'];
		if($dynupd == 1) {
			echo '<script type="text/javascript"><!--
var reverse = '.($reverse?'true':'false').';
var autoscroll = '.($autoscroll?'true':'false').';
var longpoll = '.($longpoll?'true':'false').';
var updint = '.($longpoll?'1000':$updint.'000').';
var url = "'.MP::getUrl().'msgs.php?user='.$user.'&id='.$id.'&lang='.$lng['lang'].'&t='.$timeoff.($longpoll?'&l':'').($old?'&ol':'').'";
var msglimit = '.$msglimit.';
var msg = "'.$ii.'";';
echo file_get_contents('chatupdate.js');
echo '
//--></script>';
		} else {
			echo '<script type="text/javascript"><!--
setTimeout("location.reload(true);",'.$updint.'000);
//--></script>';
		}
	}
	echo '</head>'."\n";
	$body = false;
	if($reverse && $dir != 'd') {
		echo Themes::bodyStart('onload="autoScroll(true, false);"'); $body = true;
	} elseif(!$reverse && $dir == 'u') {
		echo Themes::bodyStart('onload="autoScroll(true, true);"'); $body = true;
	}
	if(!$body)
		if($msgoffsetid > 0)
			echo Themes::bodyStart('onload="document.getElementById(\'msg_'.$id.'_'.$msgoffsetid.'\').scrollIntoView();"');
		else
			echo Themes::bodyStart();
	$useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
	$avas = strpos($useragent, 'AppleWebKit') || strpos($useragent, 'Chrome') || strpos($useragent, 'Symbian/3') || strpos($useragent, 'SymbOS') || strpos($useragent, 'Android') || strpos($useragent, 'Linux') ? 1 : 0;
	$avas = MP::getSettingInt('avas', $avas) && strpos($useragent, 'SymbianOS/9') === false;
	$statussett = MP::getSettingInt('status', 0);
	if($iev != 0 && $iev <= 7) {
		echo '<header>';
		if($avas) {
			echo '<div class="chava"><img class="ri" src="ava.php?c='.$id.'&p='.($pngava?'rc':'r').'36"></div>';
		}
		echo '<div class="chn">';
		echo MP::dehtml($name);
		echo '</div>';
		echo '<div><small><a href="chats.php">'.MP::x($lng['back']).'</a>';
		echo ' <a href="'.$file.'?c='.$id.'&upd=1">'.MP::x($lng['refresh']).'</a>';
		echo ' <a href="chatinfo.php?c='.$id.'">'.MP::x($lng['chat_info']??null).'</a>';
		echo '</small></div>';
		echo '</header>';
	} else {
		echo '<header class="ch">';
		echo '<div class="chc"><div class="chr"><small><a href="chats.php">'.MP::x($lng['back']).'</a>';
		echo ' <a href="'.$file.'?c='.$id.'&upd=1">'.MP::x($lng['refresh']).'</a>';
		echo ' <a href="chatinfo.php?c='.$id.'">'.MP::x($lng['chat_info']??null).'</a>';
		echo '</small></div>';
		$h = "height: 1.2em";
		if($avas && $statussett) {
			$h = "height: 44px";
			echo '<div class="chava"><img class="ri" src="ava.php?c='.$id.'&p='.($pngava?'rc':'r').'36"></div>';
		}
		echo '<div class="chn">';
		echo MP::dehtml($name);
		if($statussett) {
			$status = $info['User']['status'] ?? null;
			$status_str = '';
			if($status) {
				switch($status['_']) {
				case 'userStatusOnline':
					$status_str = MP::x($lng['online']);
					break;
				case 'userStatusOffline':
					$time = time()-$timeoff;
					$was = $status['was_online']-$timeoff;
					if($was >= $time - 60) {
						$status_str = MP::x($lng['last_seen'].' '.$lng['just_now']);
					} elseif($was >= $time - 60*60) {
						$status_str = MP::x($lng['last_seen'].' '.MPLocale::number('minutes_ago', intval(($time-$was)/60)));
					} else /*if($was >= $time - 24*60*60) {
						$hours = intval(($time-$was)/60/60);
						if($hours == 1) {
							$status_str = 'last seen '.$hours.' hour ago';
						} else {
							$status_str = 'last seen '.$hours.' hours ago';
						}
					} else*/ if(date('d.m.y', $was) == date('d.m.y', $time)) {
						$status_str = MP::x($lng['last_seen']).' '.MP::x($lng['last_seen_at']).' '.date('H:i', $status['was_online']-$timeoff);
					} elseif(date('d.m.y', $was) == date('d.m.y', $time-24*60*60)) {
						$status_str = MP::x($lng['last_seen']).' '.MP::x($lng['yesterday'].' '.$lng['last_seen_at']).' '.date('H:i', $was);
					} else {
						$status_str = MP::x($lng['last_seen']).' '.date('d.m.y', $was);
					}
					break;
				case 'userStatusRecently':
					$status_str = MP::x($lng['last_seen'].' '.$lng['recently']);
					break;
				case 'userStatusLastWeek':
					$status_str = MP::x($lng['last_seen'].' '.$lng['last_week']);
					break;
				case 'userStatusLastMonth':
					$status_str = MP::x($lng['last_seen'].' '.$lng['last_month']);
				default:
				case 'userStatusEmpty':
					$status_str = '';
					break;
				}
			}
			echo '</div>';
			if($status_str) {
				if(!$avas) $h = "height: 2.2em";
				echo '<small id="cst" class="cst">'.$status_str.'</small>';
			}
			echo '</div>';
		} else {
			echo '</div></div>';
		}
		echo '</header>';
		echo "<div style=\"{$h};\">&nbsp;</div>";
	}
	unset($info);
	$sname = $name ?? '';
	if(MP::utflen($sname) > 30) $sname = MP::utfsubstr($sname, 0, 30);
	if(!$reverse) {
		printInputField();
		if($hasOffset && !$endReached) {
			if(($id_offset !== null && $id_offset <= $msglimit) || $msgoffset == $msglimit) {
				echo '<p><a href="'.$file.'?c='.$id.($query !== null ? '&q='.urlencode($query) : '').'&d=u">'.MP::x($lng['history_up']).'</a></p>';
			} else {
				echo '<p><a href="'.$file.'?c='.$id.($query !== null ? '&q='.urlencode($query) : '').'&d=u&offset_from='.$rm[0]['id'].'&offset='.(-$msglimit-1).'">'.MP::x($lng['history_up']).'</a></p>';
			}
		}
	} else {
		if(count($rm) >= $msglimit) {
			echo '<p><a href="'.$file.'?c='.$id.($query !== null ? '&q='.urlencode($query) : '').'&d=u&offset_from='.$rm[count($rm)-1]['id'].'&reverse=1">'.MP::x($lng['history_up']).'</a></p>';
		}
		$rm = array_reverse($rm);
	}
	if(!$texttop && !$reverse) echo '</p><p>';
	echo '<div id="msgs">';
	MP::printMessages($MP, $rm, $id, $pm, $ch, $lng, $imgs, $name, $timeoff, $channel, true, $ar, $query !== null, $old, $photosize);
	echo '</div>';
	if(!$reverse) {
		if(count($rm) >= $msglimit) {
			if($endReached && $autoupd)
				echo '<p><a href="'.$file.'?c='.$id.($query !== null ? '&q='.urlencode($query) : '').'&offset='.$msglimit.'&d=d">'.MP::x($lng['history_down']).'</a></p>';
			else
				echo '<p><a href="'.$file.'?c='.$id.($query !== null ? '&q='.urlencode($query) : '').'&d=d&offset_from='.$rm[count($rm)-1]['id'].'">'.MP::x($lng['history_down']).'</a></p>';
		}
	} else {
		if($hasOffset && !$endReached) {
			if(($id_offset !== null && $id_offset <= $msglimit) || $msgoffset == $msglimit) {
				echo '<p><a href="'.$file.'?c='.$id.($query !== null ? '&q='.urlencode($query) : '').'&d=d">'.MP::x($lng['history_down']).'</a></p>';
			} else {
				echo '<p><a href="'.$file.'?c='.$id.($query !== null ? '&q='.urlencode($query) : '').'&d=d&offset_from='.$rm[count($rm)-1]['id'].'&offset='.(-$msglimit-1).'&reverse=1">'.MP::x($lng['history_down']).'</a></p>';
			}
		}
		printInputField();
	}
	if($texttop) echo '<div style="height: 4em;" id="bottom"></div>';
	else echo '<div id="bottom"></div>';
	// Mark as read
	try {
		if($query === null && count($rm) > 0) {
			$maxid = $endReached ? 0 : ($reverse ? $rm[count($rm)-1]['id'] : $rm[0]['id']);
			if($ch || (int)$id < 0) {
				$MP->channels->readHistory(['channel' => $id, 'max_id' => $maxid]);
			} else {
				$MP->messages->readHistory(['peer' => $id, 'max_id' => $maxid]);
			}
			$MP->messages->readMentions(['peer' => $id, 'top_msg_id' => $maxid]);
			//$MP->messages->readReactions(['peer' => $id, 'top_msg_id' => $maxid]);
		}
	} catch (Exception $e) {
	}
	unset($rm);
	unset($r);
	echo Themes::bodyEnd();
	MP::gc();
} catch (Exception $e) {
	echo '<b>'.MP::x($lng['error']).'!</b><br>';
	echo '<xmp>'.$e->getMessage()."\n".$e->getTraceAsString().'</xmp>';
}
die;