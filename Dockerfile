# Base PHP image
FROM php:8.3-apache

# Install necessary PHP extensions and dependencies
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libonig-dev \
    libzip-dev \
    libssl-dev \
    curl \
    zip \
    unzip \
    git \
    && docker-php-ext-install -j$(nproc) gd mbstring zip pdo pdo_mysql

# Install IMAP extension
RUN apt-get update && apt-get install -y libc-client-dev libkrb5-dev \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install imap

# Install and enable pcntl extension
RUN docker-php-ext-install pcntl

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set the working directory
WORKDIR /app

# Copy the application code to the container
COPY . /app

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install application dependencies using Composer
RUN composer install --no-dev --ignore-platform-reqs
