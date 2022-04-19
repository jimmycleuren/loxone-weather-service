FROM php:8.1.5-fpm

ADD . /app

RUN apt-get update
RUN apt-get install -y zip git procps supervisor cron acl

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

RUN php -r "readfile('http://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer

RUN composer install --verbose --prefer-dist --no-dev --optimize-autoloader --no-scripts --no-suggest

CMD ["/usr/bin/supervisord"]