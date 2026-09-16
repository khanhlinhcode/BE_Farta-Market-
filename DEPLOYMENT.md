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
- `analytics:prune` daily to remove page views older than
  `ANALYTICS_RETENTION_DAYS` (90 days by default).

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
credentials, Cloudinary credentials, queue connection, cache, and database
credentials there. Product, category, and banner image uploads fail closed when
`CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, or `CLOUDINARY_API_SECRET` is
missing. Other uploads keep using Laravel's configured filesystem disk.

The storefront analytics build flag is separate: leave
`VITE_ANALYTICS_ENABLED=false` during testing, then rebuild the storefront with
it set to `true` after the API and scheduler are running. Analytics stores HMAC
hashes for visitor/session UUIDs, grouped device information, pathname, and
referrer host. It does not store raw IP, user agent, UUID, query strings, form
values, cart data, or chat content. Visitor and conversion counts are estimates
and may be lower when Do Not Track or local opt-out is enabled.

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
