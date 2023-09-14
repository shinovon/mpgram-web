<?php
session_start();
function getCaptchaText($length) {
    $c = '0123456789abcdefghjkmnopqrstuvwxyz';
    $l = strlen($c);
    $s = '';
    for ($i = 0; $i < $length; $i++) {
        $s .= $c[rand(0, $l - 1)];
    }
    return $s;
}
$c = getCaptchaText(rand(5, 8));
$_SESSION["captcha"] = $c; 
$img = imagecreatetruecolor(150, 50); 
imagefill($img, 0, 0, -1);
imagestring($img, rand(4, 10), rand(0, 50),rand(0, 25), $c, 0x000000);
header("Cache-Control: no-store, no-cache, must-revalidate");
header('Content-type: image/jpeg');
imagejpeg($img);
imagedestroy($img);