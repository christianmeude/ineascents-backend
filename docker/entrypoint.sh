#!/bin/bash
set -e

# Run Laravel migrations (--force skips production confirmation prompt)
php artisan migrate --force

# Start the queue worker in the background to process emails
php artisan queue:work --tries=3 --timeout=90 &

# Start the scheduler in the background to handle cron jobs
php artisan schedule:work &

# Start Apache
exec apache2-foreground
