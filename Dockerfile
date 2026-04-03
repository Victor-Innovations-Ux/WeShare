FROM php:8.2-apache

# Install PHP extensions and dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libwebp-dev libgif-dev \
    unzip git default-mysql-client \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install gd pdo pdo_mysql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# PHP config for uploads
RUN printf "upload_max_filesize = 10M\npost_max_size = 12M\nmax_execution_time = 60\nmemory_limit = 256M\n" \
    > /usr/local/etc/php/conf.d/uploads.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache config
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy application code
COPY php-api/ /var/www/api/
COPY public/ /var/www/public/

# Create photo directory
RUN mkdir -p /var/www/photo/groups

# Install PHP dependencies
WORKDIR /var/www/api
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R www-data:www-data /var/www/photo /var/www/api /var/www/public

WORKDIR /var/www

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
