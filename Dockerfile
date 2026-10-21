FROM php:8.3-fpm-bookworm

ADD . /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends zip unzip git procps supervisor cron acl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY docker/timezone.ini /usr/local/etc/php/conf.d/timezone.ini
RUN chmod 755 /usr/local/etc/php/conf.d/timezone.ini

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

COPY docker/update-weather /etc/cron.d/update-weather
RUN chmod 0644 /etc/cron.d/update-weather
RUN crontab /etc/cron.d/update-weather

RUN mkdir -p /app/var/cache/prod
RUN mkdir -p /app/var/log/prod
RUN chmod 777 -R /app/var

RUN setfacl -dR -m u:www-data:rwX -m u:$(whoami):rwX var
RUN setfacl -R -m u:www-data:rwX -m u:$(whoami):rwX var

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN composer install --verbose --prefer-dist --no-dev --optimize-autoloader --no-scripts

CMD ["/usr/bin/supervisord"]
