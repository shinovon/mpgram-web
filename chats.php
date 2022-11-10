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
	die();
}

$count = MP::getSettingInt('chats', 15);
if(isset($_GET['count'])) {
	$count = (int) $_GET['count'];
}

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
		} catch (Exception $e) {
		}
		if(!$x || (time() - (int)$x) > 30 * 60) {
			exec("kill -9 `ps -ef | grep -v grep | grep 'MadelineProto worker' | awk '{print $2}'` > /dev/null &");
			file_put_contents('./lastclean', time());
		}
	}
} catch (Exception $e) {
}

header('Content-Type: text/html; charset='.MP::$enc);
header('Cache-Control: private, no-cache, no-store, must-revalidate');

include 'themes.php';
Themes::setTheme($theme);

$avas = false;
try {
	$MP = MP::getMadelineAPI($user);
	echo '<head><title>'.MP::x($lng['chats']).'</title>';
	echo Themes::head();
	$iev = MP::getIEVersion();
	if($iev == 0 || $iev > 4) {
		$dtz = new DateTimeZone(date_default_timezone_get());
		$t = new DateTime('now', $dtz);
		$tof = $dtz->getOffset($t);
		echo '<script type="text/javascript"><!--
try {
	document.cookie = "timeoff=;expires=Thu, 01 Jan 1970 00:00:00 GMT";
	var f = '.($tof*1000).';
	var d = new Date();
	var t = d.getTime()
	var o = d.getTimezoneOffset()*60*1000;
	var c = (t-(t-f)+o)/1000;
	var e = new Date();
	e.setTime(e.getTime() + (365*86400*1000));
	document.cookie = "timeoff="+c+"; expires="+e.toUTCString();
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
	echo '<header>';
	echo '<b>'.MP::x($selfname).'</b><div>';
	echo '<a href="login.php?logout=1">'.MP::x($lng['logout']).'</a>';
	echo ' <a href="chats.php?upd&f="'.$fid.'>'.MP::x($lng['refresh']).'</a>';
	echo ' <a href="sets.php">'.MP::x($lng['settings']).'</a>';
	echo ' <a href="chatselect.php">'.MP::x($lng['compactchats']).'</a>';
	echo '</div>';
	$folders = $MP->messages->getDialogFilters();
	$hasArchiveChats = count($MP->messages->getDialogs([
		'limit' => 1, 
		'exclude_pinned' => true,
		'folder_id' => 1
		])['dialogs']) > 0;
	if(count($folders) > 1 || $hasArchiveChats) {
		echo '<div>';
		echo '<b>'.MP::x($lng['folders']).'</b>: ';
		foreach($folders as $f) {
			if(!isset($f['id'])) {	
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
	echo '<br></header>';
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
		} else {
			if($fid > 1) {
				$folder = null;
				foreach($folders as $f) {
					if(isset($f['id']) && $f['id'] == $fid) {
						$folder = $f;
						break;
					}
				}
				unset($folders);
				$r = $MP->messages->getDialogs(['limit' => 300]);
				$dialogs = array();
				$all = $r['dialogs'];
				if($f['contacts'] || $f['non_contacts']) {
					$contacts = $MP->contacts->getContacts()['contacts'];
					foreach($all as $d) {
						if($peer['_'] !== 'peerUser') continue;
						$found = false;
						foreach($contacts as $c) {
							if(MP::getId($MP, $peer) == MP::getId($MP, $c)) {
								$found = true;
								if($f['contacts']) array_push($dialogs, $d);
								break;
							}
						}
						if(!$found && $f['non_contacts']) {
							if(!in_array($d, $dialogs)) {
								array_push($dialogs, $d);
							}
						}
					}
					unset($contacts);
				}
				if($f['groups']) {
					foreach($all as $d) {
						$peer = $d['peer'];
						if($peer['_'] === 'peerUser') continue;
						if($peer['_'] === 'peerChannel') {
							foreach($r['chats'] as $c) {
								if($c['peer_id'] == $peer && !$c['broadcast'] && !in_array($d, $dialogs)) {
									array_push($dialogs, $d);
								}
							}
							continue;
						}
						if(!in_array($d, $dialogs)) {
							array_push($dialogs, $d);
						}
					}
				}
				if($f['broadcasts']) {
					foreach($all as $d) {
						$peer = $d['peer'];
						if($peer['_'] !== 'peerChannel') continue;
						foreach($r['chats'] as $c) {
							if($c['peer_id'] == $peer && $c['broadcast'] && !in_array($d, $dialogs)) {
								array_push($dialogs, $d);
							}
						}
					}
				}
				if($f['bots']) {
					foreach($all as $d) {
						$peer = $d['peer'];
						if($peer['_'] !== 'peerUser') continue;
						foreach($r['users'] as $u) {
							if($u['id'] == $peer['user_id'] && $u['bot'] && !in_array($d, $dialogs)) {
								array_push($dialogs, $d);
							}
						}
						continue;
					}
				}
				if(count($f['include_peers']) > 0) {
					foreach($f['include_peers'] as $p) {
						foreach($all as $d) {
							$peer = $d['peer'];
							if(MP::getId($MP, $peer) == MP::getId($MP, $p)) {
								if(!in_array($d, $dialogs)) {
									array_push($dialogs, $d);
								}
								break;
							}
						}
					}
				}
				if(count($f['exclude_peers']) > 0) {
					foreach($f['exclude_peers'] as $p) {
						foreach($dialogs as $idx => $d) {
							$peer = $d['peer'];
							if(MP::getId($MP, $peer) == MP::getId($MP, $p)) {
								unset($dialogs[$idx]);
								break;
							}
						}
					}
				}
				if($f['exclude_archived']) {
					foreach($dialogs as $idx => $d) {
						if(isset($d['folder_id']) && $d['folder_id'] == 1) {
							unset($dialogs[$idx]);
						}
					}
				}
				if($f['exclude_read']) {
					foreach($dialogs as $idx => $d) {
						if(isset($d['unread_count']) && $d['unread_count'] == 0) {
							unset($dialogs[$idx]);
						}
					}
				}
				function cmp($a, $b) {
					global $r;
					$ma = null;
					$mb = null;
					foreach($r['messages'] as $m) {
						if($m['peer_id'] == $a['peer']) {
							$ma = $m;
						}
						if($m['peer_id'] == $b['peer']) {
							$mb = $m;
						}
						if($ma !== null && $mb !== null) break;
					}
					if ($ma === null || $mb === null || $ma['date'] == $mb['date']) {
						return 0;
					}
					if($a['pinned'] && !$b['pinned']) {
						return -1;
					}
					return ($ma['date'] > $mb['date']) ? -1 : 1;
				}
				usort($dialogs, 'cmp');
				$pinned = array();
				if(count($f['pinned_peers']) > 0) {
					foreach($f['pinned_peers'] as $p) {
						foreach($all as $d) {
							$peer = $d['peer'];
							if(MP::getId($MP, $peer) == MP::getId($MP, $p)) {
								if(in_array($d, $dialogs)) {
									unset($dialogs[array_search($d, $dialogs)]);
								}
								array_push($pinned, $d);
								break;
							}
						}
					}
					$dialogs = array_merge($pinned, $dialogs);
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
			}
		}
		$msgs = $r['messages'];
		$c = 0;
		$msglimit = MP::getSettingInt('limit', 20);
		foreach($dialogs as $d){
			if($fid == 0 && isset($d['folder_id']) && $d['folder_id'] == 1) continue;
			$id = MP::getId($MP, $d['peer']);
			try {
				echo '<div class="c'.($c%2==0 ? '1': '0').'">';
				$cl = 'chat.php?c='.$id;
				$unr = $d['unread_count'];
				if($unr > $msglimit) {
					$cl .= '&m='.$d['read_inbox_max_id'].'&offset='.(-$msglimit-1);
				}
				echo '<a href="'.$cl.'"><b>';
				$peer = $d['peer'];
				$n = null;
				$lid = $id;
				if((int) $lid < 0) {
					if(isset($peer['chat_id'])) {
						$lid = $peer['chat_id'];
					} else if(isset($peer['channel_id'])) {
						$lid = $peer['channel_id'];
					}
				}
				foreach(($r[$peer['_'] == 'peerUser' ? 'users' : 'chats']) as $p) {
					if($p['id'] == $lid) {
						if(isset($p['title'])) {
							$n = $p['title'];
						} else if(isset($p['first_name'])) {
							$n = trim($p['first_name']).(isset($p['last_name']) ? ' '.trim($p['last_name']) : '');
						} else {
							$n = 'Deleted Account';
						}
						break;
					}
				}
				echo $n.'</b>';
				if($unr > 0) {
					echo ' <b class="unr">+'.$unr.'</b>';
				}
				echo '</a>';
				try {
					$msg = null;
					foreach($msgs as $m1) {
						if($m1['peer_id']==$d['peer']) {
							$msg = $m1;
							break;
						}
					}
					$mfid = null;
					$mfn = null;
					if(isset($msg['from_id'])) {
						$mfid = MP::getId($MP, $msg['from_id']);
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
						if($mfn !== null && ($id > 0 ? $mfid != $selfid : true))
							echo $mfn.': ';
						$txt = MP::dehtml(str_replace("\n", " ", $msg['message']));
						if(mb_strlen($txt, 'UTF-8') > 70) $txt = mb_substr($txt, 0, 70, 'UTF-8').'..';
						echo $txt;
						echo '</a>';
					} else if(isset($msg['action'])) {
						echo '<a href="'.$cl.'" class="cma">'.MP::parseMessageAction($msg['action'], $mfn, $mfid, $n, $lng, false, $MP).'</a>';
					} else if(isset($msg['media'])) {
						echo '<a href="'.$cl.'" class="cma">';
						if($mfn !== null && ($id > 0 ? $mfid != $selfid : true))
							echo $mfn.': ';
						echo MP::x($lng['media_att']);
						echo '</a>';
					} else {
						echo '.';
					}
					echo '</div>';
				} catch (Exception $e) {
				}
				echo '</div>';
			} catch (Exception $e) {
				echo "<xmp>$e</xmp>";
			}
			$c += 1;
		}
		unset($dialogs);
		unset($r);
	} catch (Exception $e) {
		echo '<b>'.MP::x($lng['error']).'!</b><br>';
		echo "<xmp>$e</xmp>";
	}
	echo Themes::bodyEnd();
	unset($MP);
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
