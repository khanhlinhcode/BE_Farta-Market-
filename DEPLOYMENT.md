# Production Deployment Notes

## Required Runtime Processes

Run the Laravel scheduler every minute on the production server:

```cron
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler is required for:

- `payments:expire-pending` every 10 minutes to expire stale VNPay pending
  orders and restore reserved inventory.
- `idempotency:prune` daily to remove expired idempotency records.
- `sitemap:generate` daily to refresh `public/sitemap.xml` and `robots.txt`.

Run a queue worker for email and default jobs:

```bash
php artisan queue:work --queue=emails,default --tries=3
```

Supervisor example:

```ini
[program:farta-market-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/backend/artisan queue:work --queue=emails,default --tries=3 --sleep=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/farta-market-queue.log
stopwaitsecs=3600
```

## Production Environment

Use `.env.production.example` as a template only. Create a real `.env` on the
server and set the real API domain, frontend domain, VNPay credentials, SMTP
credentials, queue connection, cache, and database credentials there.

Do not commit real `.env` or `.env.production` files.

## Release Checks

Before release:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan sitemap:generate
php artisan test
composer audit
```
