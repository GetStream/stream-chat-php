ARG PHP_VERSION=8.2

FROM php:${PHP_VERSION}-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    zip \
    pcntl

# Install AST extension for Phan
RUN pecl install ast && \
    docker-php-ext-enable ast

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install --prefer-dist --no-scripts --no-progress --no-interaction

# Copy the rest of the application
COPY . .

# Set environment variables
ENV PHAN_ALLOW_XDEBUG=0
ENV PHAN_DISABLE_XDEBUG_WARN=1

# Default command
CMD ["php", "-v"] 