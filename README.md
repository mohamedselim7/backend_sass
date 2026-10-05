# Ma3roof Backend (Laravel 12 + MySQL)

REST API for the Ma3roof app: accounts and roles, brands, AI content plans and
posts, designs, goals, publishing schedule, credits, subscriptions, admin tools
and live updates over Pusher.

This package replaces Firebase/Firestore. It is a standalone Laravel project —
host it on any PHP 8.2+ server (shared hosting with SSH, VPS, Laravel Forge,
Docker). The React frontend keeps its screens and only swaps its data layer for
these endpoints.

## Stack

| Concern | Choice |
| --- | --- |
| Framework | Laravel 12 (PHP 8.2+) |
| Database | MySQL 8 (utf8mb4, InnoDB, UUID keys) |
| Auth | JWT (`tymon/jwt-auth`), email verification, password reset |
| Roles | `spatie/laravel-permission` (`admin`, `designer`, `user`) |
| Realtime | Pusher via Laravel broadcasting, private per-user channels |
| Payments | Gateway interface + Paymob driver (disabled until configured) |
| Tests | PHPUnit, SQLite in memory |

## Install

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# create the MySQL database first, then:
php artisan migrate --seed

php artisan serve
```

Seeding creates roles/permissions, the three starter plans and one admin
account (`ADMIN_EMAIL`). Leave `ADMIN_PASSWORD` empty and the admin sets a
password through the reset flow — no default credentials ship in the code.

### Scheduler and queue

```bash
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
php artisan queue:work            # broadcasting + mail
```

The scheduler publishes due posts every minute and tops up subscription credits
monthly.

### Importing the Firebase data

Export each Firestore collection to `<dir>/<collection>.json`, then:

```bash
php artisan ma3roof:import-firebase storage/app/firebase-export --dry-run
php artisan ma3roof:import-firebase storage/app/firebase-export
```

Document ids are preserved (or mapped deterministically), so re-running the
import updates rows instead of duplicating them. Imported users keep their
profile and data; each one sets a new password on first sign-in
(`must_set_password`), because Firebase password hashes cannot be reused.

## Security model

- Every price, role, credit balance and subscription state is decided
  server-side. The client can never send a price or a role.
- Ownership is enforced by policies plus `visibleTo()` query scopes, so a
  guessed id returns 403/404 rather than another tenant's row.
- Provider API keys, social tokens and app secrets are encrypted at rest and
  never appear in API responses (admins see a masked hint only).
- The payment webhook verifies the gateway HMAC before reading the body and is
  idempotent on replays.
- Credit charges take a row lock and accept an `Idempotency-Key` header, so a
  retried request cannot double-charge.
- Login is throttled per email + IP; writes require a verified email; suspended
  accounts lose access immediately even with a valid token.

## Tests

```bash
php artisan test
```

Covers auth and token handling, tenant isolation, the post status machine,
credit accounting and idempotency, admin restrictions, and webhook signature
rejection.

## Docs

- `docs/API.md` — every endpoint, payloads and error codes
- `docs/FRONTEND.md` — how to point the React app at this API
- `docs/DEPLOYMENT.md` — server setup, cron, queue, Pusher
