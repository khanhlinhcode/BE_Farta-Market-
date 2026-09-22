# Farta staging deployment

This runbook describes the staging environment deployed on 20 September 2026. It is not a production release checklist. Keep credentials in Northflank/Cloudflare secrets or a local private vault; never put them in Git, logs, or chat.

## Current topology

| Component | Provider | Resource | Public URL |
| --- | --- | --- | --- |
| Laravel API | Northflank, farta-staging (London) | farta-api | https://api.fartamarket.company |
| Queue worker | Northflank | farta-worker | Private |
| Scheduler | Northflank | farta-scheduler cron job, every 5 minutes | Private |
| MySQL | Northflank | farta-mysql, private networking and TLS | Private |
| Storefront | Cloudflare Pages | farta-storefront | https://fartamarket.company |
| Admin | Cloudflare Pages | farta-admin | https://admin.fartamarket.company |

Both Pages projects are Direct Upload projects. Their GitHub repositories hold the source code, but pushing Git does not deploy Pages. Build and upload each frontend with Wrangler after review. A Direct Upload project cannot be switched to Git integration in place; create a new Pages project if automatic Git deployment is needed.

On 22 September 2026, the Cloudflare Pages custom domains became active. In the Cloudflare DNS zone, the proxied apex CNAME points to `farta-storefront.pages.dev` and the proxied `admin` CNAME points to `farta-admin.pages.dev`; these CNAME targets are required for Pages to serve the custom domains and are not customer-facing URLs. The `api` CNAME points to Northflank. An account-level Cloudflare Bulk Redirect rule returns `301` from both legacy `pages.dev` hostnames to their custom domains while preserving the path and query string. The API CORS, Sanctum, and analytics allowlists now contain only the custom frontend domains, and the Turnstile widget is restricted to `fartamarket.company`. Keep both Pages projects: deleting either project would also remove the site behind its custom domain.

Each Pages project uses its own _worker.js and _routes.json to proxy only /api/* and /sanctum/csrf-cookie to the fixed HTTPS API_ORIGIN runtime secret. The browser calls /api on its own storefront or admin hostname, so Sanctum/XSRF requests stay same-origin. The backend remains publicly reachable at its Northflank URL and at api.fartamarket.company. Set API_ORIGIN on both Pages projects to the full origin without an /api suffix. Never put APP_KEY, DB, Cloudinary, payment, mail, or AI secrets in a VITE_ variable.

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

    VITE_API_URL=/api VITE_STOREFRONT_URL=https://fartamarket.company npm run build
    npx wrangler pages deploy build --project-name=farta-admin --branch=main

The Pages API_ORIGIN secret is a runtime setting on each project. Verify each main alias after upload, including a deep-link refresh. Changing a build-time VITE_ value requires a rebuild and upload.

## Backend and data

Northflank builds khanhlinhcode/BE_Farta-Market- branch main with Heroku 24 buildpacks. The API health endpoint is /up. Run new migrations against staging before using a new backend revision; do not run db:seed automatically on every deployment. Check php artisan migrate:status inside the API runtime. Back up MySQL before importing or changing data, then verify the backup completed. The post-sync compressed dump `post-sync-staging-backup` completed after the catalog image and admin setup on 20 September 2026. A weekly Monday 02:30 UTC compressed dump with seven-day retention is also configured; the free account rejected a daily schedule.

This staging database was created fresh. Its public catalog has 13 products and 11 Cloudinary-hosted product images. Do not import local users, sessions, orders, payment data, or other personal information. A dedicated staging admin was created with MFA; its generated credentials and recovery codes exist only in a private local file outside all repositories. Its email was marked verified for staging because no mail provider is configured. Do not copy this shortcut or account into production.

The Northflank MySQL credentials exposed during setup should be rotated before any real customer data is used. A prior attempt to rotate via the addon API was rejected because the feature is unavailable for this account; use a supported provider rotation path or recreate the addon with fresh credentials and migrate reviewed data. Historical credential exposure also requires rotation of affected Cloudinary, payment, mail, and AI secrets. Rewriting Git history does not revoke them.

## Acceptance checks and remaining integrations

### Authentication/mail hardening rollout (22 September 2026)

The `security/auth-mail-hardening-20260922` branches contain password recovery,
session encryption preparation, and HTTP header changes. They are **not** the
currently deployed revisions. Northflank backup `auth-hardening-20260922`
completed before this rollout; retain it until staging has worked for at least
24 hours. The local 41 migrations are all applied; check the staging migration
status separately in the API runtime.

Before deploying these branches, verify `fartamarket.company` with Resend and
install exactly the DNS records it provides in the authoritative DNS zone. Check
SPF and DKIM, add a monitoring DMARC record, and use a mailbox you control for
the delivery test. Create `farta-mail-runtime` with SMTP variables and restrict
it to the sending service(s). Keep `MAIL_PASSWORD` in the secret group only. Do
not enable the SMTP mailer until the domain and credential work; the old staging
log mailer is deliberately unable to deliver password resets.

After all local and CI checks pass, deploy the backend and both Pages builds to
staging, confirm email registration/verification/recovery, then enable
`SESSION_ENCRYPT=true` with database sessions, secure/HTTP-only/Lax cookies and
the narrowest cookie domain compatible with the same-origin proxy. Expire old
sessions and verify customer login/logout and admin MFA again. Confirm HSTS on
HTML, assets, proxied API responses, and `/up`; the two frontends did not return
HSTS before this deployment. Do not merge to main or claim production readiness
until runtime checks pass.

Rotate exposed credentials one provider at a time: create replacement, update
only the restricted service secret group, redeploy and verify, then revoke the
old credential. Rotate `APP_KEY` last, simultaneously for API, worker and
scheduler. Put the former key in `APP_PREVIOUS_KEYS` temporarily, run
`php artisan security:reencrypt-user-secrets --dry-run`, then the write command;
verify admin MFA and one-time recovery codes. Remove the previous key only
after the command and MFA checks succeed, redeploy all three workloads, and
expire prior sessions. On a failure, restore the previous key and stop the
rollout; never output any key or plaintext MFA data. The re-encryption command
rolls back its writes on failure.

The free Northflank plan previously rejected in-place MySQL credential rotation.
If that remains true, prepare a fresh private/TLS addon and test a restore before
switching connections; do not revoke the working credential first. Run an
isolated restore drill for the new backup before destructive changes. Record
backup/deployment IDs and rotation timestamps without storing credential values.

1. Confirm API /up, Storefront /, and Admin / return 200 over HTTPS. Storefront /api/products must show 13 products; all 11 image URLs must load from Cloudinary.
2. On each custom hostname, request /sanctum/csrf-cookie. Browser cookies must be Secure and SameSite Lax; the session cookie uses the shared `fartamarket.company` domain. A deliberately wrong login should return 401, not 419. Admin login must complete MFA and persist after reload.
3. Repeat product create/upload/replace/delete through Admin; confirm Storefront reflects changes and remove QA data. Inspect Cloudinary cleanup after deletion.
4. Run backend tests/Pint/Composer validate and audit, and each frontend's unit tests, Playwright E2E, build, and npm audit before pushing. Check git status, GitHub Actions, and deployed revisions.
5. Verify worker database connectivity and consumption of a controlled queue job. Verify a successful scheduler run and inspect failed jobs.
6. Cloudflare Turnstile is enabled for guest checkout only on fartamarket.company. Its secret is stored outside Git and injected only into the API; the public site key is included only at storefront build time. A fake token returns 422 without changing inventory. On 21 September 2026, a normal-browser Turnstile challenge completed on the former Pages hostname and a guest COD checkout created a pending order, cleared the cart, and decremented inventory. The QA order was then cancelled and deleted through a database transaction; the final checks showed zero QA orders, zero total orders, and the product inventory restored from 29 to 30. Repeat this browser checkout check on the custom domain before production. Configure outbound mail and test email verification plus queued mail. The local SMTP entries are placeholders and a safe authentication probe returned SMTP 535, so they were not copied to staging; staging continues to use the log mailer.
7. VNPay sandbox credentials are restricted to API. CLI smoke tests created a hosted payment URL, loaded the sandbox page, rejected a signed failed payment, accepted a signed successful payment, and verified the final failed/paid order states. Each QA order and user was removed after inventory restoration. Complete one hosted sandbox payment through the normal browser UI before production.
8. Groq inference is configured for the API. On 22 September 2026, a real recommendation through the Storefront proxy returned `source: ai`; an unsupported query returned `source: catalog`, and an explicit purchase request still returned `add_to_cart`. Repeat the inference smoke test after changing the key, model, catalog, or backend revision. The recommendation path now uses bounded sparse retrieval; see [docs/chat-rag.md](docs/chat-rag.md).
9. The completed post-sync data was exported through the API runtime and restored into an isolated local MySQL database. The drill verified 41 migrations, 13 products, 5 categories, 11 product images, and 0 orders, then deleted the temporary database and dump. Keep the production database private and monitor provider backup, usage, and quota alerts.

## Groq chat runtime

The backend uses Groq with `openai/gpt-oss-20b` and strict JSON output. The storefront requires no rebuild for this backend-only change. Keep these **runtime** variables in an API-restricted Northflank secret group (never in Git or a `VITE_` variable):

    AI_CHAT_DRIVER=groq
    AI_CHAT_MODEL=openai/gpt-oss-20b
    AI_CHAT_BASE_URL=https://api.groq.com/openai/v1
    AI_CHAT_TIMEOUT=15
    GROQ_API_KEY=<paste-your-key-in-Northflank-only>

Check for conflicting `AI_CHAT_*` variables in the API service and other secret groups; the effective model and base URL must match Groq. Restart/redeploy only `farta-api` after saving the runtime variables. Do not inject the Groq key into the worker or scheduler. Test `GET /api/chat/health` and a recommendation query through the Storefront proxy. The health endpoint checks key/model availability, **not** remaining inference quota. If Groq rejects a request or its quota is exhausted, catalog questions and add-to-cart still use the database, while open-ended recommendations return a safe `catalog_fallback` response. Confirm a successful recommendation returns `source: ai` before claiming that AI inference works. Remove unused Anthropic variables from the API runtime and revoke any exposed keys.

Until mail delivery, one hosted VNPay sandbox payment, and credential rotation are complete, this is a staging/demo environment, not production-ready.

Provider references: [Northflank CLI](https://northflank.com/docs/v1/application/getting-started/use-the-cli), [Northflank jobs](https://northflank.com/docs/v1/application/run/run-an-image-once-or-on-a-schedule), [Cloudflare Pages Direct Upload](https://developers.cloudflare.com/pages/get-started/direct-upload/), [Cloudflare Pages Functions](https://developers.cloudflare.com/pages/functions/), and [Laravel Sanctum SPA authentication](https://laravel.com/docs/12.x/sanctum#spa-authentication).
