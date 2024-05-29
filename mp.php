<?php
if(defined('mp_loaded')) return;
define('mp_loaded', true);

require_once("config.php");
require_once("api_values.php");

define("WINDOWS", stripos(PHP_OS, 'WIN') === 0);

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
		return $a['user_id'] ?? (isset($a['chat_id']) ? $a['chat_id'] : (isset($a['channel_id']) ? ($a['channel_id']) : null));
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
	
	static function isChannel($id) {
		return $id < 0 && $id <= Magic::ZERO_CHANNEL_ID;
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
		} else if(static::$chats !== null) {
			$id = static::getId($id);
			foreach(static::$users as $p) {
				$info = null;
				if($p['id'] == $id) {
					$info = $p;
					break;
				}
			}
			if($info !== null) {
				return static::getNameFromInfo($info, $full);
			}
		}
		return static::getNameFromInfo($MP->getInfo($id), $full);
	}

	static function getNameFromInfo($p, $full = false) {
		return isset($p['User']) ? static::getUserName($p['User'], $full) : ($p['Chat']['title'] ?? $p['title'] ?? static::getUserName($p, $full));
	}
	
	static function getUserName($p, $full = false) {
		$last = isset($p['last_name']) ? trim($p['last_name']) : null;
		return static::removeEmoji(isset($p['first_name']) ? trim($p['first_name']).($full && $last !== null ? ' '.$last : '') : ($last !== null ? $last : 'Deleted Account'));
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
					$txt = static::x($lng['action_chatedittitle']).' '.static::dehtml($a['title']);
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
						$txt = "<a href=\"chat.php?c={$mfid}\" class=\"mn\">".static::dehtml($fn).'</a>';
					} else {
						$txt = static::dehtml($fn);
					}
					if($u == $mfid || $u === null) {
						$txt .= ' '.static::x($lng['action_join']);
					} else {
						$txt .= ' '.static::x($lng['action_add']).' '.static::dehtml(static::getNameFromId($MP, $u));
					}
					break;
				case 'chatdeleteuser':
					$txt = var_export($a, true);
					$u = $a['user_id'] ?? null;
					if($u == $mfid || $u === null) {
						$txt = "<a href=\"chat.php?c={$mfid}\" class=\"mn\">".static::dehtml($fn).'</a>';
						$txt .= ' '.static::x($lng['action_leave']);
					} else {
						$txt = "<a href=\"chat.php?c={$mfid}\" class=\"mn\">".static::dehtml($fn).'</a>';
						$txt .= ' '.static::x($lng['action_deleteuser']).' ';
						$txt .= '<a href="chat.php?c='.$mfid.'" class="mn">'.static::dehtml(static::getNameFromId($MP, $u)).'</a>';
					}
					break;
				case 'chatjoinedbylink':
					$txt = "<a href=\"chat.php?c={$mfid}\" class=\"mn\">".static::dehtml($fn).'</a> '.static::x($lng['action_joinedbylink']);
					break;
				case 'channelcreate':
					$txt = static::x($lng['action_channelcreate']);
					break;
				case 'pinmessage':
					$txt = "<a href=\"chat.php?c={$mfid}\" class=\"mn\">".static::dehtml($fn).'</a> '.static::x($lng['action_pin']);
					break;
				case 'historyclear':
					$txt = static::x($lng['action_historyclear']);
					break;
				case 'chatjoinedbyreqeuset':
					$txt = "<a href=\"chat.php?c={$mfid}\" class=\"mn\">".static::dehtml($fn).'</a> '.static::x($lng['action_joinedbyrequest']);
					break;
				default:
					if(isset($lng["action_{$at}"])) {
						$txt = static::x($lng["action_{$at}"]);
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
	
	static function printMessages($MP, $rm, $id, $pm, $ch, $lng, $imgs, $name=null, $timeoff=0, $chid=false, $unswer=false, $ar=null, $search=false, $old=false, $photosize=0, $showdate=true) {
		$lastdate = date('d.m.y', time()-$timeoff);
		foreach($rm as $m) {
			try {
				$mname1 = null;
				$uid = null;
				if(isset($m['from_id'])) {
					$uid = static::getId($m['from_id']);
					$mname1 = static::getNameFromId($MP, $uid);
				}
				if($mname1 != null && static::utflen($mname1) > 30)
					$mname1 = static::utfsubstr($mname1, 0, 30);
				$mname = null;
				$l = false;
				if($m['out'] && !$ch && !$search) {
					$uid = static::getSelfId($MP);
					$mname = $lng['you'];
				} elseif(($pm || $ch) && $name && !$search) {
					$uid = $id;
					$mname = $name;
				} else {
					$l = true;
					$mname = $mname1 ? $mname1 : $name;
				}
				$color = '';
				if($uid > 0 && isset(static::$users[$uid])) {
					$user = static::$users[$uid];
					if(isset($user['color'])) {
						static::getPeerColors($MP);
						if(isset(static::$colors[$user['color']['color']])) {
							$color = 'style="color: #'. static::$colors[$user['color']['color']] . '"';
						}
					}
				} elseif($uid < 0 && isset(static::$chats[$id])) {
					$chat = static::$chats[$id];
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
						$fwname = static::getNameFromId($MP, $fwid, true);
					}
				}
				$mtime = $m['date']-$timeoff;
				$mdate = date('d.m.y', $mtime);
				if($mdate !== $lastdate && $showdate) {
					echo "<div class=\"ma\">{$mdate}</div>";
					$lastdate = $mdate;
				}
				if($fwname !== null && static::utflen($fwname) > 30)
					$fwname = static::utfsubstr($fwname, 0, 30);
				$href = "msg.php?c={$id}&m={$m['id']}";
				$out = $m['out'] ?? false;
				if($search) {
					$href = "chat.php?c={$id}&m={$m['id']}";
				} else {
					$mparams = '';
					if($out) $mparams .= '&o';
					if($ch) $mparams .= '&ch';
					if($ar !== null) {
						if(!$ch && !$out && $ar['ban_users'] ?? false) $mparams .= '&b';
						if($ar['delete_messages'] ?? false) $mparams .= '&d';
						if($ch && $ar['edit_messages'] ?? false) $mparams .= '&e';
					}
					$href = "msg.php?c={$id}&m={$m['id']}{$mparams}";
				}
				if(!isset($m['action'])) {
					echo "<div class=\"m\" id=\"msg_{$id}_{$m['id']}\">";
					if(!$old) echo "<div class=\"mc".($out && !$search ?' my':' mo')."\">";
					echo "<div class=\"mh\" onclick=\"location.href='{$href}';\">";
					if(!$pm && $uid != null && $l) {
						echo "<b><a href=\"chat.php?c={$uid}\" class=\"mn\" {$color}>".static::dehtml($mname).'</a></b>';
					} else {
						echo "<b class=\"mn\" {$color}>".static::dehtml($mname).'</b>';
					}
					echo ' '.date("H:i", $mtime);
					
					if($m['media_unread']) {
						echo ' •';
					}
					if($search) { // replace "message options" link to "go to message" in history search
						echo " <small><a href=\"{$href}\" class=\"u\">&gt;</a></small>";
					} elseif($unswer) {
						echo " <small><a href=\"{$href}\" class=\"u\">".static::x($lng['msg_options'])."</a></small>";
					}
					echo '</div>';
				} else {
					echo "<div class=\"ma\" id=\"msg_{$id}_{$m['id']}\">";
					if(!$old) echo "<div class=\"mc\">";
				}
				if($fwname != null) {
					echo '<div class="mf">'.static::x($lng['fwd_from']).' <b>'.static::dehtml($fwname).'</b></div>';
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
									$replyname = static::getName($MP, $replyfromid, true);
									if(static::utflen($replyname) > 30)
										$mname = static::utfsubstr($replyname, 0, 30).'...';
									echo '<b class="rn">'.static::dehtml($replyname).'</b>';
								}
							}
							$replytext = '';
							if(isset($m['reply_to']['quote_text'])) {
								$replytext = $m['reply_to']['quote_text'];
							} else {
								if(isset($replymsg['media'])) {
									$replytext = $lng['media_att'].' ';
								}
								if(isset($replymsg['message'])) {
									$replytext .= $replymsg['message'];
								}
							}
							if(static::utflen($replytext) > 0) {
								if(strlen($replytext) > 50)
									$replytext = static::utfsubstr($replytext, 0, 50);
								echo '<div class="rt">';
								echo '<a href="chat.php?c='.$id.'&m='.$replyid.'">';
								echo static::dehtml(str_replace("\n", " ", $replytext));
								echo '</a>';
								echo '</div>';
							}
							echo '</div>';
						}
					}
				}
				if(isset($m['message']) && strlen($m['message']) > 0) {
					$text = $m['message'];
					echo '<div class="mt">';
					if(isset($m['entities']) && count($m['entities']) > 0) {
						echo static::wrapRichText($text, $m['entities']);
					} else {
						echo str_replace("\n", "<br>", static::dehtml($text));
					}
					echo '</div>';
				}
				if(isset($m['media'])) {
					echo static::printMessageMedia($MP, $m, $id, $imgs, $lng, false, $photosize);
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
					echo static::parseMessageAction($m['action'], $mname1, $uid, $name, $lng, true, $MP);
				}
				echo '</div>';
				if(!$old) echo '</div>';
			} catch (Exception $e) {
				echo "<xmp>{$e->getMessage()}\n{$e->getTraceAsString()}</xmp>";
			}
		}
	}
	
	static function printMessageMedia($MP, $m, $id, $imgs, $lng, $mini=false, $ps=0) {
		if($ps <= 0) $ps = 180;
		$media = $m['media'];
		$reason = null;
		if(isset($media['photo'])) {
			if($imgs) {
				if($mini) {
					echo "<a href=\"chat.php?m={$m['id']}&c={$id}\"><img class=\"mi\" src=\"file.php?m={$m['id']}&c={$id}&p=rmin\"></img></a>";
				} else {
					echo "<div><a href=\"file.php?m={$m['id']}&c={$id}&p=rorig\"><img class=\"mi\" src=\"file.php?m={$m['id']}&c={$id}&p=rprev&s={$ps}\"></img></a></div>";
				}
			} else {
				echo "<div><a href=\"file.php?m={$m['id']}&c={$id}&p=rorig\">".static::x($lng['photo'])."</a></div>";
			}
		} elseif(isset($media['document'])) {
			$thumb = isset($media['document']['thumbs']);
			$d = $MP->getDownloadInfo($m);
			$fn = $d['name'];
			$fext = $d['ext'] ?? '';
			$title = $filename = $fn.$fext;
			$nameset = false;
			$voice = false;
			$dur = false;
			if(isset($media['document']['attributes'])) {
				foreach($media['document']['attributes'] as $attr) {
					if($attr['_'] == 'documentAttributeFilename') {
						if($nameset) continue;
						$title = $filename = $attr['file_name'];
					}
					if($attr['_'] == 'documentAttributeAudio') {
						$audio = true;
						$voice = $attr['voice'] ?? false;
						$dur = $attr['duration'] ?? false;
						if($nameset) continue;
						if(isset($attr['title'])) {
							$title = $attr['title'];
							if(isset($attr['performer'])) {
								$title = "{$attr['performer']} - {$title}";
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
				$ie = static::getIEVersion();
				$png = PNG_STICKERS && ($ie == 0 || $ie > 4);
				switch(strtolower(substr($fext, 1))) {
					case 'webp':
						if(strpos($d['name'], 'sticker_') === 0) {
							$open = false;
							$img = true;
							$q = 'rsticker'.($png?'p':'');
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
						$open = false;
						$img = true;
						$q = 'rtgs'.($png?'p':'');
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
					echo "<div class=\"mw\"><a href=\"voice.php?m={$m['id']}&c={$id}\">".static::x($lng['voice']).' '.static::durationstr($dur)."</a><br><audio controls preload=\"none\" src=\"voice.php?m={$m['id']}&c={$id}\"></div>";
				} elseif($img && $imgs && !$mini) {
					if($open) {
						$filename = defined('FILE_REWRITE') && FILE_REWRITE ? "file/{$filename}" : "file.php";
						echo "<div><a href=\"{$filename}?m={$m['id']}&c={$id}&p={$fq}\"><img src=\"file.php?m={$m['id']}&c={$id}&p={$q}&s={$ps}\"></img></a></div>";
					} else {
						echo "<div><img src=\"file.php?m={$m['id']}&c={$id}&p={$q}&s={$ps}\"></img></div>";
					}
				} else {
					$filename = defined('FILE_REWRITE') && FILE_REWRITE ? "file/{$filename}" : "file.php";
					$url = "{$filename}?m={$m['id']}&c={$id}";
					$size = $d['size'];
					if($size >= 1024 * 1024) {
						$size = round($size/1024.0/1024.0, 2).' MB';
					} else {
						$size = round($size/1024.0, 1).' KB';
					}
					echo '<div class="mw">';
					if($smallprev) {
						if($thumb && $imgs) {
							echo "<a href=\"{$url}\"><img src=\"file.php?m={$m['id']}&c={$id}&p=thumb{$q}\" class=\"acv\"></img></a>";
						}
						echo "<div class=\"cst\"><b><a href=\"{$url}\">".static::dehtml($title).'</a></b></div>';
						echo '<div>';
						if($dur > 0) {
							echo static::durationstr($dur);
						} else {
							echo $size.(strlen($fext)>0?' '.static::dehtml(strtoupper(substr($fext,1))):'');
						}
						echo '</div>';
					} else {
						if(static::utflen($title) > 30)
							$title = static::utfsubstr($title, 0, 30).'..';
						echo '<a href="'.$url.'">'.static::dehtml($title).'</a></b><br>';
						if($thumb && $imgs) {
							echo "<a href=\"{$url}\"><img src=\"file.php?m={$m['id']}&c={$id}&p=thumb{$q}&s={$ps}\"></img></a><br>";
						}
						echo $size.(strlen($fext)>0?' '.static::dehtml(strtoupper(substr($fext,1))):'');
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
				echo "<a href=\"{$media['webpage']['url']}\">{$media['webpage']['site_name']}</a>";
			} elseif(isset($media['webpage']['url'])) {
				echo "<a href=\"{$media['webpage']['url']}\">{$media['webpage']['url']}</a>";
			}
			if(isset($media['webpage']['title'])) {
				echo "<div class=\"mwt\"><b>{$media['webpage']['title']}</b></div>";
			}
			echo '</div>';
		} elseif(isset($media['geo'])) {
			$lat = str_replace(',', '.', strval($media['geo']['lat']));
			$long = str_replace(',', '.', strval($media['geo']['long']));
			$lat = substr($lat, 0, 9) ?? $lat;
			$long = substr($long, 0, 9) ?? $long;
			
			echo "<div class=\"mw\"><b>".static::x($lng['media_location'])."</b><br><a href=\"https://maps.google.com/maps?q={$lat},{$long}&ll={$lat},{$long}&z=16\">{$lat}, {$long}</a></div>";
		} else {
			echo '<div><i>'.static::x($lng['media_att']).'</i></div>';
		}
	}
	
	static function durationstr($time) {
		$sec = $time % 60;
		if($sec < 10) $sec = "0{$sec}";
		$min = intval($time / 60);
		if($min < 10) $min = "0{$min}";
		return "{$min}:{$sec}";
	}
	
	static function utflen($str) {
		return mb_strlen($str, 'utf-8');
	}
	
	static function utfsubstr($s, $offset, $length = null) {
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
		for($i = 0; $i < $len; $i++) {
			$entity = $entities[$i];
			if($entity['offset'] > $lastOffset) {
				$html .= static::dehtml(static::utfsubstr($text, $lastOffset, $entity['offset'] - $lastOffset));
			} elseif($entity['offset'] < $lastOffset) {
				continue;
			}
			$skipEntity = false;
			$entityText = static::utfsubstr($text, $entity['offset'], $entity['length']);
			switch($entity['_']) {
			case 'messageEntityUrl':
			case 'messageEntityTextUrl':
				$inner = null;
				if($entity['_'] == 'messageEntityTextUrl') {
					$url = $entity['url'];
					$url = static::wrapUrl($url);
					$inner = static::wrapRichNestedText($entityText, $entity, $entities);
				} else {
					$url = static::wrapUrl($entityText);
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
		$html .= static::dehtml(static::utfsubstr($text, $lastOffset, null));
		
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
		if(isset($_GET['user'])) {
			$user = $_GET['user'];
		} elseif(isset($_COOKIE['user'])) {
			$user = $_COOKIE['user'];
			if(strpos($user, ', ') !== false) {
				$user = substr($user, 0, strpos($user, ', '));
			}
	    } elseif(isset($_SESSION) && isset($_SESSION['user'])) {
			$user = $_SESSION['user'];
		} 
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
		$peer->setCacheAllPeersOnStartup(false);
		$sets->setPeer($peer);
		$db = $sets->getDb();
		$db->setEnableMinDb(false);
		$db->setEnableUsernameDb(true);
		$db->setEnableFullPeerDb(false);
		$db->setEnablePeerInfoDb(true);
		$c = $sets->getConnection();
		$c->setTimeout(10);
		return $sets;
	}
	
	static function getMadelineAPI($user, $settings = false) {
		require_once 'vendor/autoload.php';
		if($settings) {
			$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline', static::getMadelineSettings());
		} else {
			$MP = new \danog\MadelineProto\API(sessionspath.$user.'.madeline');
		}
		return $MP;
	}

	static function getSetting($name, $def=null, $write=false) {
		static::startSession();
		$x = $def;
		if(isset($_GET[$name])) {
			$x = $_GET[$name];
			$write = true;
		} elseif(isset($_SESSION[$name])) {
			$x = $_SESSION[$name];
		} elseif(isset($_COOKIE[$name])) {
			$x = $_COOKIE[$name];
			if(strpos($x, ', ') !== false) {
				$x = substr($x, 0, strpos($x, ', '));
			}
		}
		if($x && $write) {
			$_SESSION[$name] = $x;
			//static::cookie($name, $x, time() + (86400 * 365));
		}
		return $x;
	}

	static function getSettingInt($name, $def=0, $write=false) {
		static::startSession();
		$x = $def;
		if(isset($_GET[$name])) {
			$x = (int) $_GET[$name];
		} elseif(isset($_SESSION[$name])) {
			$x = (int) $_SESSION[$name];
		} elseif(isset($_COOKIE[$name])) {
			$x = $_COOKIE[$name];
			if(strpos($x, ', ') !== false) {
				$x = substr($x, 0, strpos($x, ', '));
			}
			$x = (int)$x;
		}
		if($x && $write) {
			$_SESSION[$name] = $x;
			//static::cookie($name, $x, time() + (86400 * 365));
		}
		return $x;
	}
	
	static function startSession() {
		if(defined('session_started')) return;
		ini_set('session.cookie_lifetime', 365 * 60 * 60 * 24);
		ini_set('session.gc_maxlifetime', 365 * 60 * 60 * 24);
		
		session_start();
		define('session_started', true);
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

	static function cookie($n, $v, $e = null) {
		if($e === null) $e = time() + (86400 * 365);
		$e = date('D, d M Y H:i:s \G\M\T', $e);
		header("Set-Cookie: {$n}={$v}; expires={$e}; path=/", false);
	}

	static function delCookie($n) {
		static::cookie($n, '', time() - 86400);
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
		$xlang = $lang = static::getSetting('lang');
		$lang ??= isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? (strpos(strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), 'ru') !== false ? 'ru' : 'en') : 'ru';
		include 'locale.php';
		MPLocale::init();
		if(!MPLocale::load($lang)) {
			MPLocale::load($lang = 'en');
		}
		if($lang != $xlang) {
			static::cookie('lang', $lang, time() + (86400 * 365));
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
		if(isset(static::$colors)) return;
		$peercolors = $MP->help->getPeerColors();
		$theme = static::getSettingInt('theme');
		static::$colors = [];
		foreach($peercolors['colors'] as $color) {
			if(!isset($color['colors'])) continue;
			static::$colors[$color['color_id']] = substr('000000'.dechex($color[($theme==0?'dark_':'').'colors']['colors'][0]), -6);
		}
	}
	
	// https://stackoverflow.com/questions/61481567/remove-emojis-from-string
	static function removeEmoji($text) {
		if($text === null) return null;
		return preg_replace('/\x{1F3F4}\x{E0067}\x{E0062}(?:\x{E0077}\x{E006C}\x{E0073}|\x{E0073}\x{E0063}\x{E0074}|\x{E0065}\x{E006E}\x{E0067})\x{E007F}|(?:\x{1F9D1}\x{1F3FF}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F9D1}|\x{1F469}\x{1F3FF}\x{200D}\x{1F91D}\x{200D}[\x{1F468}\x{1F469}]|\x{1FAF1}\x{1F3FF}\x{200D}\x{1FAF2})[\x{1F3FB}-\x{1F3FE}]|(?:\x{1F9D1}\x{1F3FE}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F9D1}|\x{1F469}\x{1F3FE}\x{200D}\x{1F91D}\x{200D}[\x{1F468}\x{1F469}]|\x{1FAF1}\x{1F3FE}\x{200D}\x{1FAF2})[\x{1F3FB}-\x{1F3FD}\x{1F3FF}]|(?:\x{1F9D1}\x{1F3FD}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F9D1}|\x{1F469}\x{1F3FD}\x{200D}\x{1F91D}\x{200D}[\x{1F468}\x{1F469}]|\x{1FAF1}\x{1F3FD}\x{200D}\x{1FAF2})[\x{1F3FB}\x{1F3FC}\x{1F3FE}\x{1F3FF}]|(?:\x{1F9D1}\x{1F3FC}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F9D1}|\x{1F469}\x{1F3FC}\x{200D}\x{1F91D}\x{200D}[\x{1F468}\x{1F469}]|\x{1FAF1}\x{1F3FC}\x{200D}\x{1FAF2})[\x{1F3FB}\x{1F3FD}-\x{1F3FF}]|(?:\x{1F9D1}\x{1F3FB}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F9D1}|\x{1F469}\x{1F3FB}\x{200D}\x{1F91D}\x{200D}[\x{1F468}\x{1F469}]|\x{1FAF1}\x{1F3FB}\x{200D}\x{1FAF2})[\x{1F3FC}-\x{1F3FF}]|\x{1F468}(?:\x{1F3FB}(?:\x{200D}(?:\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FF}]|\x{1F468}[\x{1F3FB}-\x{1F3FF}])|\x{200D}(?:\x{1F48B}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FF}]|\x{1F468}[\x{1F3FB}-\x{1F3FF}]))|\x{1F91D}\x{200D}\x{1F468}[\x{1F3FC}-\x{1F3FF}]|[\x{2695}\x{2696}\x{2708}]\x{FE0F}|[\x{2695}\x{2696}\x{2708}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]))?|[\x{1F3FC}-\x{1F3FF}]\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FF}]|\x{1F468}[\x{1F3FB}-\x{1F3FF}])|\x{200D}(?:\x{1F48B}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FF}]|\x{1F468}[\x{1F3FB}-\x{1F3FF}]))|\x{200D}(?:\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F468}|[\x{1F468}\x{1F469}]\x{200D}(?:\x{1F466}\x{200D}\x{1F466}|\x{1F467}\x{200D}[\x{1F466}\x{1F467}])|\x{1F466}\x{200D}\x{1F466}|\x{1F467}\x{200D}[\x{1F466}\x{1F467}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FF}\x{200D}(?:\x{1F91D}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FE}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FE}\x{200D}(?:\x{1F91D}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FD}\x{1F3FF}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FD}\x{200D}(?:\x{1F91D}\x{200D}\x{1F468}[\x{1F3FB}\x{1F3FC}\x{1F3FE}\x{1F3FF}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FC}\x{200D}(?:\x{1F91D}\x{200D}\x{1F468}[\x{1F3FB}\x{1F3FD}-\x{1F3FF}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|(?:\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{200D}[\x{2695}\x{2696}\x{2708}])\x{FE0F}|\x{200D}(?:[\x{1F468}\x{1F469}]\x{200D}[\x{1F466}\x{1F467}]|[\x{1F466}\x{1F467}])|\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FF}|\x{1F3FE}|\x{1F3FD}|\x{1F3FC}|\x{200D}[\x{2695}\x{2696}\x{2708}])?|(?:\x{1F469}(?:\x{1F3FB}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}])|\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}]))|[\x{1F3FC}-\x{1F3FF}]\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}])|\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}])))|\x{1F9D1}[\x{1F3FB}-\x{1F3FF}]\x{200D}\x{1F91D}\x{200D}\x{1F9D1})[\x{1F3FB}-\x{1F3FF}]|\x{1F469}\x{200D}\x{1F469}\x{200D}(?:\x{1F466}\x{200D}\x{1F466}|\x{1F467}\x{200D}[\x{1F466}\x{1F467}])|\x{1F469}(?:\x{200D}(?:\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}])|\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}]))|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FF}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FE}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FD}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FC}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FB}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F9D1}(?:\x{200D}(?:\x{1F91D}\x{200D}\x{1F9D1}|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FF}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FE}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FD}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FC}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FB}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F469}\x{200D}\x{1F466}\x{200D}\x{1F466}|\x{1F469}\x{200D}\x{1F469}\x{200D}[\x{1F466}\x{1F467}]|\x{1F469}\x{200D}\x{1F467}\x{200D}[\x{1F466}\x{1F467}]|(?:\x{1F441}\x{FE0F}?\x{200D}\x{1F5E8}|\x{1F9D1}(?:\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FB}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{200D}[\x{2695}\x{2696}\x{2708}])|\x{1F469}(?:\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FB}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{200D}[\x{2695}\x{2696}\x{2708}])|\x{1F636}\x{200D}\x{1F32B}|\x{1F3F3}\x{FE0F}?\x{200D}\x{26A7}|\x{1F43B}\x{200D}\x{2744}|(?:[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93D}\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}][\x{1F3FB}-\x{1F3FF}]|[\x{1F46F}\x{1F9DE}\x{1F9DF}])\x{200D}[\x{2640}\x{2642}]|[\x{26F9}\x{1F3CB}\x{1F3CC}\x{1F575}](?:[\x{FE0F}\x{1F3FB}-\x{1F3FF}]\x{200D}[\x{2640}\x{2642}]|\x{200D}[\x{2640}\x{2642}])|\x{1F3F4}\x{200D}\x{2620}|[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93C}-\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}]\x{200D}[\x{2640}\x{2642}]|[\xA9\xAE\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}\x{21AA}\x{231A}\x{231B}\x{2328}\x{23CF}\x{23ED}-\x{23EF}\x{23F1}\x{23F2}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}\x{25AB}\x{25B6}\x{25C0}\x{25FB}\x{25FC}\x{25FE}\x{2600}-\x{2604}\x{260E}\x{2611}\x{2614}\x{2615}\x{2618}\x{2620}\x{2622}\x{2623}\x{2626}\x{262A}\x{262E}\x{262F}\x{2638}-\x{263A}\x{2640}\x{2642}\x{2648}-\x{2653}\x{265F}\x{2660}\x{2663}\x{2665}\x{2666}\x{2668}\x{267B}\x{267E}\x{267F}\x{2692}\x{2694}-\x{2697}\x{2699}\x{269B}\x{269C}\x{26A0}\x{26A7}\x{26AA}\x{26B0}\x{26B1}\x{26BD}\x{26BE}\x{26C4}\x{26C8}\x{26CF}\x{26D1}\x{26D3}\x{26E9}\x{26F0}-\x{26F5}\x{26F7}\x{26F8}\x{26FA}\x{2702}\x{2708}\x{2709}\x{270F}\x{2712}\x{2714}\x{2716}\x{271D}\x{2721}\x{2733}\x{2734}\x{2744}\x{2747}\x{2763}\x{27A1}\x{2934}\x{2935}\x{2B05}-\x{2B07}\x{2B1B}\x{2B1C}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F004}\x{1F170}\x{1F171}\x{1F17E}\x{1F17F}\x{1F202}\x{1F237}\x{1F321}\x{1F324}-\x{1F32C}\x{1F336}\x{1F37D}\x{1F396}\x{1F397}\x{1F399}-\x{1F39B}\x{1F39E}\x{1F39F}\x{1F3CD}\x{1F3CE}\x{1F3D4}-\x{1F3DF}\x{1F3F5}\x{1F3F7}\x{1F43F}\x{1F4FD}\x{1F549}\x{1F54A}\x{1F56F}\x{1F570}\x{1F573}\x{1F576}-\x{1F579}\x{1F587}\x{1F58A}-\x{1F58D}\x{1F5A5}\x{1F5A8}\x{1F5B1}\x{1F5B2}\x{1F5BC}\x{1F5C2}-\x{1F5C4}\x{1F5D1}-\x{1F5D3}\x{1F5DC}-\x{1F5DE}\x{1F5E1}\x{1F5E3}\x{1F5E8}\x{1F5EF}\x{1F5F3}\x{1F5FA}\x{1F6CB}\x{1F6CD}-\x{1F6CF}\x{1F6E0}-\x{1F6E5}\x{1F6E9}\x{1F6F0}\x{1F6F3}])\x{FE0F}|\x{1F441}\x{FE0F}?\x{200D}\x{1F5E8}|\x{1F9D1}(?:\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FB}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{200D}[\x{2695}\x{2696}\x{2708}])|\x{1F469}(?:\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FB}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{200D}[\x{2695}\x{2696}\x{2708}])|\x{1F3F3}\x{FE0F}?\x{200D}\x{1F308}|\x{1F469}\x{200D}\x{1F467}|\x{1F469}\x{200D}\x{1F466}|\x{1F636}\x{200D}\x{1F32B}|\x{1F3F3}\x{FE0F}?\x{200D}\x{26A7}|\x{1F635}\x{200D}\x{1F4AB}|\x{1F62E}\x{200D}\x{1F4A8}|\x{1F415}\x{200D}\x{1F9BA}|\x{1FAF1}(?:\x{1F3FF}|\x{1F3FE}|\x{1F3FD}|\x{1F3FC}|\x{1F3FB})?|\x{1F9D1}(?:\x{1F3FF}|\x{1F3FE}|\x{1F3FD}|\x{1F3FC}|\x{1F3FB})?|\x{1F469}(?:\x{1F3FF}|\x{1F3FE}|\x{1F3FD}|\x{1F3FC}|\x{1F3FB})?|\x{1F43B}\x{200D}\x{2744}|(?:[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93D}\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}][\x{1F3FB}-\x{1F3FF}]|[\x{1F46F}\x{1F9DE}\x{1F9DF}])\x{200D}[\x{2640}\x{2642}]|[\x{26F9}\x{1F3CB}\x{1F3CC}\x{1F575}](?:[\x{FE0F}\x{1F3FB}-\x{1F3FF}]\x{200D}[\x{2640}\x{2642}]|\x{200D}[\x{2640}\x{2642}])|\x{1F3F4}\x{200D}\x{2620}|\x{1F1FD}\x{1F1F0}|\x{1F1F6}\x{1F1E6}|\x{1F1F4}\x{1F1F2}|\x{1F408}\x{200D}\x{2B1B}|\x{2764}(?:\x{FE0F}\x{200D}[\x{1F525}\x{1FA79}]|\x{200D}[\x{1F525}\x{1FA79}])|\x{1F441}\x{FE0F}?|\x{1F3F3}\x{FE0F}?|[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93C}-\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}]\x{200D}[\x{2640}\x{2642}]|\x{1F1FF}[\x{1F1E6}\x{1F1F2}\x{1F1FC}]|\x{1F1FE}[\x{1F1EA}\x{1F1F9}]|\x{1F1FC}[\x{1F1EB}\x{1F1F8}]|\x{1F1FB}[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1EE}\x{1F1F3}\x{1F1FA}]|\x{1F1FA}[\x{1F1E6}\x{1F1EC}\x{1F1F2}\x{1F1F3}\x{1F1F8}\x{1F1FE}\x{1F1FF}]|\x{1F1F9}[\x{1F1E6}\x{1F1E8}\x{1F1E9}\x{1F1EB}-\x{1F1ED}\x{1F1EF}-\x{1F1F4}\x{1F1F7}\x{1F1F9}\x{1F1FB}\x{1F1FC}\x{1F1FF}]|\x{1F1F8}[\x{1F1E6}-\x{1F1EA}\x{1F1EC}-\x{1F1F4}\x{1F1F7}-\x{1F1F9}\x{1F1FB}\x{1F1FD}-\x{1F1FF}]|\x{1F1F7}[\x{1F1EA}\x{1F1F4}\x{1F1F8}\x{1F1FA}\x{1F1FC}]|\x{1F1F5}[\x{1F1E6}\x{1F1EA}-\x{1F1ED}\x{1F1F0}-\x{1F1F3}\x{1F1F7}-\x{1F1F9}\x{1F1FC}\x{1F1FE}]|\x{1F1F3}[\x{1F1E6}\x{1F1E8}\x{1F1EA}-\x{1F1EC}\x{1F1EE}\x{1F1F1}\x{1F1F4}\x{1F1F5}\x{1F1F7}\x{1F1FA}\x{1F1FF}]|\x{1F1F2}[\x{1F1E6}\x{1F1E8}-\x{1F1ED}\x{1F1F0}-\x{1F1FF}]|\x{1F1F1}[\x{1F1E6}-\x{1F1E8}\x{1F1EE}\x{1F1F0}\x{1F1F7}-\x{1F1FB}\x{1F1FE}]|\x{1F1F0}[\x{1F1EA}\x{1F1EC}-\x{1F1EE}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F7}\x{1F1FC}\x{1F1FE}\x{1F1FF}]|\x{1F1EF}[\x{1F1EA}\x{1F1F2}\x{1F1F4}\x{1F1F5}]|\x{1F1EE}[\x{1F1E8}-\x{1F1EA}\x{1F1F1}-\x{1F1F4}\x{1F1F6}-\x{1F1F9}]|\x{1F1ED}[\x{1F1F0}\x{1F1F2}\x{1F1F3}\x{1F1F7}\x{1F1F9}\x{1F1FA}]|\x{1F1EC}[\x{1F1E6}\x{1F1E7}\x{1F1E9}-\x{1F1EE}\x{1F1F1}-\x{1F1F3}\x{1F1F5}-\x{1F1FA}\x{1F1FC}\x{1F1FE}]|\x{1F1EB}[\x{1F1EE}-\x{1F1F0}\x{1F1F2}\x{1F1F4}\x{1F1F7}]|\x{1F1EA}[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1ED}\x{1F1F7}-\x{1F1FA}]|\x{1F1E9}[\x{1F1EA}\x{1F1EC}\x{1F1EF}\x{1F1F0}\x{1F1F2}\x{1F1F4}\x{1F1FF}]|\x{1F1E8}[\x{1F1E6}\x{1F1E8}\x{1F1E9}\x{1F1EB}-\x{1F1EE}\x{1F1F0}-\x{1F1F5}\x{1F1F7}\x{1F1FA}-\x{1F1FF}]|\x{1F1E7}[\x{1F1E6}\x{1F1E7}\x{1F1E9}-\x{1F1EF}\x{1F1F1}-\x{1F1F4}\x{1F1F6}-\x{1F1F9}\x{1F1FB}\x{1F1FC}\x{1F1FE}\x{1F1FF}]|\x{1F1E6}[\x{1F1E8}-\x{1F1EC}\x{1F1EE}\x{1F1F1}\x{1F1F2}\x{1F1F4}\x{1F1F6}-\x{1F1FA}\x{1F1FC}\x{1F1FD}\x{1F1FF}]|[#\*0-9]\x{FE0F}?\x{20E3}|\x{1F93C}[\x{1F3FB}-\x{1F3FF}]|\x{2764}\x{FE0F}?|[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93D}\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}][\x{1F3FB}-\x{1F3FF}]|[\x{26F9}\x{1F3CB}\x{1F3CC}\x{1F575}][\x{FE0F}\x{1F3FB}-\x{1F3FF}]?|\x{1F3F4}|[\x{270A}\x{270B}\x{1F385}\x{1F3C2}\x{1F3C7}\x{1F442}\x{1F443}\x{1F446}-\x{1F450}\x{1F466}\x{1F467}\x{1F46B}-\x{1F46D}\x{1F472}\x{1F474}-\x{1F476}\x{1F478}\x{1F47C}\x{1F483}\x{1F485}\x{1F48F}\x{1F491}\x{1F4AA}\x{1F57A}\x{1F595}\x{1F596}\x{1F64C}\x{1F64F}\x{1F6C0}\x{1F6CC}\x{1F90C}\x{1F90F}\x{1F918}-\x{1F91F}\x{1F930}-\x{1F934}\x{1F936}\x{1F977}\x{1F9B5}\x{1F9B6}\x{1F9BB}\x{1F9D2}\x{1F9D3}\x{1F9D5}\x{1FAC3}-\x{1FAC5}\x{1FAF0}\x{1FAF2}-\x{1FAF6}][\x{1F3FB}-\x{1F3FF}]|[\x{261D}\x{270C}\x{270D}\x{1F574}\x{1F590}][\x{FE0F}\x{1F3FB}-\x{1F3FF}]|[\x{261D}\x{270A}-\x{270D}\x{1F385}\x{1F3C2}\x{1F3C7}\x{1F408}\x{1F415}\x{1F43B}\x{1F442}\x{1F443}\x{1F446}-\x{1F450}\x{1F466}\x{1F467}\x{1F46B}-\x{1F46D}\x{1F472}\x{1F474}-\x{1F476}\x{1F478}\x{1F47C}\x{1F483}\x{1F485}\x{1F48F}\x{1F491}\x{1F4AA}\x{1F574}\x{1F57A}\x{1F590}\x{1F595}\x{1F596}\x{1F62E}\x{1F635}\x{1F636}\x{1F64C}\x{1F64F}\x{1F6C0}\x{1F6CC}\x{1F90C}\x{1F90F}\x{1F918}-\x{1F91F}\x{1F930}-\x{1F934}\x{1F936}\x{1F93C}\x{1F977}\x{1F9B5}\x{1F9B6}\x{1F9BB}\x{1F9D2}\x{1F9D3}\x{1F9D5}\x{1FAC3}-\x{1FAC5}\x{1FAF0}\x{1FAF2}-\x{1FAF6}]|[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93D}\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}]|[\x{1F46F}\x{1F9DE}\x{1F9DF}]|[\xA9\xAE\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}\x{21AA}\x{231A}\x{231B}\x{2328}\x{23CF}\x{23ED}-\x{23EF}\x{23F1}\x{23F2}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}\x{25AB}\x{25B6}\x{25C0}\x{25FB}\x{25FC}\x{25FE}\x{2600}-\x{2604}\x{260E}\x{2611}\x{2614}\x{2615}\x{2618}\x{2620}\x{2622}\x{2623}\x{2626}\x{262A}\x{262E}\x{262F}\x{2638}-\x{263A}\x{2640}\x{2642}\x{2648}-\x{2653}\x{265F}\x{2660}\x{2663}\x{2665}\x{2666}\x{2668}\x{267B}\x{267E}\x{267F}\x{2692}\x{2694}-\x{2697}\x{2699}\x{269B}\x{269C}\x{26A0}\x{26A7}\x{26AA}\x{26B0}\x{26B1}\x{26BD}\x{26BE}\x{26C4}\x{26C8}\x{26CF}\x{26D1}\x{26D3}\x{26E9}\x{26F0}-\x{26F5}\x{26F7}\x{26F8}\x{26FA}\x{2702}\x{2708}\x{2709}\x{270F}\x{2712}\x{2714}\x{2716}\x{271D}\x{2721}\x{2733}\x{2734}\x{2744}\x{2747}\x{2763}\x{27A1}\x{2934}\x{2935}\x{2B05}-\x{2B07}\x{2B1B}\x{2B1C}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F004}\x{1F170}\x{1F171}\x{1F17E}\x{1F17F}\x{1F202}\x{1F237}\x{1F321}\x{1F324}-\x{1F32C}\x{1F336}\x{1F37D}\x{1F396}\x{1F397}\x{1F399}-\x{1F39B}\x{1F39E}\x{1F39F}\x{1F3CD}\x{1F3CE}\x{1F3D4}-\x{1F3DF}\x{1F3F5}\x{1F3F7}\x{1F43F}\x{1F4FD}\x{1F549}\x{1F54A}\x{1F56F}\x{1F570}\x{1F573}\x{1F576}-\x{1F579}\x{1F587}\x{1F58A}-\x{1F58D}\x{1F5A5}\x{1F5A8}\x{1F5B1}\x{1F5B2}\x{1F5BC}\x{1F5C2}-\x{1F5C4}\x{1F5D1}-\x{1F5D3}\x{1F5DC}-\x{1F5DE}\x{1F5E1}\x{1F5E3}\x{1F5E8}\x{1F5EF}\x{1F5F3}\x{1F5FA}\x{1F6CB}\x{1F6CD}-\x{1F6CF}\x{1F6E0}-\x{1F6E5}\x{1F6E9}\x{1F6F0}\x{1F6F3}]|[\x{23E9}-\x{23EC}\x{23F0}\x{23F3}\x{25FD}\x{2693}\x{26A1}\x{26AB}\x{26C5}\x{26CE}\x{26D4}\x{26EA}\x{26FD}\x{2705}\x{2728}\x{274C}\x{274E}\x{2753}-\x{2755}\x{2757}\x{2795}-\x{2797}\x{27B0}\x{27BF}\x{2B50}\x{1F0CF}\x{1F18E}\x{1F191}-\x{1F19A}\x{1F201}\x{1F21A}\x{1F22F}\x{1F232}-\x{1F236}\x{1F238}-\x{1F23A}\x{1F250}\x{1F251}\x{1F300}-\x{1F320}\x{1F32D}-\x{1F335}\x{1F337}-\x{1F37C}\x{1F37E}-\x{1F384}\x{1F386}-\x{1F393}\x{1F3A0}-\x{1F3C1}\x{1F3C5}\x{1F3C6}\x{1F3C8}\x{1F3C9}\x{1F3CF}-\x{1F3D3}\x{1F3E0}-\x{1F3F0}\x{1F3F8}-\x{1F407}\x{1F409}-\x{1F414}\x{1F416}-\x{1F43A}\x{1F43C}-\x{1F43E}\x{1F440}\x{1F444}\x{1F445}\x{1F451}-\x{1F465}\x{1F46A}\x{1F479}-\x{1F47B}\x{1F47D}-\x{1F480}\x{1F484}\x{1F488}-\x{1F48E}\x{1F490}\x{1F492}-\x{1F4A9}\x{1F4AB}-\x{1F4FC}\x{1F4FF}-\x{1F53D}\x{1F54B}-\x{1F54E}\x{1F550}-\x{1F567}\x{1F5A4}\x{1F5FB}-\x{1F62D}\x{1F62F}-\x{1F634}\x{1F637}-\x{1F644}\x{1F648}-\x{1F64A}\x{1F680}-\x{1F6A2}\x{1F6A4}-\x{1F6B3}\x{1F6B7}-\x{1F6BF}\x{1F6C1}-\x{1F6C5}\x{1F6D0}-\x{1F6D2}\x{1F6D5}-\x{1F6D7}\x{1F6DD}-\x{1F6DF}\x{1F6EB}\x{1F6EC}\x{1F6F4}-\x{1F6FC}\x{1F7E0}-\x{1F7EB}\x{1F7F0}\x{1F90D}\x{1F90E}\x{1F910}-\x{1F917}\x{1F920}-\x{1F925}\x{1F927}-\x{1F92F}\x{1F93A}\x{1F93F}-\x{1F945}\x{1F947}-\x{1F976}\x{1F978}-\x{1F9B4}\x{1F9B7}\x{1F9BA}\x{1F9BC}-\x{1F9CC}\x{1F9D0}\x{1F9E0}-\x{1F9FF}\x{1FA70}-\x{1FA74}\x{1FA78}-\x{1FA7C}\x{1FA80}-\x{1FA86}\x{1FA90}-\x{1FAAC}\x{1FAB0}-\x{1FABA}\x{1FAC0}-\x{1FAC2}\x{1FAD0}-\x{1FAD9}\x{1FAE0}-\x{1FAE7}]/u', '', $text);
	}
}
MP::init();
