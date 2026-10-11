# API compatibility and deprecation matrix

The canonical routes remain backward compatible during the configured sunset
window. Legacy aliases return `Deprecation`, `Sunset`, and
`Link: rel="successor-version"` headers.

Approved sunset date: **2027-04-30**. Guest COD checkout now uses the
`farta_guest_checkout` opaque HttpOnly cookie. `POST /api/guest-checkout/binding`
prepares it before checkout. Until the sunset date, a request without the cookie
is issued one automatically and receives the same deprecation headers; after the
sunset it fails with HTTP 428. The server stores only an HMAC hash with a bounded
TTL, rotates expired bindings, and never uses the client IP as the idempotency
scope.

| Legacy route | Canonical route | Current client usage |
| --- | --- | --- |
| `GET /api/user` | `GET /api/me` | No current first-party client usage found |
| `PUT /api/profile` | `PATCH /api/profile` | Storefront migrated to canonical route |
| `POST /api/profile/change-password` | `PATCH /api/profile/password` | Storefront migrated to canonical route |
| `GET /api/admin/orders/export` | `GET /api/admin/orders/export.csv` | Admin portal already uses canonical route |

Web and mobile registration, login, verification and password-reset endpoints
are intentionally separate because one contract is cookie/session based and the
other is stateless bearer-token based; they are not treated as duplicate aliases.

Identity and order responses now pass through explicit allowlist resources.
Money calculations use integer VND in application code. Additive `*_vnd`
columns are the canonical persistence layer, while existing decimal columns are
dual-written and decimal-string response fields are retained during the
compatibility window. No decimal column is dropped by this migration.
