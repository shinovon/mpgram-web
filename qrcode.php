<?php
/*
Copyright (c) 2022-2026 Arman Jussupgaliyev
*/
include 'mp.php';

if (!isset($_GET['t'])) MP::startSession();

require_once 'vendor/autoload.php';
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QROutputInterface;
if (!isset($_GET['t']) && !isset($_SESSION['qr_token'])) {
    http_response_code(400);
    die;
}
try {
    $options = new QROptions;
    $options->outputType = QROutputInterface::GDIMAGE_PNG;
    $options->scale = (int) ($_GET['s'] ?? '6');
    $options->imageTransparent = false;
    $options->imageBase64 = false;
    $qr = (new QRCode($options))->render(base64_decode($_GET['t'] ?? $_SESSION['qr_token']));

    if (!empty($_GET['tw']) || !empty($_GET['th'])) {
        $img = imagecreatefromstring($qr);
        $ow = imagesx($img);
        $oh = imagesy($img);

        $h = (int) $_GET['th'] ?? 128;
        $w = ($ow / $oh) * $h;

        $tw = (int) $_GET['tw'] ?? 128;
        if ($w > $tw) {
            $w = $tw;
            $h = ($oh / $ow) * $w;
        }

        if ($ow < $w && $oh < $h) {
            header("Content-Type: image/png");
            echo $qr;
            die;
        }
        $newimg = imagecreatetruecolor($w, $h);
        imagecopyresampled($newimg, $img, 0, 0, 0, 0, $w, $h, $ow, $oh);
        header("Content-Type: image/png");
        imagepng($newimg);
    } else {
        header("Content-Type: image/png");
        echo $qr;
    }
} catch (Exception $e) {
    http_response_code(500);
}
