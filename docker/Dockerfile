FROM php:8.1-apache

RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    curl \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        mysqli \
        pdo_mysql \
        zip \
        intl \
        opcache \
        xml \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN echo '\
    upload_max_filesize = 32M\n\
    post_max_size = 32M\n\
    memory_limit = 256M\n\
    max_execution_time = 300\n\
    short_open_tag = On\n\
    mbstring.func_overload = 0\n\
    opcache.revalidate_freq = 0\n\
    display_errors = On\n\
    date.timezone = Europe/Moscow\n\
    ' > /usr/local/etc/php/conf.d/bitrix.ini

RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html
