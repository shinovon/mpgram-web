# MPGram Web
Lightweight telegram web client based on MadelineProto

Test instance: <a href="https://mp.nnchan.ru/">https://mp.nnchan.ru</a>, but it is highly recommended to make your own instance.

## Setup notes
- You need to create `config.php` and `api_values.php` config files (see examples)
- You need to generate api id by creating a telegram app in <a href="https://my.telegram.org/apps">https://my.telegram.org/apps</a> and put `api_id` & `api_hash` into `api_values.php`
- You should deny access to sessions folder (`s/` by default, see in `config.php`) and MadelineProto.log
- You must have php-gd extension installed to get images working (`apt-get install php8.1-gd`)<br>
and php-mbstring for MadelineProto (`apt-get install php8.1-mbstring`)
- You need to set browscap in php.ini to get better device labels
- MadelineProto install command: `composer update` (composer v2+ needs to be installed)
- More instructions for installing MadelineProto <a href="https://docs.madelineproto.xyz/docs/INSTALLATION.html">here</a>

## Tested browsers
Fully supported:
- Internet Explorer 6.0 and above
- Opera 9.0 and above
- Nokia Browser for Symbian (Symbian 9.2 and above)
- S40 6th Edition
- Mozilla Firefox 2.0
- WebPositive
- All modern browsers (Chrome, Safari, etc)

Partially supported (Auto update doesn't work and/or no auto scroll)
- Internet Explorer 3.0-5.0
- Opera Mini (All versions)
- S40 5th Edition or older
- Internet Explorer Mobile (?)

Not supported
- Internet Explorer 2 and older
- ?
