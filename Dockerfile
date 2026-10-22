FROM php:8.5.12-fpm-trixie

ADD . /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends zip unzip git procps supervisor \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY docker/timezone.ini /usr/local/etc/php/conf.d/timezone.ini
RUN chmod 755 /usr/local/etc/php/conf.d/timezone.ini

COPY docker/php2shell.ini /usr/local/etc/php/conf.d/zz-php2shell.ini

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN composer install --verbose --prefer-dist --no-dev --optimize-autoloader --no-scripts

# php-fpm workers and the refresh job both run as www-data and must own var/
# (ACLs set with setfacl do not survive the image build, so do not rely on them).
RUN mkdir -p /app/var/cache /app/var/log && chown -R www-data:www-data /app/var

CMD ["/usr/bin/supervisord"]
