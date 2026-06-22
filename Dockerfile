FROM php:8.2-apache

# 1. Install unzip/git for Composer, install MySQL drivers, and enable rewrite
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

# 2. Install Composer inside the container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Copy your project files over
COPY . /var/www/html
WORKDIR /var/www/html

# 4. Allow Composer to run as root inside Docker
ENV COMPOSER_ALLOW_SUPERUSER=1

# 5. Run composer install
RUN composer install --no-dev --optimize-autoloader

# 6. Point Apache to the public directory
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf