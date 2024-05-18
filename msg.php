<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';
MP::startSession();
$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die;
}

$theme = MP::getSettingInt('theme');
$lng = MP::initLocale();


$id = $_POST['c'] ?? $_GET['c'] ?? die;
$msg = $_POST['m'] ?? $_GET['m'] ?? null;
$out = isset($_POST['o']) || isset($_GET['o']);
$ch = isset($_POST['ch']) || isset($_GET['ch']);
$random = $_POST['r'] ?? $_GET['r'] ?? null;
$edit = isset($_POST['edit']) || isset($_GET['edit']);
$uncompressed = isset($_GET['unc']) || isset($_POST['unc']);
$voicesupport = defined('CONVERT_VOICE_MESSAGES') && CONVERT_VOICE_MESSAGES;
$voice = (isset($_POST['voice']) || isset($_GET['voice'])) && $voicesupport;
$spoiler = isset($_POST['sp']) || isset($_GET['sp']);
$candelete = isset($_POST['d']) || isset($_GET['d']);
$canban = isset($_POST['b']) || isset($_GET['b']);
$canedit = isset($_POST['e']) || isset($_GET['e']);

header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: private, no-cache, no-store");

function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');

include 'themes.php';
Themes::setTheme($theme);
$reason = false;
try {
	if(isset($_GET['act'])) {
		$act = $_GET['act'];
		$MP = MP::getMadelineAPI($user);
		switch($act) {
		case 'delete':
			if(is_numeric($id) && (int)$id > 0) {
				$MP->messages->deleteMessages(['id' => [(int)$msg]]);
			} else {
				$MP->channels->deleteMessages(['channel' => $id, 'id' => [(int)$msg]]);
			}
			header('Location: chat.php?c='.$id);
			break;
		case 'fwdh':
			$MP->messages->forwardMessages(['from_peer' => $id, 'to_peer' => $id, 'id' => [(int)$msg]]);
			header('Location: chat.php?c='.$id);
			break;
		case 'fwd':
			$MP->messages->forwardMessages(['from_peer' => $id, 'to_peer' => $_GET['c2'], 'id' => [(int)$msg]]);
			header('Location: chat.php?c='.$id);
			break;
		case 'save':
			$MP->messages->forwardMessages(['from_peer' => $id, 'to_peer' => 'me', 'id' => [(int)$msg]]);
			header('Location: chat.php?c='.$id);
			break;
		case 'ban':
			$msg = $MP->channels->getMessages(['channel' => $id, 'id' => [(int)$msg]]);
			if(($msg = $msg['messages'] ?? null) === null || count($msg) < 1) {
				echo "Message not found";
				die;
			}
			$MP->channels->editBanned(['channel' => $id, 'participant' => $msg[0]['from_id'], 'banned_rights' => [
			'_' => 'chatBannedRights',
			'until_date' => 1,
			'view_messages' => true,
			'send_messages' => true
			]]);
			header('Location: chat.php?c='.$id);
			break;
		}
		die;
	} elseif(isset($_POST['sent'])) {
		if(isset($random)) {
			if(isset($_SESSION['random']) && $_SESSION['random'] == $random) {
				header('Location: chat.php?c='.$id);
				die;
			}
			$_SESSION['random'] = $random;
		}
		$text = '';
		if(isset($_POST['text'])) {
			$text = $_POST['text'];
		}
		$file = false;
		$filename = null;
		$type = null;
		$attr = false;
		$dur = 0;
		$waveform = false;
		if(($_FILES['file']['error'] ?? false) && $_FILES['file']['error'] != 4) {
			$reason = 'PHP Error: ' . $_FILES['file']['error'];
		}
		if(isset($_FILES['file']) && $_FILES['file']['size'] != 0) {
			if($_FILES['file']['size'] > 10 * 1024 * 1024) {
				$reason = 'File is too large!';
			} else {
				$file = $_FILES['file']['tmp_name'];
				$filename = $_FILES['file']['name'];
				$extidx = strrpos($filename, '.');
				if($extidx === false) {
					$reason = 'Invalid file';
				} else {
					$ext = strtolower(substr($filename, $extidx+1));
					if($uncompressed) {
						$type = 'inputMediaUploadedDocument';
						$attr = true;
					} elseif($voice) {
						switch($ext) {
							case 'amr':
							case 'mp3':
							case 'aac':
							case 'ogg':
							case 'm4a':
								$newfile = $file.'.ogg';
								$res = shell_exec(FFMPEG_DIR.'ffmpeg -i "'.$file.'" -ac 1 -y -map 0:a -map_metadata -1 "'.$newfile.'"'(WINDOWS?'':' 2>&1')) ?? '';
								unlink($file);
								if(strpos($res, 'failed') !== false) {
									$result = 'Conversion failed';
									break;
								}
								$i = strpos($res, 'Duration:');
								
								if($i !== false) {
									$i = strpos($res, ' ', $i);
									$s = substr($res, $i, strpos($res, '.', $i));
									$s = explode(':', $s);
									$dur = ((int)$s[2])+((int)$s[1])*60+((int)$s[0])*60*60;
									if($dur > 3600) {
										unlink($newfile);
										$reason = 'Duration is too long';
										break;
									}
								}
								try {
									$res = shell_exec(FFMPEG_DIR.'ffprobe -v error -f lavfi -i "amovie='.$newfile.',asetnsamples=44100,astats=metadata=1:reset=1" -show_entries frame_tags=lavfi.astats.Overall.Peak_level -of json'(WINDOWS?'':' 2>&1')) ?? false;
									
									if($res) {
										$j = json_decode($res);
										if($j) {
											$frames = $j->{'frames'};
											unset($j);
											$waveform = array(100);
											$sampleIndex = 0;
											$peakSample = 0;
											$index = 0;
											$sampleRate = max(1, (int) (count($frames) / 100));
											$s2 = 100 / count($frames);
											
											foreach($frames as $frame) {
												$sample = $frame->{'tags'}->{'lavfi.astats.Overall.Peak_level'};
												$sample = $sample == '-inf' ? -100.0 : floatval($sample);
												$sample = max(0, (int) (32768 * (10 ** ($sample / 20.0))));
												if($sample > $peakSample) {
													$peakSample = $sample;
												}
												if($sampleIndex++ % $sampleRate == 0) {
													if($index < 100) {
														if($sampleRate == 1) {
															$i = 0;
															while($i++ < $s2) $waveform[$index++] = $peakSample;
														} else {
															$waveform[$index++] = $peakSample;
														}
													}
													$peakSample = 0;
												}
											}
											unset($frames);
											
											if(count($waveform) > 100) {
												$waveform = array_slice($waveform, 0, 100);
											}
											
											$sumSamples = 0;
											foreach($waveform as $sample) {
												$sumSamples += $sample;
											}
											
											$peak = (int) ($sumSamples * 1.8 / 100);
											if($peak < 2500) $peak = 2500;
											
											for($i = 0; $i < 100; $i++) {
												$sample = $waveform[$i];
												if($sample > $peak) $sample = $peak;
												$waveform[$i] = max(0, min(31, (int) ($sample * 31 / $peak)));
											}
										}
									}
								} catch (Exception) {
								}
								
								$file = $newfile;
								$type = 'inputMediaUploadedDocument';
								break;
							default:
								$reason = 'Unsupported audio format';
								break;
						}
					} else {
						switch($ext) {
							case 'jpg':
							case 'jpeg':
							case 'png':
								$newfile = $file.'.'.$ext;
								if(!move_uploaded_file($file, $newfile)) {
									$reason = 'Failed to move file';
								} else {
									$type = 'inputMediaUploadedPhoto';
									$file = $newfile;
								}
								break;
							default:
								$type = 'inputMediaUploadedDocument';
								$attr = true;
								break;
						}
					}
				}
			}
		}
		if(!$reason) {
			try {
				if(!$file) {
					if(strlen($text) > 0) {
						$MP = MP::getMadelineAPI($user);
						$params = ['peer' => $id, 'message' => $text];
						if($msg) {
							$params['reply_to_msg_id'] = $msg;
						}
						if(isset($_GET["format"]) || isset($_POST["format"])) {
							$params['parse_mode'] = 'HTML';
						}
						if($edit) {
							$params['id'] = $msg;
							$MP->messages->editMessage($params);
						} else {
							$MP->messages->sendMessage($params);
						}
						header('Location: chat.php?c='.$id);
						die;
					}
				} else {
					$MP = MP::getMadelineAPI($user);
					$params = ['peer' => $id, 'message' => $text];
					if($msg) {
						$params['reply_to_msg_id'] = $msg;
					}
					$attributes = [];
					if($voice) {
						$att = ['_' => 'documentAttributeAudio', 'voice' => true, 'duration' => $dur];
						if($waveform !== false) {
							$att['waveform'] = $waveform;
						}
						array_push($attributes, $att);
					} elseif($attr) {
						array_push($attributes, ['_' => 'documentAttributeFilename', 'file_name' => $filename]);
					}
					$params['media'] = ['_' => $type, 'file' => $file, 'attributes' => $attributes, 'spoiler' => $spoiler];
					if(isset($_GET["format"]) || isset($_POST["format"])) {
						$params['parse_mode'] = 'HTML';
					}
					$MP->messages->sendMedia($params);
					try {
						unlink($file);
					} catch (Exception) {}
					header('Location: chat.php?c='.$id);
					die;
				}
			} catch (Exception $e) {
				echo $e;
				die;
			}
		}
	}
} catch (Exception $e) {
	echo $e;
	die;
}
$title = null;
try {
	$title = MP::x($msg ? $lng['reply_to'] : $lng['message_to']);
	$title .= ' '.MP::dehtml(MP::getNameFromId(MP::getMadelineAPI($user), $id));
} catch (Exception) {}
echo '<head><title>'.($title ? $title : MP::x($lng['send_message'])).'</title>';
echo Themes::head();
echo '</head>';
echo Themes::bodyStart();
echo '<div><a href="chat.php?c='.$id.'">'.MP::x($lng['back']).'</a></div>';
if($edit) {
	echo '<b>'.MP::x($lng['edit']).'</b>:';
} elseif($msg) {
	echo '<p>';
	echo '<b>'.MP::x($lng['actions']).'</b>:<br>';
	if($out || $candelete)
		echo '<a href="msg.php?c='.$id.'&m='.$msg.'&act=delete">'.MP::x($lng['delete']).'</a> ';
	if($out || $canedit)
		echo '<a href="msg.php?c='.$id.'&m='.$msg.'&edit">'.MP::x($lng['edit']).'</a> ';
	echo '<a href="chatselect.php?c='.$id.'&m='.$msg.'">'.MP::x($lng['forward']).'</a> ';
	if(!$ch) echo '<a href="msg.php?c='.$id.'&m='.$msg.'&act=fwdh">'.MP::x($lng['forward_here']).'</a> ';
	echo '<a href="msg.php?c='.$id.'&m='.$msg.'&act=save">'.MP::x($lng['forward_save']).'</a> ';
	if($canban)
		echo '<a href="msg.php?c='.$id.'&m='.$msg.'&act=ban">'.MP::x($lng['ban_msg']).'</a> ';
	echo '</p>';
	if(!$ch) echo '<b>'.MP::x($lng['reply']).'</b>:';
} elseif($title) {
	echo '<h3>'.$title.'</h3><br>';
}
if($reason) {
	echo '<b>'.$reason.'</b>';
}
if(!$ch) {
	echo '<form action="msg.php" method="post" enctype="multipart/form-data" style="display: inline;">';
	echo '<input type="hidden" name="c" value="'.$id.'">';
	echo '<input type="hidden" name="sent" value="1">';
	if($msg) {
		echo '<input type="hidden" name="m" value="'.$msg.'">';
	}
	echo '<textarea name="text" value="" style="width: 100%; height: 3em"></textarea><br>';
	echo '<input type="checkbox" id="format" name="format">';
	echo '<label for="format">'.MP::x($lng['html_formatting']).'</label>';
	if(!$edit) {
		echo '<br><input type="file" id="file" name="file"><br>';
		echo '<input type="checkbox" id="unc" name="unc">';
		echo '<label for="unc">'.MP::x($lng['send_uncompressed']).'</label>';
		if($voicesupport) {
			echo '<br><input type="checkbox" id="voice" name="voice">';
			echo '<label for="voice">'.MP::x($lng['send_voice']).'</label>';
		}
		echo '<br><input type="checkbox" id="sp" name="sp">';
		echo '<label for="sp">'.MP::x($lng['send_spoiler']).'</label>';
	}
	echo '<input type="hidden" name="r" value="'. \base64_encode(random_bytes(16)).'">';
	echo '<br><input type="submit" value="'.MP::x($lng['send']).'">';
	if($edit) {
		echo '<input type="hidden" name="edit" value="1">';
	}
	echo '</form>';
	if(!$edit) {
		echo '<form action="sendsticker.php" style="display: inline;">';
		echo '<input type="hidden" name="c" value="'.$id.'">';
		if($msg) {
			echo '<input type="hidden" name="reply_to" value="'.$msg.'">';
		}
		echo '<input type="submit" value="'.MP::x($lng['choose_sticker']).'">';
		echo '</form>';
	}
}
echo Themes::bodyEnd();
die;
