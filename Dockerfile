# Use PHP 8.2 FPM as base image
FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libgd-dev \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl

# Install Redis extension
# GAP-088: pinned version, retried because pecl.php.net is sometimes unreachable
RUN for i in 1 2 3 4 5; do \
        pecl install redis-6.3.0 && break; \
        if [ "$i" -eq 5 ]; then echo "pecl install redis-6.3.0 failed (attempt 5/5)" >&2; exit 1; fi; \
        echo "pecl install redis-6.3.0 failed (attempt $i/5), retrying in $((i * 15))s" >&2; \
        sleep $((i * 15)); \
    done \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Create storage directories
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views
RUN chown -R www-data:www-data storage
RUN chmod -R 775 storage

# Create bootstrap cache directory
RUN mkdir -p bootstrap/cache
RUN chown -R www-data:www-data bootstrap/cache
RUN chmod -R 775 bootstrap/cache

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Expose port 9000
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]