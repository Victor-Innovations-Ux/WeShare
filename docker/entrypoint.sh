#!/bin/bash
set -e

# Wait for database to be ready
echo "Waiting for database..."
until php -r "try { new PDO('mysql:host='.\$_SERVER['DB_HOST'].';port='.(\$_SERVER['DB_PORT']??3306), \$_SERVER['DB_USER'], \$_SERVER['DB_PASSWORD']); echo 'ok'; } catch(Exception \$e) { exit(1); }" 2>/dev/null; do
    sleep 2
done
echo "Database is ready."

# Run migrations
echo "Running migrations..."
php /var/www/api/Migrations/migrate.php || true

# Start Apache
exec apache2-foreground
