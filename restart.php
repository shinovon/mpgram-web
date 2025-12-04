<?php
/*
Copyright (c) 2022-2025 Arman Jussupgaliyev
*/
include 'mp.php';
$user = MP::getUser();
if (!$user) die;

try {
	$MP = MP::getMadelineAPI($user);
	$MP->restart();
} catch (Exception $e) {
	echo $e;
}
echo "1";
