# MPGram Web

Lightweight Telegram web client based on MadelineProto.

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
- Apply MadelineProto patches:
```
patch -p0 < patches/InternalDoc.php.patch
patch -p0 < patches/Files.php.patch
```
- Make a background script that restarts php service at least every hour
- Set `session.gc_maxlifetime = 8640000` in `php.ini`
- For more details on installing MadelineProto <a href="https://docs.madelineproto.xyz/docs/REQUIREMENTS.html">see here</a>

### Animated stickers conversion (Optional)

- Install `gifski`
- Download and unpack: https://github.com/ed-asriyan/lottie-converter/releases
- Make sure www-data user has rights to it
- Edit `lottie_to_gif.sh`&`lottie_to_png.sh`, add `#!/usr/bin/env bash` as first line
- Edit `config.php` by setting `CONVERT_TGS_STICKERS` to true, and `LOTTIE_DIR` to path, where lottie_to_gif.sh is contained.

Example:
```
define('CONVERT_TGS_STICKERS', true);
define('LOTTIE_DIR', '/opt/lottie/');
```

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

