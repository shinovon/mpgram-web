# MPGram Web

Lightweight Telegram web client based on MadelineProto.

Test instance is available at <a href="https://mp.nnchan.ru/">https://mp.nnchan.ru</a> (not guaranteed to run a stable version).

_It is highly recommended to run your own instance (read on)._

## Setup

- Generate your own API id by creating a Telegram app at <a href="https://my.telegram.org/apps">https://my.telegram.org/apps</a> 
- Create `api_value.php` from the `api_values.php.example` using the `api_id` and `api_hash` you generated
- Create `config.php` from the `config.php.example`

## Deployment

### Docker

You can deploy your own instance quickly with Docker Compose - [see how](https://github.com/shinovon/mpgram-web/blob/main/docker/README.md).

### Manual deployment notes

- You should deny access to sessions folder (`s/` by default, see in `config.php`) and `MadelineProto.log`
- You must have `php-gd` extension installed to get images working (`apt-get install php8.1-gd`)<br>
and `php-mbstring` for MadelineProto (`apt-get install php8.1-mbstring`)
- You need to set [browscap](https://browscap.org/) in `php.ini` to get better logged in device names
- Install Composer v2+
- Install MadelineProto and its dependencies with `composer update`
- For more details on installing MadelineProto <a href="https://docs.madelineproto.xyz/docs/INSTALLATION.html">see here</a>

## Tested browsers

Fully supported:

- Internet Explorer 6.0 and above
- Opera 9.0 and above
- Nokia Browser for Symbian (Symbian 9.2 and above)
- S40 6th Edition
- Mozilla Firefox 2.0
- WebPositive
- All modern browsers (Chrome, Safari, etc)

Partially supported (Auto update doesn't work and/or no auto scroll):

- Internet Explorer 3.0-5.0
- Opera Mini (All versions)
- S40 5th Edition or older
- Internet Explorer Mobile (?)

Not supported
- Internet Explorer 2 and older
- ?

