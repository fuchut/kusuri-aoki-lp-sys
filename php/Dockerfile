ARG PHP_VERSION=latest
FROM php:${PHP_VERSION}-apache

# Apache rewrite有効
RUN a2enmod rewrite

# 基本ツール
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl unzip less nano git mariadb-client \
 && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install mysqli pdo pdo_mysql

# タイムゾーン
ENV TZ=Asia/Tokyo

# php.ini
COPY conf.d/*.ini /usr/local/etc/php/conf.d/

# WP-CLI
ADD https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar /usr/local/bin/wp
RUN chmod +x /usr/local/bin/wp

# Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh && chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
