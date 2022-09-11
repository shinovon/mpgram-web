<?php

include 'redirect.php';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';

$iev = MP::getIEVersion();
$timeoff = MP::getSettingInt('timeoff');
$lang = MP::getSetting('lang', 'ru');
$theme = MP::getSettingInt('theme');
$autoupd = MP::getSettingInt('autoupd', ($iev == 0 || $iev > 4) ? 1 : 0);
$updint = MP::getSettingInt('updint', 10);
$dynupd = MP::getSettingInt('dynupd', 1);

try {
	include 'locale_'.$lang.'.php';
} catch (Exception $e) {
	$lang = 'ru';
	include 'locale_'.$lang.'.php';
}

$msglimit = 20;
$msgoffset = 0;
$reverse = false;
if(isset($_GET['r'])) {
	$reverse = true;
	$msglimit = 8;
}
if(isset($_GET['limit'])) {
	$msglimit = (int) $_GET['limit'];
}
if(isset($_GET['off'])) {
	$msgoffset = (int) $_GET['off'];
}

$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die();
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-cache, no-store');

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

include 'themes.php';
Themes::setTheme($theme);

$imgs = true;
try {
	$MP = MP::getMadelineAPI($user);
	try {
		$MP->getPwrChat($id, true);
	} catch (Exception $e) {
	}
	$info = $MP->getInfo($id);
	$un = null;
	$lid = null;
	$name = null;
	$pm = false;
	$ch = false;
	$left = false;
	if(isset($info['Chat'])) {
		$ch = isset($info['type']) && $info['type'] == 'channel';
		if(isset($info['Chat']['title'])) {
			$name = $info['Chat']['title'];
		}
		if(isset($info['Chat']['username'])) {
			$un = $info['Chat']['username'];
		}
		$left = isset($info['Chat']['left']) && $info['Chat']['left'];
		if(isset($info['Chat']['id'])) {
			$lid = $info['Chat']['id'];
			$id = (int)'-100'.$lid;
		}
	} else if(isset($info['User'])) {
		$pm = true;
		if(isset($info['User']['first_name'])) {
			$name = $info['User']['first_name'];
		}
		if(isset($info['User']['username'])) {
			$un = $info['User']['username'];
		}
		if(isset($info['User']['id'])) {
			$lid = $info['User']['id'];
			$id = (int)$lid;
		}
	}
	if($left && isset($_GET['join'])) {
		$MP->channels->joinChannel(['channel' => $id]);
		$left = false;
	} else if(!$left && isset($_GET['leave'])) {
		$MP->channels->leaveChannel(['channel' => $id]);
		$left = true;
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
	echo '<head><title>'.MP::dehtml($name).'</title>';
	echo Themes::head();
	if($msgoffset == 0 && $autoupd == 1 && count($rm) > 0) {
	$ii = $rm[0]['id'];
		if($dynupd == 1) {
echo '<script type="text/javascript">
<!--
var ii = "'.$ii.'";
var count = 0;
function a() {
	count++;
	if(count > 60) return;
	try {
		var r = new XMLHttpRequest();
		r.onload = function() {
			try {
				var x = r.responseText;
				//console.log(x);
				if(x != null && x.length > 1) {
					var j = x.indexOf("||");
					if(j != -1) {
						ii = x.substring(0, j);
						x = x.substring(j+2);
						if(x.length > 1) {
							var msgs = document.getElementById("msgs");
							var d = document.createElement("div");
							d.innerHTML = x;
							'.($reverse ? 
							'for (var i = 0; i < d.childNodes.length; i++) {
								msgs.appendChild(d.childNodes[i]);
							}
							while (msgs.childNodes.length > '.$msglimit.') {
								msgs.removeChild(msgs.firstChild);
							}' : 'for (var i = d.childNodes.length-1; i >= 0; i--) {
								msgs.insertBefore(d.childNodes[i], msgs.firstChild);
							}
							while (msgs.childNodes.length > '.$msglimit.') {
								msgs.removeChild(msgs.lastChild);
							}').'
						}
					}
				}
				setTimeout("a()", '.$updint.'000);
			} catch(e) {
				alert(e);
			}
		}

		r.open("GET", "'.MP::getUrl().'msgs.php?user='.$user.'&id='.$id.'&i="+ii+"&lang='.$lang.'&timeoff='.$timeoff.'");
		r.send();
	} catch(e) {
		alert(e);
	}
}
try {
	setTimeout("a()", '.$updint.'000);
} catch(e) {
	alert(e);
}
//--></script>';
	} else {
	echo '<script type="text/javascript">
         <!--
            setTimeout("location.reload(true);", '.$updint.'000);
         //--></script>';
	}
	}
	echo '</head>'."\n";
	echo Themes::bodyStart();
	echo '<div class="top_bar"><a href="chats.php">'.MP::x($lng['back']).'</a>';
	$sname = $name;
	if(mb_strlen($sname, 'UTF-8') > 30) $sname = mb_substr($sname, 0, 30, 'UTF-8');
	//if(!$ch) echo ' <a href="write.php?c='.$id.'&n='.urlencode($sname).'">'.$lng['write_msg'].'</a>';
	echo ' <a href="chat.php?c='.$id.'&upd=1">'.MP::x($lng['refresh']).'</a></div>';
	echo '<h3 class="chat_title">'.MP::dehtml($name).'</h3>';
	if(!$reverse) {
		if($left) {
			echo '<form action="chat.php">';
			echo '<input type="hidden" name="c" value="'.$id.'">';
			echo '<input type="hidden" name="join" value="1">';
			echo '<input type="submit" value="'.MP::x($lng['join']).'">';
			echo '</form>';
		} else if(!$ch) {
			echo '<form action="write.php" method="post" style="display: inline;">';
			echo '<input type="hidden" name="c" value="'.$id.'">';
			echo '<textarea name="msg" value="" style="width: 100%"></textarea><br>';
			echo '<input type="submit" value="'.MP::x($lng['send']).'">';
			echo '</form>';
			echo '<form action="file.php" style="display: inline; float: right;">';
			echo '<input type="hidden" name="c" value="'.$id.'">';
			echo '<input type="submit" value="'.MP::x($lng['send_file']).'">';
			echo '</form>';
		}
		if($msgoffset > 0) {
			$i = $msgoffset - $msglimit;
			if($i < 0) $i = 0;
			echo '<p><a href="chat.php?c='.$id.'&off='.$i.'&limit='.$msglimit.'">'.MP::x($lng['history_up']).'</a></p>';
		}
	} else {
		if(count($rm) >= $msglimit) {
			echo '<p><a href="chat.php?c='.$id.'&off='.($msgoffset+$msglimit).'&limit='.$msglimit.'&reverse=1">'.MP::x($lng['history_up']).'</a></p>';
		}
	}
	if($reverse) $rm = array_reverse($rm);
	echo '<div id="msgs">';
	MP::printMessages($MP, $rm, $id, $pm, $ch, $lng, $imgs, $name, $un, $timeoff);
	echo '</div>';
	if(!$reverse) {
		if(count($rm) >= $msglimit) {
			echo '<p><a href="chat.php?c='.$id.'&off='.($msgoffset+$msglimit).'&limit='.$msglimit.'">'.MP::x($lng['history_down']).'</a></p>';
		}
	} else {
		if($msgoffset > 0) {
			$i = $msgoffset - $msglimit;
			if($i < 0) $i = 0;
			echo '<p><a href="chat.php?c='.$id.'&off='.$i.'&limit='.$msglimit.'&reverse=1">'.MP::x($lng['history_down']).'</a></p>';
        }
	}
	if($reverse) {
		if($left) {
			echo '<form action="chat.php">';
			echo '<input type="hidden" name="c" value="'.$id.'">';
			echo '<input type="hidden" name="join" value="1">';
			echo '<input type="submit" value="'.MP::x($lng['join']).'">';
			echo '</form>';
		} else if(!$ch) {
			echo '<form action="write.php" method="post" style="display: inline;">';
			echo '<input type="hidden" name="c" value="'.$id.'">';
			echo '<textarea name="msg" value="" style="width: 100%"></textarea><br>';
			echo '<input type="submit" value="'.MP::x($lng['send']).'">';
			echo '</form>';
			echo '<form action="file.php" style="display: inline; float: right;">';
			echo '<input type="hidden" name="c" value="'.$id.'">';
			echo '<input type="submit" value="'.MP::x($lng['send_file']).'">';
			echo '</form>';
		}
	}
	try {
		if($ch || (int)$id < 0) {
			$MP->channels->readHistory(['channel' => $id, 'max_id' => 0]);
		} else {
			$MP->messages->readHistory(['peer' => $id, 'max_id' => 0]);
		}
	} catch (Exception $e) {
	}
	echo Themes::bodyEnd();
} catch (Exception $e) {
	echo '<b>'.MP::x($lng['error']).'!</b><br>';
	echo "<xmp>$e</xmp>";
}
?>
