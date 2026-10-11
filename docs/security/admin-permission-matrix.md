# Admin and staff permission matrix

Status: **approved and enforced**.

Every protected admin route is mapped to one named capability. `admin` receives
all capabilities. `staff` receives only the approved allowlist below. An admin
route without an explicit capability mapping is denied by `admin.panel`.

| Capability | Admin | Staff | Scope |
| --- | --- | --- | --- | --- |
| `dashboard.read` | Allow | Allow | Dashboard and admin self-service MFA |
| `analytics.read` | Allow | Allow | Analytics overview |
| `catalog.write` | Allow | Allow | Catalog and banner read/create/update |
| `catalog.delete` | Allow | Deny | Product/category/banner delete |
| `media.upload` | Allow | Allow | Media browse/upload/reuse/reorder |
| `media.delete` | Allow | Deny | Product/category media delete |
| `site-settings.write` | Allow | Deny | Site settings read/update |
| `reviews.moderate` | Allow | Allow | Review list and visibility mutation |
| `orders.read` | Allow | Allow | Order list/detail/export |
| `orders.transition` | Allow | Deny | Order status transitions |
| `coupons.manage` | Allow | Deny | Coupon read/create/update/delete/stats |
| `users.manage` | Allow | Deny | User read/role/delete |
| `system-health.read` | Allow | Deny | Queue/system health |

Approved staff allowlist: `dashboard.read`, `analytics.read`, `catalog.write`,
`media.upload`, `reviews.moderate`, and `orders.read`.
