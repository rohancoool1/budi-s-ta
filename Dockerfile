# Menggunakan image dasar PHP 8.2 (Anda bisa mengubahnya ke 8.1 atau 8.3)
FROM php:8.3-cli

# Menginstal dependensi sistem dan ekstensi PHP yang dibutuhkan Laravel
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo pdo_mysql

# Mengambil Composer dari image official-nya
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Menginstal Laravel Installer secara global via Composer
RUN composer global require laravel/installer

# Menambahkan direktori global Composer ke environment PATH 
# agar perintah 'laravel' bisa langsung dieksekusi di terminal container
ENV PATH="/root/.composer/vendor/bin:/root/.config/composer/vendor/bin:${PATH}"

# Mengatur folder kerja default di dalam container
WORKDIR /app