<?php
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
		return true;
	}
	public function end(string $finalData = ""): Amp\Promise {
		$this->d .= $finalData;
		return true;
	}
	public function get() {
		return $this->d;
	}
}

function resize($image, $w, $h) {
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
	if(isset($_GET['sticker'])) {
		$di = $MP->getDownloadInfo(['_' => 'document', 'id' => (int)$_GET['sticker'], 'access_hash' => (int)$_GET['access_hash'], 'attributes' => [], 'dc_id' => 2, 'mime_type' => 'image/webp']);
	} else {
		$cid = $_GET['c'];
		$mid = $_GET['m'];
		if(strpos($cid, '-100') === 0) {
			$msg = $MP->channels->getMessages(['channel' => $cid, 'id' => [$mid]]);
		} else {
			$msg = $MP->messages->getMessages(['peer' => $cid, 'id' => [$mid]]);
		}
		if($msg && isset($msg['messages']) && isset($msg['messages'][0])) {
			$msg = $msg['messages'][0];
		}
		
		$di = $MP->getDownloadInfo($msg['media']);
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
		}
		header('Content-Type: image/jpeg');
		imagejpeg($img, null, $q);
	} else {
		$MP->downloadToBrowser($di);
	}
} catch (Exception $e) {
	http_response_code(500);
}