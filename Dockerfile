FROM php:8.2-apache

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
    apt-get update && apt-get install -y unzip libzip-dev && \
    docker-php-ext-install zip && \
    a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json .
RUN composer install --no-interaction --no-dev --optimize-autoloader

COPY . .

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html
