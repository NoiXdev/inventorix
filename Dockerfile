# syntax=docker/dockerfile:1.7

# Stage 1: Build frontend assets
FROM node:26-alpine AS frontend-builder
WORKDIR /app

# Restore npm cache early so source changes don't bust dependency install.
COPY package.json package-lock.json ./
RUN npm ci

# Copy only the files Vite needs for the build, so PHP-only changes don't
# invalidate the frontend build layer.
COPY vite.config.* ./
COPY postcss.config.* ./
COPY tailwind.config.* ./
COPY tsconfig.json ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# Stage 2: PHP application
FROM dunglas/frankenphp:1-php8.4

WORKDIR /app

RUN install-php-extensions \
    pdo_mysql \
    redis \
    intl \
    gd \
    opcache \
    pcntl \
    zip

RUN apt-get update \
    && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

COPY . .
COPY --from=frontend-builder /app/public/build /app/public/build
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && chown -R www-data:www-data /app

USER www-data

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8000/up || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]
