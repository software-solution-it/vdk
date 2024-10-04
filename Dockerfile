# Use a imagem base do PHP
FROM php:8.3-cli

# Instala as dependências do sistema e as extensões do PHP
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    libicu-dev \
    libc-client-dev \
    libkrb5-dev \
    zlib1g-dev \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install imap \
    && docker-php-ext-enable imap \
    && docker-php-ext-install sockets \
    && docker-php-ext-install pdo pdo_mysql \
    && docker-php-ext-install pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configura o diretório de trabalho
WORKDIR /app

# Copia o código para o contêiner
COPY . .

# Instala o Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Verifica as extensões instaladas
RUN php -m
