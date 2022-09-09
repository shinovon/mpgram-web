# MPGram Web
Lightweight telegram web client based on MadelineProto

## Setup notes
- You need to generate api id by creating a telegram app in <a href="https://my.telegram.org/apps">https://my.telegram.org/apps</a> and put `api_id` & `api_hash` into `api_values.php`
- Server settings are located in `config.php`
- You should deny access to sessions folder (`s/` by default, see in `config.php`) and MadelineProto.log
- You must have php-gd extension installed to get images working (for example: `apt install php7.4-gd` on debian)
- You need to set browscap in php.ini to get better device labels
- MadelineProto install command: `composer update` (composer v2+ needs to be installed)
- More instructions for installing MadelineProto <a href="https://docs.madelineproto.xyz/docs/INSTALLATION.html">here</a>
