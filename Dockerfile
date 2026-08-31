FROM php:8.3-apache

RUN a2enmod headers rewrite

WORKDIR /var/www/html

COPY public/ /var/www/html/
COPY src/ /var/www/src/
COPY config/ /var/www/config/
COPY database/ /var/www/database/
COPY profiles/templates/ /var/www/profiles/templates/

RUN mkdir -p /var/www/storage /var/www/profiles/private \
    && chown -R www-data:www-data /var/www/storage /var/www/profiles/private

EXPOSE 80
