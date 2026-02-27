# ============================================================
# CanchApp — Railway deployment
# ============================================================
FROM php:8.2-bullseye

# Install system dependencies and PHP extensions
RUN apt-get update --fix-missing && apt-get install -y --no-install-recommends \
    git curl zip unzip \
    libzip-dev libxml2-dev libonig-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libicu-dev \
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

WORKDIR /app

# Install PHP dependencies
COPY composer.json composer.lock ./
RUN COMPOSER_MEMORY_LIMIT=-1 composer install \
    --no-dev --optimize-autoloader --no-scripts --no-interaction

# Install Node dependencies
COPY package*.json ./
RUN npm install

# Copy full project
COPY . .

# Generate app key and build frontend (wayfinder needs PHP/artisan)
RUN cp .env.example .env \
    && php artisan key:generate --force \
    && npm run build

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

EXPOSE 8080

CMD php artisan config:clear && \
    php artisan cache:clear && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan migrate --force && \
    php artisan storage:link && \
    (php artisan schedule:work &) && \
    (php artisan queue:work --tries=3 --timeout=90 &) && \
    php -S 0.0.0.0:${PORT:-8080} -t public
