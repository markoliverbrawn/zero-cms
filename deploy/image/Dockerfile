# Zero CMS production image (PHP 8.3 + Apache), shared by every provider toolkit under deploy/.
#
# Built from the root of the project being deployed. For Core itself that's this repo; for a host
# project that requires Core via Composer, vendor/ must already be populated (`composer install`)
# before the build -- deploy/gcp/service.sh checks this. mbstring, curl and openssl come with the
# base image; zip is added for the seeder's optional distribution ZIP (ZipArchive).
FROM php:8.3-apache

# Install system dependencies for GD, PDO MySQL and zip
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo_mysql gd exif bcmath zip \
    && rm -rf /var/lib/apt/lists/*

# Use the production php.ini configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Configure Apache DocumentRoot to point to /var/www/html/public -- Zero CMS is a front-controller
# app served directly out of the project's own public/ (Core's, or a host project's).
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Allow Override for .htaccess support
RUN echo '<Directory "/var/www/html/public">\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Suppress ServerName localhost warning in logs to keep them clean
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Configure Apache to listen on the dynamic $PORT environment variable injected by Cloud Run
# Falling back to 8080 if not set at runtime
ENV PORT=8080
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g' /etc/apache2/sites-available/000-default.conf

# Copy application source code
COPY . /var/www/html/

# Create necessary writable storage folders if they do not exist
RUN mkdir -p /var/www/html/storage/logs /var/www/html/storage/private /var/www/html/public/storage \
    /var/www/html/public/assets/css/cache

# Set permissions for Apache user (www-data). Only directories the application genuinely writes to
# at runtime are group-writable: uploads and the derived-asset caches. The stylesheet source tree
# is deliberately NOT among them -- it used to be recursively chmod 775 because one compiled bundle
# was published into it, which left every authored stylesheet shipped in the image rewritable by
# the PHP process. Compiled bundles now go to assets/css/cache, so only that directory needs it.
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/public/storage \
    && chmod -R 775 /var/www/html/public/assets/css/cache

# Copy and configure custom entrypoint script that mirrors runtime env vars into .env
COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set the working directory
WORKDIR /var/www/html

# Expose standard port
EXPOSE 8080

# Configure the runtime entrypoint and foreground command
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
