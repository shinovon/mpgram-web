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
$reverse = MP::getSettingInt('reverse', 0) == 1;
$autoscroll = MP::getSettingInt('autoscroll', 1) == 1;

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
} else if(isset($_GET['m'])) {
	$msgoffsetid = (int) $_GET['m'];
	$msgoffset = -1;
}
if(isset($_GET['max_id'])) {
	$msgmaxid = (int) $_GET['max_id'];
}

$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die();
}

header('Content-Type: text/html; charset='.MP::$enc);
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
	$info = $MP->getInfo($id);
	$un = null;
	$name = null;
	$pm = false;
	$ch = false;
	$left = false;
	$id = MP::getId($MP, $info);
	$canpost = false;
	if(isset($info['Chat'])) {
		$ch = isset($info['type']) && $info['type'] == 'channel';
		if(isset($info['Chat']['title'])) {
			$name = $info['Chat']['title'];
		}
		if(isset($info['Chat']['username'])) {
			$un = $info['Chat']['username'];
		}
		if(isset($info['Chat']['admin_rights']) && isset($info['Chat']['admin_rights']['post_messages'])) {
			$canpost = $info['Chat']['admin_rights']['post_messages'];
		}
		$left = isset($info['Chat']['left']) && $info['Chat']['left'];
	} else if(isset($info['User'])) {
		$pm = true;
		if(isset($info['User']['first_name'])) {
			$name = $info['User']['first_name'];
		}
		if(isset($info['User']['username'])) {
			$un = $info['User']['username'];
		}
	}
	if($left && isset($_GET['join'])) {
		$MP->channels->joinChannel(['channel' => $id]);
		$left = false;
	} else if(!$left && isset($_GET['leave'])) {
		$MP->channels->leaveChannel(['channel' => $id]);
		$left = true;
	}
	function printInputField() {
		global $left;
		global $ch;
		global $id;
		global $lng;
		global $reverse;
		global $canpost;
		echo '<div class="in" id="text">';
		if($left) {
			echo '<form action="chat.php">';
			echo '<input type="hidden" name="c" value="'.$id.'">';
			echo '<input type="hidden" name="join" value="1">';
			echo '<input type="submit" value="'.MP::x($lng['join']).'">';
			echo '</form>';
		} else if(!$ch || $canpost) {
			$chr = isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Series60/3') === false;
			echo '<form action="write.php"'.($chr ? ' method="post"' : '').' class="in">';
			echo '<input type="hidden" name="c" value="'.$id.'">';
			echo '<textarea name="msg" value="" style="width: 100%; height: 3em"></textarea><br>';
			echo '<input type="submit" value="'.MP::x($lng['send']).'">';
			echo '</form>';
			echo '<form action="msg.php" class="in'.($chr ? 'r' : '').'">';
			echo '<input type="hidden" name="c" value="'.$id.'">';
			echo '<input type="submit" value="'.MP::x($lng['send_file']).'">';
			echo '</form>';
		}
		if($reverse) {
			echo '<div><a href="chats.php">'.MP::x($lng['back']).'</a>';
			echo ' <a href="chat.php?c='.$id.'&upd=1">'.MP::x($lng['refresh']).'</a></div>';
		}
		echo '</div>';
	}

	$r = $MP->messages->getHistory([
	'peer' => $id,
	'offset_id' => $msgoffsetid,
	'offset_date' => 0,
	'add_offset' => $msgoffset,
	'limit' => $msglimit,
	'max_id' => $msgmaxid,
	'min_id' => 0,
	'hash' => 0]);
	$id_offset = null;
	if(isset($r['offset_id_offset'])) {
		$id_offset = $r['offset_id_offset'];
		if($msgoffset < 0) {
			$id_offset = $id_offset+$msgoffset+1;
		}
	}
	$endReached = $id_offset === 0 || ($id_offset === null && $msgoffset <= 0);
	$hasOffset = $msgoffset > 0 || $msgoffsetid > 0;
	$rm = $r['messages'];
	echo '<head><title>'.MP::dehtml($name).'</title>';
	echo Themes::head();
	if((!$hasOffset || $endReached) && $autoupd == 1 && count($rm) > 0) {
		$ii = $rm[0]['id'];
		if($dynupd == 1) {
			echo '<script type="text/javascript">
<!--
function rr(){if(typeof XMLHttpRequest===\'undefined\'){XMLHttpRequest=function(){try{return new ActiveXObject("Msxml2.XMLHTTP.6.0");}catch(e){}try{return new ActiveXObject("Msxml2.XMLHTTP.3.0");}catch(e){}try{return new ActiveXObject("Msxml2.XMLHTTP");}catch(e){}try{return new ActiveXObject("Microsoft.XMLHTTP");}catch(e){}throw new Error("NO XMLHttpRequest Support!");};}return new XMLHttpRequest();}
function ee(e){if(e.message !== undefined && e.message !== null){alert(e.message);}else{alert(e.toString());}}
var r = null;
function h(){if(r.readyState == 4){try{var e=r.responseText;if(e!=null&&e.length>1){var f=e.indexOf("||");if(f!=-1){b=e.substring(0,f);e=e.substring(f+2);if(e.length>1){var msgs=document.getElementById("msgs");var d=document.createElement("div");d.innerHTML=e;for(var i=d.childNodes.length-1;i>=0;i--){'.($reverse ? 'msgs.appendChild(d.childNodes[i])':'msgs.insertBefore(d.childNodes[i],msgs.firstChild)').';}while(msgs.childNodes.length>'.$msglimit.'){msgs.removeChild(msgs.'.($reverse ? 'first' : 'last').'Child);}}}'.($autoscroll && $reverse ? 'setTimeout("autoScroll()",500);' : '').'}}catch(e){ee(e);}}}
var b="'.$ii.'";var c=0;function a(){c++;if(c>70)return;try{r=rr();r.onreadystatechange=h;setTimeout("a();",'.$updint.'000);;r.open("GET","'.MP::getUrl().'msgs.php?user='.$user.'&id='.$id.'&i="+b+"&lang='.$lng['lang'].'&timeoff='.$timeoff.'");r.send(null);}catch(e){ee(e);}}try{setTimeout("a()",'.$updint.'000);}catch(e){ee(e);}
//--></script>';
		} else {
			echo '<script type="text/javascript"><!--
setTimeout("location.reload(true);",'.$updint.'000);
//--></script>';
		}
	}
	if($reverse) {
		echo '<script type="text/javascript"><!--
function autoScroll(){try{document.getElementById("text").scrollIntoView();}catch(e){}}
//--></script>';
	}
	echo '</head>'."\n";
	if($reverse && $autoscroll) {
		echo Themes::bodyStart('class="c" onload="autoScroll();"');
	} else {
		echo Themes::bodyStart();
	}
	echo '<div><a href="chats.php">'.MP::x($lng['back']).'</a>';
	echo ' <a href="chat.php?c='.$id.'&upd=1">'.MP::x($lng['refresh']).'</a></div>';
	$sname = $name;
	if(mb_strlen($sname, 'UTF-8') > 30) $sname = mb_substr($sname, 0, 30, 'UTF-8');
	echo '<h3>'.MP::dehtml($name).'</h3>';
	if(!$reverse) {
		printInputField();
		if($hasOffset && !$endReached) {
			if(($id_offset !== null && $id_offset <= $msglimit) || $msgoffset == $msglimit) {
				echo '<p><a href="chat.php?c='.$id.'">'.MP::x($lng['history_up']).'</a></p>';
			} else {
				echo '<p><a href="chat.php?c='.$id.'&offset_from='.$rm[0]['id'].'&offset='.(-$msglimit-1).'">'.MP::x($lng['history_up']).'</a></p>';
			}
		}
	} else {
		if(count($rm) >= $msglimit) {
			echo '<p><a href="chat.php?c='.$id.'&offset_from='.$rm[count($rm)-1]['id'].'&reverse=1">'.MP::x($lng['history_up']).'</a></p>';
		}
		$rm = array_reverse($rm);
	}
	if(!$reverse) echo '<p></p>';
	echo '<div id="msgs">';
	MP::printMessages($MP, $rm, $id, $pm, $ch, $lng, $imgs, $name, $un, $timeoff, isset($info['channel_id']), true);
	echo '</div>';
	if(!$reverse) {
		if(count($rm) >= $msglimit) {
			echo '<p><a href="chat.php?c='.$id.'&offset_from='.$rm[count($rm)-1]['id'].'">'.MP::x($lng['history_down']).'</a></p>';
		}
	} else {
		if($hasOffset && !$endReached) {
			if(($id_offset !== null && $id_offset <= $msglimit) || $msgoffset == $msglimit) {
				echo '<p><a href="chat.php?c='.$id.'">'.MP::x($lng['history_down']).'</a></p>';
			} else {
				echo '<p><a href="chat.php?c='.$id.'&offset_from='.$rm[count($rm)-1]['id'].'&offset='.(-$msglimit-1).'&reverse=1">'.MP::x($lng['history_down']).'</a></p>';
			}
		}
		printInputField();
	}
	// Mark as read
	if($endReached) {
		try {
			if($ch || (int)$id < 0) {
				$MP->channels->readHistory(['channel' => $id, 'max_id' => 0]);
			} else {
				$MP->messages->readHistory(['peer' => $id, 'max_id' => 0]);
			}
		} catch (Exception $e) {
		}
	}
	echo Themes::bodyEnd();
} catch (Exception $e) {
	echo '<b>'.MP::x($lng['error']).'!</b><br>';
	echo '<xmp>'.$e->getMessage()."\n".$e->getTraceAsString().'</xmp>';
}
?>
