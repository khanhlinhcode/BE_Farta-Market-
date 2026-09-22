# Farta Market production release gates

Status on 22 September 2026: **not approved for production**. The existing Northflank `farta-staging` project and Cloudflare Pages sites are a staging/demo setup. Northflank [says its Developer Sandbox should not be used for production](https://northflank.com/docs/v1/application/billing/pricing-on-northflank). Do not accept real customer orders or payment information there.

## 1. Credentials and environments

- Revoke every previously exposed credential at its provider, create new values, and verify the old credentials no longer work. At minimum review the Groq and Anthropic keys and the staging MySQL password that appeared during setup; audit Cloudinary, VNPay, mail, and application keys for any other exposure. A Git history rewrite does not revoke a credential. Never paste values into a ticket, chat, build log, or Git.
- Use a separate production project, database, admin account, API key, Cloudinary configuration, and payment configuration. Do not copy staging customers, QA orders, sessions, or MFA recovery codes into production.
- Store runtime secrets only in restricted secret groups. Confirm `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_ENCRYPT=true`, secure/HttpOnly cookies, exact production `CORS_ALLOWED_ORIGINS`, and the correct `SANCTUM_STATEFUL_DOMAINS` from inside the deployed runtime without printing secret values. Keep `GROQ_API_KEY` on the API service only.

## 2. Hosting, data, and recovery

- Choose a production-capable hosting plan and attach owned domains for the storefront, admin, and API with HTTPS. Restrict database access to private networking; verify TLS for API-to-database connections rather than assuming the addon toggle covers every client.
- Configure automated backups with a retention period that covers operational mistakes. Restore a backup into an isolated database and verify migrations, record counts, and representative orders before accepting real data. Set uptime, error-rate, queue, database storage, and AI usage alerts with an owner who will respond.
- Deploy API, queue worker, and scheduler with health checks. Apply reviewed migrations once per release before traffic reaches code that needs the new schema; never run `db:seed` on every deployment. Keep a tested application rollback and database recovery procedure.

## 3. Customer journeys

- Configure a real outbound mail provider and domain authentication. Test registration verification, password reset, order confirmation, queue retry, and failure handling using production-like addresses without logging message secrets or personal data.
- Use production VNPay credentials only after a normal-browser hosted payment, failure, cancellation, callback replay, amount/signature mismatch, and refund/reconciliation drill all pass. Verify COD, inventory rollback, idempotency, and order ownership. Keep the staging sandbox credentials out of production.
- Test admin MFA/recovery and product/category/banner/image create, replace, delete, and Cloudinary cleanup. Verify the storefront displays the resulting data and that an unauthorized account cannot change it.
- Run a labeled Vietnamese/English recommendation set and record retrieval Precision@5, Recall@5, abstention accuracy, unsafe-action rate, and latency. Keep AI recommendations grounded in current DB facts; do not give the model payment or order mutation authority. The public chat endpoint needs provider budget alerts and rate-limit monitoring.

## 4. Release evidence

1. Backend: `php artisan migrate:status`, `php artisan test --compact`, `vendor/bin/pint --test`, `composer validate --strict`, and `composer audit --no-dev --no-interaction` pass against the release revision. Do not print database credentials when checking migrations.
2. Storefront and admin: each repository's unit tests, lint/type checks where configured, production build, dependency audit, and browser E2E pass against the release API. Verify that no `VITE_*` value contains a private credential.
3. GitHub Actions pass for all three release commits; the three working trees are clean; the deployed revisions match those commits; no real `.env` file is tracked. Confirm previously exposed credentials are revoked, not merely absent from Git HEAD.
4. Exercise the three hosted URLs from a real browser: registration, login, admin MFA, image publication, COD, VNPay, transactional email, chat recommendations and no-evidence refusal. Record HTTP status and outcome without saving customer PII or secret values in the report.
5. Only after the gates above pass, enable real customer traffic gradually. Monitor errors, orders, payment callbacks, mail queue, AI spend, and backup completion. If a gate fails, stop the release and roll back the application revision or restore data using the rehearsed procedure.

Current local verification: backend 233 tests / 1,374 assertions, Pint on touched PHP files, strict Composer validation, and production-dependency audit pass. These checks do **not** verify production infrastructure, credential revocation, both frontend builds/E2E, or real payment and mail delivery. The staging-specific history and known gaps are in [STAGING_DEPLOY.md](STAGING_DEPLOY.md).
