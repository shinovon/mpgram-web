<?php
use Amp\Success;
function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');
require_once 'vendor/autoload.php';
use Amp\ByteStream;
class StringStream implements \Amp\ByteStream\OutputStream {
	public $d;
	public function write(string $data): Amp\Promise {
		$this->d .= $data;
		return new Success(\strlen($data));
	}
	public function end(string $finalData = ""): Amp\Promise {
		$this->d .= $finalData;
		return new Success(\strlen($finalData));
	}
	public function get() {
		return $this->d;
	}
}

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
	if(!$user) {
		http_response_code(401);
		die();
	}
	$MP = MP::getMadelineAPI($user);
	$msg = null;
	$di = null;
	$cid = $_GET['c'];
	$info = $MP->getInfo($cid);
	if(isset($info['User'])) {
		$info = $info['User'];
	} else if(isset($info['Chat'])) {
		$info = $info['Chat'];
	}
	try {
		$di = $MP->getPropicInfo($info);
	} catch (Exception $e) {
		header('Content-Type: image/png');
		if((int) $cid < 0) {
			echo file_get_contents('gr.png');
		} else {
			echo file_get_contents('us.png');
		}
		die();
	}
	$p = '';
	if(isset($_GET['p'])) {
		$p = $_GET['p'];
	}
	if(strpos($p, 'r') === 0) {
		header('Cache-Control: private, max-age=86400');
		$p = substr($p, 1);
		$stream = new StringStream();
		$MP->downloadToStream($di, $stream);
		$img = imagecreatefromstring($stream->get());
		if($p == 'png') {
			header('Content-Type: image/png');
			imagepng($img);
		} else {
			$w = imagesx($img);
			$h = imagesy($img);
			$q = 80;
			if($p == 'orig') {
				$q = 80;
			} else {
				$s = (int)$p;
				$img = resize($img, $s, $s);
			}
		}
		header('Content-Type: image/jpeg');
		imagejpeg($img, null, $q);
	} else {
		$MP->downloadToBrowser($di);
	}
} catch (Exception $e) {
	http_response_code(500);
}
