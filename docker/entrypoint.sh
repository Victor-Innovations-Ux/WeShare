#!/bin/bash
set -e

# Wait for database to be ready
echo "Waiting for database..."
while ! mysqladmin ping -h"$DB_HOST" --silent 2>/dev/null; do
    sleep 1
done
echo "Database is ready."

# Run migrations
echo "Running migrations..."
php /var/www/api/Migrations/migrate.php || true

# Start Apache
exec apache2-foreground
