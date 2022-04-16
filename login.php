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
if(!isset($_GET["user"])){
$user = md5($_GET["phone"].rand(0,1000));
$MadelineProto = new \danog\MadelineProto\API($user.'.madeline', $settings);
}
if(!isset($_GET["code"])){
    $MadelineProto->phone_login($_GET["phone"]);
    echo $user;
}else{
echo file_get_contents('<сервер>/login1.php?user='.$_GET["user"].'&code='.$_GET["code"]);
$plaintext = file_get_contents($_GET["user"].'.madeline');
$ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
$iv = openssl_random_pseudo_bytes($ivlen);
$ciphertext_raw = openssl_encrypt($plaintext, $cipher, $_GET["code"], $options=OPENSSL_RAW_DATA, $iv);
$hmac = hash_hmac('sha256', $ciphertext_raw, $_GET["code"], $as_binary=true);
$ciphertext = base64_encode( $iv.$hmac.$ciphertext_raw );
file_put_contents($_GET["user"].'.madeline', $ciphertext);
}
