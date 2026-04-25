FROM php:8.2-apache

RUN docker-php-ext-install mysqli

WORKDIR /var/www/html
COPY . .

RUN mkdir -p uploads && chown -R www-data:www-data uploads && chmod 755 uploads

COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/docker-entrypoint.sh"]
