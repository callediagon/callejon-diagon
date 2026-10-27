FROM php:8.3-apache

# Extensiones del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip \
    nodejs \
    npm \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Extensiones PHP (pgsql en vez de mysql)
RUN docker-php-ext-install pdo_pgsql pgsql mbstring exif pcntl bcmath gd

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar proyecto
COPY . /var/www/html
WORKDIR /var/www/html

# Instalar dependencias PHP (sin dev)
RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts

# Instalar dependencias frontend y build
RUN npm ci --legacy-peer-deps && npm run build && rm -rf node_modules

# Permisos
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Apache: apuntar a /public y habilitar rewrite
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
    && a2enmod rewrite

EXPOSE 80

# Entrypoint: correr migraciones + cachear config + arrancar Apache
CMD sh -c "\
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan migrate --force && \
    apache2-foreground"