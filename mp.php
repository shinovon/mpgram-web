<?php
if(defined('mp_loaded')) return;
define('mp_loaded', true);

require_once("config.php");
require_once("api_values.php");

if(!defined("api_id") || api_id == 0) {
	throw new Exception('api_id is not set!');
}
if(!file_exists(sessionspath)) {
	mkdir(sessionspath, 0777);
}
class MP {
	static $win1251;
	static $enc;
	static $iev;
	static $useragent;

	static function dehtml($s) {
		return static::x(str_replace('<', '&lt;',
		str_replace('>', '&gt;',
		str_replace('&', '&amp;', $s))));
	}

	static function getId($MP, $a) {
		if(isset($a['user_id'])) {
			return $a['user_id'];
		} else if(isset($a['chat_id'])) {
			return '-'.$a['chat_id'];
		} else if(isset($a['channel_id'])) {
			return '-100'.$a['channel_id'];
		} else {
			throw new Exception("");
		}
	}

	static function getName($MP, $a, $full = false) {
		return static::getNameFromId($MP, static::getId($MP, $a));
	}

	static function getNameFromId($MP, $id, $full = false) {
		return static::getNameFromInfo($MP->getInfo($id), $full);
	}

	static function getNameFromInfo($p, $full = false) {
		if(isset($p['User'])) {
			if(!isset($p['User']['first_name'])) {
				return 'Deleted Account';
			}
			try {
				return trim($p['User']['first_name']).($full && isset($p['User']['last_name']) ? ' '.trim($p['User']['last_name']) : '');
			} catch (Exception $e) {
				return $e->getMessage();
			}
		} else if(isset($p['Chat'])) {
			return $p['Chat']['title'];
		} else {
			return 'Не определен';
		}
	}

	static function getSelfName($MP, $full = true) {
		$p = $MP->getSelf();
		$s = $p['first_name'];
		if($full && isset($p['last_name'])) {
			$s .= ' '.$p['last_name'];
		}
		return $s;
	}

	static function getSelfId($MP) {
		$self = $MP->getSelf();
		if(!$self) throw new Exception("Could not get user info! ".var_export($self,true));
		return $self['id'];
	}

	static function win1251() {
		$h = getallheaders();
		return isset($_SERVER['HTTP_USER_AGENT'])
			&& strpos($_SERVER['HTTP_USER_AGENT'], 'PSP (PlayStation Portable)') !== false
			&& isset($h['Accept-Language'])
			&& strtolower($h['Accept-Language']) == 'ru';
	}

	static function x($s) {
		if(static::$enc !== null && static::$enc !== 'utf-8') {
			return mb_convert_encoding($s, static::$enc);
		}
		return $s;
	}

	static function getEncoding() {
		if(static::win1251()) {
			return 'windows-1251';
		}
		$iev = static::getIEVersion();
		if($iev != 0 && $iev < 5) {
			return static::getSetting('lang') == 'ru' ? 'windows-1251' : 'windows-1252';
		}
		return 'utf-8';
	}

	static function parseMessageAction($a, $mfn, $mfid, $n, $lng, $chat=true, $MP=null) {
		$fn = $mfn !== null ? $mfn : $n;
		$txt = '';
		try {
			$at = substr(strtolower($a['_']), strlen('messageaction'));
			switch($at) {
				case 'chatadduser':
				case 'chatjoinedbylink':
					$u = null;
					if(isset($a['users'])) {
						$u = $a['users'][0];
					}
					if($mfid !== null && $chat) {
						$txt = '<a href="chat.php?c='.$mfid.'" class="mn">'.MP::dehtml($fn).'</a>';
					} else {
						$txt = MP::dehtml($fn);
					}
					if($u == $mfid || $u === null) {
						$txt .= ' '.static::x($lng['action_join']);
					} else {
						$txt .= ' '.static::x($lng['action_add']).' '.MP::dehtml(MP::getNameFromId($MP, $u));
					}
					break;
				case 'pinmessage':
					$txt = $fn.' '.static::x($lng['action_pin']);
					break;
				case 'channelcreate':
					$txt = static::x($lng['action_channelcreate']);
					break;
				case 'chateditphoto':
					$txt = static::x($lng['action_chateditphoto']);
					break;
				case 'chatedittitle':
					$txt = static::x($lng['action_chatedittitle']).' '.MP::dehtml($a['title']);
					break;
				default:
					$txt = $at;
					break;
			}
		} catch (Exception $e) {
			echo $e;
		}
		return $txt;
	}
	
	static function printMessages($MP, $rm, $id, $pm, $ch, $lng, $imgs, $name= null, $un=null, $timeoff=0, $chid=false, $unswer=false) {
		$lastdate = date('d.m.y', time()-$timeoff);
		foreach($rm as $m) {
			try {
				$mname1 = null;
				$uid = null;
				if(isset($m['from_id'])) {
					$uid = MP::getId($MP, $m['from_id']);
					$mname1 = MP::getNameFromId($MP, $uid);
				}
				if(mb_strlen($mname1, 'UTF-8') > 30)
					$mname1 = mb_substr($mname1, 0, 30, 'UTF-8');
				$mname = null;
				$l = false;
				if($m['out'] && !$ch) {
					$uid = MP::getSelfId($MP);
					$mname = $lng['you'];
				} else if(($pm || $ch) && $name) {
					$uid = $id;
					$mname = $name;
				} else {
					$l = true;
					$mname = $mname1;
				}
				$fwid = null;
				$fwname = null;
				if(isset($m['fwd_from'])) {
					if(isset($m['fwd_from']['from_name'])) {
						$fwname = $m['fwd_from']['from_name'];
					} else if(isset($m['fwd_from']['from_id'])){
						$fwid = MP::getId($MP, $m['fwd_from']['from_id']);
						$fwname = MP::getNameFromId($MP, $fwid, true);
					}
				}
				$mtime = $m['date']-$timeoff;
				$mdate = date('d.m.y', $mtime);
				if($mdate !== $lastdate) {
					echo '<div class="ma">'.$mdate.'</div>';
					$lastdate = $mdate;
				}
				if($fwname !== null && mb_strlen($fwname, 'UTF-8') > 30)
					$fwname = mb_substr($fwname, 0, 30, 'UTF-8');
				if(!isset($m['action'])) {
					echo '<div class="m" id="msg_'.$id.'_'.$m['id'].'">';
					if(!$pm && $uid != null && $l) {
						echo '<b><a href="chat.php?c='.$uid.'" class="mn">'.MP::dehtml($mname).'</a></b>';
					} else {
						echo '<b class="mn">'.MP::dehtml($mname).'</b>';
					}
					echo ' '.date("H:i", $mtime);
					if($m['media_unread']) {
						echo ' •';
					}
					if($unswer && !$ch) {
						echo ' <a href="msg.php?c='.$id.'&m='.$m['id'].($m['out']?'&o':'').'" class="u">'.MP::x($lng['msg_options']).'</a>';
					}
				} else {
					echo '<div class="ma" id="msg_'.$id.'_'.$m['id'].'">';
				}
				echo '<br>';
				if($fwname != null) {
					echo '<div class="mf">'.static::x($lng['fwd_from']).' <b>'.MP::dehtml($fwname).'</b></div>';
				}
				if(isset($m['reply_to'])) {
					$replyid = $m['reply_to']['reply_to_msg_id'];
					if($replyid) {
						$replymsg = null;
						if($chid) {
							$replymsg = $MP->channels->getMessages(['channel' => $id, 'id' => [$replyid]]);
						} else {
							$replymsg = $MP->messages->getMessages(['peer' => $id, 'id' => [$replyid]]);
						}
						if($replymsg && isset($replymsg['messages']) && isset($replymsg['messages'][0])) {
							$replymsg = $replymsg['messages'][0];
							echo '<div class="r">';
							if(isset($replymsg['from_id'])) {
								$replyfromid = $replymsg['from_id'];
								if($replyfromid) {
									$replyname = MP::getName($MP, $replyfromid, true);
									if(mb_strlen($replyname, 'UTF-8') > 30)
										$mname = mb_substr($replyname, 0, 30, 'UTF-8').'...';
									echo '<b class="rn">'.MP::dehtml($replyname).'</b>';
								}
							}
							$replytext = '';
							if(isset($replymsg['media'])) {
								$replytext = $lng['media_att'].' ';
							}
							if(isset($replymsg['message'])) {
								$replytext .= $replymsg['message'];
							}
							if(mb_strlen($replytext, 'UTF-8') > 0) {
								if(strlen($replytext) > 50)
									$replytext = mb_substr($replytext, 0, 50, 'UTF-8');
								echo '<div class="rt">';
								echo '<a href="chat.php?c='.$id.'&m='.$replyid.'">';
								echo MP::dehtml($replytext);
								echo '</a>';
								echo '</div>';
							}
							echo '</div>';
						}
					}
				}
				if(isset($m['message']) && strlen($m['message']) > 0) {
					echo '<div class="mt">';
					echo str_replace("\n", "<br>", MP::dehtml($m['message']));
					echo '</div>';
				}
				if(isset($m['media'])) {
					$media = $m['media'];
					$reason = null;
					if(isset($media['photo'])) {
						if($imgs) {
							echo '<div><a href="file.php?m='.$m['id'].'&c='.$id.'&p=rorig"><img src="file.php?m='.$m['id'].'&c='.$id.'&p=rprev"></img></a></div>';
						}
					} else if(isset($media['document'])) {
						$d = $MP->getDownloadInfo($m);
						$fn = $d['name'];
						$fext = $d['ext'];
						$n = $fn.$fext;
						if(isset($media['document']['attributes'])
							&& isset($media['document']['attributes'][0])
						&& isset($media['document']['attributes'][0]['file_name'])) {
							$n = $media['document']['attributes'][0]['file_name'];
						}
						echo '<div>';
						try {
							$img = true;
							$open = true;
							$q = 'rprev';
							$fq = 'rorig';
							switch(strtolower(substr($fext, 1))) {
								case 'webp':
									if(strpos($d['name'], 'sticker_') === 0) {
										$dl = true;
										$open = false;
										$ie = MP::getIEVersion();
										if(PNG_STICKERS && $ie == 0 && $ie > 4) {
											$q = 'rstickerp';
										} else {
											$q = 'rsticker';
										}
									}
									break;
								case 'jpg':
								case 'jpeg':
									$img = true;
									break;
								case 'png':
									$q = 'rprev';
									$fq = 'rorig';
									$img = true;
									break;
								case 'gif':
									$img = false;
									break;
								case 'tgs':
									break;
								case 'mp3':
									$img = false;
									break;
								default:
									$img = false;
									break;
							}
							if($img) {
								if($open) {
									echo '<div><a href="file.php?m='.$m['id'].'&c='.$id.'&p='.$fq.'"><img src="file.php?m='.$m['id'].'&c='.$id.'&p='.$q.'"></img></a></div>';
								} else {
									echo '<div><img src="file.php?m='.$m['id'].'&c='.$id.'&p='.$q.'"></img></div>';
								}
							} else {
								echo '<div class="mw"><b><a href="file.php?m='.$m['id'].'&c='.$id.'">'.MP::dehtml($n).'</a></b><br>';
								echo round($d['size']/1024.0/1024.0, 2).' MB';
								echo '</div>';
							}
						} catch (Exception $e) {
							echo $e;
						}
						echo '</div>';
					} else if(isset($media['webpage'])) {
						echo '<div class="mw">';
						if(isset($media['webpage']['site_name'])) {
							echo '<a href="'.$media['webpage']['url'].'">';
							echo $media['webpage']['site_name'];
							echo '</a>';
						} else if(isset($media['webpage']['url'])) {
							echo '<a href="'.$media['webpage']['url'].'">';
							echo $media['webpage']['url'];
							echo '</a>';
						}
						if(isset($media['webpage']['title'])) {
							echo '<div class="mwt"><b>'.$media['webpage']['title'].'</b></div>';
						}
						echo '</div>';
					} else {
						echo '<div><i>'.$lng['media_att'].'</i></div>';
					}
				}
				if(isset($m['action'])) {
					echo MP::parseMessageAction($m['action'], $mname1, $uid, $name, $lng, true, $MP);
				}
				echo '</div>';
			} catch (Exception $e) {
				echo '<xmp>'.$e->getMessage()."\n".$e->getTraceAsString().'</xmp>';
			}
		}
	}

	static function getURL() {
		$sitepath = '';
		if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
			$sitepath .= 'http';
		} else {
			$sitepath .= 'https';
		}
		$sitepath .= '://'.$_SERVER['SERVER_NAME'];
		if(isset($_SERVER['PHP_SELF'])) {
			$ss = $_SERVER['PHP_SELF'];
			$ss = substr($ss, 0, strrpos($ss, '/')+1);
			$sitepath .= $ss;
		}
		return $sitepath;
	}
	
	static function getUser() {
		$user = null;
		if(isset($_GET['user']))
			$user = $_GET['user'];
		else if(isset($_COOKIE['user']))
			$user = $_COOKIE['user'];
		else if(isset($_SESSION) && isset($_SESSION['user']))
			$user = $_SESSION['user'];
		if(empty($user) || strlen($user) != 32) {
			return false;
		}
		if(!file_exists(sessionspath.$user.'.madeline')) {
			return false;
		}
		return $user;
	}
	
	static function getMadelineSettings() {
		$sets = new \danog\MadelineProto\Settings;
		$app = new \danog\MadelineProto\Settings\AppInfo;
		$app->setApiId(api_id);
		$app->setApiHash(api_hash);
		try {
			if(ini_get('browscap') && isset($_SERVER['HTTP_USER_AGENT'])) {
				$b = get_browser($_SERVER['HTTP_USER_AGENT'], true);
				if($b && isset($b['parent'])) {
					$br = $b['parent'];
					if(isset($b['device_name'])) {
						$br = $b['device_name'];
						if(isset($b['device_brand_name'])) {
							$br = $b['device_brand_name'].' '.$br;
						}
					}
					$pl = null;
					if(isset($b['platform_description'])) {
						$pl = $b['platform_description'];
					} else if(isset($b['platform'])) {
						$pl = $b['platform'];
					}
					if($br) {
						$app->setDeviceModel($br);
					}
					if($pl) {
						$app->setSystemVersion($pl);
					}
				}
			}
		} catch (Exception $e) {
		}
		$app->setAppVersion('web');
		$sets->setAppInfo($app);
		$peer = new \danog\MadelineProto\Settings\Peer;
		$peer->setFullFetch(false);
		$peer->setCacheAllPeersOnStartup(true);
		$sets->setPeer($peer);
		$db = $sets->getDb();
		$db->setEnableMinDb(false);
		$db->setEnableUsernameDb(true);
		$db->setEnableFullPeerDb(false);
		$db->setEnablePeerInfoDb(true);
		return $sets;
	}
	
	static function getMadelineAPI($user, $login = false) {
		require_once 'vendor/autoload.php';
		if($login) {
			$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline', static::getMadelineSettings());
		} else {
			$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline');
		}
		return $MP;
	}

	static function getSetting($name, $def=null, $write=false) {
		$x = $def;
		if(isset($_GET[$name])) {
			$x = $_GET[$name];
			$write = true;
		} else if(isset($_COOKIE[$name])) {
			$x = $_COOKIE[$name];
		}
		if($x && $write) {
			static::cookie($name, $x, time() + (86400 * 365));
		}
		return $x;
	}

	static function getSettingInt($name, $def=0, $write=false) {
		$x = $def;
		if(isset($_GET[$name])) {
			$x = (int)$_GET[$name];
		} else if(isset($_COOKIE[$name])) {
			$x = (int)$_COOKIE[$name];
		}
		if($x && $write) {
			static::cookie($name, $x, time());
		}
		return $x;
	}

	static function getIEVersion() {
		if(!static::$iev)
		try {
			if(isset($_SERVER['HTTP_USER_AGENT']))
				$ua = $_SERVER['HTTP_USER_AGENT'];
			static::$useragent = $ua;
			if(strpos($ua, 'MSIE ') !== false) {
				$i = strpos($ua, 'MSIE ')+5;
				static::$iev = (int)substr($ua, $i, $i+1);
			}
		} catch (Exception $e) {
		}
		return static::$iev;
	}

	static function cookie($n, $v) {
		header('Set-Cookie: '.$n.'='.$v.'; expires='.date('r', time() + (86400 * 365)), false);
	}

	static function delCookie($n) {
		header('Set-Cookie: '.$n.'=; expires='.date('r', time() - 86400), false);
	}
	
	static function deleteSessionFile($user) {
		try {
			unlink(sessionspath.$user.'.madeline.callback.ipc');
		} catch (Exception $e) {
		}
		try {
			unlink(sessionspath.$user.'.madeline.ipcState');
		} catch (Exception $e) {
		}
		try {
			unlink(sessionspath.$user.'.madeline.ipc');
		} catch (Exception $e) {
		}
		try {
			unlink(sessionspath.$user.'.madeline.lightState.php');
		} catch (Exception $e) {
		}
		try {
			unlink(sessionspath.$user.'.madeline.safe.php');
		} catch (Exception $e) {
		}
		try {
			unlink(sessionspath.$user.'.madeline');
		} catch (Exception $e) {
		}
	}
	
	public static function initLocale() {
		$lang = MP::getSetting('lang');
		if($lang === null) {
			$lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? (strpos(strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), 'ru') !== false ? 'ru' : 'en') : 'ru';
			MP::cookie('lang', $lang, time() + (86400 * 365));
		} else if(strlen($lang) !== 2 || !file_exists('locale_'.$lang.'.php')) {
			$lang = 'en';
			MP::cookie('lang', $lang, time() + (86400 * 365));
		}
		include 'locale_'.$lang.'.php';
		return $lng;
	}

	public static function init() {
		static::$enc = static::getEncoding();
	}
}
MP::init();
?>
