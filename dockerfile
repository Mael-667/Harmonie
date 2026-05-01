FROM php:8.5.3-apache

WORKDIR /harmonie

COPY --link \
    --from=ghcr.io/symfony-cli/symfony-cli:latest \
    /usr/local/bin/symfony /usr/local/bin/symfony

COPY conf/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY conf/apache.conf /etc/apache2/conf-available/z-app.conf

COPY conf/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

RUN a2enconf z-app
RUN docker-php-ext-install pdo pdo_mysql
RUN apt-get update && apt-get install -y git unzip && rm -rf /var/lib/apt/lists/*

