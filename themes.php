<?php
if(!defined('mp_loaded'))
require_once 'mp.php';
class Themes {
	static $theme = 0;
	static $iev;
	
	static function setTheme($theme) {
		static::$theme = $theme;
	}
	static function bodyStart($a = null) {
		if($a) {
			return '<body '.$a.'>';
		}
		return '<body>'.(static::$iev > 0 ? '<div class="c">' : '');
	}
	
	static function bodyEnd() {
		return (static::$iev > 0 ? '</div>' : '').'</body>';
	}
	
	static function head() {
		static::$iev = MP::getIEVersion();
		return (MP::$enc == null ? '<meta charset="UTF-8">' : '').
		'<meta name="viewport" content="width=device-width, initial-scale=1">
		<style type="text/css"><!--
		'.(static::$iev > 0 ? '.c {
			text-align: left;
			width: 420;
			margin-left: auto;
			margin-right: auto;
		}
		' : ''). 'body {
			'.(static::$iev > 0 ? 'text-align: center;' : 'max-width: 500px;
			margin-left: auto;
			margin-right: auto;').'
			font-family: system-ui;
			'.(static::$theme == 0 ?
			'background: #000;
			color: #eee;' : 'color: #111;').'
		}
		a {
			'.(static::$theme == 0 ?
			'color: #eee;' : 'color: #111;').'
			text-decoration: none;
		}
		a:hover {
			text-decoration: underline;
		}
		input[type=text], select, textarea {
			'.(static::$theme == 0 ? 'background-color: black;
			color: #eee;
			border-color: #eee;
			' : '').'border-style: solid;
		}
		.cm {
			margin-left: 2px;
			overflow: hidden;
			color: '.(static::$theme == 0 ? '#ccc' : '#111').';
		}
		.ct {
			margin-left: 2px;
			overflow: hidden;
			color: '.(static::$theme == 0 ? '#aaa' : '#444').';
		}
		.m {
			margin-left: 2px;
			margin-bottom: 7px;
		}
		.r, .mw {
			border-left: 2px solid '.(static::$theme == 0 ? 'white' : '#168acd').';
			padding-left: 4px;
			margin-bottom: 2px;
		}
		.rn, .mwt {
			'.(static::$theme == 0 ? '' : 'color: #37a1de;').
			'overflow: hidden;
			max-width: 200px;
			white-space: nowrap;
			text-overflow: ellipsis;
		}
		.rt {
			overflow: hidden;
			max-width: 300px;
			white-space: nowrap;
			text-overflow: ellipsis;
		}
		.c0 {
			background-color: '.(static::$theme == 0 ? '#222' : '#d7d7d7').';
		}
		'.(static::$theme == 0 ? '' : '.ml, .mf, .mn {
			color: #168acd;
		}').
		'.ma {
			text-align: center;
			margin-bottom: 10px;
		}
		.cma {
			color: #168acd;
		}
		.u {
			color: '.(static::$theme == 0 ? 'darkgrey' : 'grey').';
		}
		.in {
			display: inline;
		}
		.inr {
			display: inline;
			float: right;
		}
		.unr {
			color: '.(static::$theme == 0 ? '#f77' : '#700').';
		}
		--></style>';
	}
}