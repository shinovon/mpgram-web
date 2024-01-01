<?php
if(defined('mp_loaded')) return;
define('mp_loaded', true);

require_once("config.php");
require_once("api_values.php");

if(!defined("api_id") || api_id == 0) {
	throw new Exception('api_id is not set!');
}
if(!file_exists(sessionspath)) {
	mkdir(sessionspath, 0775);
}

use danog\MadelineProto\Magic;

class MP {
	static $enc;
	static $iev;
	static $useragent;
	static $users;
	static $chats;
	static $colors;

	// Removes html special characters and converts to browser encoding
	static function dehtml($s) {
		if($s === null) return null;
		return static::x(str_replace("\n", '<br>', htmlspecialchars($s)));
	}

	static function getId($a) {
		if(is_int($a)) return $a;
		return $a['user_id'] ?? (isset($a['chat_id']) ? -$a['chat_id'] : (isset($a['channel_id']) ? (Magic::ZERO_CHANNEL_ID - $a['channel_id']) : null));
	}
	
	static function getLocalId($id) {
		if($id < 0) {
			if(-Magic::MAX_CHAT_ID <= $id) {
				return -$id;
			}
			if(Magic::ZERO_CHANNEL_ID - Magic::MAX_CHANNEL_ID <= $id && $id !== Magic::ZERO_CHANNEL_ID) {
				return -$id + Magic::ZERO_CHANNEL_ID;
			}
			if(Magic::ZERO_SECRET_CHAT_ID + Magic::MIN_INT32 <= $id && $id !== Magic::ZERO_SECRET_CHAT_ID) {
				return -$id + DialogId::SECRET_CHAT; // TODO ?
			}
		}
		return $id;
	}

	static function getName($MP, $a, $full = false) {
		return static::getNameFromId($MP, static::getId($a));
	}

	static function getNameFromId($MP, $id, $full = false) {
		// Try to get name from cache
		if((int)$id > 0) {
			if(static::$users !== null) {
				$info = null;
				foreach(static::$users as $p) {
					if($p['id'] == $id) {
						$info = $p;
						break;
					}
				}
				if($info !== null) {
					return static::getNameFromInfo($info, $full);
				}
			}
		} else {
			if(static::$chats !== null) {
				foreach(static::$users as $p) {
				$info = null;
					if($p['id'] == static::getLocalId($id)) {
						$info = $p;
						break;
					}
				}
				if($info !== null) {
					return static::getNameFromInfo($info, $full);
				}
			}
		}
		return static::getNameFromInfo($MP->getInfo($id), $full);
	}

	static function getNameFromInfo($p, $full = false) {
		return isset($p['User']) ? static::getUserName($p['User'], $full) : ($p['Chat']['title'] ?? $p['title'] ?? static::getUserName($p, $full));
	}
	
	static function getUserName($p, $full = false) {
		$last = isset($p['last_name']) ? trim($p['last_name']) : null;
		return isset($p['first_name']) ? trim($p['first_name']).($full && $last !== null ? ' '.$last : '') : ($last !== null ? $last : 'Deleted Account');
	}

	static function getSelfName($MP, $full = true) {
		return static::getUserName($MP->getSelf(), $full);
	}

	static function getSelfId($MP) {
		$self = $MP->getSelf();
		if(!$self) throw new Exception("Could not get user info! ".var_export($self,true));
		return $self['id'];
	}

	// Converts string to browser encoding
	static function x($s) {
		if(static::$enc !== null && static::$enc !== 'utf-8') {
			return mb_convert_encoding($s, static::$enc);
		}
		return $s;
	}

	static function getEncoding() {
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
				case 'chatcreate':
					$txt = static::x($lng['action_channelcreate']);
					break;
				case 'chatedittitle':
					$txt = static::x($lng['action_chatedittitle']).' '.MP::dehtml($a['title']);
					break;
				case 'chateditphoto':
					$txt = static::x($lng['action_chateditphoto']);
					break;
				case 'chatadduser':
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
				case 'chatdeleteuser':
					$txt = var_export($a, true);
					$u = $a['user_id'] ?? null;
					if($u == $mfid || $u === null) {
						$txt = '<a href="chat.php?c='.$mfid.'" class="mn">'.MP::dehtml($fn).'</a>';
						$txt .= ' '.static::x($lng['action_leave']);
					} else {
						$txt = '<a href="chat.php?c='.$mfid.'" class="mn">'.MP::dehtml($fn).'</a>';
						$txt .= ' '.static::x($lng['action_deleteuser']).' ';
						$txt .= '<a href="chat.php?c='.$mfid.'" class="mn">'.MP::dehtml(MP::getNameFromId($MP, $u)).'</a>';
					}
					break;
				case 'chatjoinedbylink':
					$txt = '<a href="chat.php?c='.$mfid.'" class="mn">'.MP::dehtml($fn).'</a> '.static::x($lng['action_joinedbylink']);
					break;
				case 'channelcreate':
					$txt = static::x($lng['action_channelcreate']);
					break;
				case 'pinmessage':
					$txt = '<a href="chat.php?c='.$mfid.'" class="mn">'.MP::dehtml($fn).'</a> '.static::x($lng['action_pin']);
					break;
				case 'historyclear':
					$txt = static::x($lng['action_historyclear']);
					break;
				case 'chatjoinedbyreqeuset':
					$txt = '<a href="chat.php?c='.$mfid.'" class="mn">'.MP::dehtml($fn).'</a> '.static::x($lng['action_joinedbyrequest']);
					break;
				default:
					if(isset($lng['action_'.$at])) {
						$txt = static::x($lng['action_'.$at]);
						break;
					}
					$txt = $at;
					break;
			}
		} catch (Exception $e) {
			echo $e;
		}
		return $txt;
	}
	
	static function printMessages($MP, $rm, $id, $pm, $ch, $lng, $imgs, $name= null, $timeoff=0, $chid=false, $unswer=false) {
		$lastdate = date('d.m.y', time()-$timeoff);
		foreach($rm as $m) {
			try {
				$mname1 = null;
				$uid = null;
				if(isset($m['from_id'])) {
					$uid = MP::getId($m['from_id']);
					$mname1 = MP::getNameFromId($MP, $uid);
				}
				if($mname1 != null && mb_strlen($mname1, 'UTF-8') > 30)
					$mname1 = static::utf16substr($mname1, 0, 30);
				$mname = null;
				$l = false;
				if($m['out'] && !$ch) {
					$uid = MP::getSelfId($MP);
					$mname = $lng['you'];
				} elseif(($pm || $ch) && $name) {
					$uid = $id;
					$mname = $name;
				} else {
					$l = true;
					$mname = $mname1 ? $mname1 : $name;
				}
				$color = '';
				$lid = static::getLocalId($uid);
				if($uid > 0 && isset(static::$users[$uid])) {
					$user = static::$users[$uid];
					if(isset($user['color'])) {
						static::getPeerColors($MP);
						if(isset(static::$colors[$user['color']['color']])) {
							$color = 'style="color: #'. static::$colors[$user['color']['color']] . '"';
						}
					}
				} elseif($uid < 0 && isset(static::$chats[$lid])) {
					$chat = static::$chats[$lid];
					if(isset($chat['color'])) {
						static::getPeerColors($MP);
						if(isset(static::$colors[$chat['color']['color']])) {
							$color = 'style="color: #'. static::$colors[$chat['color']['color']] . '"';
						}
					}
				}
				$fwid = null;
				$fwname = null;
				if(isset($m['fwd_from'])) {
					if(isset($m['fwd_from']['from_name'])) {
						$fwname = $m['fwd_from']['from_name'];
					} elseif(isset($m['fwd_from']['from_id'])){
						$fwid = $m['fwd_from']['from_id'];
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
					$fwname = static::utf16substr($fwname, 0, 30);
				if(!isset($m['action'])) {
					echo '<div class="m" id="msg_'.$id.'_'.$m['id'].'">';
					if(!$pm && $uid != null && $l) {
						echo '<b><a href="chat.php?c='.$uid.'" class="mn" '.$color.'>'.MP::dehtml($mname).'</a></b>';
					} else {
						echo '<b class="mn" '.$color.'>'.MP::dehtml($mname).'</b>';
					}
					echo ' '.date("H:i", $mtime);
					if($m['media_unread']) {
						echo ' •';
					}
					if($unswer) {
						echo ' <small><a href="msg.php?c='.$id.'&m='.$m['id'].($m['out']?'&o':'').($ch?'&ch':'').'" class="u">'.MP::x($lng['msg_options']).'</a></small>';
					}
				} else {
					echo '<div class="ma" id="msg_'.$id.'_'.$m['id'].'">';
				}
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
										$mname = static::utf16substr($replyname, 0, 30).'...';
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
									$replytext = static::utf16substr($replytext, 0, 50);
								echo '<div class="rt">';
								echo '<a href="chat.php?c='.$id.'&m='.$replyid.'">';
								echo MP::dehtml(str_replace("\n", " ", $replytext));
								echo '</a>';
								echo '</div>';
							}
							echo '</div>';
						}
					}
				}
				if(isset($m['message']) && strlen($m['message']) > 0) {
					$text = $m['message'];
					if(isset($m['entities']) && count($m['entities']) > 0) {
						echo '<div class="mt">';
						echo static::wrapRichText($text, $m['entities']);
						echo '</div>';
					} else {
						echo '<div class="mt">';
						echo str_replace("\n", "<br>", MP::dehtml($text));
						echo '</div>';
					}
				}
				if(isset($m['media'])) {
					echo static::printMessageMedia($MP, $m, $id, $imgs, $lng);
				}
				if(isset($m['reply_markup'])) {
					$rows = $m['reply_markup']['rows'] ?? [];
					echo '<table>';
					foreach($rows as $row) {
						echo '<tr><table class="rt rc"><tr>';
						foreach($row['buttons'] ?? [] as $button) {
							$s = '';
							if(isset($button['data'])) {
								$s = 'href="botcallback.php?m='.$m['id'].'&c='.$id.'&d='.urlencode(base64_encode($button['data'])).'"';
							} elseif(isset($button['url'])) {
								$s = 'href="'.static::wrapUrl($button['url']).'"';
							}
							echo '<td class="btd"><a class="btn" '.$s.'>'.$button['text'].'</a></td>';
						}
						echo '</tr></table></tr>';
					}
					echo '</table>';
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
	
	static function printMessageMedia($MP, $m, $id, $imgs, $lng, $mini=false) {
		$media = $m['media'];
		$reason = null;
		if(isset($media['photo'])) {
			if($imgs) {
				if($mini) {
					echo '<a href="chat.php?m='.$m['id'].'&c='.$id.'"><img class="mi" src="file.php?m='.$m['id'].'&c='.$id.'&p=rmin"></img></a>';
				} else {
					echo '<div><a href="file.php?m='.$m['id'].'&c='.$id.'&p=rorig"><img class="mi" src="file.php?m='.$m['id'].'&c='.$id.'&p=rprev"></img></a></div>';
				}
			} else {
				echo '<div><a href="file.php?m='.$m['id'].'&c='.$id.'&p=rorig">'.MP::x($lng['photo']).'</a></div>';
			}
		} elseif(isset($media['document'])) {
			$thumb = isset($media['document']['thumbs']);
			$d = $MP->getDownloadInfo($m);
			$fn = $d['name'];
			$fext = $d['ext'];
			$title = $fn.$fext;
			$nameset = false;
			$voice = false;
			$dur = false;
			if(isset($media['document']['attributes'])) {
				foreach($media['document']['attributes'] as $attr) {
					if($attr['_'] == 'documentAttributeFilename') {
						if($nameset) continue;
						$title = $attr['file_name'];
					}
					if($attr['_'] == 'documentAttributeAudio') {
						$audio = true;
						$voice = $attr['voice'] ?? false;
						$dur = $attr['duration'] ?? false;
						if($nameset) continue;
						if(isset($attr['title'])) {
							$title = $attr['title'];
							if(isset($attr['performer'])) {
								$title = $attr['performer'].' - '.$title;
							}
							$nameset = true;
						}
					}
				}
			}
			echo '<div>';
			try {
				$img = true;
				$open = true;
				$q = $mini ? 'raudio' : 'rprev';
				$fq = 'rorig';
				$audio = false;
				$smallprev = false;
				switch(strtolower(substr($fext, 1))) {
					case 'webp':
						if(strpos($d['name'], 'sticker_') === 0) {
							$dl = true;
							$open = false;
							$img = true;
							$ie = MP::getIEVersion();
							if(PNG_STICKERS && ($ie == 0 || $ie > 4)) {
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
						$fq = 'rorig';
						$smallprev = $img = true;
						break;
					case 'gif':
						$img = false;
						break;
					case 'tgs':
						break;
					case 'mp3':
						$img = false;
						$smallprev = $audio = true;
						$q = 'raudio';
						break;
					default:
						$img = false;
						break;
				}
				if($voice && defined('CONVERT_VOICE_MESSAGES') && CONVERT_VOICE_MESSAGES) {
					echo '<div class="mw"><a href="voice.php?m='.$m['id'].'&c='.$id.'">'.static::x($lng['voice']).' '.MP::durationstr($dur).'</a><br><audio controls preload="none" src="voice.php?m='.$m['id'].'&c='.$id.'">'.'</div>';
				} elseif($img && $imgs && !$mini) {
					if($open) {
						echo '<div><a href="file.php?m='.$m['id'].'&c='.$id.'&p='.$fq.'"><img src="file.php?m='.$m['id'].'&c='.$id.'&p='.$q.'"></img></a></div>';
					} else {
						echo '<div><img src="file.php?m='.$m['id'].'&c='.$id.'&p='.$q.'"></img></div>';
					}
				} else {
					$url = 'file.php?m='.$m['id'].'&c='.$id;
					$size = $d['size'];
					if($size >= 1024 * 1024) {
						$size = round($size/1024.0/1024.0, 2).' MB';
					} else {
						$size = round($size/1024.0, 1).' KB';
					}
					echo '<div class="mw">';
					if($smallprev) {
						if($thumb && $imgs) {
							echo '<a href="'.$url.'"><img src="file.php?m='.$m['id'].'&c='.$id.'&p=thumb'.$q.'" class="acv"></img></a>';
						}
						echo '<div class="cst"><b><a href="'.$url.'">'.MP::dehtml($title).'</a></b></div>';
						echo '<div>';
						if($dur > 0) {
							echo MP::durationstr($dur);
						} else {
							echo $size;
						}
						echo '</div>';
					} else {
						echo '<a href="'.$url.'">'.MP::dehtml($title).'</a></b><br>';
						if($thumb && $imgs) {
							echo '<a href="'.$url.'"><img src="file.php?m='.$m['id'].'&c='.$id.'&p=thumb'.$q.'"></img></a><br>';
						}
						echo $size;
					}
					echo '</div>';
				}
			} catch (Exception $e) {
				echo $e;
			}
			echo '</div>';
		} elseif(isset($media['webpage'])) {
			echo '<div class="mw">';
			if(isset($media['webpage']['site_name'])) {
				echo '<a href="'.$media['webpage']['url'].'">';
				echo $media['webpage']['site_name'];
				echo '</a>';
			} elseif(isset($media['webpage']['url'])) {
				echo '<a href="'.$media['webpage']['url'].'">';
				echo $media['webpage']['url'];
				echo '</a>';
			}
			if(isset($media['webpage']['title'])) {
				echo '<div class="mwt"><b>'.$media['webpage']['title'].'</b></div>';
			}
			echo '</div>';
		} elseif(isset($media['geo'])) {
			$lat = str_replace(',', '.', strval($media['geo']['lat']));
			$long = str_replace(',', '.', strval($media['geo']['long']));
			$lat = substr($lat, 0, 9) ?? $lat;
			$long = substr($long, 0, 9) ?? $long;
			
			echo '<div class="mw"><b>'.$lng['media_location'].'</b><br><a href="https://maps.google.com/maps?q='.$lat.','.$long.'&ll='.$lat.','.$long.'&z=16">'.$lat.', '.$long.'</a></div>';
		} else {
			echo '<div><i>'.$lng['media_att'].'</i></div>';
		}
	}
	
	static function durationstr($time) {
		$sec = $time % 60;
		if($sec < 10) $sec = '0'.$sec;
		$min = intval($time / 60);
		if($min < 10) $min = '0'.$min;
		return $min.':'.$sec;
	}
	
	static function utf16substr($s, $offset, $length = null) {
		$s = iconv('utf-8', 'utf-16le', $s);
		$s = $length !== null ? substr($s, $offset*2, $length*2) : substr($s, $offset*2);
		return iconv('utf-16le', 'utf-8', $s);
	}
	
	static function wrapRichNestedText($text, $entity, $allEntities) {
		$off = $entity['offset'];
		$len = $entity['length'];
		$entities = [];
		foreach($allEntities as $e) {
			if($e == $entity) continue;
			if($e['offset'] >= $off && $e['offset']+$e['length'] <= $off+$len) {
				$ne = [];
				foreach($e as $k => $v) {
					$ne[$k] = $v;
				}
				$ne['offset'] = $ne['offset'] - $off;
				array_push($entities, $ne);
			}
		}
		if(count($entities) > 0) {
			return static::wrapRichText($text, $entities);
		}
		return static::dehtml($text);
	}
	
	static function wrapRichText($text, $entities) {
		$len = count($entities);
		$html = [];
		$lastOffset = 0;
		$html = '';
		for ($i = 0; $i < $len; $i++) {
			$entity = $entities[$i];
			if($entity['offset'] > $lastOffset) {
				$html .= static::dehtml(static::utf16substr($text, $lastOffset, $entity['offset'] - $lastOffset));
			} elseif($entity['offset'] < $lastOffset) {
				continue;
			}
			$skipEntity = false;
			$entityText = static::utf16substr($text, $entity['offset'], $entity['length']);
			switch($entity['_']) {
			case 'messageEntityUrl':
			case 'messageEntityTextUrl':
				$inner = null;
				if ($entity['_'] == 'messageEntityTextUrl') {
					$url = $entity['url'];
					$url = static::wrapUrl($url, true);
					$inner = static::wrapRichNestedText($entityText, $entity, $entities);
				} else {
					$url = static::wrapUrl($entityText, false);
					$inner = static::dehtml($entityText);
				}
				$html .= '<a href="';
				$html .= static::dehtml($url);
				$html .= '" class="ml" target="_blank" rel="noopener noreferrer">';
				$html .= $inner;
				$html .= '</a>';
				break;
			case 'messageEntityBold':
				$html .= '<b>';
				$html .= static::wrapRichNestedText($entityText, $entity, $entities);
				$html .= '</b>';
				break;
			case 'messageEntityItalic':
				$html .= '<i>';
				$html .= static::wrapRichNestedText($entityText, $entity, $entities);
				$html .= '</i>';
				break;
			case 'messageEntityCode':
				$html .= '<pre>';
				$html .= static::wrapRichNestedText($entityText, $entity, $entities);
				$html .= '</pre>';
				break;
			case 'messageEntityPre':
				$html .= '<pre>';
				$html .= static::wrapRichNestedText($entityText, $entity, $entities);
				$html .= '</pre>';
				break;
			case 'messageEntityUnderline':
				$html .= '<u>';
				$html .= static::wrapRichNestedText($entityText, $entity, $entities);
				$html .= '</u>';
				break;
			case 'messageEntityStrike':
				$html .= '<s>';
				$html .= static::wrapRichNestedText($entityText, $entity, $entities);
				$html .= '</s>';
				break;
			case 'messageEntitySpoiler':
				$html .= '<span>';
				$html .= static::wrapRichNestedText($entityText, $entity, $entities);
				$html .= '</span>';
				break;
			default:
				$skipEntity = true;
			}
			$lastOffset = $entity['offset'] + ($skipEntity ? 0 : $entity['length']);
		}
		$html .= static::dehtml(static::utf16substr($text, $lastOffset, null));
		
		return $html;
	}
	
	static function wrapUrl($url, $unsafe=false) {
		if(strpos($url, 'http') !== 0) {
			$url = 'http://'.$url;
		}
		if(!$unsafe) {
			if(preg_match('/^https?:\/\/t(?:elegram)?\.me\/(.+)/', $url, $tgMeMatch)) {
				$fullPath = $tgMeMatch[1];
				$path = explode('/', $fullPath);
				switch($path[0]) {
				case 'joinchat':
					$url = static::getURL().'chat.php?c='.$path[1];
					break;
				case 'addstickers':
					$url = static::getURL().'addstickers.php?n='.$path[1];
					break;
				default:
					if(count($path) == 2 && strlen($path[1] > 0)) {
						$url = static::getURL().'chat.php?c='.$path[0].'&m='.$path[1];
					} elseif(count($path) == 1) {
						if(strpos($path[0], 'iv?') !== 0) {
							$s = $path[0];
							if(strpos($s, '?start=') !== false) {
								$s = str_replace('?start=', "&start=", $s);
								$s .= '&rnd='.rand(0, 100000);
							} elseif(strpos($s, '?') !== false) {
								$i = strpos($s, '?');
								$s = substr($s, 0, $i).'&'.substr($s, $i+1);
								$s .= '&rnd='.rand(0, 100000);
							}
							$url = static::getURL().'chat.php?c='.$s;
						}
					}
					break;
				}
			}
		}
		return $url;
	}

	static function getURL() {
		$sitepath = "";
		if(($_SERVER["HTTPS"] ?? "") === "on" || ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") == "https" || strpos($_SERVER["HTTP_CF_VISITOR"] ?? "", "https") !== false) {
			$sitepath .= "https";
		} else {
			$sitepath .= "http";
		}
		$sitepath .= "://".$_SERVER["SERVER_NAME"];
		if(isset($_SERVER["PHP_SELF"])) {
			$ss = $_SERVER["PHP_SELF"];
			$ss = substr($ss, 0, strrpos($ss, "/")+1);
			$sitepath .= $ss;
		}
		return $sitepath;
	}
	
	static function getUser() {
		$user = null;
		if(isset($_GET['user']))
			$user = $_GET['user'];
		elseif(isset($_COOKIE['user']))
			$user = $_COOKIE['user'];
		elseif(isset($_SESSION) && isset($_SESSION['user']))
			$user = $_SESSION['user'];
		if(strpos(strval($user), '/') !== false || strpos(strval($user), '.') !== false) {
			$user = null;
		}
		if(empty($user) || strlen($user) < 32 || strlen($user) > 200) {
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
				$ua = $_SERVER['HTTP_USER_AGENT'];
				$b = get_browser($ua, true);
				if($b && isset($b['parent'])) {
					$info = $br = $b['parent'];
					if(isset($b['device_name'])) {
						$d = $b['device_name'];
						if($d !== 'unknown' && strpos($b['device_name'], 'general') !== 0) {
							if(isset($b['device_brand_name']) && $b['device_brand_name'] !== 'unknown') {
								$dbn = $b['device_brand_name'];
								if(!stripos($dbn, 'desktop')) {
									$d = $dbn.' '.$d;
								}
							}
							$info = $br . ' on ' . $d;
						}
					}
					$pl = null;
					if(strpos($ua, 'Opera Mini') !== false) {
						if(strpos($ua, 'J2ME/MIDP') !== false) {
							$pl = 'J2ME';
						} elseif(strpos($ua, 'BlackBerry') !== false) {
							$pl = 'BlackBerry OS';
						} elseif(strpos($ua, 'Android') !== false) {
							$pl = 'Android';
						}
					} elseif(strpos($ua, 'Series60/') !== false) {
						$s60 = substr($ua, strpos($ua, 'Series60/')+9, 3);
						switch($s60) {
						case '3.0':
							$pl = 'Symbian 9.1';
							break;
						case '3.1':
							$pl = 'Symbian 9.2';
							break;
						case '3.2':
							$pl = 'Symbian 9.3';
							break;
						case '5.0':
							$pl = 'Symbian 9.4';
							break;
						case '5.2':
							$pl = 'Symbian^3';
							break;
						case '5.3':
							$pl = 'Symbian Belle';
							break;
						case '5.4':
							$pl = 'Symbian Belle FP1';
							break;
						case '5.5':
							$pl = 'Symbian Belle FP2';
							break;
						default:
							if(strpos($ua, 'Symbian/3') !== false) {
								$pl = 'Symbian^3';
							} else {
								$pl = 'Symbian OS';
							}
						}
					} elseif(isset($b['platform_description'])) {
						$pl = $b['platform_description'];
					} elseif(isset($b['platform'])) {
						$pl = $b['platform'];
					}
					if($info) {
						$app->setDeviceModel($info);
					}
					if($pl) {
						$app->setSystemVersion($pl);
					}
				}
			}
		} catch (Exception) {}
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
		//if($login) {
			$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline', static::getMadelineSettings());
		//} else {
		//	$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline');
		//}
		return $MP;
	}

	static function getSetting($name, $def=null, $write=false) {
		$x = $def;
		if(isset($_GET[$name])) {
			$x = $_GET[$name];
			$write = true;
		} elseif(isset($_COOKIE[$name])) {
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
		} elseif(isset($_COOKIE[$name])) {
			$x = (int)$_COOKIE[$name];
		}
		if($x && $write) {
			static::cookie($name, $x, time() + (86400 * 365));
		}
		return $x;
	}

	static function getIEVersion() {
		if(!static::$iev)
		try {
			$ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
			static::$useragent = $ua;
			if(strpos($ua, 'MSIE ') !== false) {
				$i = strpos($ua, 'MSIE ')+5;
				static::$iev = (int)substr($ua, $i, $i+1);
			}
		} catch (Exception) {}
		return static::$iev;
	}

	static function cookie($n, $v) {
		header('Set-Cookie: '.$n.'='.$v.'; path=/; expires='.date('r', time() + (86400 * 365)), false);
	}

	static function delCookie($n) {
		header('Set-Cookie: '.$n.'=; path=/; expires='.date('r', time() - 86400), false);
	}
	
	static function deleteSessionFile($user) {
		$session = sessionspath.$user.'.madeline';
		if(is_dir($session)) {
			$files = scandir($session);
			foreach($files as $file) {
				if($file == '.' || $file == '..') continue;
				try {
					unlink($session.DIRECTORY_SEPARATOR.$file);
				} catch (Exception) {}
			}
			try {
				rmdir($session);
			} catch (Exception) {}
		} else {
			// old madeline sessions
			try {
				unlink($session.'callback.ipc');
			} catch (Exception) {}
			try {
				unlink($session.'.ipcState');
			} catch (Exception) {}
			try {
				unlink($session.'.ipc');
			} catch (Exception) {}
			try {
				unlink($session.'.lightState.php');
			} catch (Exception) {}
			try {
				unlink($session.'.safe.php');
			} catch (Exception) {}
			try {
				unlink($session);
			} catch (Exception) {}
		}
	}
	
	public static function initLocale() {
		$xlang = $lang = MP::getSetting('lang');
		$lang ??= isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? (strpos(strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), 'ru') !== false ? 'ru' : 'en') : 'ru';
		include 'locale.php';
		MPLocale::init();
		if(!MPLocale::load($lang)) {
			MPLocale::load($lang = 'en');
		}
		if($lang != $xlang) {
			MP::cookie('lang', $lang, time() + (86400 * 365));
		}
		return MPLocale::$lng;
	}

	public static function init() {
		static::$enc = static::getEncoding();
	}
	
	public static function addUsers($users, $chats) {
		foreach($users as $user) {
			static::$users[$user['id']] = $user;
		}
		foreach($chats as $chat) {
			static::$chats[$chat['id']] = $chat;
		}
	}
	
	public static function gc() {
		static::$users = [];
		static::$chats = [];
	}
	
	public static function getAllDialogs($MP, $limit = 0, $folder_id = -1) {
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

	static function getPeerColors($MP) {
		 if(!isset(static::$colors)) {
                        $peercolors = $MP->help->getPeerColors();
                        $theme = MP::getSettingInt('theme');
                        static::$colors = [];
                        foreach($peercolors['colors'] as $color) {
                                if(isset($color['colors'])) {
                 		       static::$colors[$color['color_id']] = substr('000000'.dechex($color[($theme==0?'dark_':'').'colors']['colors'][0]), -6);
                                }
                        }
                }
	}
}
MP::init();
