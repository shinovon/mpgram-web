<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
header("Content-Type: text/html; charset=utf-8");

if (!file_exists('madeline.php')) {
    copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}
include 'madeline.php';


$settings['app_info']['api_id']=1488323;
$settings['app_info']['api_hash'] = '2005074e61d3fd226313e87c667453ef';
$MadelineProto = new \danog\MadelineProto\API($_GET["user"].'.madeline', $settings);
$MadelineProto->complete_phone_login((int)$_GET["code"]);
$me = $MadelineProto->getSelf();
$MadelineProto->logger($me);
echo($me);
