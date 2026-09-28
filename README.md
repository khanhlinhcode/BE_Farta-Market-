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
and adds `message`, `intent`, `decision_state`, verified `products`,
`suggested_actions`, and an opaque conversation ID. The legacy `action` field is always inert. The
Storefront changes its session cart only after the customer clicks a visible
add-to-cart button.

Common configuration:

```dotenv
AI_CHAT_ENABLED=true
AI_CHAT_ORCHESTRATION=router
AI_PRODUCT_SEARCH_MODE=database
AI_SEMANTIC_ROUTER_ENABLED=false
AI_SEMANTIC_ROUTER_MIN_CONFIDENCE=0.75
AI_VECTOR_SEARCH_ENABLED=false
AI_KNOWLEDGE_GENERATION_ENABLED=false
AI_QUERY_EXPANSION_ENABLED=false
AI_CHAT_PROMPT_VERSION=grounded-commerce-v3
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

High-confidence security and business rules run before an optional semantic
intent classifier. The classifier returns a strict, fixed intent/entity schema;
it cannot call tools or grant access. Low-confidence, unavailable, or unrelated
classification returns a clarification question instead of defaulting to
product search. Clear shipping, catalog, product, cart and order intents work
with `AI_SEMANTIC_ROUTER_ENABLED=false`. Phase 14 kept that fallback disabled
because no live, versioned classifier was available for a comparable benchmark.
Requests spanning more than one domain become explicit bounded branch frames;
failed or denied branches do not erase safe siblings, and no agent loop is used.

Denied capabilities are evaluated before multi-intent and ordinary intent
routing. Order/payment mutation, authentication bypass, prompt disclosure,
stock override, and account/role mutation return a deterministic denial without
calling a model or a business mutation handler.

Deterministic intent classification and entity extraction are separate. A
shared extractor handles bounded Vietnamese/English whole-number quantities and
candidate product spans. A focused canonicalizer resolves them to active MySQL
identities before dispatch, and handlers revalidate current business state.
Bounded context can retain at most five ordered canonical product IDs and one
category reference for five minutes; it never caches price, stock, order, or
payment state.

Current Phase 14 status is **LOCAL CANDIDATE FROZEN; NOT APPROVED FOR
STAGING**. V10 is development evidence, not a release score. Its development
regression reached 99.32% intent, 99.20% macro-F1, 100% handler, 99.32% business
outcome, 100% missing-evidence safety, and zero wrong-topic, unsupported-policy,
unsafe-execution, or wrong-entity unsafe-action signals. A new independently
authored and audited V11 is required before any staging claim. See
[docs/chat-phase-14-v10-root-cause-remediation.md](docs/chat-phase-14-v10-root-cause-remediation.md)
for the candidate identity, full metrics, QA, and remaining risks.

Groq responses use strict JSON schemas. Laravel reloads every product before
returning its name, price, or inventory. Only authenticated, email-verified
customers can receive cart proposals or submit cart context; guests still browse
and chat but receive `AUTH_REQUIRED_FOR_CART`. Order queries remain customer-only
and owner-scoped.

Current shipping fee/free-shipping threshold bypass RAG and are read directly
from `SiteSetting`. Catalog listing likewise reads active products from MySQL,
and cart suggestions are revalidated against MySQL without mutating the cart.
Knowledge RAG handles FAQ/policy/guide questions, published curated documents,
and relevant dynamic contact facts. Sparse retrieval is the local default.
Optional `aliases` and `sample_questions` improve retrieval only: they are
stored in `retrieval_text`, while answers and citations remain limited to the
approved section `content`.
Optional dense retrieval uses Qdrant Cloud Inference with
`intfloat/multilingual-e5-small` and automatically falls back to sparse. The
dedicated collection must be named `farta_chat_knowledge` (an environment suffix
is allowed), and every point ID/payload `chunk_id` is the MySQL chunk ID. This
guard prevents the chatbot from writing to an unrelated Qdrant collection.
Knowledge candidates are constrained by the immutable route domain and allowed
source IDs in MySQL and Qdrant before dense IDs are accepted for fusion. Every
ranked result receives a final eligibility and claim-support check, so an
unrelated high-scoring vector cannot cross the authority boundary. RRF uses
`k=60`; at most five chunks are evidence. Generated knowledge answers
require strict claims, exact evidence quotes, semantic verification and at most
one repair. With generation disabled, the API returns a verified direct extract
instead of inventing prose.

Source precedence is fixed: current transactional facts come from database and
business services, approved policy/explanation comes from published knowledge,
and an LLM may only synthesize those sources. A missing topic therefore returns
`NO_EVIDENCE`; it never borrows an answer from a different policy topic.

```dotenv
QDRANT_URL=https://your-cluster.cloud.qdrant.io
QDRANT_API_KEY=
QDRANT_COLLECTION=farta_chat_knowledge
QDRANT_INFERENCE_ENABLED=true
QDRANT_INFERENCE_MODEL=intfloat/multilingual-e5-small
AI_VECTOR_SEARCH_ENABLED=true
```

When one cluster serves multiple environments, use separate collections such as
`farta_chat_knowledge_local` and `farta_chat_knowledge_staging` so identical
database chunk IDs cannot collide.

Validate and index authored documents with:

```bash
php artisan chat:knowledge:sync --dry-run
php artisan chat:knowledge:sync
```

Authoring rules are in
[docs/chat-knowledge-authoring.md](docs/chat-knowledge-authoring.md). Architecture and trust boundaries are in
[docs/chat-architecture.md](docs/chat-architecture.md); retrieval evaluation is
in [docs/chat-rag.md](docs/chat-rag.md), and the current release decision is in
[docs/chat-phase-14-v10-root-cause-remediation.md](docs/chat-phase-14-v10-root-cause-remediation.md).

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
