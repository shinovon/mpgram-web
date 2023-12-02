<?php
class MPLocale {
	public static $lng = array(
	"lang" => "",
	"refresh" => "",
	"back" => "",
	"logout" => "",
	"write_msg" => "",
	"media_att" => "",
	"fwd_from" => "",
	"history_down" => "",
	"history_up" => "",
	"phone_number" => "",
	"phone_code" => "",
	"error" => "",
	"reply_from" => "",
	"chats" => "",
	"login" => "",
	"action_pin" => "",
	"action_join" => "",
	"action_add" => "",
	"you" => "",
	"phone_code_invalid" => "",
	"phone_code_expired" => "",
	"settings" => "",
	"set_chat_autoupdate" => "",
	"set_chat_autoupdate_interval" => "",
	"set_language" => "",
	"about" => "",
	"set_theme" => "",
	"set_theme_dark" => "",
	"set_theme_light" => "",
	"set_chat" => "",
	"action_channelcreate" => "",
	"action_chateditphoto" => "",
	"action_chatedittitle" => "",
	"join" => "",
	"leave" => "",
	"send" => "",
	"sending_file" => "",
	"send_file" => "",
	"set_chats_count" => "",
	"size_too_large" => "",
	"send_message" => "",
	"or" => "",
	"choose_sticker" => "",
	"reply" => "",
	"set_chat_reverse_mode" => "",
	"set_chat_autoscroll" => "",
	"set_msgs_limit" => "",
	"archived_chats" => "",
	"reply_to" => "",
	"message_to" => "",
	"folders" => "",
	"all_chats" => "",
	"delete" => "",
	"forward" => "",
	"forward_here" => "",
	"actions" => "",
	"msg_options" => "",
	"wrong_captcha" => "",
	"no_pass_code" => "",
	"pass_code" => "",
	"need_signup" => "",
	"password_hash_invalid" => "",
	"wrong_number_format" => "",
	"html_formatting" => "",
	"action_leave" => "",
	"action_deleteuser" => "",
	"action_joinedbylink" => "",
	"action_joinedbyrequest" => "",
	"action_historyclear" => "",
	"action_chatcreate" => "",
	"compactchats" => ""
	);
	
	public static function init() {
		static::load();
	}
	
	public static function load($lang = 'en') {
		if(!$lang) {
			return false;
		}
		$x = './locale/' . $lang . '.json';
		if(!file_exists($x)) {
			return false;
		}
		$file = file_get_contents($x);
		if(!$file) {
			return false;
		}
		$json = json_decode($file, true);
		if(!$json) {
			return false;
		}
		foreach($json as $k => $v) {
			static::$lng[$k] = $v;
		}
		return true;
	}
	
	public static function number($key, $number) {
		$value = null;
		$arr = static::$lng[$key];
		foreach($arr as $a) {
			$tmp = $number;
			for($i = 0; $i < count($a); $i++) {
				$b = $a[$i];
				switch($b) {
				case "%":
					$tmp %= $a[$i += 1];
					break;
				case "=":
					$c = $a[$i += 1];
					$i += 1;
					if($tmp == $c) {
						$value = $a[$i];
						goto end;
					}
					break;
				case "<":
					$c = $a[$i += 1];
					$i += 1;
					if($tmp < $c) {
						$value = $a[$i];
						goto end;
					}
					break;
				case ">":
					$c = $a[$i += 1];
					$i += 1;
					if($tmp > $c) {
						$value = $a[$i];
						goto end;
					}
					break;
				default:
					$value = $a[$i];
					goto end;
				}
			}
		}
		end:
		return str_replace('$', strval($number), $value);
	}
}
