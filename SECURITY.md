# Security Notes

## Authentication Tokens

Personal access tokens are configured with a short expiration through
`SANCTUM_TOKEN_EXPIRATION`. The frontend currently reads bearer tokens from
browser storage for local development, so production deployments should prefer
Sanctum SPA authentication with secure, HTTP-only cookies instead of exposing
bearer tokens to JavaScript.

Required production hardening:

- Use HTTPS only.
- Keep `SANCTUM_TOKEN_EXPIRATION=60` or lower.
- Revoke tokens on logout and password changes.
- Never log access tokens in application, queue, web server, or browser logs.
- Add a Content Security Policy that blocks inline scripts and limits trusted
  script, image, and API origins.
- Prefer Sanctum SPA cookie auth with `HttpOnly`, `Secure`, and `SameSite`
  cookies before handling real customer payments.
- Do not commit `.env` or `.env.production`.
- Run the Laravel scheduler and queue worker in production; see
  `DEPLOYMENT.md`.
- Run `composer audit` and `php artisan test` before release.
