<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';
$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die();
}

$lang = MP::getSetting('lang', 'ru');
$theme = MP::getSettingInt('theme');

try {
	include 'locale_'.$lang.'.php';
} catch (Exception $e) {
	$lang = 'ru';
	include 'locale_'.$lang.'.php';
}

$id = null;
if(isset($_POST['c'])) {
	$id = $_POST['c'];
} else if(isset($_GET['c'])) {
	$id = $_GET['c'];
} else {
	die();
}
$reply_to = null;
if(isset($_POST['reply_to'])) {
	$reply_to = $_POST['reply_to'];
} else if(isset($_GET['reply_to'])) {
	$reply_to = $_GET['reply_to'];
}

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
	if(isset($_POST['sent'])) {
		$msg = '';
		if(isset($_POST['msg'])) {
			$msg = $_POST['msg'];
		}
		$file = false;
		$filename = null;
		$type = null;
		$attr = false;
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
						case 'mp3':
						case 'amr':
						case '3gp':
						case 'mp4':
						case 'gif':
						case 'zip':
						case 'jar':
						case 'jad':
						case 'sis':
						case 'sisx':
						case 'apk':
						case 'deb':
						case 'htm':
							$type = 'inputMediaUploadedDocument';
							$attr = true;
							break;
						default:
							$reason = 'This type of file ('.$ext.') is not supported!';
							break;
					}
				}
			}
		}
		if(!$reason) {
			try {
				if(!$file) {
					if(strlen($msg) > 0) {
						$MP = MP::getMadelineAPI($user);
						$params = ['peer' => $id, 'message' => $msg];
						if($reply_to) {
							$params['reply_to_msg_id'] = $reply_to;
						}
						$MP->messages->sendMessage($params);
						header('Location: chat.php?c='.$id);
						die();
					}
				} else {
					$MP = MP::getMadelineAPI($user);
					$params = ['peer' => $id, 'message' => $msg];
					if($reply_to) {
						$params['reply_to_msg_id'] = $reply_to;
					}
					$attributes = [];
					if($attr) {
						array_push($attributes, ['_' => 'documentAttributeFilename', 'file_name' => $filename]);
					}
					$params['media'] = ['_' => $type, 'file' => $file, 'attributes' => $attributes];
					$MP->messages->sendMedia($params);
					header('Location: chat.php?c='.$id);
					die();
				}
			} catch (Exception $e) {
				echo $e;
				die();
			}
		}
	}
} catch (Exception $e) {
	echo $e;
	die();
}
$title = null;
try {
	$title = MP::x($reply_to ? $lng['reply_to'] : $lng['message_to']);
	$title .= ' '.MP::dehtml(MP::getNameFromId(MP::getMadelineAPI($user), $id));
} catch(Exception $e) {
}
echo '<head><title>'.($title ? $title : MP::x($lng['send_message'])).'</title>';
echo Themes::head();
echo '</head>';
echo Themes::bodyStart();
echo '<div><a href="chat.php?c='.$id.'">'.MP::x($lng['back']).'</a></div>';
if($title) {
	echo '<h3>'.$title.'</h3><br>';
} else {
	echo '<br>';
}
if($reason) {
	echo '<b>'.$reason.'</b>';
}
echo '<form action="sendfile.php" method="post" enctype="multipart/form-data" style="display: inline;">';
echo '<input type="hidden" name="c" value="'.$id.'">';
echo '<input type="hidden" name="sent" value="1">';
if($reply_to) {
	echo '<input type="hidden" name="reply_to" value="'.$reply_to.'">';
}
echo '<textarea name="msg" value="" style="width: 100%; height: 3em"></textarea><br>';
echo '<br><input type="file" id="file" name="file"><br>';
echo '<input type="submit" value="'.MP::x($lng['send']).'">';
echo '</form>';
echo '<form action="sendsticker.php" style="display: inline;">';
echo '<input type="hidden" name="c" value="'.$id.'">';
if($reply_to) {
	echo '<input type="hidden" name="reply_to" value="'.$reply_to.'">';
}
echo '<input type="submit" value="'.MP::x($lng['choose_sticker']).'">';
echo '</form>';
echo Themes::bodyEnd();