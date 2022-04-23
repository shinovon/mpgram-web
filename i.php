<?php
function resize($image, $w, $h) {
    $oldw = imagesx($image);
    $oldh = imagesy($image);
    $temp = imagecreatetruecolor($w, $h);
    imagecopyresampled($temp, $image, 0, 0, 0, 0, $w, $h, $oldw, $oldh);
    return $temp;
}
if(!isset($_GET['u'])) {
	http_response_code(400);
	die();
}
$u = $_GET['u'];
$p = '';
if(isset($_GET['p'])) $p = $_GET['p'];
if(strpos($p, 't') === 0) {
	$t = file_get_contents($u);
	preg_match('/background-image:url\(\'(.+?)\'\)/', $t, $rr, PREG_OFFSET_CAPTURE, 0);
	if(!isset($rr[1])) {
		http_response_code(500);
		die();
	}
	$u = $rr[1][0];
	if(strpos($t, 'message_reply_thumb') !== false) {
	$o = $rr[0][1]+strlen($rr[0][0]);
		preg_match('/background-image:url\(\'(.+?)\'\)/', $t, $rr, PREG_OFFSET_CAPTURE, $o);
		if(isset($rr[1])) {
			$u = $rr[1][0];
		}
	}
	$t = null;
	$rr = null;
	$p = substr($p, 1);
}
$img = imagecreatefromjpeg($u);
if(!$img) {
	http_response_code(500);
	die();
}
$w = imagesx($img);
$h = imagesy($img);
$q = 50;
if($p == 'orig') {
	$q = 80;
} else if($p == 'prev') {
	if($w > 320) {
		$h = ($h/$w)*320;
		$w = 320;
		$img = resize($img, $w, $h);
	} else if($h > 180) {
		$w = ($w/$h)*180;
		$h = 180;
		$img = resize($img, $w, $h);
	}
} else if($p == 'min') {
	$q = 30;
	if($w > 180) {
		$h = ($h/$w)*180;
		$w = 180;
		$img = resize($img, $w, $h);
	} else if($h > 90) {
		$w = ($w/$h)*90;
		$h = 90;
		$img = resize($img, $w, $h);
	}
}
header('Content-Type: image/jpeg');
imagejpeg($img, null, $q);