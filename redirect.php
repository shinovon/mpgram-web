<?php
/*
Copyright (c) 2022-2026 Arman Jussupgaliyev
*/
include 'config.php';
if (!isset($_SERVER['HTTPS']) && (FORCE_HTTPS || (CHROME_HTTPS && isset($_SERVER['HTTP_USER_AGENT']) && str_contains($_SERVER['HTTP_USER_AGENT'], 'Chrome')))) {
    $s = 'https://' . (defined("SERVER_NAME") ? SERVER_NAME : $_SERVER["SERVER_NAME"]);
    $s .= $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: ' . $s);
    die;
}