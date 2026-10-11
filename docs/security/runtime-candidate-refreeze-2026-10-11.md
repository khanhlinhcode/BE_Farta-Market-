# Reviewed runtime candidate refreeze — 2026-10-11

This refreeze was explicitly approved after the backend security and commerce
runtime changes were complete. It does not modify or execute Final V11.

- Git parent revision: `721bd0b2ccb8d3d16b1e51ee4c781e1edb165f60`
- Previous candidate hash: `eb4d5d239789eeb087185e93612516090ad2c7760c7b18febf5a995d1327ce41`
- New candidate hash: `bd2f2dcfe58085107fab47e20d4b95baeb304a4e7451d5f075e3ea8858cefcd0`
- Runtime diff identity: `418c83c60551911c7f66cebba97d6ef071b731e0a37aa4fe769685eac00e46a2`

The semantic reason is the reviewed cumulative runtime remediation: stateless
mobile account lifecycle, bearer-token ability enforcement, reservation limits,
admin capability default-deny authorization, additive canonical integer VND
persistence, opaque guest checkout binding, and the approved API deprecation
window. The hash also includes all pre-existing uncommitted runtime changes in
the same reviewed tree; it is not represented as a clean Git commit.

Before updating the candidate hash, the isolated freeze test was run three times
and produced the same new hash each time. Security, capability, guest-binding,
money, API-contract, and analytics tests were then run before a fourth freeze
calculation; the hash remained identical. This establishes deterministic file
ordering and no observed test pollution for the covered runtime tree.

`ChatFixtureAudit::runtimeHash()` covers files under `app`, `config`, `routes`,
`resources/chat`, and `database/migrations`. The changed or untracked files in
that covered tree were:

- `app/Console/Commands/ExpireGuestOrders.php`
- `app/Console/Commands/ExpirePendingPayments.php`
- `app/Http/Controllers/AdminUserController.php`
- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/CouponController.php`
- `app/Http/Controllers/GuestCheckoutBindingController.php`
- `app/Http/Controllers/MobileAuthController.php`
- `app/Http/Controllers/OrderController.php`
- `app/Http/Controllers/PasswordResetController.php`
- `app/Http/Controllers/PaymentController.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Middleware/AdminCapabilityMiddleware.php`
- `app/Http/Middleware/AdminPanelMiddleware.php`
- `app/Http/Middleware/CustomerMiddleware.php`
- `app/Http/Middleware/DeprecationHeaders.php`
- `app/Http/Middleware/GuestCheckoutBindingMiddleware.php`
- `app/Http/Middleware/RequirePersonalAccessTokenAbility.php`
- `app/Http/Resources/OrderResource.php`
- `app/Http/Resources/UserResource.php`
- `app/Jobs/SendOrderConfirmationEmail.php`
- `app/Mail/OrderConfirmation.php`
- `app/Models/Coupon.php`
- `app/Models/CouponUsage.php`
- `app/Models/Order.php`
- `app/Models/OrderDetail.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/AnalyticsSessionService.php`
- `app/Services/Chat/ChatOrderTool.php`
- `app/Services/CouponService.php`
- `app/Services/GuestCheckoutBindingService.php`
- `app/Services/ReservationPolicy.php`
- `app/Services/SepayService.php`
- `app/Support/DashboardRevenueQuery.php`
- `app/Support/Vnd.php`
- `config/api.php`
- `config/commerce.php`
- `config/security.php`
- `config/trustedproxy.php`
- `database/migrations/2026_10_10_000001_add_reservation_expiry_to_orders_table.php`
- `database/migrations/2026_10_11_000001_add_canonical_vnd_columns.php`
- `routes/api.php`
- `routes/console.php`
