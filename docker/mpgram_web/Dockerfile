FROM phpdockerio/php:8.4-fpm

ARG UID

RUN apt-get update \
    && apt-get -y --no-install-recommends install \
    php8.4-mbstring \
    php8.4-gd \
    php8.4-ffi \
    php8.4-gmp \
    php8.4-iconv \
    php8.4-xml \
  # php8.4-json \ PHP8.3-JSON is not shipped as a separate component anymore
    php8.4-fileinfo \
    curl \
	patch \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

COPY ./php.ini /etc/php/8.4/fpm/conf.d/90-php.ini
COPY ./php.ini /etc/php/8.4/cli/conf.d/90-php.ini

RUN curl -o /usr/local/etc/browscap.ini http://browscap.org/stream?q=Lite_PHP_BrowsCapINI

RUN usermod -u ${UID} www-data

WORKDIR "/var/www/mpgram"

EXPOSE 9000
