<?php
class Utils {
	static function dehtml($s) {
		return str_replace('<', '&lt;', str_replace('>', '&gt;', str_replace('&', '&amp;', $s)));
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
		$p = $MP->getInfo($id);
		if(isset($p['User'])) {
			return $p['User']['first_name'].($full && isset($p['User']['last_name']) ? ' '.$p['User']['last_name'] : '');
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
		return $MP->getSelf()['id'];
	}
}
?>
