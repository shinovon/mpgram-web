<?php
if(defined('mp_loaded')) die();
define('utils_loaded', true);

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
		if(static::$enc !== null) {
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
		return null;
	}

	static function parseMessageAction($a, $mfn, $mfid, $n, $lng, $chat=true, $MP) {
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
	
	static function printMessages($MP, $rm, $id, $pm, $ch, $lng, $imgs, $name= null, $un=null, $timeoff=0) {
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
				if($m['out'] == true && !$ch) {
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
				if(mb_strlen($fwname, 'UTF-8') > 30)
					$fwname = mb_substr($fwname, 0, 30, 'UTF-8');
				if(!isset($m['action'])) {
					echo '<div class="m" id="msg_'.$id.'_'.$m['id'].'">';
					if(!$pm && $uid != null && $l) {
						echo '<b><a href="chat.php?c='.$uid.'" class="mn">'.MP::dehtml($mname).'</a></b>';
					} else {
						echo '<b class="mn">'.MP::dehtml($mname).'</b>';
					}
					echo ' '.date("H:i", $m['date']-$timeoff);
					if($m['media_unread']) {
						echo ' •';
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
						if($id < 0) {
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
								echo MP::dehtml($replytext);
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
						$load = false;
						if($imgs) {
							if($id < 0 && isset($un) && isset($m['message']) && strlen($m['message']) > 0) {
								try {
									$iur = 'https://t.me/'.$un.'/'.$m['id'].'?embed=1';
									$iur = 'i.php?u='.urlencode($iur).'&p=t';
									echo '<div><a href="'.$iur.'orig"><img alt="'.$lng['media_att'].'" src="'.$iur.'prev"></img></a></div>';
									$load = true;
								} catch (Exception $e) {
								}
							} else {
								$d = $MP->getDownloadInfo($m);
								$filename = hash('sha1', $d['name']);
								switch(substr($d['ext'], 1)) {
									case 'jpg':
									case 'jpeg':
									case 'gif':
									case 'png':
										if($d['size'] < MAX_PHOTO_SIZE) {
											$dest = dirname(__FILE__).'/img/'.$filename;
											if (!file_exists('img/'.$filename) && !file_exists('img/'.$filename.'.lock')) {
												$MP->downloadToFile($media, $dest);
											}
											$load = true;
											echo '<div><a href="i.php?i='.$filename.'&p=orig"><img src="i.php?i='.urlencode($filename).'&p=prev"></img></a></div>';
										} else {
											$reason = $lng['size_too_large'];
										}
										break;
									default:
										break;
								}
							}
						}
						if(!$load) {
							echo '<div><i>'.$lng['media_att'].($reason != null ? ' ('.$reason.')' : '').'</i></div>';
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
						$filename = $fn;
						if(!$filename) {
							$filename = $n;
						}
						$filename = hash('sha1', $filename);
						echo '<div>';
						try {
							if(stripos($n, '.php') === false && !empty($fext) && strtolower($fext) !== '.php') {
								if($d['size'] > MAX_FILE_SIZE) {
									// не скачивать большие файлы
									if(mb_strlen($n, 'UTF-8') > 25) {
										$n = $fn;
										if(mb_strlen($n, 'UTF-8') > 25)
											$n = mb_substr($n, 0, 25, 'UTF-8').'..';
										$n .= $fext;
									}
									echo '<div class="mw"><b>'.MP::dehtml($n).'</b><br>';
									echo round($d['size']/1024.0/1024.0, 2).' MB ('.$lng['size_too_large'].')';
									echo '</div>';
								} else {
									$img = true;
									$open = true;
									$q = 'prev';
									$fq = 'orig';
									$dir = 'img';
									$dl = false;
									$reason = null;
									switch(strtolower(substr($fext, 1))) {
										case 'webp':
											if(strpos($d['name'], 'sticker_') === 0) {
												if($d['size'] > MAX_STICKER_SIZE) {
													$reason = $lng['file_too_large'];
													break;
												}
												$dl = true;
												$open = false;
												if(PNG_STICKERS && isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== false) {
													$q = 'wstickerp';
												} else {
													$q = 'wsticker';
												}
											}
											break;
										case 'jpg':
										case 'jpeg':
											if($d['size'] < MAX_FILE_JPG_SIZE) {
												$img = true;
												$dl = true;
											} else {
												$reason = $lng['file_too_large'];
											}
											break;
										case 'png':
											if($d['size'] < MAX_FILE_PNG_SIZE) {
												$q = 'lprev';
												$fq = 'lpng';
												$dl = true;
												$img = true;
											} else {
												$reason = $lng['file_too_large'];
											}
											break;
										case 'gif':
											if($d['size'] < MAX_FILE_GIF_SIZE) {
												$dl = true;
												$img = false;
											} else {
												$reason = $lng['file_too_large'];
											}
											break;
										case 'tgs':
											break;
										case 'mp3':
											if(DOWNLOAD_MP3) {
												if($d['size'] > MAX_FILE_MP3_SIZE) {
													$reason = $lng['file_too_large'];
													break;
												}
												$img = false;
												$dl = true;
												$dir = 'doc';
												break;
											}
										default:
											if(DOWNLOAD_DOCUMENTS) {
												if($d['size'] > MAX_DOCUMENT_SIZE) {
													$reason = $lng['file_too_large'];
													break;
												}
												$img = false;
												$dl = true;
												$dir = 'doc';
											}
											break;
									}
									if($dl) {
										$dest = dirname(__FILE__).'/'.$dir.'/'.$filename;
										if(!file_exists($dir)) {
											mkdir($dir, 0777);
										}
										if (!file_exists($dest) && !file_exists($dest.'.lock')) {
											$MP->downloadToFile($media, $dest);
										}
										if($img) {
											if($open) {
												echo '<div><a href="i.php?i='.urlencode($filename).'&p='.$fq.'"><img src="i.php?i='.urlencode($filename).'&p='.$q.'"></img></a></div>';
											} else {
												echo '<div><img src="i.php?i='.urlencode($filename).'&p='.$q.'"></img></div>';
											}
										} else {
											echo '<div class="mw"><b><a href="d.php?n='.urlencode($filename).'&f='.urlencode($n).'">'.MP::dehtml($n).'</a></b><br>';
											echo round($d['size']/1024.0/1024.0, 2).' MB';
											echo '</div>';
										}
									} else {
										if(mb_strlen($n, 'UTF-8') > 25) {
											$n = $fn;
											if(mb_strlen($n, 'UTF-8') > 25)
												$n = mb_substr($n, 0, 25, 'UTF-8').'..';
											$n .= $fext;
										}
										echo '<div class="mw"><b>'.MP::dehtml($n).'</b><br>';
										echo round($d['size']/1024.0/1024.0, 2).' MB'.($reason !== null ? '( '.$reason.')' : '');
										echo '</div>';
									}
								}
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
				echo "<xmp>$e</xmp>";
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
	
	static function getMadelineAPI($user) {
		require_once 'vendor/autoload.php';
		return new \danog\MadelineProto\API(sessionspath.$user.'.madeline', static::getMadelineSettings());
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
			static::cookie($name, $x, time());
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

	public static function init() {
		static::$enc = static::getEncoding();
	}
}
MP::init();
?>
