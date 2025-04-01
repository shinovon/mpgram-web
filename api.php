<?php
ini_set('error_reporting', E_ERROR);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

require_once("api_values.php");
require_once("config.php");

define("def", 1);
define("api_version", 5);
define("api_version_min", 2);

use danog\MadelineProto\Magic;

// Setup error handler
function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');


function json($json) {
	if(defined("json")) return;
	global $PARAMS;
	// Add content-type if needed
	if(isset($PARAMS['pretty']) || isset($PARAMS['json'])) {
		header("Content-Type: application/json");
	}
	$c = JSON_UNESCAPED_SLASHES | (isset($_SERVER['HTTP_X_MPGRAM_UNICODE']) || isset($PARAMS['utf']) ? JSON_UNESCAPED_UNICODE : 0);
	if(isset($PARAMS['pretty'])) {
		$c |= JSON_PRETTY_PRINT;
	}
	$time = time();
	$sv = api_version;
	header("X-Server-Time: {$time}");
	header("X-Server-Api-Version: {$sv}");
	header("Content-Type: application/json");
	echo json_encode($json, $c);
	define("json", 1);
}

function error($error) {
	$obj['error'] = $error;
	json($obj);
	die();
}

function checkField($field, $def = def) {
	global $PARAMS;
	if(!isset($PARAMS['fields'])) {
		return $def;
	}
	return in_array($field, explode(',',$PARAMS['fields']));
}

function checkCount($count, $def=100) {
	global $PARAMS;
	if(!isset($PARAMS['count']) || empty($PARAMS['count'])) {
		return $count < $def;
	}
	return $count < (int) $PARAMS['count'];
}

function isParamEmpty($param) {
	global $PARAMS;
	return !isset($PARAMS[$param]) || empty($PARAMS[$param]);
}

function checkParamEmpty($param) {
	if(isParamEmpty($param)) {
		error(['message'=>"Required parameter '$param' is not set"]);
	}
}

function getParam($param, $def=false) {
	global $PARAMS;
	if (!isset($PARAMS[$param])) {
		if ($def === false) {
			error(['message'=>"Required parameter '$param' is not set"]);
		}
		return $def;
	}
	
	return $PARAMS[$param];
}

function addParamToArray(&$array, $param, $type = null) {
	global $PARAMS;
	if(!isset($PARAMS[$param])) {
		return;
	}
	$value = $PARAMS[$param];
	if($type) {
		switch($type) {
		case 'int':
			if(!is_numeric($value)) {
				error(['message'=>"Given parameter '$param' is not integer"]);
			}
			$value = intval($value);
			break;
		case 'boolean':
			$value = strtolower($value);
			if($value == 'true' || $value == '1') {
				$value = true;
			} elseif($value == 'false' || $value == '0') {
				$value = false;
			} else {
				error(['message'=>"Given parameter '$param' is not boolean"]);
			}
			break;
		}
	}
	$array[$param] = $value;
}

function checkAuth() {
	if(!defined('user_checked')) {
		define('user_checked', 1);
	} else return;
	global $PARAMS;
	$user = $_SERVER['HTTP_X_MPGRAM_USER'] ?? $PARAMS['user'] ?? null;
	
	if($user == null || empty($user)
		|| strlen($user) < 32 || strlen($user) > 200
		|| strpos($user, '\\') !== false
		|| strpos($user, '/') !== false
		|| strpos($user, '.') !== false
		|| strpos($user, ';') !== false
		|| strpos($user, ':') !== false
		|| strpos($user, ' ') !== false
		|| !file_exists(sessionspath.$user.'.madeline')) {
		http_response_code(401);
		error(['message'=>'Invalid authorization']);
	}
}

function setupMadelineProto($user=null) {
	global $MP;
	global $PARAMS;
	if($MP != null) {
		return;
	}
	if(!$user) {
		checkAuth();
		$user = $_SERVER['HTTP_X_MPGRAM_USER'] ?? $PARAMS['user'];
	}
	require_once 'vendor/autoload.php';
	$sets = new \danog\MadelineProto\Settings;
	$app = new \danog\MadelineProto\Settings\AppInfo;
	$app->setApiId(api_id);
	$app->setApiHash(api_hash);
	$app->setShowPrompt(false);
	
	$app->setAppVersion($_SERVER['HTTP_X_MPGRAM_APP_VERSION'] ?? 'api');
	if (isset($_SERVER['HTTP_X_MPGRAM_DEVICE'])) {
		$app->setDeviceModel($_SERVER['HTTP_X_MPGRAM_DEVICE']);
	}
	if (isset($_SERVER['HTTP_X_MPGRAM_SYSTEM'])) {
		$app->setSystemVersion($_SERVER['HTTP_X_MPGRAM_SYSTEM']);
	}
	$sets->setAppInfo($app);
	try {
		$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline', $sets);
	} catch (Exception $e) {
		http_response_code(401);
		error(['message' => "Failed to load session", 'stack_trace' => strval($e)]);
	}
}

function getId($a) {
	if(is_int($a)) return $a;
	return $a['user_id'] ?? (isset($a['chat_id']) ? $a['chat_id'] : (isset($a['channel_id']) ? ($a['channel_id']) : null));
}

function getAllDialogs($limit = 0, $folder_id = -1) {
	global $MP;
	$p = ['limit' => 100, 'offset_date' => 0, 'offset_id' => 0, 'offset_peer' => ['_' => 'inputPeerEmpty'], 'count' => 0, 'hash' => 0];
	if($folder_id != -1) {
		$p['folder_id'] = $folder_id;
	}
	$t = ['dialogs' => [0], 'count' => 1];
	$r = ['dialogs' => [], 'messages' => [], 'users' => [], 'chats' => []];
	$d = [];
	while($p['count'] < $t['count']) {
		$t = $MP->messages->getDialogs($p);
		$r['users'] = array_merge($r['users'], $t['users']);
		$r['chats'] = array_merge($r['chats'], $t['chats']);
		$r['messages'] = array_merge($r['messages'], $t['messages']);
		$last_peer = 0;
		$last_date = 0;
		$last_id = 0;
		$t['messages'] = array_reverse($t['messages'] ?? []);
		foreach(array_reverse($t['dialogs'] ?? []) as $dialog) {
			$id = $dialog['peer'];
			if(!isset($d[$id])) {
				$d[$id] = $dialog;
				array_push($r['dialogs'], $dialog);
			}
			if(!$last_date) {
				if(!$last_peer) {
					$last_peer = $id;
				}
				if(!$last_id) {
					$last_id = $dialog['top_message'];
				}
				foreach($t['messages'] as $message) {
					if($message['_'] !== 'messageEmpty' && $message['peer_id'] === $last_peer && $last_id === $message['id']) {
						$last_date = $message['date'];
						break;
					}
				}
			}
		}
		if($last_date) {
			$p['offset_date'] = $last_date;
			$p['offset_peer'] = $last_peer;
			$p['offset_id'] = $last_id;
			$p['count'] = count($d);
		} else {
			break;
		}
		if(!isset($t['count'])) {
			break;
		}
	}
	return $r;
}

function utflen($str) {
	return mb_strlen($str, 'utf-8');
}

function utfsubstr($s, $offset, $length = null) {
	$s = mb_convert_encoding($s, 'UTF-16');
	return mb_convert_encoding(substr($s, $offset << 1, $length === null ? null : ($length << 1)), 'UTF-8', 'UTF-16');
}

function findPeer($id, $r) {
	if ((int) $id < 0) {
		if (!isset($r['chats'])) return null;
		foreach($r['chats'] as $u) {
			if($u['id'] != $id) continue;
			return $u;
		}
	} else if (isset($r['users'])) {
		foreach($r['users'] as $u) {
			if($u['id'] != $id) continue;
			return $u;
		}
	}
	return null;
}

function parsePeer($peer) {
	return getId($peer);
}

function parseDialog($rawDialog) {
	$dialog = array();
	$dialog['id'] = strval(getId($rawDialog['peer']));
	if ($rawDialog['unread_count'] ?? 0 > 0) $dialog['unread'] = $rawDialog['unread_count'];
	if ($rawDialog['pinned'] ?? false) $dialog['pin'] = true;
	return $dialog;
}

function parseUser($rawUser) {
	global $v;
	if (!$rawUser) {
		return false;
	}
	$user = array();
	$user['id'] = strval($rawUser['id']);
	$user[$v < 5 ? 'first_name' : 'fn'] = $rawUser['first_name'] ?? null;
	$user[$v < 5 ? 'last_name' : 'ln'] = $rawUser['last_name'] ?? null;
	if ((isset($rawUser['username']) && $rawUser['username'] !== null) || $v < 5) $user[$v < 5 ? 'username' : 'name'] = $rawUser['username'] ?? null;
	if ($v >= 5) {
		if (isset($rawUser['photo'])) $user['p'] = true;
		if ($rawUser['contact'] ?? false) $user['k'] = true;
		if ($rawUser['bot'] ?? false) $user['b'] = true;
	}
	return $user;
}

function parseChat($rawChat) {
	global $v;
	$chat = array();
	$chat['type'] = $rawChat['_'];
	$chat['id'] = strval($rawChat['id']);
	$chat[$v < 5 ? 'title' : 't'] = $rawChat['title'] ?? null;
	if ((isset($rawUser['username']) && $rawUser['username'] !== null) || $v < 5) $chat[$v < 5 ? 'username' : 'name'] = $rawChat['username'] ?? null;
	if ($v >= 5) {
		if (isset($rawChat['photo'])) $chat['p'] = true;
		if ($rawChat['broadcast'] ?? false) $chat['c'] = true;
		if ($rawChat['forum'] ?? false) $chat['f'] = true;
		if ($rawChat['left'] ?? false) $chat['l'] = true;
	}
	return $chat;
}

function getCaptchaText($length) {
	$c = '0123456789abcdefghjkmnopqrstuvwxyz.#*';
	$l = strlen($c);
	$s = '';
	for ($i = 0; $i < $length; $i++) {
		$s .= $c[rand(0, $l - 1)];
	}
	return $s;
}

function parseMessage($rawMessage, $media=false, $short=false) {
	global $v;
	$message = array();
	//$message['raw'] = $rawMessage;
	$message['id'] = $rawMessage['id'] ?? null;
	$message['date'] = $rawMessage['date'] ?? null;
	if (isset($rawMessage['message'])) {
		$t = $rawMessage['message'];
		if ($short) {
			if (utflen($t) > 150) {
				$t = trim(utfsubstr($t, 0, 150)).'..';
			}
		} else if ($v >= 5 && isset($rawMessage['entities']) && count($rawMessage['entities']) != 0) {
			$message['entities'] = $rawMessage['entities'];
		}
		$message['text'] = $t;
		
	}
	if (isset($rawMessage['out'])) $message['out'] = $rawMessage['out'];
	if (isset($rawMessage['peer_id'])) {
		$message['peer_id'] = parsePeer($rawMessage['peer_id']);
	}
	if (isset($rawMessage['from_id'])) {
		$message['from_id'] = parsePeer($rawMessage['from_id']);
	}
	if (isset($rawMessage['fwd_from'])) {
		$rawFwd = $rawMessage['fwd_from'];
		$fwd = array();
		if (isset($rawFwd['from_id'])) {
			$fwd['from_id'] = parsePeer($rawFwd['from_id']);
		}
		if (isset($rawFwd['date'])) {
			$fwd['date'] = $rawFwd['date'];
		}
		if (isset($rawFwd['saved_from_msg_id']) || isset($rawFwd['channel_post'])) {
			$fwd['msg'] = $rawFwd['saved_from_msg_id'] ?? $rawFwd['channel_post'];
		}
		if (isset($rawFwd['saved_from_peer'])) {
			$fwd['peer'] = $rawFwd['saved_from_peer'];
		}
		if (isset($rawFwd['from_name'])) {
			$fwd['from_name'] = $rawFwd['from_name'];
		}
		if (isset($rawFwd['saved_out'])) {
			$fwd['s'] = true;
		}
		$message['fwd'] = $fwd;
	}
	if (isset($rawMessage['media'])) {
		if (!$media) {
			// media is disabled
			$message['media'] = $v < 5 ? ['_' => 0] : null;
		} else {
			$rawMedia = $rawMessage['media'];
			$media = [];
			if(isset($rawMedia['photo'])) {
				$media['type'] = 'photo';
				$media['id'] = strval($rawMedia['photo']['id']);
				$media['date'] = $rawMedia['photo']['date'] ?? null;
			} elseif(isset($rawMedia['document'])) {
				$media['type'] = 'document';
				$media['id'] = strval($rawMedia['document']['id']);
				if (isset($rawMedia['document']['date'])) $media['date'] = $rawMedia['document']['date'];
				$media['size'] = $rawMedia['document']['size'] ?? null;
				$media['mime'] = $rawMedia['document']['mime_type'] ?? null;
				$media['thumb'] = isset($rawMedia['document']['thumbs']);
				if(isset($rawMedia['document']['attributes'])) {
				foreach($rawMedia['document']['attributes'] as $attr) {
					if($attr['_'] == 'documentAttributeFilename') {
						$media['name'] = $attr['file_name'];
					}
					if($attr['_'] == 'documentAttributeAudio') {
						$audio = [];
						$audio['voice'] = $attr['voice'] ?? false;
						$audio['time'] = $attr['duration'] ?? false;
						if(isset($attr['title'])) {
							$audio['title'] = $attr['title'];
							if(isset($attr['performer'])) {
								$audio['artist'] = $attr['performer'];
							}
						}
						$media['audio'] = $audio;
					}
				}
			}
			} elseif(isset($rawMedia['webpage'])) {
				$media['type'] = 'webpage';
				$media['name'] = $rawMedia['webpage']['site_name'] ?? null;
				$media['url'] = $rawMedia['webpage']['url'] ?? null;
				$media['title'] = $rawMedia['webpage']['title'] ?? null;
			} elseif(isset($rawMedia['geo'])) {
				$media['type'] = 'geo';
				$media['lat'] = str_replace(',', '.', strval($rawMedia['geo']['lat'])) ?? null;
				$media['long'] = str_replace(',', '.', strval($rawMedia['geo']['long'])) ?? null;
			} elseif(isset($rawMedia['poll'])) {
				$media['type'] = 'poll';
				$media['voted'] = $media['results']['total_voters'] ?? 0;
			} else {
				// TODO
				$media['type'] = $rawMedia['undefined'];
				$media['_'] = $rawMedia['_'];
			}
			$message['media'] = $media;
		}
	}
	if (isset($rawMessage['action'])) {
		$rawAction = $rawMessage['action'];
		$action = ['_' => $rawAction['_']];
		if (isset($rawAction['user_id']) || isset($rawAction['users'])) {
			$action['user'] = $rawAction['user_id'] ?? $a['users'][0] ?? null;
		}
		$message['act'] = $action;
	}
	if (isset($rawMessage['reply_to'])) {
		$rawReply = $rawMessage['reply_to'];
		$reply = [];
		$reply[$v < 5 ? 'msg' : 'id'] = $rawReply['reply_to_msg_id'] ?? null;
		if (isset($rawReply['reply_to_peer_id'])) $reply['peer'] = $rawReply['reply_to_peer_id'];
		if (isset($rawReply['quote_text'])) $reply['quote'] = $rawReply['quote_text'];
		if ($v >= 5 && !$short && isset($reply['id'])) {
			$rawReplyMsg = null;
			try {
				global $MP;
				$peer = $message['peer_id'];
				if ((int) $peer < 0) {
					$rawReplyMsg = $MP->channels->getMessages(['channel' => $peer, 'id' => [$reply['id']]]);
				} else {
					$rawReplyMsg = $MP->messages->getMessages(['peer' => $peer, 'id' => [$reply['id']]]);
				}
				if($rawReplyMsg && isset($rawReplyMsg['messages']) && isset($rawReplyMsg['messages'][0])) {
					$reply['msg'] = parseMessage($rawReplyMsg['messages'][0], false, true);
				}
			} catch (Exception) {}
		}
		$message['reply'] = $reply;
	}
	if ($v >= 5) {
		if (isset($rawMessage['grouped_id'])) $message['group'] = $rawMessage['grouped_id'];
	}
	//$message['raw'] = $rawMessage;
	return $message;
}

try {
	if (!defined('ENABLE_API') || !ENABLE_API) {
		error(['message' => "API is disabled"]);
	}
	if(defined('INSTANCE_PASSWORD') && INSTANCE_PASSWORD !== null) {
		$ipass = $_SERVER['HTTP_X_MPGRAM_INSTANCE_PASSWORD'] ?? null;
		if ($ipass != null) {
			if ($ipass != INSTANCE_PASSWORD) {
				http_response_code(403);
				error(['message' => "Wrong password"]);
			}
		} else {
			http_response_code(403);
			error(['message' => "Password is required"]);
		}
	}
	$MP = null;
	// Parameters
	$PARAMS = array();
	if(count($_GET) > 0) {
		$PARAMS = array_merge($PARAMS, $_GET);
	}
	if(count($_POST) > 0) {
		$PARAMS = array_merge($PARAMS, $_POST);
	}
	if(count($PARAMS) == 0) {
		error(['message' => "No parameters set"]);
	}
	if(isset($_COOKIE['user'])) {
		$PARAMS['user'] = $_COOKIE['user'];
	}
	if(!isset($PARAMS['method'])) {
		error(['message' => "No method set"]);
	}
	checkParamEmpty('v');
	$v = (int) $PARAMS['v'];
	if($v < api_version_min || $v > api_version) {
		error(['message' => "Unsupported API version"]);
	}
	$METHOD = $PARAMS['method'];
	switch($METHOD) {
	case 'getCaptchaImg':
		if (!defined('ENABLE_LOGIN_API') || !ENABLE_LOGIN_API) {
			http_response_code(403);
			error(['message' => "Login API is disabled"]);
		}
		checkParamEmpty('captcha_id');
		session_id('API'.$PARAMS['captcha_id']);
		session_start(['use_cookies' => '0']);
		if(empty($_SESSION['captcha_key'])) {
			error('Captcha id expired');
		}
		$c = getCaptchaText(rand(6, 10));
		$_SESSION['captcha_key'] = $c;
		session_write_close();
		$img = imagecreatetruecolor(150, 50);
		imagefill($img, 0, 0, -1);
		imagestring($img, rand(4, 10), rand(0, 50), rand(0, 25), $c, 0x000000);
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header('Content-type: image/png');
		imagepng($img);
		imagedestroy($img);
		break;
	case 'phoneLogin':
		if ($PARAMS['user'] != null) {
			error(['message' => 'Authorized']);
		}
	case 'initLogin':
		if ($v != api_version) {
			http_response_code(403);
			error(['message' => "Unsupported API version"]);
		}
		if (!defined('ENABLE_LOGIN_API') || !ENABLE_LOGIN_API) {
			http_response_code(403);
			error(['message' => "Login API is disabled"]);
		}
		if(!isset($PARAMS['captcha_id']) || !isset($PARAMS['captcha_key'])) {
			$id = md5(random_bytes(32));
			session_id('API'.$id);
			session_start(['use_cookies' => '0']);
			$c = getCaptchaText(rand(6, 10));
			$_SESSION['captcha_key'] = $c; 
			json(['res' => 'need_captcha', 'captcha_id' => $id]);
			session_write_close();
			die();
		}
		checkParamEmpty('phone');
		session_id('API'.$PARAMS['captcha_id']);
		session_start(['use_cookies' => '0']);
		if(!isset($_SESSION['captcha_key']) || empty($_SESSION['captcha_key'])) {
			unset($_SESSION['captcha_key']);
			$id = md5(random_bytes(32));
			session_id('API'.$id);
			session_start(['use_cookies' => '0']);
			$c = getCaptchaText(rand(6, 10));
			$_SESSION['captcha_key'] = $c; 
			json(['res' => 'captcha_expired', 'captcha_id' => $id]);
			session_write_close();
			die();
		}
		if(strtolower($PARAMS['captcha_key']) != $_SESSION['captcha_key']) {
			unset($_SESSION['captcha_key']);
			$id = md5(random_bytes(32));
			session_id('API'.$id);
			session_start(['use_cookies' => '0']);
			$c = getCaptchaText(rand(6, 10));
			$_SESSION['captcha_key'] = $c; 
			json(['res' => 'wrong_captcha', 'captcha_id' => $id]);
			session_write_close();
			die();
		}
		unset($_SESSION['captcha_key']);
		session_write_close();
		
		$phone = getParam('phone');
		$user = $_SERVER['HTTP_X_MPGRAM_USER'] ?? $PARAMS['user'] ?? null;
		// generate user id
		if($user === null) {
			$user = rtrim(strtr(base64_encode(hash('sha384', sha1(md5($phone.rand(0,1000).random_bytes(6))).random_bytes(30), true)), '+/', '-_'), '=');
		}
		setupMadelineProto($user);
		try {
			$a = $MP->phoneLogin($phone);
			json(['user' => $user, 'res' => 'code_sent', 'phone_code_hash' => $a['phone_code_hash'] ?? null]);
		} catch (Exception $e) {
			if(strpos($e->getMessage(), 'PHONE_NUMBER_INVALID') !== false) {
				json(['user' => $user, 'res' => 'phone_number_invalid']);
			} else {
				json(['user' => $user, 'result' => 'exception', 'message' => $e->getMessage()]);
			}
		}
		break;
	case 'resendCode':
		if ($v != api_version) {
			http_response_code(403);
			error(['message' => "Unsupported API version"]);
		}
		if (!defined('ENABLE_LOGIN_API') || !ENABLE_LOGIN_API) {
			http_response_code(403);
			error(['message' => "Login API is disabled"]);
		}
		checkParamEmpty('code');
		checkAuth();
		setupMadelineProto();
		
		$MP->auth->resendCode(['phone' => $phone, 'phone_code_hash' => $hash]);
		json(['res' => 1]);
		break;
	case 'completePhoneLogin':
		if ($v != api_version) {
			http_response_code(403);
			error(['message' => "Unsupported API version"]);
		}
		if (!defined('ENABLE_LOGIN_API') || !ENABLE_LOGIN_API) {
			http_response_code(403);
			error(['message' => "Login API is disabled"]);
		}
		checkParamEmpty('code');
		checkAuth();
		setupMadelineProto();
		try {
			$a = $MP->completePhoneLogin($PARAMS['code']);
			$hash = $a['phone_code_hash'] ?? null;
			if(isset($a['_']) && $a['_'] === 'account.noPassword') {
				json(['res' => 'no_password', 'phone_code_hash' => $hash]);
			} elseif(isset($a['_']) && $a['_'] === 'account.password') {
				json(['res' => 'password', 'phone_code_hash' => $hash]);
			} elseif(isset($a['_']) && $a['_'] === 'account.needSignup') {
				json(['res' => 'need_signup', 'phone_code_hash' => $hash]);
			} else {
				json(['res' => 1, 'phone_code_hash' => $hash]);
			}
		} catch (Exception $e) {
			if(strpos($e->getMessage(), 'PHONE_CODE_INVALID') !== false) {
				json(['res' => 'phone_code_invalid']);
			} elseif(strpos($e->getMessage(), 'PHONE_CODE_EXPIRED') !== false) {
				json(['res' => 'phone_code_expired']);
			} elseif(strpos($e->getMessage(), 'AUTH_RESTART') !== false) {
				json(['res' => 'auth_restart']);
			} else {
				error(['message' => $e->getMessage()]);
			}
		}
		break;
	case 'complete2faLogin':
		// TODO password encryption
		if ($v != api_version) {
			http_response_code(403);
			error(['message' => "Unsupported API version"]);
		}
		if (!defined('ENABLE_LOGIN_API') || !ENABLE_LOGIN_API) {
			http_response_code(403);
			error(['message' => "Login API is disabled"]);
		}
		checkParamEmpty('password');
		checkAuth();
		setupMadelineProto();
		try {
			$MP->complete2faLogin($PARAMS['password']);
			json(['res' => 1]);
		} catch(Exception $e) {
			if(strpos($e->getMessage(), 'PASSWORD_HASH_INVALID') !== false) {
				json(['res' => 'password_hash_invalid']);
			} else {
				error(['message' => $e->getMessage()]);
			}
		}	
		break;
	case 'getServerTimeOffset':
		$dtz = new DateTimeZone(date_default_timezone_get());
		$t = new DateTime('now', $dtz);
		$tof = $dtz->getOffset($t);
		json(['res' => $tof]);
		break;
	// Authorized methods
	case 'completeSignup':
		//checkParamEmpty('first_name');
		//checkParamEmpty('last_name');
		//checkAuth();
		//setupMadelineProto();
		//try {
		//	$MP->completeSignup($PARAMS['first_name'], $PARAMS['last_name']);
		//	json(['result' => 1]);
		//} catch(Exception $e) {
		//	json(['result' => 'exception', 'message' => $e->getMessage()]);
		//}
		json(['res' => 0]);
		break;
	case 'checkAuth':
		checkAuth();
		setupMadelineProto();
		json(['res' => '1']);
		break;
	case 'getDialogs':
		checkAuth();
		setupMadelineProto();
		
		function cmp($a, $b) {
			global $rawData;
			$ma = $a['message'] ?? null;
			$mb = $b['message'] ?? null;
			if ($ma == null || $mb == null) {
				foreach($rawData['messages'] as $m) {
					if(parsePeer($m['peer_id']) == ($a['id'] ?? $a['peer'])) {
						$ma = $m;
					}
					if(parsePeer($m['peer_id']) == ($b['id'] ?? $b['peer'])) {
						$mb = $m;
					}
					if($ma !== null && $mb !== null) break;
				}
			}
			if ($ma === null || $mb === null || $ma['date'] == $mb['date']) {
				return 0;
			}
			if(($a['pinned'] ?? false) && !($b['pinned'] ?? false)) {
				return -1;
			}
			return ($ma['date'] > $mb['date']) ? -1 : 1;
		}
		
		$f = $v < 5 ? null : getParam('f', null);
		$rawData = null;
		
		$p = [];
		addParamToArray($p, 'offset_id', 'int');
		addParamToArray($p, 'offset_date', 'int');
		addParamToArray($p, 'offset_peer');
		addParamToArray($p, 'limit', 'int');
		$sort = $v < 5 || $f === null;
		if ($f !== null) {
			if ((int) $f == 1) {
				$p['folder_id'] = 1;
				$rawData = $MP->messages->getDialogs($p);
			} else if ((int) $f > 1) {
				$sort = false;
				$fid = (int) $f;
				$folders = $MP->messages->getDialogFilters();
				if (($folders['_'] ?? '') == 'messages.dialogFilters')
					$folders = $folders['filters'];
				$folder = null;
				foreach($folders as $f) {
					if(!isset($f['id']) || $f['id'] != $fid) continue;
					$folder = $f;
					break;
				}
				if ($folder == null) {
					error(['message' => 'Folder not found']);
				}
				unset($folders);
				$rawData = getAllDialogs();
				$dialogs = [];
				$all = $rawData['dialogs'];
				foreach($rawData['messages'] as $m) {
					foreach($all as $k => $d) {
						if($m['peer_id'] != $d['peer']) continue;
						$all[$k]['message'] = $m;
						break;
					}
				}
				if($f['contacts'] || $f['non_contacts']) {
					$contacts = $MP->contacts->getContacts()['contacts'];
					foreach($all as $d) {
						if($d['peer'] < 0) continue;
						$found = false;
						foreach($contacts as $c) {
							if($d['peer'] != getId($c)) continue;
							$found = true;
							if($f['contacts']) array_push($dialogs, $d);
							break;
						}
						if($found || $f['non_contacts']) continue;
						if(!in_array($d, $dialogs)) array_push($dialogs, $d);
					}
					unset($contacts);
				}
				if($f['groups']) {
					foreach($all as $d) {
						$peer = $d['peer'];
						if($peer > 0) continue;
						foreach($rawData['chats'] as $c) {
							if($c['id'] != $peer) continue;
							if(!($c['broadcast'] ?? false) && !in_array($d, $dialogs))
								array_push($dialogs, $d);
							break;
						}
					}
				}
				if($f['broadcasts']) {
					foreach($all as $d) {
						$peer = $d['peer'];
						if($peer > 0) continue;
						foreach($rawData['chats'] as $c) {
							if($c['id'] != $peer) continue;
							if(($c['broadcast'] ?? false) && !in_array($d, $dialogs))
								array_push($dialogs, $d);
							break;
						}
					}
				}
				if($f['bots']) {
					foreach($all as $d) {
						$peer = $d['peer'];
						if($peer < 0) continue;
						foreach($rawData['users'] as $u) {
							if($u['id'] != $peer) continue;
							if(($u['bot'] ?? false) && !in_array($d, $dialogs))
								array_push($dialogs, $d);
							break;
						}
						continue;
					}
				}
				if(count($f['exclude_peers']) > 0) {
					foreach($f['exclude_peers'] as $p) {
						$p = getId($p);
						foreach($dialogs as $idx => $d) {
							if($d['peer'] != $p) continue;
							unset($dialogs[$idx]);
							break;
						}
					}
				}
				if($f['exclude_archived']) {
					foreach($dialogs as $idx => $d) {
						if(!isset($d['folder_id']) || $d['folder_id'] != 1) continue;
						unset($dialogs[$idx]);
					}
				}
				if($f['exclude_read']) {
					foreach($dialogs as $idx => $d) {
						if(!isset($d['unread_count']) || $d['unread_count'] > 0) continue;
						unset($dialogs[$idx]);
					}
				}
				if(count($f['include_peers']) > 0) {
					foreach($f['include_peers'] as $p) {
						$p = getId($p);
						foreach($all as $d) {
							if($d['peer'] != $p) continue;
							if(!in_array($d, $dialogs)) array_push($dialogs, $d);
							break;
						}
					}
				}
				usort($dialogs, 'cmp');
				if(count($f['pinned_peers']) > 0) {
					$pinned = array();
					foreach($f['pinned_peers'] as $p) {
						$p = getId($p);
						foreach($all as $d) {
							if($d['peer'] != $p) continue;
							if(in_array($d, $dialogs)) {
								unset($dialogs[array_search($d, $dialogs)]);
							}
							array_push($pinned, $d);
							break;
						}
					}
					$dialogs = array_merge($pinned, $dialogs);
					unset($pinned);
				}
				$rawData['dialogs'] = $dialogs;
				unset($all);
			} else {
				$p['folder_id'] = (int) $f;
				$rawData = $MP->messages->getDialogs($p);
			}
		} else {
			$rawData = $MP->messages->getDialogs($p);
		}
		$res = array();
		if(checkField('raw') === true) {
			$res['raw'] = $rawData;
		}
		$dialogPeers = array();
		$senderPeers = array();
		$mesages = array();
		if(checkField('dialogs', true)) {
			foreach($rawData['messages'] as $rawMessage) {
				$message = parseMessage($rawMessage, $PARAMS['media'] ?? false, $v < 5 ? false : ($PARAMS['text'] ?? true));
				$messages[strval($message['peer_id'])] = $message;
			}
			$res['dialogs'] = array();
			foreach($rawData['dialogs'] as $rawDialog) {
				$dialog = parseDialog($rawDialog);
				array_push($res['dialogs'], $dialog);
			}
			if ($sort) usort($res['dialogs'], 'cmp');
			for($i = count($rawData['dialogs'])-1; $i >= 0; $i--) {
				if(!checkCount($i)) {
					unset($res['dialogs'][$i]);
				} else {
					$id = $res['dialogs'][$i]['id'];
					array_push($dialogPeers, $id);
					if(!isset($messages[$id]) || !isset($messages[$id]['from_id'])) {
						continue;
					}
					$fid = strval($messages[$id]['from_id']);
					if($fid == $id) {
						continue;
					}
					array_push($senderPeers, $fid);
				}
			}
		}
		if(checkField('users', true)) {
			$res['users'] = array();
			$res['users']['0'] = 0;
			foreach($rawData['users'] as $rawUser) {
				$id = strval($rawUser['id']);
				if(isset($res['users'][$id]) || (count($dialogPeers) != 0 && !in_array($id, $dialogPeers) && !in_array($id, $senderPeers))) continue;
				$res['users'][$id] = parseUser($rawUser);
			}
		}
		if(checkField('chats', true)) {
			$res['chats'] = array();
			$res['chats']['0'] = 0;
			foreach($rawData['chats'] as $rawChat) {
				$id = strval($rawChat['id']);
				if(isset($res['chats'][$id]) || (count($dialogPeers) != 0 && !in_array($id, $dialogPeers) && !in_array($id, $senderPeers))) continue;
				$res['chats'][$id] = parseChat($rawChat);
			}
		}
		if(checkField('messages', true)) {
			if ($v < 5) {
				$res['messages'] = array();
				$res['messages']['0'] = 0;
				foreach($messages as $message) {
					$id = $message['peer_id'];
					if(count($dialogPeers) != 0 && !in_array($id, $dialogPeers)) continue;
					$res['messages'][$id] = $message;
				}
			} else {
				$l = count($res['dialogs']);
				foreach($messages as $message) {
					$id = $message['peer_id'];
					if(count($dialogPeers) != 0 && !in_array($id, $dialogPeers)) continue;
					$idx = null;
					for ($i = 0; $i < $l; $i++) {
						if ($res['dialogs'][$i]['id'] != strval($id)) continue;
						$idx = $i;
						break;
					}
					if ($idx === null) continue;
					unset($message['peer_id']);
					$res['dialogs'][$idx]['msg'] = $message;
				}
			}
		}
		json($res);
		break;
	case 'getAllDialogs':
		checkAuth();
		setupMadelineProto();
		$rawData = getAllDialogs();
		$res = array();
		if(checkField('raw') === true) {
			$res['raw'] = $rawData;
		}
		$dialogPeers = array();
		$senderPeers = array();
		$mesages = array();
		if(checkField('dialogs')) {
			foreach($rawData['messages'] as $rawMessage) {
				$message = parseMessage($rawMessage);
				$messages[strval($message['peer_id'])] = $message;
			}
			$res['dialogs'] = array();
			foreach($rawData['dialogs'] as $rawDialog) {
				$dialog = parseDialog($rawDialog);
				array_push($res['dialogs'], $dialog);
			}
			function cmp($a, $b) {
				global $rawData;
				$ma = null;
				$mb = null;
				foreach($rawData['messages'] as $m) {
					if(parsePeer($m['peer_id']) == $a['id']) {
						$ma = $m;
					}
					if(parsePeer($m['peer_id']) == $b['id']) {
						$mb = $m;
					}
					if($ma !== null && $mb !== null) break;
				}
				if ($ma === null || $mb === null || $ma['date'] == $mb['date']) {
					return 0;
				}
				if($a['pinned'] && !$b['pinned']) {
					return -1;
				}
				return ($ma['date'] > $mb['date']) ? -1 : 1;
			}
			usort($res['dialogs'], 'cmp');
			for($i = count($rawData['dialogs'])-1; $i >= 0; $i--) {
				if(!checkCount($i)) {
					unset($res['dialogs'][$i]);
				} else {
					$id = $res['dialogs'][$i]['id'];
					array_push($dialogPeers, $id);
					if(!isset($messages[$id]) || !isset($messages[$id]['from_id'])) {
						continue;
					}
					$fid = strval($messages[$id]['from_id']);
					if($fid == $id) {
						continue;
					}
					array_push($senderPeers, $fid);
				}
			}
		}
		if(checkField('users')) {
			$res['users'] = array();
			$res['users']['0'] = 0;
			foreach($rawData['users'] as $rawUser) {
				$id = strval($rawUser['id']);
				if(isset($res['users'][$id]) || (count($dialogPeers) != 0 && !in_array($id, $dialogPeers) && !in_array($id, $senderPeers))) continue;
				$res['users'][$id] = parseUser($rawUser);
			}
		}
		if(checkField('chats')) {
			$res['chats'] = array();
			$res['chats']['0'] = 0;
			foreach($rawData['chats'] as $rawChat) {
				$id = strval($rawChat['id']);
				if(isset($res['chats'][$id]) || (count($dialogPeers) != 0 && !in_array($id, $dialogPeers) && !in_array($id, $senderPeers))) continue;
				$res['chats'][$id] = parseChat($rawChat);
			}
		}
		if(checkField('messages', false) && $v < 5) {
			$res['messages'] = array();
			$res['messages']['0'] = 0;
			foreach($messages as $message) {
				$id = $message['peer_id'];
				if(count($dialogPeers) != 0 && !in_array($id, $dialogPeers)) continue;
				$res['messages'][$id] = $message;
			}
		}
		json($res);
		break;
	case 'getHistory':
	case 'searchMessages':
		checkParamEmpty('peer');
		checkAuth();
		setupMadelineProto();
		$p = array();
		addParamToArray($p, 'peer');
		addParamToArray($p, 'offset_id', 'int');
		addParamToArray($p, 'offset_date', 'int');
		addParamToArray($p, 'add_offset', 'int');
		addParamToArray($p, 'limit', 'int');
		addParamToArray($p, 'max_id', 'int');
		addParamToArray($p, 'min_id', 'int');
		addParamToArray($p, 'q');
		addParamToArray($p, 'top_msg_id');
		if (!isParamEmpty('filter')) {
			$p['filter'] = ['_' => 'inputMessagesFilter'.getParam('filter')];
		}
		$rawData = $METHOD == 'searchMessages' ? $MP->messages->search($p) : $MP->messages->getHistory($p);
		$res = array();
		if (isset($rawData['count'])) $res['count'] = $rawData['count'];
		if (isset($rawData['offset_id_offset'])) $res['off'] = $rawData['offset_id_offset'];
		if(checkField('messages')) {
			$res['messages'] = array();
			foreach($rawData['messages'] as $rawMessage) {
				array_push($res['messages'], parseMessage($rawMessage, $PARAMS['media'] ?? false));
			}
		}
		if(checkField('users')) {
			$res['users'] = array();
			$res['users']['0'] = 0;
			foreach($rawData['users'] as $rawUser) {
				$id = strval($rawUser['id']);
				if(isset($res['users'][$id])) continue;
				$res['users'][$id] = parseUser($rawUser);
			}
		}
		if(checkField('chats')) {
			$res['chats'] = array();
			$res['chats']['0'] = 0;
			foreach($rawData['chats'] as $rawChat) {
				$id = strval($rawChat['id']);
				if(isset($res['chats'][$id])) continue;
				$res['chats'][$id] = parseChat($rawChat);
			}
		}
		json($res);
		break;
	case 'sendMessage':
		checkParamEmpty('peer');
		checkParamEmpty('text');
		checkAuth();
		setupMadelineProto();
		$p = array();
		addParamToArray($p, 'peer');
		$p['message'] = getParam('text');
		if (!isParamEmpty('reply')) {
			$p['reply_to_msg_id'] = getParam('reply');
		}
		$r = $MP->messages->sendMessage($p);
		json(['res' => '1']);
		break;
	case 'getSelf':
	case 'me':
		checkAuth();
		setupMadelineProto();
		$r = $MP->getSelf();
		if (!$r) {
			http_response_code(401);
			error(['message' => 'Could not get user info']);
		}
		json(parseUser($r));
		break;
/*
	case 'getUser':
	case 'getChat':
		break;
*/
	case 'getPeer':
		checkParamEmpty('id');
		checkAuth();
		setupMadelineProto();
		$r = $MP->getInfo(getParam('id'));
		if (isset($r['User']) && (!isset($PARAMS['type']) || $PARAMS['type'] == 'user')) {
			json(parseUser($r['User']));
		} elseif (isset($r['Chat']) && (!isset($PARAMS['type']) || $PARAMS['type'] == 'chat')) {
			json(parseChat($r['Chat']));
		} else {
			error(['message'=>'']);
		}
		break;
	case 'getPeers':
		checkParamEmpty('id');
		checkAuth();
		setupMadelineProto();
		$users = ['0'=>0];
		$chats = ['0'=>0];
		foreach (explode(',', getParam('id')) as $id) {
			$id = (int) trim($id);
			if ($id == 0) error(['message'=>'Invalid id']);
			$r = $MP->getInfo($PARAMS['id']);
			if (isset($r['User'])) {
				$users[strval($id)] = parseUser($r['User']);
			} elseif (isset($r['Chat'])) {
				$chats[strval($id)] = parseChat($r['Chat']);
			}
		}
		json(['users' => $users, 'chats' => $chats]);
		break;
	case 'updates':
		// TODO
		checkAuth();
		setupMadelineProto();
		$timeout = (int) getParam('timeout', '10');
		$offset = (int) getParam('offset');
		$peer = (int) getParam('peer', '0');
		$message = (int) getParam('message', '0'); 
		$types = isParamEmpty('types') ? false : explode(',', getParam('types'));
		$exclude = isParamEmpty('exclude') ? false : explode(',', getParam('exclude'));
		
		$time = microtime(true);
		$so = $offset;
		$i = $message;
		$res = array();
		while (true) {
			flush();
			if(connection_aborted() || microtime(true) - $time >= $timeout) break;
			$updates = $MP->getUpdates(['offset' => $offset+1, 'limit' => 100, 'timeout' => 2]);
			foreach ($updates as $update) {
				if ($update['update_id'] == $so) continue;
				$type = $update['update']['_'];
				$offset = $update['update_id'];
				if ($types && !in_array($type, $types)) continue;
				if ($exclude && in_array($type, $exclude)) continue;
				if ($peer && ($type == 'updateNewMessage' || $type == 'updateNewChannelMessage')) {
					$msg = $update['update']['message'];
					if($msg['peer_id'] != $id) continue;
					if($msg['id'] < $i) continue;
					if($msg['id'] == $i) continue;
					if($minid == 0) {
						$minid = $update['update_id'];
						$minmsg = $msg;
					}
					$maxid = $update['update_id'];
					$maxmsg = $msg;
				}
				if ($peer) continue;
				array_push($res, $update);
			}
			if ($res) break;
			
		}
		if (!$res) {
			json(['res' => 0]);
		} else {
			json(['res' => $res]);
		}
		break;
	// v5
	case 'getFullInfo':
		checkParamEmpty('id');
		checkAuth();
		setupMadelineProto();
		$r = $MP->getFullInfo(getParam('id')) ?? null;
		if ($r) {
			json($r);
		} else {
			error(['message'=>'']);
		}
		break;
	case 'getFolders':
		checkAuth();
		setupMadelineProto();
		$folders = $MP->messages->getDialogFilters();
		if(($folders['_'] ?? '') == 'messages.dialogFilters')
			$folders = $folders['filters'];
		$hasArchiveChats = count($MP->messages->getDialogs([
			'limit' => 1, 
			'exclude_pinned' => true,
			'folder_id' => 1
			])['dialogs']) > 0;
		if(count($folders) == 0 && !$hasArchiveChats) {
			json(['res' => 0]);
			break;
		}
		$res = ['archive' => $hasArchiveChats];
		if (count($folders) > 0) {
			$res['folders'] = [];
			foreach($folders as $f) {
			if(($f['_'] ?? '') == 'dialogFilterDefault' || !isset($f['id'])) {
				array_push($res['folders'], ['id' => 0]);
			} else {
				array_push($res['folders'], ['id' => $f['id'], 't' => $f['title']]);
			}
		}
		}
		json($res);
		break;
	case 'readMessages':
		checkAuth();
		setupMadelineProto();
		
		$id = getParam('id');
		$maxid = (int) getParam('max');
		$thread = getParam('thread', null);
		
		if ($thread != null) {
			$MP->messages->readDiscussion(['peer' => $id, 'read_max_id' => $maxid, 'msg_id' => (int) $thread]);
			$MP->messages->readMentions(['peer' => $id, 'top_msg_id' => (int) $thread]);
		} else if($ch || (int)$id < 0) {
			$MP->channels->readHistory(['channel' => $id, 'max_id' => $maxid]);
			$MP->messages->readMentions(['peer' => $id]);
		} else {
			$MP->messages->readHistory(['peer' => $id, 'max_id' => $maxid]);
			$MP->messages->readMentions(['peer' => $id]);
		}
		break;
	case 'startBot':
		checkAuth();
		setupMadelineProto();
		
		$id = getParam('id');
		$start = getParam('start', null);
		$random = getParam('random', null);
		$MP->messages->startBot(['start_param' => $start, 'bot' => $id, 'random_id' => $random]);
	
		json(['res' => 1]);
		break;
	case 'getContacts':
		checkAuth();
		setupMadelineProto();
		
		$rawData = $MP->contacts->getContacts();
		$res = [];
		foreach ($rawData['contacts'] as $contact) {
			array_push($res, parseUser(findPeer(getId($contact), $rawData)));
		}
		json(['res' => $res]);
		break;
	case 'joinChannel':
		checkAuth();
		setupMadelineProto();
		$MP->channels->joinChannel(['channel' => getParam('id')]);
		json(['res' => 1]);
		break;
	case 'leaveChannel':
		checkAuth();
		setupMadelineProto();
		$MP->channels->leaveChannel(['channel' => getParam('id')]);
		json(['res' => 1]);
		break;
	case 'checkChatInvite':
		checkAuth();
		setupMadelineProto();
		json(['res' => $MP->messages->checkChatInvite(hash: getParam('id'))]);
		break;
	case 'importChatInvite':
		checkAuth();
		setupMadelineProto();
		$MP->messages->importChatInvite(hash: getParam('id'));
		json(['res' => 1]);
		break;
	case 'editMessage':
		checkParamEmpty('peer');
		checkParamEmpty('text');
		checkParamEmpty('id');
		checkAuth();
		setupMadelineProto();
		$p = array();
		addParamToArray($p, 'peer');
		addParamToArray($p, 'id', 'int');
		$p['message'] = getParam('text');
		$r = $MP->messages->editMessage($p);
		json(['res' => '1']);
		break;
	case 'deleteMessage':
		checkParamEmpty('peer');
		checkParamEmpty('id');
		checkAuth();
		setupMadelineProto();
		$peer = getParam('peer');
		$id = getParam('id');
		if (is_numeric($peer) && (int) $peer > 0) {
			$MP->messages->deleteMessages(['id' => [(int) $id]]);
		} else {
			$MP->channels->deleteMessages(['channel' => $peer, 'id' => [(int) $id]]);
		}
		json(['res' => '1']);
		break;
	case 'resolvePhone':
		checkAuth();
		setupMadelineProto();
		json(['res' => $MP->contacts->resolvePhone(phone: getParam('phone'))]);
		break;
	// TODO topics, getBotCallbackAnswer, sendVote
	default:
		error(['message' => "Method \"$METHOD\" is undefined"]);
	}
} catch (Throwable $e) {
	http_response_code(500);
	error(['message' => "Unhandled exception", 'stack_trace' => strval($e)]);
}
?>