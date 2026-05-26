FROM node:20-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

FROM php:8.3-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    libonig-dev \
    libpq-dev \
    supervisor \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pcntl \
    zip \
    mbstring \
    bcmath \
    gd

RUN pecl install redis \
    && docker-php-ext-enable redis

RUN pecl install xdebug-3.4.2 \
    && docker-php-ext-enable xdebug

RUN mkdir -p /var/log/xdebug

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies (no dev in production, but we'll use one Dockerfile for both)
RUN composer install --no-interaction --optimize-autoloader --no-scripts

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache && \
    chown -R www-data:www-data /var/log

COPY --from=node-builder /app/public/build /var/www/html/public/build

EXPOSE 9000
CMD ["php-fpm"]
