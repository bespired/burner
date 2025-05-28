FROM php:8.4-apache

RUN a2enmod ssl && a2enmod rewrite

RUN apt-get update \
    && apt-get upgrade -y \
    && apt-get install -y \
    libzip-dev libicu-dev libyaml-dev libtidy-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    curl unzip nano \
    && docker-php-ext-configure gd --with-freetype --with-jpeg

RUN pecl install yaml \
    && echo "extension=yaml.so" > /usr/local/etc/php/conf.d/docker-php-ext-yaml.ini \
    && docker-php-ext-enable yaml

RUN docker-php-ext-install \
    tidy mysqli pdo pdo_mysql zip intl gd exif opcache \
    && docker-php-ext-enable mysqli


RUN pecl install -o -f redis \
    && docker-php-ext-enable redis

RUN mkdir -p /etc/apache2/ssl
COPY ./ssl/*.pem /etc/apache2/ssl/
COPY ./ssl/*.crt /etc/apache2/ssl/
COPY ./ssl/*.key /etc/apache2/ssl/

COPY ./apache.conf /etc/apache2/sites-available/000-default.conf

# COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

EXPOSE 80
EXPOSE 443
