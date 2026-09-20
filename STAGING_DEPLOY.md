# Farta staging deployment

This runbook describes the staging environment deployed on 20 September 2026. It is not a production release checklist. Keep credentials in Northflank/Cloudflare secrets or a local private vault; never put them in Git, logs, or chat.

## Current topology

| Component | Provider | Resource | Public URL |
| --- | --- | --- | --- |
| Laravel API | Northflank, farta-staging (London) | farta-api | https://site--farta-api--45n45kqfhjvs.code.run |
| Queue worker | Northflank | farta-worker | Private |
| Scheduler | Northflank | farta-scheduler cron job, every 5 minutes | Private |
| MySQL | Northflank | farta-mysql, private networking and TLS | Private |
| Storefront | Cloudflare Pages | farta-storefront | https://farta-storefront.pages.dev |
| Admin | Cloudflare Pages | farta-admin | https://farta-admin.pages.dev |

Both Pages projects are Direct Upload projects. Their GitHub repositories hold the source code, but pushing Git does not deploy Pages. Build and upload each frontend with Wrangler after review. A Direct Upload project cannot be switched to Git integration in place; create a new Pages project if automatic Git deployment is needed.

Each Pages project uses its own _worker.js and _routes.json to proxy only /api/* and /sanctum/csrf-cookie to the fixed HTTPS API_ORIGIN runtime secret. The browser calls /api on its own Pages hostname, so Sanctum/XSRF cookies stay first-party without a custom domain. The backend remains publicly reachable at its Northflank URL. Set API_ORIGIN on both Pages projects to the full origin without an /api suffix. Never put APP_KEY, DB, Cloudinary, payment, mail, or AI secrets in a VITE_ variable.

## Inspect with CLI

    northflank list services --projectId farta-staging
    northflank list jobs --projectId farta-staging
    northflank get service --projectId farta-staging --serviceId farta-api
    northflank get service --projectId farta-staging --serviceId farta-worker
    northflank get job builds --projectId farta-staging --jobId farta-scheduler
    northflank get job runs --projectId farta-staging --jobId farta-scheduler

Do not print runtime-environment, secret groups, or addon connection details to a shared terminal. The farta-db-runtime secret group links MySQL aliases DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, and DB_PASSWORD. Its resource restrictions include API, worker, and scheduler. The farta-cloudinary-runtime, farta-vnpay-runtime, and farta-ai-runtime groups are restricted to API. API, worker, and scheduler use the same APP_KEY and QUEUE_CONNECTION=database. Only API exposes port 8080.

The worker runs bash railway/run-worker.sh and consumes emails,default queues. The cron job runs php artisan schedule:run --no-interaction every five minutes. A controlled queue probe was processed and a manual scheduler run succeeded on 20 September 2026. Recheck both after changes; queued email delivery still requires a real mail provider.

## Deploy frontends

From the storefront repository:

    VITE_API_URL=/api VITE_ANALYTICS_ENABLED=true VITE_TURNSTILE_SITE_KEY=replace-with-public-site-key npm run build
    npx wrangler pages deploy build --project-name=farta-storefront --branch=main

From the admin repository:

    VITE_API_URL=/api VITE_STOREFRONT_URL=https://farta-storefront.pages.dev npm run build
    npx wrangler pages deploy build --project-name=farta-admin --branch=main

The Pages API_ORIGIN secret is a runtime setting on each project. Verify each main alias after upload, including a deep-link refresh. Changing a build-time VITE_ value requires a rebuild and upload.

## Backend and data

Northflank builds khanhlinhcode/BE_Farta-Market- branch main with Heroku 24 buildpacks. The API health endpoint is /up. Run new migrations against staging before using a new backend revision; do not run db:seed automatically on every deployment. Check php artisan migrate:status inside the API runtime. Back up MySQL before importing or changing data, then verify the backup completed. The post-sync compressed dump `post-sync-staging-backup` completed after the catalog image and admin setup on 20 September 2026. A weekly Monday 02:30 UTC compressed dump with seven-day retention is also configured; the free account rejected a daily schedule.

This staging database was created fresh. Its public catalog has 13 products and 11 Cloudinary-hosted product images. Do not import local users, sessions, orders, payment data, or other personal information. A dedicated staging admin was created with MFA; its generated credentials and recovery codes exist only in a private local file outside all repositories. Its email was marked verified for staging because no mail provider is configured. Do not copy this shortcut or account into production.

The Northflank MySQL credentials exposed during setup should be rotated before any real customer data is used. A prior attempt to rotate via the addon API was rejected because the feature is unavailable for this account; use a supported provider rotation path or recreate the addon with fresh credentials and migrate reviewed data. Historical credential exposure also requires rotation of affected Cloudinary, payment, mail, and AI secrets. Rewriting Git history does not revoke them.

## Acceptance checks and remaining integrations

1. Confirm API /up, Storefront /, and Admin / return 200 over HTTPS. Storefront /api/products must show 13 products; all 11 image URLs must load from Cloudinary.
2. On each Pages hostname, request /sanctum/csrf-cookie. Browser cookies must be Secure, SameSite Lax, and scoped to that Pages hostname. A deliberately wrong login should return 401, not 419. Admin login must complete MFA and persist after reload.
3. Repeat product create/upload/replace/delete through Admin; confirm Storefront reflects changes and remove QA data. Inspect Cloudinary cleanup after deletion.
4. Run backend tests/Pint/Composer validate and audit, and each frontend's unit tests, Playwright E2E, build, and npm audit before pushing. Check git status, GitHub Actions, and deployed revisions.
5. Verify worker database connectivity and consumption of a controlled queue job. Verify a successful scheduler run and inspect failed jobs.
6. Cloudflare Turnstile is enabled for guest checkout on farta-storefront.pages.dev. Its secret is stored outside Git and injected only into the API; the public site key is included only at storefront build time. A fake token returns 422 without changing inventory. On 21 September 2026, a normal-browser Turnstile challenge completed and a guest COD checkout created a pending order, cleared the cart, and decremented inventory. The QA order was then cancelled and deleted through a database transaction; the final checks showed zero QA orders, zero total orders, and the product inventory restored from 29 to 30. Configure outbound mail and test email verification plus queued mail. The local SMTP entries are placeholders and a safe authentication probe returned SMTP 535, so they were not copied to staging; staging continues to use the log mailer.
7. VNPay sandbox credentials are restricted to API. CLI smoke tests created a hosted payment URL, loaded the sandbox page, rejected a signed failed payment, accepted a signed successful payment, and verified the final failed/paid order states. Each QA order and user was removed after inventory restoration. Complete one hosted sandbox payment through the normal browser UI before production.
8. Anthropic credentials and a verified model are restricted to API, and /api/chat/health returns 200. Catalog-only replies still work. A real inference request currently returns the safe 503 fallback because the Anthropic account has insufficient credit; add credit and repeat the grounded recommendation test before enabling AI for users.
9. The completed post-sync data was exported through the API runtime and restored into an isolated local MySQL database. The drill verified 41 migrations, 13 products, 5 categories, 11 product images, and 0 orders, then deleted the temporary database and dump. Keep the production database private and monitor provider backup, usage, and quota alerts.

Until mail delivery, one hosted VNPay sandbox payment, funded AI inference, and credential rotation are complete, this is a staging/demo environment, not production-ready.

Provider references: [Northflank CLI](https://northflank.com/docs/v1/application/getting-started/use-the-cli), [Northflank jobs](https://northflank.com/docs/v1/application/run/run-an-image-once-or-on-a-schedule), [Cloudflare Pages Direct Upload](https://developers.cloudflare.com/pages/get-started/direct-upload/), [Cloudflare Pages Functions](https://developers.cloudflare.com/pages/functions/), and [Laravel Sanctum SPA authentication](https://laravel.com/docs/12.x/sanctum#spa-authentication).
