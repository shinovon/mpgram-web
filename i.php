<?php
function resize($image, $w, $h) {
    $oldw = imagesx($image);
    $oldh = imagesy($image);
    $temp = imagecreatetruecolor($w, $h);
    imagecopyresampled($temp, $image, 0, 0, 0, 0, $w, $h, $oldw, $oldh);
    return $temp;
}
$u = null;
if(isset($_GET['u'])) {
	$u = $_GET['u'];
	if(strpos($u, 'https://t.me') !== 0) {
		die();
	}
} else if(isset($_GET['i'])) {
	if(strpos($u, '/') !== false) {
		die();
	}
	if(strpos($u, '.') !== false) {
		die();
	}
	$u = 'img/'.$_GET['i'];
} else {
	http_response_code(400);
	die();
}
$p = '';
$webp = false;
$png = false;
if(isset($_GET['p'])) $p = $_GET['p'];
if(strpos($p, 't') === 0) {
	header('Cache-Control: public, max-age=86400');
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
} else if(strpos($p, 'w') === 0) {
	header('Cache-Control: private, max-age=86400');
	$webp = true;
	$p = substr($p, 1);
} else if(strpos($p, 'l') === 0) {
	header('Cache-Control: private, max-age=86400');
	$png = true;
	$p = substr($p, 1);
}
if($png) {
	$img = imagecreatefrompng($u);
} else if($webp) {
	$img = imagecreatefromwebp($u);
} else {
	$img = imagecreatefromjpeg($u);
}
if(!$img) {
	http_response_code(500);
	die();
}
if($p == 'stickerp') {
	$w1 = $w = imagesx($img);
	$h1 = $h = imagesy($img);
	
	if($w > 180) {
		$h = ($h/$w)*180;
		$w = 180;
		$temp = imagecreatetruecolor($w, $h);
		$c = imagecolorallocatealpha($temp, 0, 0, 0, 127);
		imagefill($temp, 0, 0, $c);
		imagealphablending($temp, false);
		imagesavealpha($temp, true);
		imagecopyresampled($temp, $img, 0, 0, 0, 0, $w, $h, $w1, $h1);
		$img = $temp;
	}
	header('Content-Type: image/png');
	imagepng($img);
} else if($p == 'png') {
	header('Content-Type: image/png');
	imagepng($img);
} else {
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
	} else if($p == 'sticker') {
		$q = 75;
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
}