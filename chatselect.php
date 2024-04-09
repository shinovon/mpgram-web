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

$count = 300;
if(isset($_GET['count'])) {
	$count = (int) $_GET['count'];
}
$fwdchat = $_GET['c'] ?? null;
$fwdmsg = $_GET['m'] ?? null;
$query = null;
$query = $_GET['q'] ?? null;
$globalsearch = isset($_GET['g']);

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');


header('Content-Type: text/html; charset='.MP::$enc);
header('Cache-Control: private, no-cache, no-store, must-revalidate');

include 'themes.php';
Themes::setTheme($theme);

$avas = false;
try {
	$MP = MP::getMadelineAPI($user);
	echo '<head><title>'.MP::x($lng['chats']).'</title>';
	echo Themes::head();
	echo '</head>';
	echo Themes::bodyStart();
	$selfid = MP::getSelfId($MP);
	$selfname = MP::dehtml(MP::getSelfName($MP));
	$hasArchiveChats = false;
	$fid = 0;
	if(isset($_GET['f'])) {
		$fid = (int)$_GET['f'];
	}
	echo '<div>';
	$backurl = 'chats.php';
	if($fwdchat !== null) {
		$backurl = 'chat.php?c='.$fwdchat;
		if($fwdmsg !== null) {
			$backurl = 'msg.php?c='.$fwdchat.'&m='.$fwdmsg;
		}
	}
	echo '<a href="'.$backurl.'">'.MP::x($lng['back']).'</a><br>';
	$folders = $MP->messages->getDialogFilters();
	if(($folders['_'] ?? '') == 'messages.dialogFilters')
		$folders = $folders['filters'];
	$hasArchiveChats = count($MP->messages->getDialogs([
		'limit' => 1, 
		'exclude_pinned' => true,
		'folder_id' => 1
		])['dialogs']) > 0;
	$url = 'chatselect.php?';
	if($fwdchat !== null) {
		$url .= 'c='.$fwdchat.'&';
	}
	if($fwdmsg !== null) {
		$url .= 'm='.$fwdmsg.'&';
	}
	if($query === null && (count($folders) > 1 || $hasArchiveChats)) {
		echo '<div><b>'.MP::x($lng['folders']).'</b>: ';
		foreach($folders as $f) {
			if(!isset($f['id'])) {
				echo '<a href="'.$url.'">'.MP::x($lng['all_chats']).'</a> ';
			} else {
				$sel = $fid == $f['id'];
				if($sel) echo '<u>';
				echo '<a href="'.$url.'f='.$f['id'].'">'.MP::dehtml($f['title']).'</a>';
				if($sel) echo '</u>';
				echo ' ';
			}
		}
		if($hasArchiveChats) {
			$sel = $fid == 1;
			if($sel) echo '<u>';
			echo '<a href="'.$url.'f=1">'.MP::x($lng['archived_chats']).'</a>';
			if($sel) echo '</u>';
		}
		echo '</div>';
	}
	echo '<br></div>';
	try {
		$r = null;
		$dialogs = null;
		if($query !== null) {
			$dialogs = [];
			$r = $MP->contacts->search(['q' => $query]);
			$dialogs = $r[$globalsearch ? 'results' : 'my_results'];
		} elseif($fid == 1) {
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
				$r = MP::getAllDialogs($MP);
				$dialogs = [];
				$all = $r['dialogs'];
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
				if(count($f['pinned_peers']) > 0) {
					$pinned = array();
					foreach($f['pinned_peers'] as $p) {
						$p = MP::getId($p);
						foreach($all as $d) {
							if($d['peer'] == $p) {
								if(in_array($d, $dialogs)) unset($dialogs[array_search($d, $dialogs)]);
								array_push($pinned, $d);
								break;
							}
						}
					}
					$dialogs = array_merge($pinned, $dialogs);
				}
				unset($all);
				unset($r['messages']);
			} else {
				$r = MP::getAllDialogs($MP, 0, 0);
				$dialogs = $r['dialogs'];
				unset($r['messages']);
			}
		}
		$c = 0;
		foreach($dialogs as $d){
			if($fid == 0 && isset($d['folder_id']) && $d['folder_id'] == 1) continue;
			$id = $d['peer'] ?? $d;
			$name = null;
			foreach(($r[$id > 0 ? 'users' : 'chats']) as $p) {
				if($p['id'] != $id) continue;
				if(isset($p['title'])) {
					$name = $p['title'];
				} elseif(isset($p['first_name'])) {
					$name = trim($p['first_name']).(isset($p['last_name']) ? ' '.trim($p['last_name']) : '');
				} elseif(isset($p['last_name'])) {
					$name = trim($p['last_name']);
				} else {
					$name = 'Deleted Account';
				}
				break;
			}
			try {
				echo '<div class="c'.($c%2==0 ? '1': '0').'">';
				$cl = 'chat.php?c='.$id;
				if($fwdchat !== null) {
					$cl = 'msg.php?act=fwd&c='.$fwdchat.'&m='.$fwdmsg.'&c2='.$id;
				}
				echo '<a href="'.$cl.'">'.MP::dehtml($name).'</a>';
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
	echo '<b>'.$lng['error'].'!</b><br>';
	echo "<xmp>$e</xmp><br>";
}
