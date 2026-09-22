# =============================================================
# Stage 1: Frontend Asset Compilation (Vite + Inertia React)
# =============================================================
FROM node:22-alpine AS frontend

WORKDIR /app

# Copy package descriptors
COPY package.json .npmrc* pnpm-workspace.yaml* ./

# Install npm packages
RUN npm install

# Copy application sources for Vite build
COPY resources/ resources/
COPY public/ public/
COPY vite.config.ts tsconfig.json components.json ./
COPY routes/ routes/

# Compile production assets into /app/public/build
RUN npm run build

# =============================================================
# Stage 2: PHP Composer Dependencies
# =============================================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# Install production dependencies without dev packages or scripts
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --ignore-platform-reqs

# Copy application files needed for autoloader generation
COPY app/ app/
COPY bootstrap/ bootstrap/
COPY config/ config/
COPY database/ database/
COPY routes/ routes/
COPY artisan artisan

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# =============================================================
# Stage 3: Production Runtime with FrankenPHP
# =============================================================
FROM dunglas/frankenphp:1-php8.3-alpine AS runtime

# Install essential PHP extensions for modern Laravel
RUN install-php-extensions \
    bcmath \
    intl \
    opcache \
    pcntl \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    zip

WORKDIR /app

# Copy Caddyfile web server configuration
COPY Caddyfile /etc/caddy/Caddyfile

# Copy full application codebase
COPY . .

# Copy production PHP vendor dependencies from vendor stage
COPY --from=vendor /app/vendor /app/vendor

# Copy compiled frontend assets from frontend stage
COPY --from=frontend /app/public/build /app/public/build

# Setup entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Environment settings for production
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PHP_OPCACHE_ENABLE=1 \
    PORT=80

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
