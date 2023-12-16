<?php

include 'redirect.php';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';

$timeoff = MP::getSettingInt('timeoff');
$theme = MP::getSettingInt('theme');
$lng = MP::initLocale();

$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die;
}

$count = 300;
if(isset($_GET['count'])) {
	$count = (int) $_GET['count'];
}

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');


header('Content-Type: text/html; charset='.MP::$enc);
header('Cache-Control: private, no-cache, no-store, must-revalidate');

include 'themes.php';
Themes::setTheme($theme);

$avas = false;
try {
	$MP = MP::getMadelineAPI($user);
	echo '<head><title>'.MP::x($lng['contacts']).'</title>';
	echo Themes::head();
	echo '</head>';
	echo Themes::bodyStart();
	echo '<div>';
	$backurl = 'chats.php';
	echo '<a href="'.$backurl.'">'.MP::x($lng['back']).'</a><br>';
	echo '<br></div>';
	try {
		$r = $MP->contacts->getContacts();
		$c = 0;
		foreach($r['contacts'] as $contact){
			$id = $contact['user_id'];
			$name = null;
			foreach($r['users'] as $user) {
				if($user['id'] == $id) {
					if(isset($user['first_name'])) {
						$name = trim($user['first_name']).(isset($user['last_name']) ? ' '.trim($user['last_name']) : '');
					} elseif(isset($user['last_name'])) {
						$name = trim($user['last_name']);
					} else {
						$name = 'Deleted Account';
					}
					if(isset($user['username'])) {
						$name .= ' ('.$user['username'].')';
					}
					break;
				}
			}
			try {
				$n = ($c %2 ==0 ? '1': '0');
				echo '<div class="c'.$n.'">';
				echo '<a href="chat.php?c='.$id.'">'.MP::dehtml($name).'</a>';
				echo '</div>';
			} catch (Exception $e) {
				echo "<xmp>$e</xmp>";
			}
			$c += 1;
		}
		unset($r);
	} catch (Exception $e) {
		echo '<b>'.MP::x($lng['error']).'!</b><br>';
		echo "<xmp>$e</xmp>";
	}
	echo Themes::bodyEnd();
	unset($MP);
} catch (Exception $e) {
	echo '<b>'.$lng['error'].'!</b><br>';
	echo "<xmp>$e</xmp><br>";
}
