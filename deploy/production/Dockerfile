FROM php:8.2-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    libonig-dev \
    && docker-php-ext-install -j"$(nproc)" \
       pdo_mysql \
       mbstring \
       opcache \
    && a2enmod rewrite headers \
    && sed -ri \
       's!/var/www/html!/var/www/public_html!g' \
       /etc/apache2/sites-available/*.conf \
    && printf '%s\n' \
       'ServerName anas.masar-up.com' \
       '<Directory /var/www/public_html>' \
       '    Options FollowSymLinks' \
       '    AllowOverride All' \
       '    Require all granted' \
       '</Directory>' \
       > /etc/apache2/conf-available/anas.conf \
    && a2enconf anas \
    && printf '%s\n' \
       'upload_max_filesize=50M' \
       'post_max_size=55M' \
       'memory_limit=256M' \
       'max_execution_time=120' \
       > /usr/local/etc/php/conf.d/anas.ini \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/public_html

EXPOSE 80

CMD ["apache2-foreground"]
