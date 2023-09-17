# Your own mpgram-web in 5 minutes

## Requirements

1. Docker Engine (see [instructions](https://docs.docker.com/engine/install/ubuntu/))
2. [docker-compose](https://github.com/docker/compose).

## Configuration

Edit `../api_values.php` and `../config.php` as per mpgram-web's [README](https://github.com/shinovon/mpgram-web), then:

```
cp .env.example .env
```

See if you need to edit the ports, IP etc.

### HTTP (default)

You are all set!

```
docker-compose up --build -d
```

Your mpgram will await you on [http://127.0.0.1](http://127.0.0.1).

### HTTPS (recommended)

* Place your SSL chain certificate (named `fullchain.pem`) and private key (named `privkey.pem`) into `nginx/ssl`
* Set `PROTO=https` in `.env`

If you want to create a self-signed certificate instead of obtaining one from a CA, run:

```
openssl req -x509 -nodes -days 365 -subj "/C=CA/ST=QC/O=Company, Inc./CN=mydomain.com" -addext "subjectAltName=DNS:mydomain.com" -newkey rsa:2048 -keyout nginx/ssl/privkey.pem -out nginx/ssl/fullchain.pem
```

Add a `-sha1` option if you are targeting a retro platform like Symbian S60. **Warning**: [SHA1 is insecure!](https://en.wikipedia.org/wiki/SHA-1#Attacks).

Run with `docker-compose` as per above.

Happy mpgram'ming!

