<?php

include 'redirect.php';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';

$timeoff = MP::getSettingInt('timeoff');
$theme = MP::getSettingInt('theme');
$lng = MP::initLocale();

$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die;
}

$count = MP::getSettingInt('chats', 15);
if(isset($_GET['count'])) {
	$count = (int) $_GET['count'];
}
$useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$avas = strpos($useragent, 'AppleWebKit') || strpos($useragent, 'Chrome') || strpos($useragent, 'Symbian/3') || strpos($useragent, 'SymbOS') || strpos($useragent, 'Android') || strpos($useragent, 'Linux') ? 1 : 0;
$avas = MP::getSettingInt('avas', $avas);
$pngava = MP::getSettingInt('pngava', 0);

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');

try {
	if(PHP_OS_FAMILY === "Linux") {
		// Automatically kill madeline sessions
		$x = false;
		try {
			$x = file_get_contents('./lastclean');
		} catch (Exception) {
		}
		if(!$x || (time() - (int)$x) > 30 * 60) {
			exec("kill -9 `ps -ef | grep -v grep | grep 'MadelineProto worker' | awk '{print $2}'` > /dev/null &");
			file_put_contents('./lastclean', time());
		}
	}
} catch (Exception) {
}

header('Content-Type: text/html; charset='.MP::$enc);
header('Cache-Control: private, no-cache, no-store, must-revalidate');

include 'themes.php';
Themes::setTheme($theme);

try {
	$MP = MP::getMadelineAPI($user);
	echo '<head><title>'.MP::x($lng['chats']).'</title>';
	echo Themes::head();
	$iev = MP::getIEVersion();
	if($iev == 0 || $iev > 4) {
		$dtz = new DateTimeZone(date_default_timezone_get());
		$t = new DateTime('now', $dtz);
		$tof = $dtz->getOffset($t);
		echo
'<script type="text/javascript"><!--
try {
	var d = new Date();
	var c = ((d.getTime()+'.($tof*1000).')-(d.getTime()-(d.getTimezoneOffset()*60*1000)))/1000 | 0;
	var e = new Date();
	e.setTime(e.getTime() + (365*86400*1000));
	document.cookie = "timeoff=" + c + "; expires="+e.toUTCString()+"; path=/";
} catch (e) {
}
//--></script>';
	}
	echo '</head>';
	echo Themes::bodyStart();
	$selfid = MP::getSelfId($MP);
	$selfname = MP::dehtml(MP::getSelfName($MP));
	$hasArchiveChats = false;
	$fid = 0;
	if(isset($_GET['f'])) {
		$fid = (int)$_GET['f'];
	}
	echo '<div class="hed">';
	echo '<b>'.MP::x($selfname).'</b><div class="hb">';
	echo '<a href="chats.php?upd&f='.$fid.'">'.MP::x($lng['refresh']).'</a>';
	echo ' <a href="chatselect.php">'.MP::x($lng['compactchats']).'</a>';
	echo ' <a href="contacts.php">'.MP::x($lng['contacts']).'</a>';
	echo ' <a href="chatsearch.php">'.MP::x($lng['search']).'</a>';
	echo ' <a href="sets.php">'.MP::x($lng['settings']).'</a>';
	echo '</div>';
	$folders = $MP->messages->getDialogFilters();
	if(($folders['_'] ?? '') == 'messages.dialogFilters')
		$folders = $folders['filters'];
	$hasArchiveChats = count($MP->messages->getDialogs([
		'limit' => 1, 
		'exclude_pinned' => true,
		'folder_id' => 1
		])['dialogs']) > 0;
	if(count($folders) > 1 || $hasArchiveChats) {
		echo '<div>';
		echo '<b>'.MP::x($lng['folders']).'</b>: ';
		foreach($folders as $f) {
			if(($f['_'] ?? '') == 'dialogFilterDefault' || !isset($f['id'])) {
				echo '<a href="chats.php">'.MP::x($lng['all_chats']).'</a> ';
			} else {
				$sel = $fid == $f['id'];
				if($sel) echo '<u>';
				echo '<a href="chats.php?f='.$f['id'].'">'.MP::dehtml($f['title']).'</a>';
				if($sel) echo '</u>';
				echo ' ';
			}
		}
		if($hasArchiveChats) {
			$sel = $fid == 1;
			if($sel) echo '<u>';
			echo '<a href="chats.php?f=1">'.MP::x($lng['archived_chats']).'</a>';
			if($sel) echo '</u>';
		}
		echo '</div>';
	}
	echo '</div><br>';
	try {
		$r = null;
		$dialogs = null;
		if($fid == 1) {
			$r = $MP->messages->getDialogs([
			'offset_date' => 0,
			'offset_id' => 0,
			'add_offset' => 0,
			'limit' => $count, 
			'hash' => 0,
			'exclude_pinned' => true,
			'folder_id' => 1
			]);
			$dialogs = $r['dialogs'];
			foreach($r['messages'] as $m) {
				foreach($dialogs as $k => $d) {
					if($m['peer_id'] != $d['peer']) continue;
					$dialogs[$k]['message'] = $m;
					break;
				}
			}
			unset($r['messages']);
			unset($r['dialogs']);
		} else {
			if($fid > 1) {
				$folder = null;
				foreach($folders as $f) {
					if(!isset($f['id']) || $f['id'] != $fid) continue;
					$folder = $f;
					break;
				}
				unset($folders);
				$r = MP::getAllDialogs($MP);
				$dialogs = [];
				$all = $r['dialogs'];
				foreach($r['messages'] as $m) {
					foreach($all as $k => $d) {
						if($m['peer_id'] != $d['peer']) continue;
						$all[$k]['message'] = $m;
						break;
					}
				}
				unset($r['messages']);
				unset($r['dialogs']);
				if($f['contacts'] || $f['non_contacts']) {
					$contacts = $MP->contacts->getContacts()['contacts'];
					foreach($all as $d) {
						if($d['peer'] < 0) continue;
						$found = false;
						foreach($contacts as $c) {
							if($d['peer'] != MP::getId($c)) continue;
							$found = true;
							if($f['contacts']) array_push($dialogs, $d);
							break;
						}
						if($found || $f['non_contacts']) continue;
						if(!in_array($d, $dialogs)) array_push($dialogs, $d);
					}
					unset($contacts);
				}
				if($f['groups']) {
					foreach($all as $d) {
						$peer = $d['peer'];
						if($peer > 0) continue;
						foreach($r['chats'] as $c) {
							if($c['id'] != $peer) continue;
							if(!($c['broadcast'] ?? false) && !in_array($d, $dialogs))
								array_push($dialogs, $d);
							break;
						}
					}
				}
				if($f['broadcasts']) {
					foreach($all as $d) {
						$peer = $d['peer'];
						if($peer > 0) continue;
						foreach($r['chats'] as $c) {
							if($c['id'] != $peer) continue;
							if(($c['broadcast'] ?? false) && !in_array($d, $dialogs))
								array_push($dialogs, $d);
							break;
						}
					}
				}
				if($f['bots']) {
					foreach($all as $d) {
						$peer = $d['peer'];
						if($peer < 0) continue;
						foreach($r['users'] as $u) {
							if($u['id'] != $peer) continue;
							if(($u['bot'] ?? false) && !in_array($d, $dialogs))
								array_push($dialogs, $d);
							break;
						}
						continue;
					}
				}
				if(count($f['include_peers']) > 0) {
					foreach($f['include_peers'] as $p) {
						$p = MP::getId($p);
						foreach($all as $d) {
							if($d['peer'] != $p) continue;
							if(!in_array($d, $dialogs)) array_push($dialogs, $d);
							break;
						}
					}
				}
				if(count($f['exclude_peers']) > 0) {
					foreach($f['exclude_peers'] as $p) {
						$p = MP::getId($p);
						foreach($dialogs as $idx => $d) {
							if($d['peer'] != $p) continue;
							unset($dialogs[$idx]);
							break;
						}
					}
				}
				if($f['exclude_archived']) {
					foreach($dialogs as $idx => $d) {
						if(!isset($d['folder_id']) || $d['folder_id'] != 1) continue;
						unset($dialogs[$idx]);
					}
				}
				if($f['exclude_read']) {
					foreach($dialogs as $idx => $d) {
						if(!isset($d['unread_count']) || $d['unread_count'] > 0) continue;
						unset($dialogs[$idx]);
					}
				}
				function cmp($a, $b) {
					global $r;
					$ma = $a['message'] ?? null;
					$mb = $b['message'] ?? null;
					if ($ma === null || $mb === null || $ma['date'] == $mb['date']) {
						return 0;
					}
					return ($ma['date'] > $mb['date']) ? -1 : 1;
				}
				usort($dialogs, 'cmp');
				if(count($f['pinned_peers']) > 0) {
					$pinned = array();
					foreach($f['pinned_peers'] as $p) {
						$p = MP::getId($p);
						foreach($all as $d) {
							if($d['peer'] != $p) continue;
							if(in_array($d, $dialogs)) {
								unset($dialogs[array_search($d, $dialogs)]);
							}
							array_push($pinned, $d);
							break;
						}
					}
					$dialogs = array_merge($pinned, $dialogs);
					unset($pinned);
				}
				unset($all);
			} else {
				$r = $MP->messages->getDialogs([
				'offset_date' => 0,
				'offset_id' => 0,
				'add_offset' => 0,
				'limit' => $count, 
				'hash' => 0,
				'folder_id' => 0
				]);
				$dialogs = $r['dialogs'];
				foreach($r['messages'] as $m) {
					foreach($dialogs as $k => $d) {
						if($m['peer_id'] != $d['peer']) continue;
						$dialogs[$k]['message'] = $m;
						break;
					}
				}
				unset($r['messages']);
				unset($r['dialogs']);
			}
		}
		MP::addUsers($r['users'], $r['chats']);
		$c = 0;
		$msglimit = MP::getSettingInt('limit', 20);
		echo '<table class="cl">';
		foreach($dialogs as $d){
			if($fid == 0 && isset($d['folder_id']) && $d['folder_id'] == 1) continue;
			try {
				$id = $d['peer'] ?? $d;
				$n = null;
				$cl = 'chat.php?c='.$id;
				$unr = $d['unread_count'];
				$broadcast = false;
				if($unr > $msglimit) {
					$cl .= '&m='.$d['read_inbox_max_id'].'&offset='.(-$msglimit-1);
				}
				echo '<tr class="c" onclick="location.href=\''.$cl.'\';">';
				if($avas) {
					echo '<td class="cava cbd"><img class="ri" src="ava.php?c='.$id.'&p='.($pngava?'rc':'r').'36"></td>';
				}
				echo '<td class="ctext cbd">';
				echo '<a href="'.$cl.'"><b>';
				foreach(($r[$id > 0 ? 'users' : 'chats']) as $p) {
					if($p['id'] != $id) continue;
					$broadcast = $p['broadcast'] ?? false;
					if(isset($p['title'])) {
						$n = $p['title'];
					} elseif(isset($p['first_name'])) {
						$n = trim($p['first_name']).(isset($p['last_name']) ? ' '.trim($p['last_name']) : '');
					} elseif(isset($p['last_name'])) {
						$n = trim($p['last_name']);
					} else {
						$n = 'Deleted Account';
					}
					break;
				}
				echo MP::dehtml(MP::removeEmoji($n)).'</b>';
				if($unr > 0) {
					echo ' <b class="unr">+'.$unr.'</b>';
				}
				echo '</a>';
				try {
					$msg = $d['message'] ?? null;
					if($msg !== null) {
						$mfid = $msg['from_id'] ?? null;
						$mfn = null;
						if($mfid !== null && $id < 0) {
							$mfn = MP::dehtml(MP::getNameFromId($MP, $msg['from_id']));
						}
						$t = null;
						if(date('d.m.Y', time()-$timeoff) !== date('d.m.Y', $msg['date']-$timeoff)) {
							$t = date('d.m.Y', $msg['date']-$timeoff);
						} else {
							$t = date('H:i', $msg['date']-$timeoff);
						}
						echo '<br><div class="cm">'.$t.' ';
						if(isset($msg['message']) && strlen($msg['message']) > 0) {
							echo '<a href="'.$cl.'" class="ct">';
							if(!$broadcast && (($msg['out'] ?? false) || $mfid == $selfid))
								echo MP::x($lng['you']).': ';
							elseif($mfn !== null)
								echo $mfn.': ';
							$txt = MP::dehtml(trim(str_replace("\r","",str_replace("\n", " ", $msg['message']))));
							if(MP::utflen($txt) > 250) $txt = MP::utfsubstr($txt, 0, 250).'..';
							echo $txt;
							echo '</a>';
						} elseif(isset($msg['action'])) {
							echo '<a href="'.$cl.'" class="cma">'.MP::parseMessageAction($msg['action'], $mfn, $mfid, $n, $lng, false, $MP).'</a>';
						} elseif(isset($msg['media'])) {
							echo '<a href="'.$cl.'" class="cma">';
							if(!$broadcast && (($msg['out'] ?? false) || $mfid == $selfid))
								echo MP::x($lng['you']).': ';
							elseif($mfn !== null)
								echo $mfn.': ';
							echo MP::x($lng['media_att']);
							echo '</a>';
						} else {
							echo '.';
						}
						echo '</div>';
					}
				} catch (Exception) {
				}
				echo '</td>';
				echo '</tr>';
			} catch (Exception $e) {
				echo "<xmp>$e</xmp>";
			}
			$c += 1;
		}
		echo '</table>';
		unset($dialogs);
		unset($r);
	} catch (Exception $e) {
		echo '<b>'.MP::x($lng['error']).'!</b><br>';
		echo "<xmp>$e</xmp>";
	}
	echo Themes::bodyEnd();
	unset($MP);
	MP::gc();
} catch (Exception $e) {
	if(strpos($e->getMessage(), 'SESSION_REVOKED') !== false || strpos($e->getMessage(), 'session created on newer PHP') !== false) {
		header('Location: login.php?revoked=1');
		die();
	}
	if(strpos($e->getMessage(), 'Could not get user info!') !== false) {
		header('Location: login.php?logout=1');
		die();
	}
	echo '<b>'.$lng['error'].'!</b><br>';
	echo "<xmp>$e</xmp><br>";
	echo '<b><a href="login.php?logout=1">'.MP::x($lng['logout']).'</a><b>';
}
