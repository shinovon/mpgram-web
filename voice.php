<?php

function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');

try {
	include 'mp.php';
	if(!defined('CONVERT_VOICE_MESSAGES') || !CONVERT_VOICE_MESSAGES) {
		echo 'Voice messages converting disabled';
		die;
	}
	$user = MP::getUser();
	if(!$user) {
		http_response_code(401);
		die;
	}
	$MP = MP::getMadelineAPI($user);
	if(!isset($_GET['c']) || !isset($_GET['m'])) die;
	$cid = $_GET['c'];
	$mid = $_GET['m'];
	if(!is_numeric($cid) || !is_numeric($mid)) die;
	if(MP::isChannel($cid)) {
		$msg = $MP->channels->getMessages(['channel' => $cid, 'id' => [$mid]]);
	} else {
		$msg = $MP->messages->getMessages(['peer' => $cid, 'id' => [$mid]]);
	}
	if($msg && isset($msg['messages']) && isset($msg['messages'][0])) {
		$msg = $msg['messages'][0];
	}
	$di = $MP->getDownloadInfo($msg['media']);
	if(!file_exists(VOICE_TMP_DIR)) mkdir(VOICE_TMP_DIR);
	
	// automatically delete converted voices
	try {
		$scan = scandir(VOICE_TMP_DIR);
		foreach($scan as $n) {
			if(strpos($n, '.mp3') === false) continue;
			if(date('d.m.y', filemtime(VOICE_TMP_DIR.$n)) == date('d.m.y', time())) {
				continue;
			}
			unlink(VOICE_TMP_DIR.$n);
		}
	} catch (Exception) {}
	
	$inpath = VOICE_TMP_DIR.\hash('crc32',$user).$cid.'_'.$mid;
	$outpath = $inpath.'.mp3';
	if(!file_exists($outpath)) {
		$MP->downloadToFile($di, $inpath);
		$res = shell_exec(FFMPEG_DIR.'ffmpeg -i "'.$inpath.'" -b:a 64k -ac 1 -y -acodec mp3 "'.$outpath.'"'.(WINDOWS?'':' 2>&1')) ?? '';
		unlink($inpath);
		if(strpos($res, 'failed') !== false) {
			echo 'Conversion failed';
			die;
		}
	}
	header('Content-Type: audio/mpeg');
	echo file_get_contents($outpath);
} catch (Exception $e) {
	echo $e;
}
