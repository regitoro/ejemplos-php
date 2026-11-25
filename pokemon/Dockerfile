FROM php:8.3-apache

# Apache escucha en $PORT (lo asignan Render/Railway); 80 por defecto
ENV PORT=80
RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

COPY . /var/www/html/

# La caché debe poder escribirse por el usuario de Apache
RUN mkdir -p /var/www/html/cache && chown -R www-data:www-data /var/www/html/cache
