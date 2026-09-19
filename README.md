# Farta Market API

Laravel 12 API for products, orders, admin management, and the AI assistant.

## Local development

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Cloudinary images

Product, category, banner, and avatar uploads are stored on Cloudinary. The
application does not fall back to local image storage when Cloudinary is
unavailable.

Before removing legacy source files, inspect and migrate their database
references:

```bash
php artisan images:migrate-to-cloudinary \
  --source=/absolute/path/to/websivi/public \
  --dry-run

php artisan images:migrate-to-cloudinary \
  --source=/absolute/path/to/websivi/public
```

The command can be run again safely. It skips managed Cloudinary images, keeps
source files for rollback, and returns a failure status if any legacy file is
missing or cannot be migrated.

## AI assistant

Local Ollama configuration:

```dotenv
AI_CHAT_DRIVER=ollama
AI_CHAT_MODEL=qwen3:4b
AI_CHAT_BASE_URL=http://127.0.0.1:11434
AI_CHAT_TIMEOUT=15
AI_CHAT_KEEP_ALIVE=30m
```

Install and start the configured model before starting Laravel:

```bash
ollama pull qwen3:4b
ollama serve
```

Preload the model after a machine restart to avoid a slow first chat request:

```bash
curl http://127.0.0.1:11434/api/generate \
  -d '{"model":"qwen3:4b","keep_alive":"30m"}'
```

Anthropic configuration:

```dotenv
AI_CHAT_DRIVER=anthropic
AI_CHAT_MODEL=claude-sonnet-4-6
AI_CHAT_BASE_URL=https://api.anthropic.com
ANTHROPIC_API_KEY=
```

The API never exposes the provider key to the frontend.

## Local/QA seed accounts

No production credential is stored in source. To create reusable local/QA
accounts through the seeder, enable the flag below. The seeder refuses to run
these accounts in production.

```dotenv
SEED_ADMIN_ENABLED=true
```

Created test accounts:

| Role | Email | Password |
| --- | --- | --- |
| admin | qa.admin@example.test | FartaQa12345 |
| staff | qa.staff@example.test | FartaQa12345 |
| customer | qa.customer@example.test | FartaQa12345 |

You can also add a custom local admin by setting `SEED_ADMIN_NAME`,
`SEED_ADMIN_EMAIL`, and `SEED_ADMIN_PASSWORD` with a password of at least
12 characters.

Admin/QA seeding is ignored outside `local` and `testing`.

## Order API

`POST /api/order` requires a unique `X-Idempotency-Key` header. Replaying the
same key returns the original order and does not decrement inventory again.
Guest order creation is limited to five requests per minute per IP.

## Production checklist

- Set a real `APP_URL`, database credentials, and `APP_KEY`.
- Set the AI driver, model, base URL, and provider secret.
- Set the Cloudinary cloud name, API key, and API secret.
- Keep `SEED_ADMIN_ENABLED=false`; create admins using controlled deployment tooling.
- Configure a shared cache store so rate limits and idempotency locks work across servers.

## Verification

```bash
php artisan test
composer audit
```

Testing is configured to avoid real network calls for mail, queues, and password
breach checks. If `php artisan test` appears to hang on macOS/Homebrew PHP, first
check whether CLI OPcache is stuck compiling files; running with
`php -d opcache.enable_cli=0 artisan test` should behave consistently.
# BE_Farta-Market-
# BE_Farta-Market-
# BE_Farta-Market-
