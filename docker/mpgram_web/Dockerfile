FROM phpdockerio/php:8.2-fpm

ARG UID

RUN apt-get update \
    && apt-get -y --no-install-recommends install \
    php8.2-mbstring \
    php8.2-gd \
    php8.2-ffi \
    php8.2-gmp \
    php8.2-iconv \
    curl \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

COPY ./php.ini /etc/php/8.2/fpm/conf.d/90-php.ini
COPY ./php.ini /etc/php/8.2/cli/conf.d/90-php.ini

RUN curl -o /usr/local/etc/browscap.ini http://browscap.org/stream?q=Lite_PHP_BrowsCapINI

RUN usermod -u ${UID} www-data

WORKDIR "/var/www/mpgram"

EXPOSE 9000
