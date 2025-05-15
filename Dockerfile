FROM php:8.2-cli

# Install system packages and extensions
RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev zip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Install PHP dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Set Laravel environment (optional)
ENV APP_ENV=production

# Expose port
EXPOSE 8000

# Run Laravel development server
CMD php artisan serve --host=0.0.0.0 --port=8000
