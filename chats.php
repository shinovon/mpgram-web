<?php
header("Content-Type: text/html; charset=utf-8");
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

if (!file_exists('madeline.php')) {
    copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}
include 'madeline.php';
$temp = tmpfile();
$c = base64_decode(file_get_contents($_GET["user"].'.madeline'));
$ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
$iv = substr($c, 0, $ivlen);
$hmac = substr($c, $ivlen, $sha2len=32);
$ciphertext_raw = substr($c, $ivlen+$sha2len);
$plaintext = openssl_decrypt($ciphertext_raw, $cipher, $_GET["code"], $options=OPENSSL_RAW_DATA, $iv);
$calcmac = hash_hmac('sha256', $ciphertext_raw, $_GET["code"], $as_binary=true);
fwrite($temp, $plaintext);
fseek($temp, 0);
$tmpfile_path = stream_get_meta_data($temp)['uri'];
$MP = new \danog\MadelineProto\API($tmpfile_path);
$MP->start();

$dialogs = $MP->getFullDialogs();
$c = 0;
foreach(array_reverse($dialogs) as $dialogo){
if($c==10){break;}else{$c=$c+1;}
if(isset($dialogo["peer"]["user_id"])){
    $chat = $MP->getInfo($dialogo["peer"]["user_id"]);
    if(isset($chat["User"]["username"])){
        preg_match('/<meta property="og:image" content="([\s\S]+?)">/', file_get_contents('https://t.me/'.$chat["User"]["username"]), $ava);
        echo '<div>'.$chat["User"]["username"].'</div><div>http://nfwwcz3fom.o5sxgzlsoyxg43a.cmle.ru/?url='.$ava[1].'&h=30&mask=circle&output=png</div><div>'.$chat["User"]["first_name"].'</div><div>'.$dialogo["unread_count"].'</div>';
    }else{
        echo '<div>'.$dialogo["peer"]["user_id"].'</div><div>none</div><div>'.$chat["User"]["first_name"].'</div><div>'.$dialogo["unread_count"].'</div>';
    }
}elseif(isset($dialogo["peer"]["chat_id"])){
    $chat = $MP->getInfo('-'.$dialogo["peer"]["chat_id"]);
    echo '<div>-'.$dialogo["peer"]["chat_id"].'</div><div>none</div><div>'.$chat["Chat"]["title"].'</div><div>'.$dialogo["unread_count"].'</div>';
}else{
    $chat = $MP->getInfo('-100'.$dialogo["peer"]["channel_id"]);
    if(isset($chat["Chat"]["username"])){
        preg_match('/<meta property="og:image" content="([\s\S]+?)">/', file_get_contents('https://t.me/'.$chat["Chat"]["username"]), $ava);
        echo '<div>'.$chat["Chat"]["username"].'</div><div>http://nfwwcz3fom.o5sxgzlsoyxg43a.cmle.ru/?url='.$ava[1].'&h=30&mask=circle&output=png</div><div>'.$chat["Chat"]["title"].'</div><div>'.$dialogo["unread_count"].'</div>';
    }else{
        echo '<div>-100'.$dialogo["peer"]["channel_id"].'</div><div>none</div><div>'.$chat["Chat"]["title"].'</div><div>'.$dialogo["unread_count"].'</div>';
    }
}
}
fclose($temp);
