# ============================================================
# CanchApp — Railway deployment v2
# ============================================================
FROM php:8.2-fpm-bullseye

# Install system dependencies and PHP extensions
RUN apt-get update --fix-missing && apt-get install -y --no-install-recommends \
    git curl zip unzip nginx supervisor \
    libzip-dev libxml2-dev libonig-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libicu-dev default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo pdo_mysql mbstring xml ctype fileinfo bcmath zip \
        exif pcntl intl opcache sockets gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Install PHP dependencies
COPY composer.json composer.lock ./
RUN COMPOSER_MEMORY_LIMIT=-1 composer install \
    --no-dev --optimize-autoloader --no-scripts --no-interaction

# Install Node dependencies
COPY package*.json ./
RUN npm install

# Copy full project
COPY . .

# Generate app key and build frontend
RUN cp .env.example .env \
    && php artisan key:generate --force \
    && npm run build

# Copy docker config files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
