FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends python3 sqlite3 libsqlite3-dev \
    && (php -m | grep -qi '^pdo_sqlite$' || docker-php-ext-install pdo_sqlite) \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /opt/ipfeed

COPY . /opt/ipfeed
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/ipfeed-entrypoint

RUN chmod +x /opt/ipfeed/install.sh /usr/local/bin/ipfeed-entrypoint \
    && mkdir -p /var/lib/ipfeed \
    && chown -R www-data:www-data /var/lib/ipfeed /opt/ipfeed/ipfeed

ENV IP_FEED_PROJECT_DIR=/opt/ipfeed \
    IP_FEED_SETTINGS_DIR=/var/lib/ipfeed \
    IP_FEED_CONFIG_FILE=/var/lib/ipfeed/config.php \
    IP_FEED_FEED_FILE=/var/lib/ipfeed/ips.txt

EXPOSE 80

ENTRYPOINT ["ipfeed-entrypoint"]
CMD ["apache2-foreground"]
