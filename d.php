<?php
if(!isset($_GET['n'])) {
	die();
}
$u = $_GET['n'];
if(strpos($u, ':') !== false) {
	die();
}
if(strpos($u, '/') !== false) {
	die();
}
if(strpos($u, '.') !== false) {
	die();
}
$u = 'doc/'.$u;
if(!file_exists($u)) {
	http_response_code(404);
	die();
}
if(isset($_GET['f'])) {
	header('Content-Disposition: attachment; filename="'.$_GET['f'].'"');
}
header('Content-Length: '.filesize($u));
readfile($u);