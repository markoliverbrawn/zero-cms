# Zero CMS - local docker-compose dev image (PHP 8.3 + Apache, mod_php)
FROM php:8.3-apache

# System libs + PHP extensions Zero CMS needs (GD w/ webp for media,
# pdo_mysql for the DB layer, zip for Seeder's ZipArchive usage,
# mbstring for Str helpers, exif/bcmath used by Media + billing-style code).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libzip-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd exif bcmath zip mbstring \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

# Only needed when this image serves a *host* project that requires Core via
# Composer (bin/create-project) instead of being Core's own repo - see entrypoint.sh.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Zero CMS is a front-controller app served out of public/, with .htaccess
# handling all rewriting (see public/.htaccess) - point DocumentRoot there.
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory "/var/www/html/public">\n\tAllowOverride All\n\tRequire all granted\n</Directory>\n' >> /etc/apache2/apache2.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/entrypoint.sh /usr/local/bin/zero-entrypoint.sh
RUN chmod +x /usr/local/bin/zero-entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["zero-entrypoint.sh"]
CMD ["apache2-foreground"]
