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
	$name = null;
	$pm = false;
	$ch = false;
	$left = false;
	if(!is_numeric($id)) {
		$id = MP::getId($MP, $info);
	}
	$canpost = false;
	if(isset($info['Chat'])) {
		$ch = isset($info['type']) && $info['type'] == 'channel';
		if(isset($info['Chat']['title'])) {
			$name = $info['Chat']['title'];
		}
		if(isset($info['Chat']['admin_rights']) && isset($info['Chat']['admin_rights']['post_messages'])) {
			$canpost = $info['Chat']['admin_rights']['post_messages'];
		}
		$left = isset($info['Chat']['left']) && $info['Chat']['left'];
	} else if(isset($info['User'])) {
		$pm = true;
		if(isset($info['User']['first_name'])) {
			$name = $info['User']['first_name'];
		} else if(isset($info['User']['last_name'])) {
			$name = $info['User']['last_name'];
		}
	}
	$channel = isset($info['channel_id']);
	unset($info);
	if($left && isset($_GET['join'])) {
		$MP->channels->joinChannel(['channel' => $id]);
		$left = false;
	} else if(!$left && isset($_GET['leave'])) {
		$MP->channels->leaveChannel(['channel' => $id]);
		$left = true;
	}
	function printInputField() {
		global $full;
		global $left;
		global $ch;
		global $id;
		global $lng;
		global $reverse;
		global $canpost;
		echo '<div class="in'.($reverse?' t':'').'" id="text">';
		if($left) {
			echo '<form action="chat.php">';
			echo '<input type="hidden" name="c" value="'.$id.'">';
			echo '<input type="hidden" name="join" value="1">';
			echo '<input type="submit" value="'.MP::x($lng['join']).'">';
			echo '</form>';
		} else if(!$ch || $canpost) {
			$post = false;
			$opera = false;
			if(isset($_SERVER['HTTP_USER_AGENT'])) {
				$post = strpos($_SERVER['HTTP_USER_AGENT'], 'Series60/3') === false;
				$opera = strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') !== false;
			}
			echo '<form action="write.php"'.($post ? ' method="post"' : '').' class="in">';
			echo '<input type="hidden" name="c" value="'.$id.'">';
			echo '<textarea name="msg" value="" style="width: 100%; height: 3em"></textarea><br>';
			echo '<input type="submit" value="'.MP::x($lng['send']).'">';
			//echo '<input type="checkbox" id="format" name="format">';
			//echo '<label for="format">'.MP::x($lng['html_formatting']).'</label>';
			echo '</form>';
			echo '<form action="msg.php" class="in'.((!$opera) ? 'r' : '').'">';
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
	$r = $MP->messages->getHistory([
	'peer' => $id,
	'offset_id' => $msgoffsetid,
	'offset_date' => 0,
	'add_offset' => $msgoffset,
	'limit' => $msglimit,
	'max_id' => $msgmaxid,
	'min_id' => 0,
	'hash' => 0]);
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
	$rm = $r['messages'];
	$full = MP::getSetting('full', 0);
	echo '<head><title>'.MP::dehtml($name).'</title>';
	echo Themes::head();
	if((!$hasOffset || $endReached) && $autoupd == 1 && count($rm) > 0) {
		$ii = $rm[0]['id'];
		if($dynupd == 1) {
			echo '<script type="text/javascript">
<!--
function rr(){if(typeof XMLHttpRequest===\'undefined\'){XMLHttpRequest=function(){try{return new ActiveXObject("Msxml2.XMLHTTP.6.0");}catch(e){}try{return new ActiveXObject("Msxml2.XMLHTTP.3.0");}catch(e){}try{return new ActiveXObject("Msxml2.XMLHTTP");}catch(e){}try{return new ActiveXObject("Microsoft.XMLHTTP");}catch(e){}throw new Error("NO XMLHttpRequest Support!");};}return new XMLHttpRequest();}
function ee(e){if(e.message !== undefined && e.message !== null){}else{}}
var r = null;
function h(){if(r.readyState == 4){try{var e=r.responseText;if(e!=null&&e.length>1){var f=e.indexOf("||");if(f!=-1){b=e.substring(0,f);e=e.substring(f+2);if(e.length>1){var msgs=document.getElementById("msgs");var d=document.createElement("div");d.innerHTML=e;for(var i=d.childNodes.length-1;i>=0;i--){'.($reverse ? 'msgs.appendChild(d.childNodes[i])':'msgs.insertBefore(d.childNodes[i],msgs.firstChild)').';}while(msgs.childNodes.length>'.$msglimit.'){msgs.removeChild(msgs.'.($reverse ? 'first' : 'last').'Child);}}}'.($autoscroll && $reverse ? 'setTimeout("autoScroll(false)",500);' : '').'}}catch(e){ee(e);}}}
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
function getScrollY(){var a = window.pageXOffset !== undefined;var b = ((document.compatMode || "") === "CSS1Compat");return a?window.pageYOffset:b?document.documentElement.scrollTop:document.body.scrollTop;}
function getHeight(){var a = window.innerHeight !== undefined;return a?window.innerHeight:document.documentElement.clientHeight||document.body.clientHeight;}
function autoScroll(force){try{text=document.getElementById("text");if(force){text.scrollIntoView();}else{try{tw=text.clientHeight;sh=getHeight();sy=getScrollY();ph=document.body.scrollHeight;if(sy>ph-tw-sy) {text.scrollIntoView();}}catch(e){text.scrollIntoView();}}}catch(e){}}
//--></script>';
	}
	echo '</head>'."\n";
	if($reverse && $autoscroll) {
		echo Themes::bodyStart('class="c" onload="autoScroll(true);"');
	} else {
		echo Themes::bodyStart();
	}
	$useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
	$avas = strpos($useragent, 'AppleWebKit') || strpos($useragent, 'Chrome') || strpos($useragent, 'Symbian/3') || strpos($useragent, 'SymbOS') || strpos($useragent, 'Android') || strpos($useragent, 'Linux') ? 1 : 0;
	$avas = MP::getSettingInt('avas', $avas) && strpos($useragent, 'SymbianOS/9') === false;
	echo '<header class="ch">';
	echo '<div class="chc"><div class="chr"><small><a href="chats.php">'.MP::x($lng['back']).'</a>';
	echo ' <a href="chat.php?c='.$id.'&upd=1">'.MP::x($lng['refresh']).'</a>';
	echo ' <a href="chatinfo.php?c='.$id.'">'.MP::x($lng['chat_info']??null).'</a>';
	echo '</small></div>';
	if($avas) {
		echo '<div class="chava"><img class="ri" src="ava.php?c='.$id.'&p=r36"></div>';
	}
	echo '<div class="chn">';
	echo MP::dehtml($name);
	echo '</div></div>';
	echo '</header>';
	if($avas) {
		echo '<div style="height: 36px;">&nbsp;</div>';
	} else {
		echo '<div>&nbsp;</div>';
	}
	$sname = $name;
	if(mb_strlen($sname, 'UTF-8') > 30) $sname = mb_substr($sname, 0, 30, 'UTF-8');
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
	MP::printMessages($MP, $rm, $id, $pm, $ch, $lng, $imgs, $name, $timeoff, $channel, true);
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
	unset($rm);
	unset($r);
	echo Themes::bodyEnd();
	MP::gc();
} catch (Exception $e) {
	echo '<b>'.MP::x($lng['error']).'!</b><br>';
	echo '<xmp>'.$e->getMessage()."\n".$e->getTraceAsString().'</xmp>';
}
?>
