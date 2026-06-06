FROM php:8.2-apache

RUN apt-get update && apt-get install -y libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN echo '<Directory /var/www/html>\n    AllowOverride All\n    Require all granted\n</Directory>' \
    > /etc/apache2/conf-available/dashboard.conf \
    && a2enconf dashboard

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -name "*.php" -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

EXPOSE 80
