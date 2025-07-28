<?php
if(!isset($_COOKIE['user'])) {
	http_response_code(401);
	die();
}
echo $_COOKIE['user'];