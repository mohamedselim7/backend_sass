# iden API

Base URL: `https://your-domain/api/v1`
All requests and responses are JSON. Authenticated requests send
`Authorization: Bearer <access_token>`.

Collections are paginated Laravel resources: `{ data: [...], links, meta }`.
Single records are returned as `{ data: {...} }`.

## Errors

One envelope for everything:

```json
{ "message": "...", "error": "machine_code", "context": {} }
```

| Status | `error` | Meaning |
| --- | --- | --- |
| 401 | `invalid_credentials` | Wrong email/password |
| 401 | – | Missing/expired token |
| 403 | `account_suspended` | Account disabled by an admin |
| 403 | `email_not_verified` | Write attempted before verifying email |
| 402 | `insufficient_credits` | `context: {required, available}` |
| 409 | `invalid_status_transition` | `context: {from, to}` |
| 422 | – | Validation, with `errors` keyed by field |
| 429 | `too_many_attempts` | `retry_after` in seconds |

## Auth

| Method | Path | Notes |
| --- | --- | --- |
| POST | `/auth/register` | `name, email, password, password_confirmation, phone?, locale?` → token + user |
| POST | `/auth/login` | `email, password` → token + user |
| GET | `/auth/me` | Current user with credits and subscription |
| POST | `/auth/refresh` | New token from the current one |
| POST | `/auth/logout` | Invalidates the token |
| POST | `/auth/verify/send` | Re-sends the verification email |
| GET | `/auth/verify/{id}/{hash}` | Signed link target |
| POST | `/auth/password/forgot` | Always 200 (no email enumeration) |
| POST | `/auth/password/reset` | `token, email, password, password_confirmation` |

Token response:

```json
{ "access_token": "...", "token_type": "bearer", "expires_in": 3600, "user": { ... } }
```

## Profile

| Method | Path | Notes |
| --- | --- | --- |
| PATCH | `/profile` | `name, phone, avatar_url, locale, active_provider, active_model` |
| POST | `/profile/password` | `current_password, password, password_confirmation` |

## Brands

| Method | Path |
| --- | --- |
| GET | `/brands` (`search`, `per_page`) |
| POST | `/brands` |
| GET | `/brands/{brand}` |
| PATCH | `/brands/{brand}` |
| DELETE | `/brands/{brand}` |

## Content

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/content/generations` | filter `brand_id` |
| POST | `/content/generations` | Saves a finished generation; charges `content_plan` credits. Send `Idempotency-Key` to make retries safe |
| GET | `/content/generations/{generation}` | Includes plans and posts |
| GET | `/content/posts` | filter `brand_id`, `plan_id`, `status` |
| PATCH | `/content/posts/{post}` | Edit copy fields |
| POST | `/content/posts/{post}/status` | `status`, `reason` (required when rejecting) |
| DELETE | `/content/posts/{post}` | Soft delete |

Post status machine:

```text
generated → approved | rejected
approved  → design_pending | ready | rejected
rejected  → generated
design_pending → design_generated | approved
design_generated → design_approved | design_pending
design_approved → ready
ready → published
published → (final)
```

Generation request body:

```json
{
  "brand_id": "uuid",
  "brand_name": "Acme",
  "business_brief": "...",
  "options": { "posts_per_plan": 12 },
  "plans": [
    { "name": "Plan A", "strategy": "...", "pillars": ["..."],
      "posts": [ { "headline": "...", "content": "...", "platform": "instagram", "hashtags": ["..."] } ] }
  ]
}
```

## Designs

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/designs` | filter `brand_id`, `status` |
| POST | `/designs` | Creates the design plus version 1; charges `design_generate` |
| GET | `/designs/{design}` | With all versions |
| POST | `/designs/{design}/versions` | Revision; version number assigned server-side; charges `design_revise` |
| DELETE | `/designs/{design}` | |

## Goals, schedule, records

| Method | Path | Notes |
| --- | --- | --- |
| GET/POST | `/goals` | |
| PATCH/DELETE | `/goals/{goal}` | |
| GET | `/schedule` | filter `status`, `from`, `to` |
| POST | `/schedule` | `platform, scheduled_at, timezone?, social_account_id?, caption?, media?, hashtags?` |
| PATCH | `/schedule/{scheduledPost}` | |
| POST | `/schedule/{scheduledPost}/cancel` | |
| GET | `/social-accounts` | Tokens are never returned |
| DELETE | `/social-accounts/{socialAccount}` | Disconnect |
| GET/POST | `/records` | Generic feature outputs, filter `feature` |
| GET/DELETE | `/records/{workspaceRecord}` | |

## Notifications

| Method | Path |
| --- | --- |
| GET | `/notifications` (`unread=1`) |
| GET | `/notifications/unread-count` |
| POST | `/notifications/{notification}/read` |
| POST | `/notifications/read-all` |

## Billing

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/plans` | Public |
| POST | `/billing/checkout` | `plan_code` only — price comes from the database |
| GET | `/billing/wallet` | Balance plus per-feature credit costs |
| GET | `/billing/credits` | Ledger |
| POST | `/webhooks/payment` | Gateway callback; HMAC verified, idempotent |

## Admin (`role:admin`)

| Method | Path |
| --- | --- |
| GET | `/admin/stats`, `/admin/activity`, `/admin/usage` |
| GET | `/admin/users`, `/admin/users/{user}` |
| POST | `/admin/users/{user}/status` `{status: active|suspended}` |
| POST | `/admin/users/{user}/roles` `{roles: ["user"]}` |
| POST | `/admin/users/{user}/credits` `{amount: 50, note?}` |
| GET/POST | `/admin/plans` |
| PATCH/DELETE | `/admin/plans/{plan}` |
| GET/POST | `/admin/settings`, `POST /admin/settings/providers`, `DELETE /admin/settings/providers/{providerKey}` |
| GET | `/admin/design-requests`, `PATCH /admin/design-requests/{designRequest}` |
| POST | `/admin/notifications` (omit `user_id` to broadcast) |

## Realtime (Pusher)

Private channel `users.{userId}` — a user may only subscribe to their own:

| Event | Payload |
| --- | --- |
| `post.status.changed` | `id, plan_id, status, updated_at` |
| `notification.created` | `id, type, title, body, link, created_at` |
| `schedule.published` | `id, status, platform, external_post_id, published_at` |

Public channel `announcements` carries global notifications; `admin` is
restricted to admins.
Authorise subscriptions against `POST /broadcasting/auth` with the bearer token.
