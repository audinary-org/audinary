# Multi-stage build for Vue.js frontend + PHP backend
FROM node:25-alpine AS frontend-builder

# Build Vue.js frontend
WORKDIR /app/client

# Update npm to latest version
RUN npm install -g npm@latest

COPY client/package*.json ./
RUN npm install

COPY client/ ./
RUN npm run build

# PHP + Nginx container
FROM trafex/php-nginx:3.10.0

# Switch to root for installation
USER root

# Install additional packages needed for your application
RUN apk add --no-cache \
    git \
    unzip \
    ffmpeg \
    mediainfo \
    postgresql-client \
    php84-zip \
    php84-pgsql \
    php84-pdo_pgsql \
    php84-simplexml \
    php84-xmlwriter \
    php84-xmlreader \
    php84-fileinfo

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy the server code into the container
COPY server/ ./

# Copy built Vue.js frontend from the first stage
COPY --from=frontend-builder /app/client/dist/ ./public/

# Copy config sample to config directory
COPY server/src/config_sample.php ./var/config/config.php

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Make scheduler executable
RUN chmod +x ./scripts/scheduler.sh

# Add scheduler as supervised process
RUN echo -e '[program:scheduler]\ncommand=/var/www/html/scripts/scheduler.sh\nautostart=true\nautorestart=true\nstdout_logfile=/dev/stdout\nstdout_logfile_maxbytes=0\nstderr_logfile=/dev/stderr\nstderr_logfile_maxbytes=0\nuser=nobody' > /etc/supervisor/conf.d/scheduler.conf

# Create necessary directories and set permissions
RUN mkdir -p /var/www/.composer \
    && mkdir -p ./var/cache ./var/img ./var/logs ./var/music \
    && chown -R nobody:nobody /var/www

# Configure Nginx for SPA
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Switch to the nobody user to run Composer install and clear cache
USER nobody
RUN composer install --no-interaction --prefer-dist --optimize-autoloader && \
    composer clear-cache

# Switch back to root to remove composer and clean up
USER root
RUN rm -f /usr/bin/composer && rm -rf /var/www/.composer

# Switch back to nobody for running the container
USER nobody

# Declare persistent volume directories
VOLUME ["/var/www/html/config", "/var/www/html/var/cache", "/var/www/html/var/img", "/var/www/html/var/logs", "/var/www/html/var/music"]

# Use custom entrypoint that runs migrations before starting the app
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]