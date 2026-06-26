# Farta Market API

Laravel 12 API for products, orders, admin management, and the AI assistant.

## Local development

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

## AI assistant

Local Ollama configuration:

```dotenv
AI_CHAT_DRIVER=ollama
AI_CHAT_MODEL=qwen3:4b
AI_CHAT_BASE_URL=http://127.0.0.1:11434
AI_CHAT_TIMEOUT=60
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

## Local admin seed

No default admin credential is stored in source. To create a local admin through
the seeder, set all values explicitly:

```dotenv
SEED_ADMIN_ENABLED=true
SEED_ADMIN_NAME="Local Admin"
SEED_ADMIN_EMAIL=admin@example.test
SEED_ADMIN_PASSWORD=replace-with-at-least-12-characters
```

Admin seeding is ignored outside `local` and `testing`.

## Order API

`POST /api/order` requires a unique `X-Idempotency-Key` header. Replaying the
same key returns the original order and does not decrement inventory again.
Guest order creation is limited to five requests per minute per IP.

## Production checklist

- Set a real `APP_URL`, database credentials, and `APP_KEY`.
- Set the AI driver, model, base URL, and provider secret.
- Keep `SEED_ADMIN_ENABLED=false`; create admins using controlled deployment tooling.
- Configure a shared cache store so rate limits and idempotency locks work across servers.
- Route `/storage` correctly after running `php artisan storage:link`.

## Verification

```bash
php artisan test
composer audit
```
