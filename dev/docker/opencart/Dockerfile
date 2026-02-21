FROM php:8.1-apache

ARG OPENCART_VERSION=4.1.0.3

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
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && a2enmod rewrite \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY apache.conf /etc/apache2/conf-available/opencart.conf
RUN a2enconf opencart

COPY xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

RUN git clone --depth 1 --branch "${OPENCART_VERSION}" https://github.com/opencart/opencart.git /usr/src/opencart \
    && rm -rf /usr/src/opencart/.git

COPY entrypoint.sh /usr/local/bin/docker-opencart-entrypoint
RUN chmod +x /usr/local/bin/docker-opencart-entrypoint

ENTRYPOINT ["docker-opencart-entrypoint"]
CMD ["apache2-foreground"]
