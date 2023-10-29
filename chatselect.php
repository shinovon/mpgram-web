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

$count = 300;
if(isset($_GET['count'])) {
	$count = (int) $_GET['count'];
}
$fwdchat = null;
$fwdmsg = null;
if(isset($_GET['c'])) {
	$fwdchat = $_GET['c'];
}
if(isset($_GET['m'])) {
	$fwdmsg = $_GET['m'];
}
$query = null;
if(isset($_GET['q'])) {
	$query = $_GET['q'];
}
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
	if(count($folders) > 1 || $hasArchiveChats) {
		echo '<div>';
		echo '<b>'.MP::x($lng['folders']).'</b>: ';
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
		} else if($fid == 1) {
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
				$dialogs = array();
				$all = $r['dialogs'];
				if($f['contacts'] || $f['non_contacts']) {
					$contacts = $MP->contacts->getContacts()['contacts'];
					foreach($all as $d) {
						if($d['peer']['_'] !== 'peerUser') continue;
						$peer = $d['peer'];
						$found = false;
						foreach($contacts as $c) {
							if(MP::getId($peer) == MP::getId($c)) {
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
								if($c['id'] == $peer && !$c['broadcast'] && !in_array($d, $dialogs)) {
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
							if($c['id'] == $peer && $c['broadcast'] && !in_array($d, $dialogs)) {
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
							if(MP::getId($peer) == MP::getId($p)) {
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
							if(MP::getId($peer) == MP::getId($p)) {
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
							if(MP::getId($peer) == MP::getId($p)) {
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
				$r = MP::getAllDialogs($MP, 0, 0);
				$dialogs = $r['dialogs'];
				unset($r['messages']);
			}
		}
		$c = 0;
		foreach($dialogs as $d){
			if($fid == 0 && isset($d['folder_id']) && $d['folder_id'] == 1) continue;
			$peer = $d['peer'] ?? $d;
			$id = MP::getId($peer);
			$name = null;
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
						$name = $p['title'];
					} else if(isset($p['first_name'])) {
						$name = trim($p['first_name']).(isset($p['last_name']) ? ' '.trim($p['last_name']) : '');
					} else if(isset($p['last_name'])) {
						$name = trim($p['last_name']);
					} else {
						$name = 'Deleted Account';
					}
					break;
				}
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
