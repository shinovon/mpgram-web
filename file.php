<?php
set_time_limit(300);
use Amp\Success;
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
	if(!$user) {
		http_response_code(401);
		die;
	}
	$MP = MP::getMadelineAPI($user);
	$msg = null;
	$di = null;
	if(isset($_GET['sticker'])) {
		$di = $MP->getDownloadInfo(['_' => 'document', 'id' => (int)$_GET['sticker'], 'access_hash' => (int)$_GET['access_hash'], 'attributes' => [], 'dc_id' => 2, 'mime_type' => 'image/webp']);
	} else {
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
	}
	
	if(($info['size'] ?? 0) > 1024 * 1024 * 1024) { // 1 gb
		http_response_code(400);
		echo 'File is too large!';
		die;
	}

	$size = 180;
	if(isset($_GET['s'])) $size = (int) $_GET['s'];
	if($size <= 0) $size = 180;
	
	$p = $_GET['p'] ?? '';
	if(strpos($p, 'thumb') === 0) {
		$p = substr($p, 5);
		$t = str_replace('messagemedia', '', strtolower($msg['media']['_']));
		$m = $msg['media'][$t];
		$d = array();
		$d['InputFileLocation']['_'] = 'inputDocumentFileLocation';
		$d['InputFileLocation']['id'] = $m['id'];
		$d['InputFileLocation']['access_hash'] = $m['access_hash'];
		$d['InputFileLocation']['thumb_size'] = 'm';
		$d['InputFileLocation']['file_reference'] = $m['file_reference'];
		$d['InputFileLocation']['dc_id'] = $m['dc_id'];
		$di = $d;
	}
	if(strpos($p, 'r') === 0) {
		header('Cache-Control: private, max-age=86400');
		$p = substr($p, 1);
		if(strpos($p, 'tgs') === 0) {
			if(!defined('CONVERT_TGS_STICKERS') || !CONVERT_TGS_STICKERS) {
				http_response_code(403);
				die;
			}
			if(($di['MessageMedia']['document']['size'] ?? 0) >= 512*1024) {
				http_response_code(400);
				die;
			}
			if($size >= 240) $size = 240;
			if(!file_exists(TGS_TMP_DIR)) mkdir(TGS_TMP_DIR);
			else {
				try {
					$scan = scandir(TGS_TMP_DIR);
					$time = time();
					foreach($scan as $n) {
						if(strpos($n, '.tgs') === false && strpos($n, '.gif') === false && strpos($n, '.png') === false)
							continue;
						if(filectime(TGS_TMP_DIR.$n) + 30 * 60 > $time)
							continue;
						unlink(TGS_TMP_DIR.$n);
					}
				} catch (Exception) {}
			}
			$p = substr($p, 3);
			$png = strpos($p, 'p') === 0;
			$gif = LOTTIE_TO_GIF && strpos($p, 's') === false;
			$prefix = TGS_TMP_DIR.\hash('crc32',$user).(isset($_GET['sticker']) ? (int)$_GET['sticker'] : ($cid.'_'.$mid));
			$outpath = $prefix.($gif?'.gif':'.png');
			$inpath = $prefix.'.tgs';
			if(!file_exists($outpath)) {
				if(!file_exists($inpath)) {
					$MP->downloadToFile($di, $inpath);
				}
				$res = null;
				if($gif) {
					$res = shell_exec('bash `'.LOTTIE_DIR.'lottie_to_gif.sh --output "'.$outpath.'" --width '.$size.' --height '.$size.' --quality 70 --threads 1 --fps 10 "'.$inpath.'"'.(WINDOWS?'':' 2>&1').'`') ?? '';
				} else {
					$outpath = $prefix;
					$res = shell_exec('bash `'.LOTTIE_DIR.'lottie_to_png.sh --output "'.$outpath.'" --width '.$size.' --height '.$size.' --quality 70 --threads 1 --fps 10 "'.$inpath.'"'.(WINDOWS?'':' 2>&1').'`') ?? '';
					if(file_exists($outpath.'/')) {
						if(file_exists($outpath.'/000.png')) {
							rename($outpath.'/000.png', $outpath.'.png');
						}
						$scan = scandir($outpath.'/');
						foreach($scan as $n) {
							if($n == '.' || $n == '..') continue;
							if(is_dir($outpath.'/'.$n)) {
								foreach(scandir($outpath.'/'.$n.'/') as $n2) {
									if($n2 == '.' || $n2 == '..') continue;
									unlink($outpath.'/'.$n.'/'.$n2);
								}
								rmdir($outpath.'/'.$n);
								continue;
							}
							unlink($outpath.'/'.$n);
						}
						rmdir($outpath.'/');
						$outpath.='.png';
					}
				}
			}
			if(file_exists($inpath)) {
				unlink($inpath);
			}
			if(!file_exists($outpath)) {
				http_response_code(500);
				die;
			}
			if($gif) {
				header('Content-Type: image/gif');
				echo file_get_contents($outpath);
				die;
			} elseif($png) {
				header('Content-Type: image/png');
				echo file_get_contents($outpath);
				die;
			}
			
			header('Content-Type: image/jpeg');
			$img = imagecreatefromstring(file_get_contents($outpath));
			imagejpeg($img, null, 40);
			imagedestroy($img);
			die;
		}
		$payload = new Amp\ByteStream\Payload($MP->downloadToReturnedStream($di));
		$img = imagecreatefromstring($payload->buffer());
		$payload->close();
		if($p == 'stickerp') {
			$w1 = $w = imagesx($img);
			$h1 = $h = imagesy($img);

			if($w > $size) {
				$h = ($h/$w)*$size;
				$h = (int)$h;
				$w = $size;
				$temp = imagecreatetruecolor($w, $h);
				$c = imagecolorallocatealpha($temp, 0, 0, 0, 127);
				imagefill($temp, 0, 0, $c);
				imagealphablending($temp, false);
				imagesavealpha($temp, true);
				imagecopyresampled($temp, $img, 0, 0, 0, 0, $w, $h, $w1, $h1);
				imagedestroy($img);
				$img = $temp;
			}
			header('Content-Type: image/png');
			imagepng($img);
			imagedestroy($img);
			die;
		} elseif($p == 'png') {
			header('Content-Type: image/png');
			imagepng($img);
			imagedestroy($img);
			die;
		} else {
			$w = imagesx($img);
			$h = imagesy($img);
			$q = 50;
			if($p == 'orig') {
				$q = 80;
			} elseif($p == 'prev') {
				if($w > $size) {
					$h = ($h/$w)*$size;
					$w = $size;
					$img = resize($img, $w, $h);
				} elseif($h > $size) {
					$w = ($w/$h)*$size;
					$h = $size;
					$img = resize($img, $w, $h);
				}
			} elseif($p == 'min') {
				$q = 30;
				if($h > 90) {
					$w = ($w/$h)*90;
					$h = 90;
					$img = resize($img, $w, $h);
				}
				if($w > 180) {
					$h = ($h/$w)*180;
					$w = 180;
					$img = resize($img, $w, $h);
				}
			} elseif($p == 'sticker') {
				$q = 75;
				if($w > $size) {
					$h = ($h/$w)*$size;
					$w = $size;
					$img = resize($img, $w, $h);
				} elseif($h > 90) {
					$w = ($w/$h)*90;
					$h = 90;
					$img = resize($img, $w, $h);
				}
			} elseif($p == 'sprev') {
				$q = 29;//2
				if($w > 100) {
					$h = ($h/$w)*100;
					$w = 100;
					$img = resize($img, $w, $h);
				} elseif($h > 80) {
					$w = ($w/$h)*80;
					$h = 80;
					$img = resize($img, $w, $h);
				}
			} elseif($p == 'audio') {
				if($h > 36) {
					$w = ($w/$h)*36;
					$h = 36;
					$img = resize($img, $w, $h);
				}
				if($w > 36) {
					$h = ($h/$w)*36;
					$w = 36;
					$img = resize($img, $w, $h);
				}
			}
		}
		header('Content-Type: image/jpeg');
		imagejpeg($img, null, $q);
		imagedestroy($img);
	} else /*if(isset($_GET['audio'])) {
		echo '<a href="file.php?m='.$_GET['m'].'&c='.$_GET['c'].'">Download</a><br>';
		echo '<audio controls preload="none" src="file.php?m='.$_GET['m'].'&c='.$_GET['c'].'">';
	} else */{
		if(isset($_GET['name'])) {
			unset($di['name']);
			unset($di['ext']);
		}
		header('Cache-Control: no-cache, no-store');
		$MP->downloadToBrowser($di);
	}
} catch (Exception $e) {
	http_response_code(500);
	echo $e;
}
