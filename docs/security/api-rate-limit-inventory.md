# API rate-limit inventory

Status: implemented in the current working tree. All routes in the `api`
middleware group receive the default `api` limiter. Route-specific limiters are
additional, stricter controls.

| Route family | Classification | Identity | Additional limiter |
| --- | --- | --- | --- |
| `/api/products*`, `/api/categories*`, `/api/site-content`, public reviews | Public read | Browser: hashed client IP + server session; stateless: hashed client IP | Product suggest/recommendation/relationship reads use `expensive-read` |
| `/api/register`, `/api/mobile/register` | Authentication mutation | Hashed account plus client IP | `register` |
| `/api/login`, `/api/mobile/login`, `/api/admin/login` | Authentication mutation | Hashed account plus client IP | `user-login` or `admin-login` |
| Forgot/reset password | Authentication mutation | Hashed account plus hashed client IP | `forgot-password` or `reset-password` |
| MFA setup/confirm/challenge | Privileged authentication mutation | Session plus client IP | `mfa-challenge` |
| Email verification links/resend | Authentication mutation | User ID, otherwise client IP | Signed URL limiter or `verification-resend` |
| `/api/profile*`, addresses, wishlist, customer reviews | Authenticated read/mutation | Authenticated user ID | Profile, password and upload routes retain their stricter limiter |
| `/api/order` | Checkout mutation | User ID or guest IP/account dimensions | `guest-orders` plus `authenticated-checkout` |
| `/api/payment/create`, payment status | Payment mutation/read | Authenticated user ID | Checkout/status-specific limiter |
| `/api/payment/sepay/webhook` | Webhook mutation | Default API identity | `120,1`; HMAC verification remains authoritative |
| `/api/chat*` | Expensive public operation | Hashed client IP plus global bucket | `chat` or `30,1` |
| Analytics issue/store | Public mutation | Hashed client IP/token plus global bucket | `analytics-session` or `analytics` |
| Admin dashboard/media/catalog/settings/reviews/orders/coupons | Privileged read/mutation | Authenticated user ID | Default API ceiling; uploads retain `uploads` |
| Admin order CSV export | Privileged expensive read | Authenticated user ID | `admin-export` |

The default ceiling is 120 requests/minute per authenticated user or anonymous
client identity, 600/minute per anonymous browser IP, and 2000/minute globally.
This keeps browser sessions behind one shared proxy isolated while retaining an
aggregate IP ceiling against session rotation. Expensive public reads use
30/minute per client, 120/minute per anonymous browser IP, and 600/minute
globally.

Default values are environment-backed in `config/security.php`. Production must
set `TRUSTED_PROXIES` to the exact reverse-proxy IP/CIDR allowlist. An empty value
is fail-closed: forwarded client headers are ignored.
