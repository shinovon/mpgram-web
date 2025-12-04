<?php
/*
Copyright (c) 2022-2025 Arman Jussupgaliyev
*/
if (!defined('mp_loaded'))
require_once 'mp.php';
class Themes {
    static $theme = 0;
    static $bg = 0;
    static $fillMsg = 0;
    static $round = 1;
    static $bgsize = 240;
    static $iev;
    static $colors = [];
    static $fillChats = 0;
    static $chat;
    
    static function loadColors($theme) {
        $file = './colors/colors_'.$theme.'.json';
        if (!file_exists($file)) {
            return false;
        }
        $file = file_get_contents($file);
        if (!$file) {
            return false;
        }
        $json = json_decode($file, true);
        if (!$json) {
            return false;
        }
        foreach ($json as $k => $v) {
            static::$colors[$k] = $v;
        }
        static::$fillMsg = static::$fillMsg || ($json['fill_messages'] ?? 0);
        static::$fillChats = static::$fillChats || ($json['fill_chats'] ?? 0);
        static::$bg = static::$bg || ($json['force_background_image'] ?? 0);
        return true;
    }
    
    static function color($d) {
        if (static::$theme == 4) {
            $a = dechex(rand(0,0xfff));
            while (strlen($a) < 3) $a = '0'.$a;
            return "#{$a}";
        }
        if (strpos($d, '!') === 0) {
            return static::$colors[substr($d, 1)];
        }
        return $d;
    }
    
    static function setTheme($theme, $chat=false) {
        switch($theme) {
        case 2:
            $theme = 1;
            static::$bg = $chat;
            break;
        case 3:
            $theme = 0;
            static::$bg = $chat;
            break;
        case 4:
            $theme = 4;
            static::$fillMsg = 1;
            break;
        case 5:
            $theme = 1;
            static::$bg = 1;
            break;
        case 7:
            $theme = 0;
            static::$fillMsg = 1;
            static::$fillChats = 1;
            break;
        case 8:
            $theme = 1;
            static::$fillMsg = 1;
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
        static::$chat = $chat;
        static::$theme = $theme;
        if ($theme != 0) static::loadColors(0);
        static::loadColors($theme);
    }
    
    static function setChatTheme($theme) {
        static::setTheme($theme, true);
    }
    
    static function bodyStart($a = null) {
        if ($a) {
            return '<body '.$a.'>'.(static::$iev > 0 ? '<div class="bc">' : '');
        }
        return '<body>'.(static::$iev > 0 ? '<div class="bc">' : '');
    }
    
    static function bodyEnd() {
        return (static::$iev > 0 ? '</div>' : '').'</body></html>';
    }
    
    static function head() {
        $nocss = MP::getSettingInt('nocss', 0, true) == 1;
        if ($nocss) {
            return (MP::$enc == null ? '<meta charset="UTF-8">' : '').
            '<meta name="viewport" content="width=device-width, initial-scale=1">';
        }
        static::$iev = MP::getIEVersion();
        $full = MP::getSettingInt('full', 0, true) == 1;
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
            '.(static::$iev > 0 ? 'text-align: center;' : ($full ? (static::$chat ? '' : 'max-width: 540px;
            margin-right: auto;') : 'max-width: 540px;
            margin-left: auto;
            margin-right: auto;')).'
            font-family: sans-serif, system-ui;
            '.(static::$theme !== 1 ?
            'background: '.static::color('!background').';
            color: '.static::color('!foreground').';' : 'color: '.static::color('!foreground').';').'
            '.(static::$bg ?
            'background-attachment: fixed;
            background-image: url(/img/bg'.static::$bgsize.'.png);
            '.(static::$bgsize == 1000 ?
            'background-repeat: repeat;' : 
            'background-size: cover;
            background-repeat: no-repeat;') : '').'
        }
        a {
            color: '.static::color('!foreground').';'.'
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        input[type=text], select, textarea {
            '.(static::$theme != 1 ? 'background-color: '.static::color('!textbox_background').';
            color: '.static::color('!textbox_text').';
            border-color: '.static::color('!textbox_border').';
            ' : '').'border-style: solid;
        }
        .ct {
            margin-left: 2px;
            overflow: hidden;
            color: '.static::color('!chat_list_text').';
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
        .mca {
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
            '.(static::$bg || static::$fillMsg ? 'background-color: ' : 'border: 1px solid ').
            static::color('!message_out_background').';
        }
        .mo {
            '.(static::$bg || static::$fillMsg ? 'background-color: ' : 'border: 1px solid ').
            static::color('!message_background').';
        }
        .mpc {
            '.(static::$bg || static::$fillMsg ? 'background-color: ' : 'border: 1px solid ').
            static::color('!message_background').';
        }
        .mpd {
            background-color: '.static::color('!message_mentioned_background').';
        }
        .r, .mw {
            display: block;
            text-align: left;
            border-left: 2px solid '.static::color('!message_attachment_border').';
            padding-left: 4px;
            margin-bottom: 2px;
            margin-top: 2px;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }
        .rn, .mwt {
            color: '.static::color('!message_attachment_title').';'.
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
            border-color: '.static::color('!chat_list_border').';
            border-collapse: collapse;
            width: 100%;
        }
        .c {
            min-height: 42px;
            margin: 0px;'
            .(static::$bg || static::$fillChats ? ('background: '.static::color('!chat_list_background').';') : '').'
        }
        .cm {
            color: '.static::color('!chat_list_time').';
            display: -webkit-box;
            text-overflow: ellipsis;
            overflow: hidden;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            max-height: 2.5em;
            line-height: 1.25em;
        }
        .ctt {
            color: '.static::color('!chat_list_time').';
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
            border-bottom: 1px solid '.static::color('!chat_list_border').';
        }
        '.(static::$theme == 0 ? '' : '.mf, .mn {
            color: '.static::color('!message_link').';
        }').
        '.ma {
            text-align: center;
            margin-bottom: 10px;
        }
        .cma {
            color: '.static::color('!chat_list_action').';
        }
        .u {
            color: '.static::color('!message_options').';
        }
        .in {
            display: inline;
        }
        .inr {
            display: inline;
            float: right;
        }
        .unr {
            color: '.static::color('!chat_list_unread').';
        }
        input[type="file"] {
            color: '.static::color('!foreground').';
        }
        .ml {
            color: '.static::color('!message_link').';
        }
        .ch {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1;
            background: '.static::color('!chat_header_background').';
        }
        .cb {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            z-index: 1;
            background: '.static::color('!chat_header_background').';
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
        .mci {
            text-align: right;
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
            background: '.static::color('!textbox_background').';
            height: 4em;
        ' : '').'}
        .rc {
            width: 100%;
        }
        .btn {
            background-color: '.static::color('!button_background').';
            color: '.static::color('!button_text').';
            padding: 1px;
            border: solid 1px '.static::color('!button_border').';
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
            border: 1px solid '.static::color('!logout_button_border').';
            padding: 0 2px 0 2px;
            border-radius: 4px;
            background: '.static::color('!logout_button_background').';
        }
        .ra {
            color: '.static::color('!red_text').';
        }
        .hb {
            margin: 2px 0 2px 0;
        }
        .hed {
            '.(static::$bg || static::$fillChats ? ('background: '.static::color('!chat_list_header_background').';') : '').'
        }
        .fs {
            '.(static::$fillChats?('color: '.static::color('!chat_list_selected_folder').';') : '') .'
        }
        --></style>';
    }
}
