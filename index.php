<?php
/*
Copyright (c) 2022-2025 Arman Jussupgaliyev
*/
include 'config.php';
// HTTPS redirecting
if (FORCE_HTTPS || (CHROME_HTTPS && isset($_SERVER['HTTP_USER_AGENT']) && str_contains($_SERVER['HTTP_USER_AGENT'], 'Chrome'))) {
    $s = 'https://' . $_SERVER['SERVER_NAME'];
    if (isset($_SERVER['PHP_SELF'])) {
        $ss = $_SERVER['PHP_SELF'];
        $ss = substr($ss, 0, strrpos($ss, '/')+1);
        $s .= $ss;
    } else {
        $s .= '/';
    }
    header('Location: ' . $s . 'login.php');
    die;
}
header('Location: login.php');