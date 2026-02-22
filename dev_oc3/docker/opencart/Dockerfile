FROM composer:2 AS composer

FROM php:7.4-apache

ARG OPENCART_VERSION=3.0.3.8
ARG XDEBUG_VERSION=3.1.6

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libcurl4-openssl-dev \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libonig-dev \
    libpng-dev \
    libzip-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
      curl \
      gd \
      intl \
      mbstring \
      mysqli \
      zip \
    && pecl install "xdebug-${XDEBUG_VERSION}" \
    && docker-php-ext-enable xdebug \
    && a2enmod rewrite \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY apache.conf /etc/apache2/conf-available/opencart.conf
RUN a2enconf opencart

COPY xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

RUN git clone --depth 1 --branch "${OPENCART_VERSION}" https://github.com/opencart/opencart.git /usr/src/opencart \
    && rm -rf /usr/src/opencart/.git

WORKDIR /usr/src/opencart

RUN rm -rf ./upload/system/storage/vendor \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

WORKDIR /

COPY entrypoint.sh /usr/local/bin/docker-opencart3-entrypoint
RUN chmod +x /usr/local/bin/docker-opencart3-entrypoint

ENTRYPOINT ["docker-opencart3-entrypoint"]
CMD ["apache2-foreground"]
