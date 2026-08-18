FROM php:7.4-apache

# Install ekstensi MySQL/MariaDB yang dibutuhkan PHP 7
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable module rewrite Apache (jika pakai routing/framework)
RUN a2enmod rewrite