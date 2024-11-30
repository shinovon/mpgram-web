FROM phpdockerio/php:8.3-fpm

ARG UID

RUN apt-get update \
    && apt-get -y --no-install-recommends install \
    php8.3-mbstring \
    php8.3-gd \
    php8.3-ffi \
    php8.3-gmp \
    php8.3-iconv \
    php8.3-xml \
    php8.3-json \
    php8.3-fileinfo \
    curl \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

COPY ./php.ini /etc/php/8.3/fpm/conf.d/90-php.ini
COPY ./php.ini /etc/php/8.3/cli/conf.d/90-php.ini

RUN curl -o /usr/local/etc/browscap.ini http://browscap.org/stream?q=Lite_PHP_BrowsCapINI

RUN usermod -u ${UID} www-data

WORKDIR "/var/www/mpgram"

EXPOSE 9000
