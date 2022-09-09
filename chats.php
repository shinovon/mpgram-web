<?php

include 'redirect.php';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';

$timeoff = MP::getSettingInt('timeoff');
$lang = MP::getSetting('lang', 'ru');
$theme = MP::getSettingInt('theme');
try {
	include 'locale_'.$lang.'.php';
} catch (Exception $e) {
	$lang = 'ru';
	include 'locale_'.$lang.'.php';
}

$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die();
}

$count = 15;
if(isset($_GET['count'])) {
	$count = (int) $_GET['count'];
}

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');

if(MP::$win1251) {
	header('Content-Type: text/html; charset=windows-1251');
} else {
	header('Content-Type: text/html; charset=utf-8');
}
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
		function a() {
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
		}
		window.onload = a();
//--></script>';
	}
	echo '</head>';
	echo Themes::bodyStart();
	$selfid = MP::getSelfId($MP);
	$selfname = MP::dehtml(MP::getSelfName($MP));
	echo '<header>';
	echo '<b class="self_name">'.MP::x($selfname).'</b><div class="top_bar">';
	echo '<a href="login.php?logout=1">'.MP::x($lng['logout']).'</a>';
	echo ' <a href="chats.php?upd=1">'.MP::x($lng['refresh']).'</a>';
	echo ' <a href="sets.php">'.MP::x($lng['settings']).'</a>';
	echo '</div><br>';
	echo '</header>';

	try {
		$r = $MP->messages->getDialogs([
		'offset_date' => 0,
		'offset_id' => 0,
		'add_offset' => 0,
		'limit' => $count, 
		'hash' => 0,
		]);
		$dialogs = $r['dialogs'];
		$msgs = $r['messages'];
		$c = 0;
		foreach($dialogs as $k => $d){
			$id = MP::getId($MP, $d['peer']);
			$info = $MP->getInfo($d['peer']);
			try {
				echo '<div class="c'.($c%2==0 ? '1': '0').'">';
				echo '<a href="chat.php?c='.$id.'"><b>';
				$n = MP::dehtml(MP::getNameFromInfo($info, true));
				echo $n.'</b>';
				if($d['unread_count']) {
					echo ' <b>+'.$d['unread_count'].'</b>';
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
					//if(date('d.m.Y') !== date('d.m.Y', $msg['date'])) {
						//$t = date('d.m.Y', $msg['date']);
					//} else {
						$t = date('H:i', $msg['date']-$timeoff);
					//}
					echo '<div class="cm">'.$t.' ';
					if(isset($msg['message']) && strlen($msg['message']) > 0) {
						echo '<a href="chat.php?c='.$id.'">';
						if($mfn !== null && ($id > 0 ? $mfid != $selfid : true))
							echo $mfn.': ';
						$txt = str_replace("\n", " ", MP::dehtml($msg['message']));
						if(mb_strlen($txt, 'UTF-8') > 70) $txt = mb_substr($txt, 0, 70, 'UTF-8').'..';
						echo $txt;
						echo '</a>';
					} else if(isset($msg['action'])) {
						echo '<a href="chat.php?c='.$id.'" class="cma">'.MP::parseMessageAction($msg['action'], $mfn, $mfid, $n, $lng, false, $MP).'</a>';
					} else if(isset($msg['media'])) {
						echo '<a href="chat.php?c='.$id.'" class="cma">';
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
	} catch (Exception $e) {
		echo '<b>'.MP::x($lng['error']).'!</b><br>';
		echo "<xmp>$e</xmp>";
	}
	echo Themes::bodyEnd();
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
