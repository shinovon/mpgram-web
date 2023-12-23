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

### Manual deployment

- Deny access to sessions folder (`s/` by default, see in `config.php`) and `MadelineProto.log`
- Install required php extensions: `gd`, `mbstring`, `xml`, `json`, `fileinfo`, `gmp`, `iconv`, `ffi`
- Download and set [browscap](https://browscap.org/) database in `php.ini` to get better logged in device names
- Install Composer v2+
- Install MadelineProto and its dependencies with `composer update`
- Make a background script that restarts php service at least every hour
- For more details on installing MadelineProto <a href="https://docs.madelineproto.xyz/docs/REQUIREMENTS.html">see here</a>

## Tested browsers

Fully supported:

- Internet Explorer 6.0 and above
- Opera 9.0 and above
- Nokia Browser for Symbian (S60v3 FP1 and above)
- S40 6th Edition
- Mozilla Firefox 2.0
- WebPositive
- Opera Mobile 12
- All modern browsers (Chrome, Safari, etc)

Partially supported (Auto update doesn't work and/or no auto scroll):

- Internet Explorer 3.0-5.0
- Opera Mini (All versions)
- S40 5th Edition or older
- Internet Explorer Mobile (?)

Not supported
- Internet Explorer 2 and older

