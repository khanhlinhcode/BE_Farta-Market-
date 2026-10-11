# Inventory reservation policy decision memo

Audit date: 2026-10-10

Status: **approved for implementation** on 2026-10-10. The user approved the
Balanced profile by instructing implementation of the remaining prompts after
the profile and exact values were presented.

## 1. Scope and authority

This memo covers inventory held by pending COD and SePay orders. Backend source
and feature tests are authoritative; the mobile contract audit is supporting
context only. No staging request was made.

Primary evidence:

- `app/Http/Controllers/OrderController.php`
- `app/Http/Controllers/PaymentController.php`
- `app/Services/OrderStatusService.php`
- `app/Console/Commands/ExpireGuestOrders.php`
- `app/Console/Commands/ExpirePendingPayments.php`
- `app/Models/Order.php`
- `app/Models/OrderDetail.php`
- `app/Models/Product.php`
- `app/Models/Coupon.php`
- `app/Providers/AppServiceProvider.php`
- `config/services.php`
- `routes/api.php` and `routes/console.php`
- `tests/Feature/OrderTest.php`
- `tests/Feature/PaymentTest.php`
- `tests/Feature/GuestCheckoutSecurityTest.php`
- mobile `docs/audit/cart-order-contract.md`

## 2. Current behavior

### Reservation creation

- COD accepts at most 20 distinct lines, 20 units per line, and 50 units in
  total. Validation occurs before the transaction
  (`OrderController.php:192-221`).
- A valid mobile Bearer must have the `mobile:customer` ability; invalid,
  expired, or revoked Bearer credentials cannot fall back to guest checkout
  (`OrderController.php:232-258`).
- Guest COD is guarded by Turnstile plus a pending-order check keyed separately
  by normalized email, phone, and IP hash (`OrderController.php:260-273,586-595`).
- Both COD and SePay serialize the same logical attempt with a cache lock and a
  database-backed idempotency record. Same key plus same payload replays the
  order; changed payload returns 409 (`OrderController.php:289-324,424-443`;
  `PaymentController.php:63-100,201-220`).
- Products are sorted and locked by ascending ID before stock is checked and
  decremented (`OrderController.php:346-371`;
  `PaymentController.php:118-143`). This protects physical inventory from
  oversell when every checkout writer follows the same locking order.
- The order row is currently inserted before the product rows are locked, but
  the surrounding transaction rolls it back on rejection
  (`OrderController.php:294-341`; `PaymentController.php:68-126`).
- Price, discount, shipping, coupon usage, and totals are server-controlled.
  Coupon usage is committed in the same transaction as the reservation
  (`OrderController.php:385-431`; `PaymentController.php:157-208`).

### Existing limits

- Guest checkout is limited to 5 requests/hour for each IP, normalized email,
  and normalized phone, plus 100 requests/minute globally
  (`AppServiceProvider.php:109-118`).
- A guest identity is rejected after 5 pending COD orders created in the last
  two hours (`OrderController.php:586-595`; coverage at
  `GuestCheckoutSecurityTest.php:178-199`).
- SePay creation is limited to 10 requests/minute by the route middleware, but
  it has no per-user reservation count, unit, or value cap
  (`routes/api.php:103-107`).
- There is no authenticated-user cap for pending orders, reserved units, or
  reserved monetary value.
- There is no aggregate unconfirmed-reservation cap per SKU. A caller may
  reserve all remaining physical inventory if request-level quantity limits
  permit it.

### Current TTL and expiration

- Guest COD gets `guest_expires_at`; the configured default is 120 minutes and
  runtime enforces a minimum of 15 minutes
  (`OrderController.php:326-340`; `config/services.php:105-112`).
- Authenticated COD gets no expiry field and therefore can hold inventory
  indefinitely while it remains pending (`OrderController.php:326-340`).
- SePay writes `payment_expires_at` using `SEPAY_PAYMENT_TTL_MINUTES`, default
  30 minutes (`PaymentController.php:182-186`; `config/services.php:81-89`).
- Guest and payment expiration commands are scheduled every ten minutes
  (`routes/console.php:14-17`). Actual release scheduler operation is an
  infrastructure requirement and was not verified here.
- Guest expiration selects only pending guest COD with an elapsed
  `guest_expires_at`, then delegates cancellation to `OrderStatusService`
  (`ExpireGuestOrders.php:20-43`).
- Payment expiration currently selects by `created_at < now - --minutes`
  instead of the stored `payment_expires_at` value
  (`ExpirePendingPayments.php:13-30,41-56`). The scheduled command uses the
  command's 30-minute default. Changing only `SEPAY_PAYMENT_TTL_MINUTES` can
  therefore make displayed/payment TTL and actual reservation expiry diverge.

### State and release behavior

- Order states are `pending`, `confirmed`, `processing`, `shipped`,
  `delivered`, and `cancelled`; payment states are `pending`, `paid`, and
  `failed` (`Order.php:14-55`).
- Inventory is reserved at order creation by decrementing `products.inventory`.
  It is restored on transition to `cancelled`; coupon usage is deleted and the
  coupon counter is decremented only if a usage row was removed
  (`OrderStatusService.php:31-70`). Locking the order row makes repeated
  cancellation fail before a second restore (`OrderStatusService.php:34-47`).
- A pending online order becomes `confirmed` only after server-side payment
  confirmation; paid/confirmed orders no longer match expiration predicates
  (`PaymentController.php:291-340`; `OrderStatusService.php:49-53`).
- Guest exact-once restoration and confirmed-order exclusion are tested
  (`GuestCheckoutSecurityTest.php:214-247`). SePay exact-once inventory restore
  is tested (`PaymentTest.php:344-365`).

### Material current gap

`ExpirePendingPayments` restores product inventory directly but does not remove
`CouponUsage` or decrement `coupons.used_count`
(`ExpirePendingPayments.php:60-83`). By contrast, normal cancellation and guest
expiration use `OrderStatusService`, which restores both inventory and coupon
usage (`OrderStatusService.php:55-70`). No inspected SePay-expiration test covers
coupon restoration. This must be fixed and regression-tested before release,
independently of the threshold values chosen below.

## 3. Abuse scenarios

1. **Authenticated reservation exhaustion:** one verified customer creates many
   pending COD or SePay orders and holds most inventory. Current per-request
   limits do not bound aggregate reservations per account.
2. **Rotating guest identity:** an attacker rotates IP, email, and phone values
   to evade identity-specific throttles and reserve a scarce SKU. There is no
   aggregate SKU ceiling.
3. **Concurrent cap bypass:** two requests for the same user or SKU both read a
   count below the limit, then reserve concurrently unless the decision and
   mutation occur under a shared database lock.
4. **Low-inventory starvation:** an otherwise small valid request reserves the
   last units of a critical SKU for the full TTL, preventing confirmed demand.
5. **Long-lived authenticated COD:** an abandoned authenticated COD order never
   expires and holds inventory until manual cancellation.
6. **TTL split-brain:** SePay instructions say one expiry while the scheduled
   command uses another cutoff.
7. **Coupon leakage on payment expiry:** an expired SePay order can release
   stock but continue consuming coupon usage.
8. **Idempotency misclassification:** a replay must return the original order
   before applying caps again; otherwise a safe network retry could be rejected
   or reserve twice.

## 4. Candidate policy options

The numbers below are calibration candidates, not production approvals. Order
distribution, inventory depth, fulfillment SLA, payment completion time, and
fraud-loss tolerance were not supplied.

| Control | Conservative | Balanced | Permissive |
| --- | ---: | ---: | ---: |
| Pending orders per authenticated user | 2 | 3 | 5 |
| Reserved units per authenticated user | 10 | 20 | 50 |
| Reserved value per authenticated user | 2,000,000 VND | 5,000,000 VND | 15,000,000 VND |
| Aggregate unconfirmed reservation per SKU | 25% of sellable supply | 50% of sellable supply | 80% of sellable supply |
| Authenticated COD TTL | 30 minutes | 120 minutes | 360 minutes |
| Guest COD TTL | 30 minutes | 60 minutes | 120 minutes |
| Pending SePay TTL | 15 minutes | 30 minutes | 45 minutes |

`sellable supply` for this calculation should be defined atomically as current
available inventory plus active unconfirmed reserved units for that SKU. A
percentage must specify rounding and a minimum/maximum; otherwise a one-unit SKU
is ambiguous.

### Conservative

- Strongest defense against stock starvation and unattended orders.
- Highest false-rejection risk for family purchases, bulk health products, and
  slow COD operations.
- Requires reliable staff processing within a short authenticated-COD window.

### Balanced

- Moderate account and SKU exposure with current SePay timing retained.
- Still requires business evidence that typical baskets and manual COD
  confirmation fit the proposed units, value, and TTL.
- Does not solve flash-sale fairness by itself; queueing or per-account SKU
  limits may still be needed.

### Permissive

- Lowest customer-friction risk and closest guest TTL to current behavior.
- Leaves substantial inventory exposed to one account or a coordinated group.
- Requires stronger anomaly monitoring and faster operational cancellation.

No option is recommended yet because the repository contains no trustworthy
order-distribution or fulfillment-SLA data from which to choose a production
threshold.

## 5. Small-inventory policy choices

An aggregate percentage alone is insufficient for inventory of one to five
units. One of these policies must be approved:

1. **Reserve-last-unit allowed:** cap is at least one and may equal all sellable
   supply. Maximizes conversion but permits temporary total unavailability.
2. **Safety stock:** keep at least one unit unreserved until an order is paid or
   manually confirmed. Reduces starvation but prevents online sale of the last
   unit.
3. **Per-user scarce-SKU cap:** allow the SKU to sell out, but limit one active
   unconfirmed unit per user/guest identity. This still needs the aggregate SKU
   lock to resist rotating identities.

Decision required: choose one policy, its rounding rule, and whether the rule
applies equally to COD and short-lived SePay reservations.

## 6. Proposed error contract

This is a contract proposal, not an implemented decision:

| Condition | Proposed HTTP | Stable error code | Client behavior |
| --- | ---: | --- | --- |
| Per-user pending/unit/value ceiling | `429` | `reservation_user_limit` | Show retry guidance; include `Retry-After` when the earliest active reservation expiry is known. |
| Aggregate SKU reservation ceiling | `409` | `reservation_capacity_conflict` | Refresh product/order state; do not automatically retry mutation. |
| Physical inventory below requested quantity | `422` | `insufficient_inventory` | Map to affected cart line and let the user change quantity. |
| Idempotency key reused with changed payload | `409` | `idempotency_payload_conflict` | Start a new logical attempt only after user-visible payload change. |

Decision required: approve these statuses/codes or provide the product/API
standard that supersedes them. Messages may be localized, but mobile should
branch on stable codes rather than Vietnamese text.

## 7. Migration and configuration impact

If a policy is approved, the smallest coherent implementation is expected to
need:

- centralized non-secret settings, preferably `config/commerce.php`, backed by
  explicit environment values for all seven approved thresholds;
- matching entries in `.env.example` and `.env.production.example`;
- an additive nullable `reservation_expires_at` column for all reserving order
  types, instead of extending the guest-specific field to authenticated COD;
- indexes supporting active reservations by user/status/expiry and by
  order-detail product/order;
- a migration/backfill rule for existing pending authenticated COD orders;
- a single expiration path through `OrderStatusService` (or one shared release
  primitive) so inventory and coupon restoration cannot drift;
- structured, non-PII observability for cap rejection and expiration counts.

All migrations must be additive and reversible. Existing `guest_expires_at`
can be retained during a compatibility window, then removed only in a separate
approved cleanup.

## 8. Proposed concurrency mechanism

Within one database transaction and after idempotent replay detection:

1. Lock the authenticated `users` row for per-user counters. Guest paths skip
   this lock and rely on the aggregate SKU guard plus existing abuse controls.
2. Sort requested product IDs and lock the corresponding product rows in
   ascending order.
3. Under those locks, calculate active unconfirmed reservations, using a single
   documented predicate for `pending` COD and pending/unpaid SePay orders whose
   reservation has not expired.
4. Enforce user count, unit, value, and per-SKU caps before inserting the order,
   decrementing inventory, or incrementing coupon usage.
5. Create the order/details, decrement inventory, apply coupon usage, write the
   expiry, and persist the idempotency record in the same transaction.
6. On expiration or cancellation, lock order, product rows in ascending order,
   then coupon row; restore each resource exactly once and write status history.

Locking product rows makes concurrent and rotating-identity requests for the
same SKU serialize at the aggregate guard. Locking the user row makes parallel
requests from the same account serialize at user limits. Every checkout writer
must use the same lock order to avoid deadlocks.

## 9. Required business decisions

Approved values:

```text
APPROVED_MAX_PENDING_ORDERS_PER_USER=3
APPROVED_MAX_RESERVED_UNITS_PER_USER=20
APPROVED_MAX_RESERVED_VALUE_PER_USER=5000000
APPROVED_MAX_UNCONFIRMED_PER_SKU=50% of sellable supply, rounded up, minimum 1;
  for sellable supply <= 5, one active unit per authenticated user or guest request
APPROVED_AUTH_COD_TTL_MINUTES=120
APPROVED_GUEST_COD_TTL_MINUTES=60
APPROVED_SEPAY_TTL_MINUTES=30
```

Also required:

1. What are the p50/p95/p99 basket units and order value for real customers?
2. How quickly can operations confirm authenticated COD orders during and
   outside business hours?
3. Must online checkout be able to sell the last unit of a scarce SKU?
4. Are COD and SePay allowed different aggregate SKU shares?
5. What existing pending orders receive an expiry during migration?
6. Are reservation-limit responses standardized as proposed in section 6?
7. Is scheduler execution monitored with an alert stricter than the shortest
   approved TTL?

The operational questions above remain useful monitoring inputs, but no longer
block implementation of the explicitly approved Balanced thresholds.
