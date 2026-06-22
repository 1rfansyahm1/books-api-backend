FROM php:8.2-apache

# 1. Install MySQL drivers and enable Apache's mod_rewrite for Slim routing
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

# 2. Install Composer inside the container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Copy your project files over
COPY . /var/www/html

# 4. Run composer install to pull down your Slim framework dependencies
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader

# 5. Point Apache to the public directory
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf