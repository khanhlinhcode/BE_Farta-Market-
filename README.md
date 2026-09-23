# Farta Market API

Laravel 12 API for the Farta Market storefront and administration portal. It
owns authentication, catalog data, orders, payments, Cloudinary media,
analytics, coupons, reviews, and a Grounded Conversational AI Assistant.

## Current release status

The reviewed authentication, storefront loading, and SePay integration changes
were merged into `main` on 24 September 2026. The public domains are currently
used as a staging/demo environment, not as a completed production release.

- Storefront: <https://fartamarket.company>
- Admin: <https://admin.fartamarket.company>
- API health: <https://api.fartamarket.company/up>
- Deployment details and remaining gates: [STAGING_DEPLOY.md](STAGING_DEPLOY.md)

SePay code and its database migration are deployed to staging, but the staging
runtime does not yet contain the real bank-account and webhook-secret settings.
Until those settings and an end-to-end simulated payment are verified, SePay
checkout intentionally fails closed with HTTP `503`.

## Main capabilities

- Laravel Sanctum cookie authentication with CSRF protection.
- Encrypted database-backed sessions, password recovery, email verification,
  and separate Admin MFA using TOTP or one-time recovery codes.
- Admin, staff, and customer authorization with order-ownership checks.
- Product, category, banner, site-content, review, coupon, user, order, and
  analytics APIs.
- Cloudinary-backed product, category, banner, and avatar media. Admins can
  browse and reuse product/category/banner images without exposing Cloudinary
  provider identifiers; avatars remain private to their owner records.
- COD and SePay VietQR checkout with scoped idempotency keys and inventory
  locking. VNPay values remain readable only for historical orders.
- Pending online-payment expiration with inventory restoration.
- Grounded Conversational AI Assistant with explicit intent routing,
  database-verified product cards, read-only cart/order context, and
  user-confirmed cart proposals.
- Rate limits, CORS allowlists, security headers, CSV-injection protection, and
  bounded analytics/session retention.

## Local development

Requirements: PHP 8.2 or newer, Composer, MySQL, and Node.js when Laravel's Vite
assets are required.

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Run the database queue worker and scheduler in separate terminals when testing
queued mail or scheduled cleanup:

```bash
php artisan queue:work --queue=emails,default
php artisan schedule:work
```

Use the matching local frontend origins and keep `SESSION_SECURE_COOKIE=false`
only for local HTTP development. Hosted environments must use HTTPS, secure and
HTTP-only cookies, an explicit CORS allowlist, and the correct Sanctum stateful
domains.

## Authentication and sessions

The storefront and Admin portal use Sanctum session cookies rather than JWTs.
The default project configuration uses database sessions with encrypted payloads:

```dotenv
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_LIFETIME=120
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Admin password authentication never creates a privileged session by itself. An
admin must complete the MFA enrollment or challenge before Admin routes become
available. Customer accounts are not sent through the Admin MFA flow.

## Payments

### COD

`POST /api/order` creates COD orders. Every request requires a unique
`X-Idempotency-Key`; replaying the same scoped key returns the existing order
instead of decrementing inventory again. Guest order creation is rate-limited
and protected by Turnstile when it is required by the environment.

### SePay VietQR

Authenticated and email-verified customers create a SePay order through
`POST /api/payment/create`. The API generates the amount, transfer reference,
expiry time, and VietQR URL. The browser never marks an order as paid.

SePay calls `POST /api/payment/sepay/webhook`. The API verifies the timestamped
HMAC signature, configured recipient account, inbound transfer type, exact
amount, payment reference, and unique transaction ID inside a locked database
transaction. Customers poll `GET /api/payment/{order}/status`, which is protected
by order ownership.

Required backend-only configuration:

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

Never expose these values through a `VITE_` variable or commit them to Git. The
staging webhook URL is:

```text
https://api.fartamarket.company/api/payment/sepay/webhook
```

`php artisan payments:expire-pending` cancels stale online-payment orders and
restores reserved inventory. The scheduler runs it every ten minutes.

## Cloudinary images

Product, category, banner, and avatar uploads are stored on Cloudinary. The
application does not fall back to local image storage when Cloudinary is
unavailable.

Admin uploads accept JPG/JPEG, PNG, WEBP, GIF, and AVIF files up to 2 MB and
6000 × 6000 pixels. Product galleries contain at most eight images. The Admin
media-library endpoint lists only images already referenced by a product,
category, or banner. Reuse requests identify a managed source record; clients
cannot submit an arbitrary Cloudinary URL or `public_id`. Deleting one reference
does not delete the provider asset while another database record still uses it.

Before removing legacy source files, inspect and migrate their database
references:

```bash
php artisan images:migrate-to-cloudinary \
  --source=/absolute/path/to/websivi/public \
  --dry-run

php artisan images:migrate-to-cloudinary \
  --source=/absolute/path/to/websivi/public
```

The command is safe to rerun. It skips managed Cloudinary images, keeps source
files for rollback, and fails when a referenced legacy file cannot be migrated.

## AI assistant

The assistant keeps `/api/chat` backward-compatible through the `reply` field
and adds `message`, `intent`, verified `products`, `suggested_actions`, and an
opaque conversation ID. The legacy `action` field is always inert. The
Storefront changes its session cart only after the customer clicks a visible
add-to-cart button.

Common configuration:

```dotenv
AI_CHAT_ENABLED=true
AI_CHAT_ORCHESTRATION=router
AI_PRODUCT_SEARCH_MODE=database
AI_VECTOR_SEARCH_ENABLED=false
AI_CHAT_PROMPT_VERSION=catalog-v2
AI_CHAT_DEBUG_LOG=false
```

Local Ollama configuration:

```dotenv
AI_CHAT_DRIVER=ollama
AI_CHAT_MODEL=qwen3:4b
AI_CHAT_BASE_URL=http://127.0.0.1:11434
AI_CHAT_TIMEOUT=15
AI_CHAT_KEEP_ALIVE=30m
```

Hosted Groq configuration:

```dotenv
AI_CHAT_DRIVER=groq
AI_CHAT_MODEL=openai/gpt-oss-20b
AI_CHAT_BASE_URL=https://api.groq.com/openai/v1
GROQ_API_KEY=
```

Groq responses use a strict JSON schema and are accepted only as product-ID
suggestions. Laravel reloads every product before returning its name, price, or
inventory. Cart input accepts only product IDs and quantities. Customer order
queries require authentication, customer role, and server-side ownership. If
any provider is unavailable, the API returns a deterministic catalog fallback.

This version does not implement a provider-controlled tool loop, vector search,
Qdrant, LangChain, or LangGraph. Architecture and trust boundaries are in
[docs/chat-architecture.md](docs/chat-architecture.md); retrieval evaluation is
in [docs/chat-rag.md](docs/chat-rag.md).

Anthropic remains supported through `AI_CHAT_DRIVER=anthropic` and the matching
model, base URL, and API key.

## Local/QA seed accounts

No production credential is stored in source. Reusable accounts can be created
only in the `local` and `testing` environments:

```dotenv
SEED_ADMIN_ENABLED=true
```

| Role | Email | Password |
| --- | --- | --- |
| admin | `qa.admin@example.test` | `FartaQa12345` |
| staff | `qa.staff@example.test` | `FartaQa12345` |
| customer | `qa.customer@example.test` | `FartaQa12345` |

Custom local admin credentials use `SEED_ADMIN_NAME`, `SEED_ADMIN_EMAIL`, and a
`SEED_ADMIN_PASSWORD` of at least 12 characters. The seeder refuses to create
these QA accounts in the production application environment.

## Verification

```bash
php artisan test
vendor/bin/pint --test
composer validate --strict
composer audit
```

Tests avoid real mail, queue, breach-check, payment, and media-provider calls.
If the suite appears to hang on macOS/Homebrew PHP, check CLI OPcache first;
`php -d opcache.enable_cli=0 artisan test` should behave consistently.

## Production gates

Before declaring production readiness:

1. Configure SePay Test Mode with a real HMAC secret and complete an inbound
   simulated payment through the normal browser flow.
2. Verify Admin MFA and Cloudinary create/replace/delete operations on the
   deployed domains.
3. Repeat registration, email verification, password reset, COD order, order
   view, and cancellation with disposable accounts.
4. Review historical duplicate orders manually; do not bulk-delete records that
   may have affected inventory or payment state.
5. Rotate any historically exposed provider/database credentials and retest.
6. Point staging services at the intended `main` revisions, rerun CI and smoke
   tests, and perform a separately controlled production release.
