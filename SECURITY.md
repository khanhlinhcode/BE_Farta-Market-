# Security Notes

## Authentication sessions

The frontend uses Sanctum SPA authentication with server-side sessions and does
not store bearer tokens in browser storage. Legacy token keys are removed during
authentication bootstrap. Production must use secure, HTTP-only session cookies.

Required production hardening:

- Use HTTPS only.
- Keep `SANCTUM_TOKEN_EXPIRATION=60` or lower.
- Revoke tokens on logout and password changes.
- Never log access tokens in application, queue, web server, or browser logs.
- Add a Content Security Policy that blocks inline scripts and limits trusted
  script, image, and API origins.
- Keep Sanctum session cookies `HttpOnly`, `Secure`, and `SameSite`.
- Do not commit `.env` or `.env.production`.
- Run the Laravel scheduler and queue worker in production; see
  `DEPLOYMENT.md`.
- Run `composer audit` and `php artisan test` before release.
