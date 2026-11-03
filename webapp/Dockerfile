FROM php:8.4-fpm-alpine

RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev postgresql-dev \
    && docker-php-ext-install pdo_pgsql bcmath intl zip opcache mbstring \
    && printf "sys_temp_dir=/tmp\nupload_tmp_dir=/tmp\n" > /usr/local/etc/php/conf.d/zz-temp.ini \
    && sed -i 's/^user = www-data/user = root/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^group = www-data/group = root/' /usr/local/etc/php-fpm.d/www.conf

# DEV ONLY: pool jalan sebagai root karena bind mount Windows tidak menghormati
# chmod/chown untuk www-data. Image produksi nanti membake file dan kembali ke www-data.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

CMD ["php-fpm", "--allow-to-run-as-root"]

WORKDIR /var/www/html
