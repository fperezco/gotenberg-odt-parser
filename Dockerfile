FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo pdo_mysql

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN a2enmod rewrite

RUN echo '<VirtualHost *:80>
    DocumentRoot /var/www/html/public
    DirectoryIndex index.php
    <Directory /var/www/html/public>
        AllowOverride All
        Require all granted
        FallbackResource /index.php
    </Directory>
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

COPY . /var/www/html

WORKDIR /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
