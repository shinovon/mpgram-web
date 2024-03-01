<?php
$user = $_GET['user'] ?? die;
function cookie($n, $v, $e=null) {
	if($e === null) $e = time() + (86400 * 365);
	$e = date('D, d M Y H:i:s \G\M\T', $e);
	header('Set-Cookie: '.$n.'='.$v.'; expires='.$e.'; path=/', false);
}
cookie('user', $user);
cookie('code', '1');
header('Location: chats.php');
