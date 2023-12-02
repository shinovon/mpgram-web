<?php
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
	if(strpos($cid, '-100') === 0) {
		$msg = $MP->channels->getMessages(['channel' => $cid, 'id' => [$mid]]);
	} else {
		$msg = $MP->messages->getMessages(['peer' => $cid, 'id' => [$mid]]);
	}
	if($msg && isset($msg['messages']) && isset($msg['messages'][0])) {
		$msg = $msg['messages'][0];
	}
	$di = $MP->getDownloadInfo($msg['media']);
	if(!file_exists(VOICE_TMP_DIR)) mkdir(VOICE_TMP_DIR);
	$inpath = VOICE_TMP_DIR.\hash('crc32',$user).$cid.'_'.$mid;
	$outpath = $inpath.'.mp3';
	if(!file_exists($outpath)) {
		$MP->downloadToFile($di, $inpath);
		shell_exec(FFMPEG_DIR.'ffmpeg -i "'.$inpath.'" -b:a 64k -ac 1 -acodec mp3 "'.$outpath.'"');
		unlink($inpath);
	}
	header('Content-Type: audio/mpeg');
	echo file_get_contents($outpath);
} catch (Exception $e) {
	echo $e;
}
