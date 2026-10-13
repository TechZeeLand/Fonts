FROM php:8.3-apache

# python3 + fonttools + brotli: automatic WOFF2 conversion and font inspection in the admin portal
RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev python3 python3-fonttools python3-brotli \
 && docker-php-ext-install zip \
 && rm -rf /var/lib/apt/lists/*

RUN a2enmod headers rewrite deflate \
 && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY docker/apache.conf /etc/apache2/conf-available/tzl.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-tzl.ini
COPY docker/entrypoint.sh /usr/local/bin/tzl-entrypoint
RUN a2enconf tzl \
 && sed -i 's/\r$//' /usr/local/bin/tzl-entrypoint \
 && chmod +x /usr/local/bin/tzl-entrypoint

WORKDIR /var/www/html
COPY . /var/www/html
RUN mkdir -p data && chown -R www-data:www-data data

VOLUME ["/var/www/html/data"]
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s CMD php -r 'exit(@file_get_contents("http://127.0.0.1/health")?0:1);'
ENTRYPOINT ["tzl-entrypoint"]
CMD ["apache2-foreground"]
