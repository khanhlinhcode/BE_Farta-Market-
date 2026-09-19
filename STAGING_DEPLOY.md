# Staging deployment

This runbook deploys the Laravel API, Storefront, and Admin as separate services. Keep all credentials in provider environment variables; never commit them or paste them into an issue or chat.

## Prerequisites

- A Railway account/project, a Cloudflare account, and a domain you control. Use three HTTPS subdomains under that domain, such as `api.example.com`, `shop.example.com`, and `admin.example.com`. Sanctum's cookie-based SPA authentication requires the API and both SPAs to share the same top-level domain. `*.railway.app` plus `*.pages.dev` is not a reliable authenticated staging configuration.
- Rotate credentials that may have appeared in the old storefront Git history before using them on staging: Cloudinary, database, VNPay, mail, AI provider, and any other tokens.
- Decide whether staging uses a fresh database or a private, reviewed catalog-only import. Do not import local users, sessions, orders, or payment data into public staging. Product image Cloudinary URLs and public IDs live in the database, not in Git. `ProductSeeder` alone creates products without images.

## Railway: API, MySQL, worker, scheduler

1. Create a Railway project with a MySQL service and an API service from `khanhlinhcode/BE_Farta-Market-` branch `main`. Railway detects Laravel and starts PHP-FPM/Caddy. Set API health check to `/up`.
2. Set the API pre-deploy command to `bash railway/init-app.sh`. It runs migrations and caches configuration/routes/views. Do not run `db:seed` automatically on deployment.
3. Create a worker service from the same repository/commit with start command `bash railway/run-worker.sh`. Create a scheduler service from the same repository/commit with start command `php artisan schedule:run --no-interaction` and cron schedule `*/5 * * * *` (UTC). Only the API needs a public domain.
4. Set the same `APP_KEY`, MySQL connection, mail, Cloudinary, queue, and relevant app variables on API, worker, and scheduler. Use Railway reference variables for MySQL host, port, database, user, and password. Generate a new `APP_KEY` for a fresh database; preserve the original key only if importing encrypted records that must remain readable.
5. Configure the API custom domain in Railway, then add **both** DNS records Railway supplies (`CNAME` and verification `TXT`). Wait for HTTPS and `/up` to work before building the frontends.

Use `.env.production.example` as the variable inventory. Key non-secret settings for `example.com` are:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
FRONTEND_URL=https://shop.example.com
CORS_ALLOWED_ORIGINS=https://shop.example.com,https://admin.example.com
SESSION_DRIVER=database
SESSION_DOMAIN=.example.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=shop.example.com,admin.example.com
QUEUE_CONNECTION=database
CACHE_STORE=database
DB_QUEUE_RETRY_AFTER=120
ANALYTICS_ALLOWED_ORIGINS=https://shop.example.com
TURNSTILE_REQUIRED=true
VNPAY_RETURN_URL=https://api.example.com/api/payment/vnpay-return
```

Set the remaining required values from `.env.production.example` privately in Railway. Do not put server secrets in any `VITE_` variable. Create a dedicated staging admin, verify its email, and enroll MFA; do not seed QA accounts in production mode.
Replace the domain placeholders inside `SECURITY_CSP` as well. Configure a working mail provider before testing email verification, and use separate sandbox credentials for VNPay.

## Cloudflare Pages: Storefront and Admin

Create two Pages projects connected to `main`:

| Project | GitHub repository | Build command | Output directory |
| --- | --- | --- | --- |
| Storefront | `khanhlinhcode/Farta_Market` | `npm run build` | `build` |
| Admin | `khanhlinhcode/websivi-admin` | `npm run build` | `build` |

Set Node.js to a version supported by the lockfile/Vite (at least 22.12). Set these build-time public variables, then rebuild after any change:

```dotenv
# Storefront
VITE_API_URL=https://api.example.com/api
VITE_SITE_URL=https://shop.example.com
VITE_ANALYTICS_ENABLED=true
VITE_TURNSTILE_SITE_KEY=<public site key>

# Admin
VITE_API_URL=https://api.example.com/api
VITE_STOREFRONT_URL=https://shop.example.com
```

Associate `shop.example.com` and `admin.example.com` with their Pages projects using Pages **Custom domains** before relying on their DNS records. Keep SPA fallback to `index.html` and verify deep-link refreshes.

## Data and acceptance checks

After the API is healthy, run `php artisan migrate:status` in the Railway API environment. For a fresh staging database, import only reviewed public catalog/CMS data if sample images are needed; keep that dump outside Git. Verify product and banner Cloudinary URLs before testing uploads. Never assume a local database migration or image upload is included in a Git push.

Run these checks through the actual staging domains:

1. Open Storefront and Admin on HTTPS; refresh a deep link on each.
2. Sign in to Admin, complete MFA, reload the page, and sign out. Confirm wrong-role access is rejected.
3. Create a temporary product, upload/replace/delete its Cloudinary image, and verify Storefront reflects each change; then remove the temporary data.
4. Edit a banner and a site setting, verify Storefront updates, then restore the original values.
5. Place a test COD order, advance statuses, inspect customer order history, and check that the worker processes email. Test VNPay only with sandbox credentials and a signed callback.
6. Confirm guest checkout Turnstile, analytics, queue health, and scheduler logs. Check API security headers and CORS from the two allowed origins.

Keep a database backup before importing data. If a deployment fails, roll back the affected Git/Pages/Railway deployment; restore the database only from a reviewed backup compatible with the running code. Do not delete the pre-history-rewrite Git backup until staging is verified.

Railway trial credit is temporary; monitor resource usage before committing to an always-on setup. Cloudflare Pages static hosting has a free tier, subject to current provider limits.

Provider references: [Laravel Sanctum SPA authentication](https://laravel.com/docs/12.x/sanctum#spa-authentication), [Railway Laravel deployment](https://docs.railway.com/guides/laravel), [Railway custom domains](https://docs.railway.com/networking/domains/working-with-domains), and [Cloudflare Pages custom domains](https://developers.cloudflare.com/pages/configuration/custom-domains/).
