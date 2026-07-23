# syntax=docker/dockerfile:1.7

# ============================================================
# Stage 1 - Build frontend assets (Vite + Tailwind)
# ============================================================
FROM node:22-alpine AS assets

WORKDIR /app

# Install node deps using lockfile-aware install
COPY package.json package-lock.json* ./
RUN npm install --no-audit --no-fund

# Build the production assets
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
ENV VITE_SKIP_FONTS=true
RUN npm run build


# ============================================================
# Stage 2 - Install PHP/Composer dependencies (no dev)
# ============================================================
FROM composer:2 AS vendor

WORKDIR /app

# Copy only the files needed to resolve dependencies first (better cache)
COPY composer.json composer.lock ./

# Install production dependencies without running scripts (artisan not present yet)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction \
    --no-progress

# Copy the rest of the source and finalize the autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative


# ============================================================
# Stage 3 - Runtime (PHP 8.3 + Apache)
# ============================================================
FROM php:8.3-apache AS runtime

# --- System packages & PHP extensions -----------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mysqli \
        intl \
        zip \
        gd \
        bcmath \
        opcache \
        exif \
        pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# --- Apache configuration -----------------------------------
# Enable rewrite + headers; set docroot to Laravel's public/
RUN a2enmod rewrite headers remoteip
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# --- PHP production configuration ---------------------------
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

WORKDIR /var/www/html

# --- Application source -------------------------------------
# Vendor + app code from the composer stage (includes full source)
COPY --from=vendor /app /var/www/html
# Built assets + manifest from the node stage
COPY --from=assets /app/public/build /var/www/html/public/build

# --- Permissions & entrypoint -------------------------------
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R ug+rw /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
