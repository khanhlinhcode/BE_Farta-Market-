# Farta Market staging deployment report

Last verified: 24 September 2026 (Asia/Ho_Chi_Minh).

This document describes the currently deployed public staging/demo environment.
It is not a production-release declaration. Store credentials only in provider
secret stores or a private local vault; never put passwords, cookies, MFA data,
API keys, webhook secrets, database values, or provider tokens in Git or chat.

## Current topology

| Component | Provider/resource | Public URL | Operational role |
| --- | --- | --- | --- |
| Storefront | Cloudflare Pages `farta-storefront` | <https://fartamarket.company> | Public staging/demo |
| Admin | Cloudflare Pages `farta-admin` | <https://admin.fartamarket.company> | Public staging/demo |
| Laravel API | Northflank `farta-staging` / `farta-api` | <https://api.fartamarket.company> | Public staging API |
| Queue worker | Northflank `farta-worker` | Private | `emails,default` queues |
| Scheduler | Northflank `farta-scheduler` | Private | `schedule:run` every five minutes |
| MySQL | Northflank `farta-mysql` | Private/TLS | Staging data only |

The Cloudflare deployments use the `main` alias, so Cloudflare labels them
`Production`. The Pages projects and custom domains are nevertheless being used
as this project's staging/demo environment. Provider labels must not be used as
evidence of application production readiness.

Both Pages projects are Direct Upload projects. Git push and pull-request merge
do not deploy them automatically. Each project uses its own `_worker.js` and
`_routes.json` to proxy only `/api/*` and `/sanctum/csrf-cookie` to an HTTPS
`API_ORIGIN` runtime secret. The browser therefore calls its current frontend
origin while the worker forwards the request to the API.

## Git and deployed revisions

The reviewed feature branches were merged into `main` on 24 September 2026:

| Repository | `main` merge commit | Current staging artifact/source |
| --- | --- | --- |
| Backend | `f782d83` | Northflank build `08fcff7` |
| Storefront | `7dcf7d2` | Pages Direct Upload source `315e90d` |
| Admin | `68ba1a7` | Pages Direct Upload source `8fb9d3c` |

The staged artifact commits are ancestors of the merge commits and contain the
same reviewed application changes. For release traceability, rebuild and deploy
from the final `main` commits before declaring a production release.

Northflank API, worker, and scheduler currently track
`security/auth-mail-hardening-20260922`. Change their source branch to `main`
only as a controlled deployment step, then verify migrations, worker, scheduler,
health checks, and rollback readiness.

## Verified runtime state

Read-only runtime checks on 24 September 2026 confirmed:

- `farta-api` build status `SUCCESS` and deployment status `COMPLETED`.
- `farta-worker` build status `SUCCESS` and deployment status `COMPLETED`.
- Laravel runs with production safety defaults inside the staging project.
- `SESSION_DRIVER=database` and effective `session.encrypt=true`.
- Default mailer is SMTP and queues use the database connection.
- Storefront, Admin, and `/up` return HTTP 200 with HSTS.
- Storefront can load the product API and Cloudinary-backed catalog data.
- Product-detail loading uses a stable accessible skeleton without horizontal
  overflow at the tested desktop and mobile widths.
- Backend, Storefront, and Admin GitHub Actions on their merged `main` commits
  completed successfully.

Latest local verification on the same code line:

| Check | Result |
| --- | --- |
| Backend Pest | 252 tests, 1,444 assertions — PASS |
| Backend Pint | 164 files — PASS |
| Composer validate/audit | Valid; no security advisories |
| Storefront Vitest | 24 files, 89 tests — PASS |
| Storefront build/audit | PASS; 0 vulnerabilities |
| Admin Vitest | 12 files, 39 tests — PASS |
| Admin build/audit | PASS; 0 vulnerabilities |
| Storefront/Admin Playwright on merged `main` | GitHub Actions — PASS |

The latest Cloudflare Direct Uploads are:

| Project | Deployment ID | Source |
| --- | --- | --- |
| `farta-storefront` | `03688cd4-7315-4b54-acef-43329cc60fc4` | `315e90d` |
| `farta-admin` | `76ed1529-e5f4-485e-852e-48d16e99c4fc` | `8fb9d3c` |

## Completed application work

- Sanctum session authentication, CSRF protection, secure/HTTP-only/SameSite
  cookies, encrypted database session payloads, and a 120-minute runtime session
  lifetime.
- Registration, login, email verification, password recovery, and user refresh
  after verification.
- MFA for every Admin portal account while customers remain outside that flow.
- Admin/staff/customer authorization and order-ownership protection.
- Product, category, banner, CMS content, image, order, user, review, coupon, and
  analytics management.
- Cloudinary media lifecycle for product, category, banner, and avatar images.
- Scoped order/payment idempotency, inventory locks, double-submit protection,
  and scheduled expiration of stale online payments.
- COD and SePay VietQR application flows. VNPay is no longer offered for new
  payments; its enum value remains only to read historical orders safely.
- Grounded AI recommendations whose model-proposed product IDs are reloaded from
  the database before product facts or cart actions are returned.
- Rate limits, restrictive CORS, CSP, HSTS, security headers, CSV-injection
  protection, and bounded analytics identifiers.
- Storefront `sessionStorage` cart with a 60-minute expiry and logout cleanup.
- Route/product skeletons, image lazy loading, and stable footer placement.

## Database rollout

Before the SePay migration, the Northflank MySQL backup
`pre-sepay-20260924` completed successfully. Migration
`2026_09_23_000002_add_sepay_payment_fields_to_orders_table` then ran in staging
batch 3.

The migration adds SePay reference, expiry, and provider transaction fields and
preserves the `vnpay` enum value for historical rows. Do not automatically
rewrite or delete historical payment records.

The staging database was created separately and must not receive copied
production users, sessions, payment data, or other personal information. A
dedicated MFA-protected staging administrator exists; its credential and
recovery material are stored outside every repository.

## SePay status and required setup

The code, UI, CSP allowance for `https://vietqr.app`, migration, webhook route,
payment-status polling, expiration command, and automated tests are deployed.

The effective Northflank API runtime currently has no SePay bank code, account
number, or webhook secret. This is intentional until Test Mode setup is complete.
Payment creation and webhook handling therefore fail closed rather than creating
an unverifiable payment.

Required backend-only variables:

```dotenv
SEPAY_BANK_CODE=
SEPAY_ACCOUNT_NUMBER=
SEPAY_ACCOUNT_HOLDER=
SEPAY_WEBHOOK_SECRET=
SEPAY_PAYMENT_PREFIX=FM
SEPAY_PAYMENT_TTL_MINUTES=30
SEPAY_WEBHOOK_TOLERANCE_SECONDS=300
SEPAY_QR_BASE_URL=https://vietqr.app/img
```

Do not put these values in either frontend build. Configure SePay Test Mode as
follows:

1. Create an active payment-code rule with prefix `FM`, an alphanumeric suffix,
   minimum length 7, and maximum length 30.
2. Create an inbound-only JSON webhook with retries enabled:

   ```text
   https://api.fartamarket.company/api/payment/sepay/webhook
   ```

3. Select the dedicated SePay Test Mode account and enable payment verification
   for that account.
4. Select HMAC-SHA256, generate the secret, and save it immediately in a private
   local vault and an API-restricted Northflank secret group. Never paste it into
   a report, issue, commit, or chat.
5. Restart only the affected API service, then verify configuration presence
   without printing any value.
6. Create a disposable verified customer order and simulate one inbound transfer
   with the exact total and `FM...` reference displayed by checkout.
7. Require webhook HTTP 200, a server-confirmed paid order, successful status
   polling, and cart cleanup only after confirmation.

The webhook requires `X-SePay-Timestamp` and `X-SePay-Signature`, checks request
freshness, uses a constant-time HMAC comparison, and verifies transfer direction,
recipient account, reference, amount, and transaction uniqueness before changing
payment state.

## Deployment procedure

### Backend

1. Back up MySQL before schema or payment changes and wait for completion.
2. Confirm the intended commit and source branch for API, worker, and scheduler.
3. Deploy and wait for build/deployment completion.
4. Run `php artisan migrate:status`; apply reviewed pending migrations once.
5. Confirm non-secret runtime state, `/up`, queue consumption, and a scheduler
   run. Never dump runtime environment or secret-group values to shared logs.

Useful read-only commands:

```bash
northflank get service \
  --projectId farta-staging \
  --serviceId farta-api

northflank get service \
  --projectId farta-staging \
  --serviceId farta-worker

northflank get job runs \
  --projectId farta-staging \
  --jobId farta-scheduler
```

### Storefront

```bash
VITE_API_URL=/api \
VITE_ANALYTICS_ENABLED=true \
VITE_TURNSTILE_SITE_KEY=replace-with-public-site-key \
npm run build

npx wrangler pages deploy build \
  --project-name=farta-storefront \
  --branch=main
```

### Admin

```bash
VITE_API_URL=/api \
VITE_STOREFRONT_URL=https://fartamarket.company \
npm run build

npx wrangler pages deploy build \
  --project-name=farta-admin \
  --branch=main
```

Verify each custom domain and at least one deep-link refresh after Direct Upload.
Changing a build-time `VITE_` value requires a new build and upload. Changing
`API_ORIGIN` does not require placing the backend origin in the JavaScript bundle.

## Verification commands

Backend:

```bash
php artisan test
vendor/bin/pint --test
composer validate --strict
composer audit
```

Storefront and Admin, from their respective repositories:

```bash
npm test
npm run test:e2e
npm run build
npm audit --audit-level=high
```

Neither frontend currently defines a `lint` or `typecheck` script. Report those
checks as unavailable rather than inventing commands.

## Remaining production gates

1. Configure SePay Test Mode with the real HMAC secret and complete the full
   simulated-payment flow described above.
2. Run a deployed-domain customer smoke test: new Gmail registration, email
   verification, login, COD order, order view, and cancellation.
3. Verify Admin MFA, one recovery-code login, and Cloudinary
   upload/replace/delete on the deployed domain; remove all QA data afterward.
4. Review possible duplicate orders created before scoped idempotency. Do not
   bulk-delete records because inventory and payment state may be affected.
5. Rotate historically exposed database/provider credentials one provider at a
   time, verify the replacement, and revoke the old credential only afterward.
6. Point all Northflank workloads to the reviewed `main` revision and rebuild
   both Pages projects from their merged `main` commits.
7. Run all automated checks and desktop/mobile smoke tests again, prepare a
   rollback point, and release production separately.

No Strix or active penetration test is part of this rollout. Any future active
security assessment requires a separately authorized scope, disposable accounts,
rate limits, and explicit exclusion of real payment/provider side effects.

## Current conclusion

**Ready for staging verification; not yet ready to be declared production.**
