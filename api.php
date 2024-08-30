<?php
ini_set('error_reporting', E_ERROR);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

require_once("api_values.php");
require_once("config.php");

define("def", 1);
define("api_version", 2);

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
	$c = JSON_UNESCAPED_SLASHES | (isset($_SERVER['HTTP_X_MPGRAM_UNICODE']) ? JSON_UNESCAPED_UNICODE : 0);
	if(isset($PARAMS['pretty'])) {
		$c |= JSON_PRETTY_PRINT;
	}
	echo json_encode($json, $c);
	define("json", 1);
}

function error($error) {
	$obj['error'] = $error;
	json($obj);
	die();
}

function checkField($field) {
	global $PARAMS;
	if(!isset($PARAMS['fields'])) {
		return def;
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
	$user = $PARAMS['user'] ?? $_SERVER['HTTP_X_MPGRAM_USER'] ?? null;
	
	if($user == null || empty($user)
		|| strlen($user) < 32 || strlen($user) > 200
		|| strpos($user, '\\') !== false
		|| strpos($user, '/') !== false
		|| strpos($user, '.') !== false
		|| strpos($user, ';') !== false
		|| strpos($user, ':') !== false
		|| !file_exists(sessionspath.$user.'.madeline')) {
		if ($PARAMS['method'] == 'checkAuth') {
			error(['message'=>'Invalid authorization']);
		} else {
			error(['message'=>'Authorization is required for this method']);
		}
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
		$user = $PARAMS['user'] ?? $_SERVER['HTTP_X_MPGRAM_USER'];
	}
	require_once 'vendor/autoload.php';
	$sets = new \danog\MadelineProto\Settings;
	$app = new \danog\MadelineProto\Settings\AppInfo;
	$app->setApiId(api_id);
	$app->setApiHash(api_hash);
	
	$app->setAppVersion($_SERVER['HTTP_X_MPGRAM_APP_VERSION'] ?? 'api');
	if (isset($_SERVER['HTTP_X_MPGRAM_DEVICE'])) {
		$app->setDeviceModel($_SERVER['HTTP_X_MPGRAM_DEVICE']);
	}
	if (isset($_SERVER['HTTP_X_MPGRAM_SYSTEM'])) {
		$app->setSystemVersion($_SERVER['HTTP_X_MPGRAM_SYSTEM']);
	}
	$sets->setAppInfo($app);
	$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline', $sets);
}

function getId($a) {
	if(is_int($a)) return $a;
	return $a['user_id'] ?? (isset($a['chat_id']) ? -$a['chat_id'] : (isset($a['channel_id']) ? (Magic::ZERO_CHANNEL_ID - $a['channel_id']) : null));
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

function parsePeer($peer) {
	return getId($peer);
}

function parseDialog($rawDialog) {
	$dialog = array();
	$dialog['id'] = strval(getId($rawDialog['peer']));
	$dialog['unread_count'] = $rawDialog['unread_count'] ?? 0;
	$dialog['pinned'] = $rawDialog['pinned'] ?? null;
	return $dialog;
}

function parseUser($rawUser) {
	if (!$rawUser) {
		return false;
	}
	$user = array();
	$user['id'] = strval($rawUser['id']);
	$user['first_name'] = $rawUser['first_name'] ?? null;
	$user['last_name'] = $rawUser['last_name'] ?? null;
	$user['username'] = $rawUser['username'] ?? null;
	return $user;
}

function parseChat($rawChat) {
	$chat = array();
	$chat['type'] = $rawChat['_'];
	$chat['id'] = strval($rawChat['id']);
	$chat['title'] = $rawChat['title'] ?? null;
	$chat['username'] = $rawChat['username'] ?? null;
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

function parseMessage($rawMessage, $media=false) {
	$message = array();
	//$message = $rawMessage;
	$message['id'] = $rawMessage['id'] ?? null;
	$message['date'] = $rawMessage['date'] ?? null;
	if (isset($rawMessage['message'])) $message['text'] = $rawMessage['message'];
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
		$message['fwd'] = $fwd;
	}
	if (isset($rawMessage['media'])) {
		if (!$media) {
			// media is disabled
			$message['media'] = ['_' => 0];
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
				$media['thumb'] = isset($media['document']['thumbs']);
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
		$reply['msg'] = $rawReply['reply_to_msg_id'] ?? null;
		if (isset($rawReply['reply_to_peer_id'])) $reply['peer'] = $rawReply['reply_to_peer_id'];
		if (isset($rawReply['quote_text'])) $reply['quote'] = $rawReply['quote_text'];
		$message['reply'] = $reply;
	}
	//$message['raw'] = $rawMessage;
	return $message;
}

try {
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
	if (!defined('ENABLE_API') || !ENABLE_API) {
		error(['message' => "API is disabled"]);
	}
	if(!isset($PARAMS['method'])) {
		error(['message' => "No method set"]);
	}
	checkParamEmpty('v');
	$v = (int) $PARAMS['v'];
	if($v != api_version && $v != api_version + 1) {
		error(['message' => "Unsupported API version"]);
	}
	$METHOD = $PARAMS['method'];
	switch($METHOD) {
/*
	case 'getCaptchaImg':
	case 'initLogin':
		break;
*/
	case 'getServerTimeOffset':
		$dtz = new DateTimeZone(date_default_timezone_get());
		$t = new DateTime('now', $dtz);
		$tof = $dtz->getOffset($t);
		json(['res' => $tof]);
		break;
	// Authorized methods
/*
	case 'logout':
	case 'completePhoneLogin':
	case 'complete2faLogin':
	case 'completeSignup':
		break;
*/
	case 'checkAuth':
		checkAuth();
		setupMadelineProto();
		json(['res' => '1']);
		break;
	case 'getDialogs':
		checkAuth();
		setupMadelineProto();
		$p = array();
		addParamToArray($p, 'offset_id', 'int');
		addParamToArray($p, 'offset_date', 'int');
		addParamToArray($p, 'offset_peer');
		addParamToArray($p, 'limit', 'int');
		$rawData = $MP->messages->getDialogs($p);
		$res = array();
		if(checkField('raw') === true) {
			$res['raw'] = $rawData;
		}
		$dialogPeers = array();
		$senderPeers = array();
		$mesages = array();
		if(checkField('dialogs')) {
			foreach($rawData['messages'] as $rawMessage) {
				$message = parseMessage($rawMessage, $PARAMS['include_media'] ?? false);
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
		if(checkField('messages')) {
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
		if(checkField('messages')) {
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
		$rawData = $MP->messages->getHistory($p);
		$res = array();
		$res['count'] = $rawData['count'];
		if(checkField('messages')) {
			$res['messages'] = array();
			foreach($rawData['messages'] as $rawMessage) {
				array_push($res['messages'], parseMessage($rawMessage, $PARAMS['include_media'] ?? false));
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
		$p['message'] = $PARAMS['text'];
		$r = $MP->messages->sendMessage($p);
		json(['res' => '1']);
		break;
	case 'getSelf':
		checkAuth();
		setupMadelineProto();
		$r = $MP->getSelf();
		json(parseUser($r));
		break;
	case 'getUser':
		checkParamEmpty('id');
		checkAuth();
		setupMadelineProto();
		$r = $MP->getInfo($PARAMS['id'])['User'];
		json(parseUser($r));
		break;
	case 'getChat':
		checkParamEmpty('id');
		checkAuth();
		setupMadelineProto();
		$r = $MP->getInfo($PARAMS['id'])['Chat'];
		json(parseChat($r));
		break;
	default:
		error(['message' => "Method \"$METHOD\" is undefined"]);
	}
} catch (Throwable $e) {
	http_response_code(500);
	error(['message' => "Unhandled exception", 'stack_trace' => strval($e)]);
}
?>