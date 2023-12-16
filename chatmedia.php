<?php
include 'redirect.php';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';

$lng = MP::initLocale();

$msglimit = MP::getSettingInt('limit', 20);
$theme = MP::getSettingInt('theme');

$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die;
}

$id = $_GET['c'] ?? $_GET['peer'] ?? die;
$filter = $_GET['f'] ?? 'photos';
$filter = strtoupper(substr($filter, 0, 1)).\substr($filter, 1);
$file = htmlentities($_SERVER['PHP_SELF']);
$query = $_GET['q'] ?? null;
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

function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');

include 'themes.php';
Themes::setTheme($theme);
try {
	$MP = MP::getMadelineAPI($user);
	$info = $MP->getInfo($id);
	$name = null;
	if(!is_numeric($id)) {
		$id = MP::getId($info);
	}
	$name = $info['Chat']['title'] ?? $info['User']['first_name'] ?? $info['User']['last_name'] ?? null;
	$r = $MP->messages->search([
	'peer' => $id,
	'limit' => $msglimit,
	'filter' => ['_' => 'inputMessagesFilter'.$filter],
	'q' => $query,
	'offset_id' => $msgoffsetid,
	'offset_date' => 0,
	'add_offset' => $msgoffset,
	'max_id' => $msgmaxid,
	'min_id' => 0,
	'hash' => 0
	]);
	$id_offset = null;
	if(isset($r['offset_id_offset'])) {
		$id_offset = $r['offset_id_offset'];
		if($msgoffset < 0) {
			$id_offset = $id_offset+$msgoffset;
		}
	}
	$endReached = $id_offset === 0 || ($id_offset === null && $msgoffset <= 0);
	$hasOffset = $msgoffset > 0 || $msgoffsetid > 0;
	$rm = $r['messages'];
	echo '<head><title>'.MP::dehtml($name).'</title>';
	echo Themes::head();
	echo '</head>';
	echo Themes::bodyStart();
	echo '<div><a href="chat.php?c='.$id.'">'.MP::x($lng['back']).'</a></div>';
	echo '<p><a href="chatmedia.php?c='.$id.'&f=Photos">Photos</a> <a href="chatmedia.php?c='.$id.'&f=Document">Documents</a> <a href="chatmedia.php?c='.$id.'&f=Music">Audio</a> <a href="chatmedia.php?c='.$id.'&f=Voice">Voice</a></p>';
	if($hasOffset && !$endReached) {
		if(($id_offset !== null && $id_offset <= $msglimit) || $msgoffset == $msglimit) {
			echo '<p><a href="'.$file.'?c='.$id.'&f='.$filter.'">'.MP::x($lng['history_up']).'</a></p>';
		} else {
			echo '<p><a href="'.$file.'?c='.$id.'&f='.$filter.'&offset_from='.$rm[0]['id'].'&offset='.(-$msglimit-1).'">'.MP::x($lng['history_up']).'</a></p>';
		}
	}
	foreach($rm as $m) {
		try {
			if(!isset($m['media'])) continue;
			echo '<div class="mm" id="msg_'.$id.'_'.$m['id'].'">';
			MP::printMessageMedia($MP, $m, $id, true, $lng, true);
			echo '</div>';
		} catch (Exception $e) {
			echo '<xmp>'.$e->getMessage()."\n".$e->getTraceAsString().'</xmp>';
		}
	}
	if(count($rm) >= $msglimit) {
		echo '<p><a href="'.$file.'?c='.$id.'&f='.$filter.'&offset_from='.$rm[count($rm)-1]['id'].'">'.MP::x($lng['history_down']).'</a></p>';
	}
	echo Themes::bodyEnd();
} catch (Exception $e) {
	echo '<b>'.MP::x($lng['error']).'!</b><br>';
	echo '<xmp>'.$e->getMessage()."\n".$e->getTraceAsString().'</xmp>';
}
