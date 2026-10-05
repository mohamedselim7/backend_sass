# Deployment

## Requirements

PHP 8.2+ (`pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `intl`), MySQL 8,
Composer, and a process able to run cron plus one queue worker.

## Steps

```bash
git clone <your-repo> && cd backend
composer install --no-dev --optimize-autoloader
cp .env.example .env         # fill DB, Pusher, mail, admin
php artisan key:generate
php artisan jwt:secret
php artisan migrate --force --seed
php artisan config:cache && php artisan route:cache
```

Point the web root at `public/`. Grant the web user write access to `storage/`
and `bootstrap/cache/`.

## Cron and queue

```cron
* * * * * cd /var/www/backend && php artisan schedule:run >> /dev/null 2>&1
```

Supervisor:

```ini
[program:iden-queue]
command=php /var/www/backend/artisan queue:work --sleep=1 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=2
```

## CORS

Set `CORS_ALLOWED_ORIGINS` to the exact frontend origins (comma separated). Do
not use `*` in production — credentials are sent on broadcasting auth.

## Payments

Leave `PAYMENT_GATEWAY=none` until Paymob is ready. Then set
`PAYMENT_GATEWAY=paymob` with `PAYMOB_API_KEY`, `PAYMOB_INTEGRATION_ID`,
`PAYMOB_IFRAME_ID`, `PAYMOB_HMAC_SECRET`, and register the callback:

```text
https://api.your-domain.com/api/v1/webhooks/payment
```

## Going live checklist

- `APP_DEBUG=false`, real `APP_URL`, HTTPS enforced at the proxy
- Admin password set through the reset flow, not committed anywhere
- MySQL backups scheduled; `storage/logs` rotated
- Firebase import run once, verified, then the export files deleted
