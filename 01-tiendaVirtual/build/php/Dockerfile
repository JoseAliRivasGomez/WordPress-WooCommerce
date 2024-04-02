FROM php:8.1-apache

# Actualizar el índice de paquetes e instalar las extensiones PHP
RUN apt-get update && \
    apt-get install -y zip libzip-dev libpng-dev unzip git

# Installing additional PHP modules
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configurar Apache para servir desde /var/www/html/public (personalizarlo según necesidades)
# RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf

# Configurar el tamaño máximo de subida de archivos en PHP
RUN echo "upload_max_filesize = 20M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 20M" >> /usr/local/etc/php/conf.d/uploads.ini