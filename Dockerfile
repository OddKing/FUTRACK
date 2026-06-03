FROM php:8.2-apache

# Habilitar módulos de Apache (rewrite y headers para CORS)
RUN a2enmod rewrite headers

# Instalar git + extensiones PHP para MySQL
RUN apt-get update && apt-get install -y git && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo pdo_mysql

# Copiar el proyecto al directorio de Apache
COPY . /var/www/html/

# Ajustar permisos para evitar problemas de lectura
RUN chown -R www-data:www-data /var/www/html/
