<?php
/*
Copyright (c) 2022-2025 Arman Jussupgaliyev
*/
function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');
require_once 'vendor/autoload.php';

function resize($image, $w, $h) {
    $w = (int) $w;
    $h = (int) $h;
    $oldw = imagesx($image);
    $oldh = imagesy($image);
    $temp = imagecreatetruecolor($w, $h);
    imagecopyresampled($temp, $image, 0, 0, 0, 0, $w, $h, $oldw, $oldh);
    return $temp;
}
try {
    include 'mp.php';
    $user = MP::getUser();
    if (!$user) {
        http_response_code(401);
        die;
    }
    $MP = MP::getMadelineAPI($user);
    $msg = null;
    $di = null;
    $cid = $_GET['c'];
    $info = $MP->getInfo($cid);
    $info = $info['User'] ?? $info['Chat'] ?? $info;
    try {
        $di = $MP->getPropicInfo($info);
    } catch (Exception) {}
    $p = $_GET['p'] ?? '';
    $default = false;
    if ($di === null) {
        if (isset($_GET['a'])) {
            http_response_code(404);
            die;
        }
        $default = true;
    }
    if (str_starts_with($p, 'r')) {
        header('Cache-Control: private, max-age=2592000');
        $p = substr($p, 1);
        if ($default) {
            $img = imagecreatefromstring(file_get_contents((int)$cid < 0 ?'img/gr.png' : 'img/us.png'));
        } else {
            $payload = new Amp\ByteStream\Payload($MP->downloadToReturnedStream($di));
            $img = imagecreatefromstring($payload->buffer());
            $payload->close();
        }
        $png = false;
        if (str_starts_with($p, 'c')) {
            $png = true;
            $p = substr($p, 1);
            $w = imagesx($img);
            $h = imagesy($img);
            
            $mask = imagecreatetruecolor($w, $h);
            $c = imagecolorallocate($mask, 255, 0, 0);
            imagecolortransparent($mask, $c);
            imagefilledellipse($mask, $w/2, $h/2, $w, $h, $c);
            
            $r = imagecolorallocate($mask, 0, 0, 0);
            imagecopymerge($img, $mask, 0, 0, 0, 0, $w, $h, 100);
            imagecolortransparent($img, $r);
            imagefill($img, 0, 0, $r);
        }
        if (str_starts_with($p, 'p')) {
            $png = true;
            $p = substr($p, 1);
        }
        if ($p != 'orig') {
            $s = (int)$p;
            $img = resize($img, $s, $s);
        }
        if ($png) {
            header('Content-Type: image/png');
            imagepng($img);
            imagedestroy($img);
            die;
        }
        header('Content-Type: image/jpeg');
        imagejpeg($img, null, 80);
        imagedestroy($img);
    } else if ($default) {
        header('Content-Type: image/png');
        if ((int) $cid < 0) {
            echo file_get_contents('img/gr.png');
        } else {
            echo file_get_contents('img/us.png');
        }
        header('Cache-Control: private, max-age=2592000');
    } else {
        $MP->downloadToBrowser($di);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo '<xmp>'.$e.'</xmp>';
}
