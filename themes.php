<?php
if(!defined('mp_loaded'))
require_once 'mp.php';
class Themes {
	static $theme = 0;
	static $bg = 0;
	static $fill = 0;
	static $round = 1;
	static $bgsize = 240;
	static $iev;
	
	static function setTheme($theme) {
		switch($theme) {
		case 2:
			$theme = 1;
			break;
		case 3:
			$theme = 0;
			break;
		}
		static::$theme = $theme;
	}
	
	static function setChatTheme($theme) {
		switch($theme) {
		case 2:
			$theme = 1;
			static::$bg = 1;
			break;
		case 3:
			$theme = 0;
			static::$bg = 1;
			break;
		}
		$bgsize = MP::getSettingInt('bgsize', 0);
		switch($bgsize) {
		case 240:
		case 320:
		case 640:
		case 720:
		case 1000:
			static::$bgsize = $bgsize;
			break;
		default:
			static::$bgsize = '';
			break;
		}
		static::$theme = $theme;
	}
	
	static function bodyStart($a = null) {
		if($a) {
			return '<body '.$a.'>'.(static::$iev > 0 ? '<div class="bc">' : '');
		}
		return '<body>'.(static::$iev > 0 ? '<div class="bc">' : '');
	}
	
	static function bodyEnd() {
		return (static::$iev > 0 ? '</div>' : '').'</body>';
	}
	
	static function head() {
		static::$iev = MP::getIEVersion();
		$full = MP::getSettingInt('full', 0) == 1;
		return (MP::$enc == null ? '<meta charset="UTF-8">' : '').
		'<meta name="viewport" content="width=device-width, initial-scale=1">
		<style type="text/css"><!--
		'.(static::$iev > 0 ? '.bc {
			text-align: left;
			width: 420;
			margin-left: auto;
			margin-right: auto;
		}
		' : ''). 'body {
			'.(static::$iev > 0 ? 'text-align: center;' : ($full ? '' : 'max-width: 540px;
			margin-left: auto;
			margin-right: auto;')).'
			font-family: system-ui;
			'.(static::$theme == 0 ?
			'background: #000;
			color: #eee;' : 'color: #111;').'
			'.(static::$bg ?
			'background-attachment: fixed;
			background-image: url(/img/bg'.static::$bgsize.'.png);
			'.(static::$bgsize == 1000 ?
			'background-repeat: repeat;' : 
			'background-size: cover;
			background-repeat: no-repeat;') : '').'
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
		.ct {
			margin-left: 2px;
			overflow: hidden;
			color: '.(static::$theme == 0 ? '#aaa' : '#444').';
		}
		.m {
			margin-left: 2px;
			margin-bottom: 7px;
			width: 99%;
		}
		.mc {
			display: table;
			width: auto;
			overflow: hidden;
			'.(static::$round ?
			'border-radius: 6px;
			padding-top: 4px;
			padding-left: 6px;
			padding-bottom: 4px;
			padding-right: 4px;' :
			'padding-left: 4px;
			padding-top: 2px;
			padding-bottom: 4px;
			padding-right: 4px;
			').'
		}
		.my {
			margin-left: auto; 
			'.(static::$bg || static::$fill ? 'background-color: ' : 'border: 1px solid ').
			(static::$theme == 0 ? '#333' : '#eee').';
		}
		.mo {
			'.(static::$bg || static::$fill ? 'background-color: ' : 'border: 1px solid ').
			(static::$theme == 0 ? '#222' : '#eaeaea').';
		}
		.r, .mw {
			display: block;
			text-align: left;
			border-left: 2px solid '.(static::$theme == 0 ? 'white' : '#168acd').';
			padding-left: 4px;
			margin-bottom: 2px;
			margin-top: 2px;
			white-space: nowrap;
			text-overflow: ellipsis;
			overflow: hidden;
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
		.cl {
			border-spacing: 0;
			border-color: '.(static::$theme == 0 ? '#222' : '#eee').';
			border-collapse: collapse;
			width: 100%;
		}
		.c {
			min-height: 42px;
			margin: 0px;'
			.(static::$bg ? (static::$theme == 0 ?
			'background: #111;' : 'background: #ddd;') : '').'
		}
		.cm {
			color: '.(static::$theme == 0 ? '#ccc' : '#111').';
			display: -webkit-box;
			text-overflow: ellipsis;
			overflow: hidden;
			-webkit-box-orient: vertical;
			-webkit-line-clamp: 2;
			max-height: 2.5em;
			line-height: 1.25em;
		}
		.cava {
			vertical-align: top;
			padding-left: 2px;
			padding-top: 4px;
			padding-bottom: 4px;
			padding-right: 4px;
		}
		.ctext {
			vertical-align: top;
			width: 100%;
		}
		.cbd {
			border-bottom: 1px solid '.(static::$theme == 0 ? '#222' : '#eee').';
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
		input[type="file"] {
			'.(static::$theme == 0 ? 'color: #eee;' : 'color: #111;').';
		}
		.ml {
			color: #37a1de;
		}
		.ch {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			z-index: 1;
			background: '.(static::$theme == 0?'#000':'#fff').';
		}
		.cb {
			position: fixed;
			bottom: 0;
			left: 0;
			width: 100%;
			z-index: 1;
			background: '.(static::$theme == 0?'#000':'#fff').';
		}
		.chc {
			'./*($full ? '' : 'max-width: 540px;
			margin-left: auto;
			margin-right: auto;
			') .*/'padding-top: 2px;
		}
		.chr {
			float: right;
			text-align: right;
		}
		.ri {
			border-radius: 50%;
			height: 36px;
			width: 36px;
		}
		.chn {
			text-overflow: ellipsis;
			overflow: hidden;
			white-space: nowrap;
			vertical-align: top;
		}
		.cin {
			text-overflow: ellipsis;
			overflow: hidden;
			vertical-align: top;
		}
		.cst {
			text-overflow: ellipsis;
			overflow: hidden;
			white-space: nowrap;
		}
		.chava {
			display: inline;
			padding-left: 2px;
			margin-top: 4px;
			padding-right: 4px;
			float: left;
		}
		.mi {
			max-width: 50vw;
		}
		textarea {
			resize: none;
		}
		.t {
			'.($full ?
			'display: inline;
			z-index: 1;
			position: fixed;
			left: 0;
			width: 100%;
			bottom: 0;
			background: #000;
			height: 4em;
		' : '').'}
		.rc {
			width: 100%;
		}
		.btn {
			background-color: grey;
			color: white;
			padding: 1px;
			border: solid 1px white;
			width: 100%;
			display: block;
			text-align: center;
		}
		.btd {
			padding: 2px;
		}
		.cta {
			width: 100%;
			'.(static::$iev > 0 && static::$iev < 5 ? 'height: 48px;' : 'height: 2.7em;').'
		}
		.acv {
			padding-left: 2px;
			margin-top: 4px;
			padding-right: 4px;
			float: left;
			max-height: 36px;
		}
		.mm {
			display: inline;
			margin-right: 4px;
			margin-bottom: 4px;
			margin-top: 4px;
		}
		pre {
			display: inline;
			white-space: pre-wrap;
			word-break: break-all;
		}
		.bth {
			border: 1px solid '.(static::$theme == 0 ? '#444' : '#aaa').';
			padding: 0 2px 0 2px;
			border-radius: 4px;
			background: '.(static::$theme == 0 ? '#222' : '#eee').';
		}
		.ra {
			color: red;
		}
		.hb {
			margin: 2px 0 2px 0;
		}
		.hed {
		}
		--></style>';
	}
}
