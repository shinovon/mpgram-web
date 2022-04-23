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
	if(strpos($t, 'tgme_widget_message_photo') !== false) {
		$t = substr($t, strpos($t, 'tgme_widget_message_photo'));
	}
	preg_match('/background-image:url\(\'(.+?)\'\)/', $t, $rr, PREG_OFFSET_CAPTURE, 0);
	if(!isset($rr[1])) {
		http_response_code(500);
		die();
	}
	$u = $rr[1][0];
	$t = null;
	$rr = null;
	$p = substr($p, 1);
}
/*if(strpos($u, '//') === 0) {
	$u = 'https:'.$u;
}
if(strpos($u, '.png') == strlen($u)-4) {
	$img = imagecreatefrompng($u);
} else {*/
$img = imagecreatefromjpeg($u);
//}
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
	if($w > 240) {
		$h = ($h/$w)*240;
		$w = 240;
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