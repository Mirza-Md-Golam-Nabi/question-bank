#!/bin/bash
set -e

PHP="/opt/cpanel/ea-php83/root/usr/bin/php"

echo "🚀 Deployment started..."

# Pull the latest code from Git
git pull origin dev
echo "✅ Git pull done"

# Run composer install before artisan down — if the new code doesn't
# match the old vendor/ folder, artisan down itself will crash, and
# the next deploy attempt will get stuck at this same spot (deadlock).
$PHP composer.phar install --no-dev --optimize-autoloader
echo "✅ Composer done"

# Copy .env file if it doesn't exist
if [ ! -f .env ]; then
    cp .env.example .env
    $PHP artisan key:generate
    echo "✅ .env created"
fi

$PHP artisan down
echo "✅ Maintenance mode ON"

# Cache clear
$PHP artisan optimize:clear
$PHP artisan filament:optimize-clear
echo "✅ Cache cleared"

# Migrate
$PHP artisan migrate --force
echo "✅ Migration done"

# Cache rebuild
$PHP artisan optimize
$PHP artisan filament:optimize
echo "✅ Cache rebuilt"

# Force the queue worker to load the new code
$PHP artisan queue:restart
echo "✅ Queue restarted"

$PHP artisan up
echo "✅ Maintenance mode OFF"

echo "🎉 Deployment finished!"
