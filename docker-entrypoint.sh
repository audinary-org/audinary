#!/bin/sh

# Docker Entrypoint Script
# Created to initialize the environment and run migrations before starting the application

echo "Starting application..."

# Check if migrations are needed first
echo "Checking if migrations are needed..."
cd /var/www/html
php scripts/run_migrations.php --check

# Check the exit code to determine if migrations are needed
if [ $? -eq 1 ]; then
    echo "Migrations are needed. Creating backup before running migrations..."
    php scripts/create_backup.php
    
    # Run migrations
    echo "Running database migrations..."
    php scripts/run_migrations.php
    
    # Check if migrations were successful
    if [ $? -eq 0 ]; then
        echo "Migrations completed successfully"
    else
        echo "Migration failed - exiting"
        exit 1
    fi
else
    echo "No migrations needed, skipping backup and migration steps."
fi

# Clean up old log files (older than 7 days)
echo "Cleaning up old log files..."
find /var/www/html/var/logs -name "*.log" -mtime +7 -type f -delete 2>/dev/null

# Start the original app (from the base image)
echo "Starting web server..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf 