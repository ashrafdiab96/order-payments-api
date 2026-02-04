FROM php:8.4-cli

# System deps
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libxml2-dev \
    libonig-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        zip \
        dom \
        xml \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
