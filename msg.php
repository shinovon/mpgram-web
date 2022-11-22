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

$theme = MP::getSettingInt('theme');
$lng = MP::initLocale();

$id = null;
if(isset($_POST['c'])) {
	$id = $_POST['c'];
} else if(isset($_GET['c'])) {
	$id = $_GET['c'];
} else {
	die();
}
$msg = null;
if(isset($_POST['m'])) {
	$msg = $_POST['m'];
} else if(isset($_GET['m'])) {
	$msg = $_GET['m'];
}
$out = isset($_POST['o']) || isset($_GET['o']);

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
		}
		die();
	} else if(isset($_POST['sent'])) {
		$text = '';
		if(isset($_POST['text'])) {
			$text = $_POST['text'];
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
						default:
							$type = 'inputMediaUploadedDocument';
							$attr = true;
							break;
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
						$MP->messages->sendMessage($params);
						header('Location: chat.php?c='.$id);
						die();
					}
				} else {
					$MP = MP::getMadelineAPI($user);
					$params = ['peer' => $id, 'message' => $text];
					if($msg) {
						$params['reply_to_msg_id'] = $msg;
					}
					$attributes = [];
					if($attr) {
						array_push($attributes, ['_' => 'documentAttributeFilename', 'file_name' => $filename]);
					}
					$params['media'] = ['_' => $type, 'file' => $file, 'attributes' => $attributes];
					if(isset($_GET["format"]) || isset($_POST["format"])) {
						$params['parse_mode'] = 'HTML';
					}
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
	$title = MP::x($msg ? $lng['reply_to'] : $lng['message_to']);
	$title .= ' '.MP::dehtml(MP::getNameFromId(MP::getMadelineAPI($user), $id));
} catch(Exception $e) {
}
echo '<head><title>'.($title ? $title : MP::x($lng['send_message'])).'</title>';
echo Themes::head();
echo '</head>';
echo Themes::bodyStart();
echo '<div><a href="chat.php?c='.$id.'">'.MP::x($lng['back']).'</a></div>';
if($msg) {
	echo '<p>';
	echo '<b>'.MP::x($lng['actions']).'</b>:<br>';
	if($out) echo '<a href="msg.php?c='.$id.'&m='.$msg.'&act=delete">'.MP::x($lng['delete']).'</a> ';
	echo '<a href="chatselect.php?c='.$id.'&m='.$msg.'">'.MP::x($lng['forward']).'</a> ';
	echo '<a href="msg.php?c='.$id.'&m='.$msg.'&act=fwdh">'.MP::x($lng['forward_here']).'</a>';
	echo '</p>';
	echo '<b>'.MP::x($lng['reply']).'</b>:';
} else if($title) {
	echo '<h3>'.$title.'</h3><br>';
}
if($reason) {
	echo '<b>'.$reason.'</b>';
}
echo '<form action="msg.php" method="post" enctype="multipart/form-data" style="display: inline;">';
echo '<input type="hidden" name="c" value="'.$id.'">';
echo '<input type="hidden" name="sent" value="1">';
if($msg) {
	echo '<input type="hidden" name="m" value="'.$msg.'">';
}
echo '<textarea name="text" value="" style="width: 100%; height: 3em"></textarea><br>';
echo '<input type="checkbox" id="format" name="format">';
echo '<label for="format">'.MP::x($lng['html_formatting']).'</label>';
echo '<br><input type="file" id="file" name="file"><br>';
echo '<input type="submit" value="'.MP::x($lng['send']).'">';
echo '</form>';
echo '<form action="sendsticker.php" style="display: inline;">';
echo '<input type="hidden" name="c" value="'.$id.'">';
if($msg) {
	echo '<input type="hidden" name="reply_to" value="'.$msg.'">';
}
echo '<input type="submit" value="'.MP::x($lng['choose_sticker']).'">';
echo '</form>';
echo Themes::bodyEnd();
