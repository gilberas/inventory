FROM php:8.4-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    curl \
    unzip \
    zip \
    libpng-dev \
    libzip-dev \
    libxml2-dev \
    oniguruma-dev \
    libpq-dev \
    nodejs \
    npm

# PHP extensions Laravel commonly needs
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    zip \
    gd \
    bcmath \
    mbstring \
    xml \
    exif

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application code
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install & build frontend assets (Vite/Tailwind)
RUN npm install && npm run build

# Copy nginx and supervisor configs
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 10000

CMD ["/start.sh"]
