FROM php:7.4-fpm

ADD . /app

RUN apt-get update
RUN apt-get install -y zip git procps supervisor cron

WORKDIR /app

COPY docker/timezone.ini /usr/local/etc/php/conf.d/timezone.ini
RUN chmod 755 /usr/local/etc/php/conf.d/timezone.ini

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

COPY docker/update-weather /etc/cron.d/update-weather
RUN chmod 0644 /etc/cron.d/update-weather
RUN crontab /etc/cron.d/update-weather

RUN php -r "readfile('http://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer

RUN composer install --verbose --prefer-dist --no-dev --optimize-autoloader --no-scripts --no-suggest

CMD ["/usr/bin/supervisord"]