# API contract additions (agreed with the React frontend)

The React app calls `${VITE_API_URL}/v1/...` with `Authorization: Bearer <JWT>`.
Single-resource responses are `{ "data": ... }`. List responses are
`{ "data": [...], "meta": {...} }`. Errors are `{ "error": { "message", "code" }, "errors"? }`.

All endpoints below are scoped to the authenticated user unless marked admin.

## Activity / usage

- `GET  v1/activity?brand_id=&limit=` → ActivityLog[]
- `POST v1/activity` {action, brand_id?, brand_name?, entity?, feature?, status?, cost?, details?} → ActivityLog
- `GET  v1/usage?brand_id=&limit=` → UsageLog[]

## Prompt library

- `GET    v1/prompts?search=&section=` → Prompt[]  (favorites first, then newest)
- `POST   v1/prompts` {title, section, prompt, result?, notes?, brand_id?, brand_name?, is_favorite?}
- `PATCH  v1/prompts/{prompt}`
- `POST   v1/prompts/{prompt}/favorite` {is_favorite}
- `DELETE v1/prompts/{prompt}`

## Drafts (autosave of in-progress editors)

- `GET v1/drafts/{key}` → {key, data} | 404
- `PUT v1/drafts/{key}` {data: object} → {key, data}
  Stored in `workspace_records` with `type=draft`, `key` unique per user.

## Design requests (user scope; admin scope already exists)

- `GET    v1/design-requests?brand_id=` → DesignRequest[]
- `POST   v1/design-requests` {post_id, brand_id?, brand_name?, headline?, content?, cta?, design_idea?, platform?, content_type?, funnel_stage?}
- `PATCH  v1/design-requests/{designRequest}`
- `DELETE v1/design-requests/{designRequest}`

## Content additions

- `PATCH  v1/designs/{design}`  (status/title/notes)
- `POST   v1/content/posts` — create a single approved manual post
- `POST   v1/content/posts/bulk` {ids: string[], patch: {...}} → {updated: n}
- `POST   v1/content/generations/{generation}/delete` — soft delete (status=deleted, deleted_at)
- `DELETE v1/content/generations/{generation}` — hard purge with plans + posts

## Media / storage (replaces Firebase Storage)

- `POST   v1/media` multipart {file, folder?, visibility?} → {id, url, path, mime, size}
- `GET    v1/media?folder=` → Media[]
- `DELETE v1/media/{media}`
- `GET    media/{path}` public signed-file read (outside v1, `routes/web.php`)

Files go to the `public` disk under `media/{user_id}/{folder}/`. `url` is absolute.

## AI (replaces the Node server functions; keys live in Laravel .env)

- `POST v1/ai/content/generate` {brand_id?, brief, platforms[], plan_count?, posts_per_plan?, model?}
  → ContentGeneration with plans+posts; charges credits; dispatches `GenerateContentJob` when `async=true`.
- `POST v1/ai/angles` {brand_id?, topic, count?} → MarketingAngle[]
- `POST v1/ai/chat` {thread_id?, message, model?} → {thread, message}
- `POST v1/ai/design` {post_id?, prompt, model?} → DesignVersion
- `POST v1/ai/image` {prompt, size?} → {url}
- `GET  v1/ai/models` → available providers/models from `provider_keys` + config

## User settings / onboarding

- `GET   v1/settings` → {active_provider, active_model, locale, ...}
- `PATCH v1/settings`
- `POST  v1/onboarding/setup` {full_name?, ...} → {user, wallet} (idempotent; grants welcome credits once)

## Social

- `GET  v1/social/apps` → configured platforms (no secrets)
- `POST v1/social/connect` {platform} → {redirect_url, state}
- `GET  v1/social/callback/{platform}` (public, state-verified) → redirects back to frontend
- `POST v1/social/publish` {post_id, account_ids[]} → {dispatched: n}

## Realtime

- `POST /broadcasting/auth` (jwt guard) authorises `private-users.{id}`.
- Events broadcast on `private-users.{id}`:
  `NotificationCreated`, `CreditsChanged`, `PostStatusChanged`, `GenerationCompleted`,
  `DesignReady`, `ScheduledPostPublished`.

## Versioning

`routes/api.php` loads `v1` and `v2` groups. `Api\V2` controllers extend their V1
counterparts so v2 is live from day one and can diverge per endpoint.

## Admin additions

- `PATCH v1/admin/users/{user}` {name?, status?, allowed_pages?, allowed_brands?, command_limit?, commands_used?}
  Requires columns `allowed_pages` (json), `allowed_brands` (json), `command_limit` (int), `commands_used` (int)
  on users — add a migration if missing, and expose them in UserResource.
- `UserResource` must include: id, name, email, phone, avatar_url, locale, status, email_verified,
  roles[], permissions[], allowed_pages, allowed_brands, command_limit, commands_used,
  active_provider, active_model, created_at, updated_at, last_login_at.

## Requested by frontend (content/ai/chat domain)

### Marketing angles (mapped from former Firestore `marketing_angles` collection)

- `GET    v1/angles?expertise_id=` → MarketingAngle[] (owned by user, one project = expertise_id)
- `POST   v1/angles/generate` {expertise_id, understanding:{business,audience,topics,goal,tone,references}, count?, topic_focus?}
  → {created, skipped, angles: MarketingAngle[]} — calls `v1/ai/angles` internally, dedupes semantically, persists as drafts.
- `POST   v1/angles` {id?, expertise_id, name, topic?, perspective, audience?, tension?, objective?, belief_shift?, key_message,
  example?, hook?, formats?, guiding_questions?, proof_requirements?, needs_verification?, references?}
  → creates (no id) or updates (id) a MarketingAngle, sets edited_by_user=true on update.
- `POST   v1/angles/{angle}/status` {status: draft|approved|archived} → {ok:true}
- `POST   v1/angles/{angle}/usage` {generation_id, brief_excerpt?} → {ok:true} (increments usage_count, stores a snapshot)
- `GET    v1/angles/{angle}/usages` → AngleUsage[] (latest 10)

MarketingAngle shape (camelCase in API responses): id, expertiseId, name, topic, perspective, audience, tension,
objective, beliefShift, keyMessage, example, hook, formats[], guidingQuestions[], proofRequirements,
needsVerification, references[], source(ai|manual), editedByUser, status(draft|approved|archived),
createdAt, updatedAt, usageCount, lastUsedAt, understandingSnapshot, understandingHash.

### Chat threads (mapped from former Firestore `chat_threads` collection; message exchange itself uses `v1/ai/chat`)

- `GET    v1/ai/chat/threads` → ChatThreadSummary[] {id, title, createdAt, updatedAt, messageCount, mode(free|smart)}
- `POST   v1/ai/chat/threads` {mode} → ChatThreadSummary
- `GET    v1/ai/chat/threads/{thread}` → {id, title, messages: ChatMessage[], mode}
- `PATCH  v1/ai/chat/threads/{thread}` {title} → {ok:true}
- `DELETE v1/ai/chat/threads/{thread}` → {ok:true}

`POST v1/ai/chat` should accept `{thread_id, message, mode?, page_context?}` and persist the exchange onto the
thread (creating one if `thread_id` is omitted), returning `{thread: ChatThreadSummary, message: ChatMessage}`
where `ChatMessage` is `{id, role: user|assistant, content, createdAt}`. The frontend now performs a single
request/response call per turn instead of streaming — no SSE/tokens needed.

Note: the previous Node-side chat also supported a `generate_image` tool and a "smart assistant" tool set
(read account data, create/update/delete approved posts and goals) invoked autonomously mid-conversation.
Those tool-calling capabilities are NOT covered by `v1/ai/chat` in this contract and have been dropped from the
frontend for now (see migration report). If needed, `v1/ai/chat` would need a `tools`/`tool_results` contract.

### Manual design idea text (content domain)

No dedicated endpoint exists for generating a short design-idea text for a manual post. The frontend now sends
this as a `v1/ai/chat` one-off message (no thread_id) and reads `message.content` as the idea text. If a cleaner
endpoint is preferred, consider `POST v1/ai/content/design-idea {headline?, content, hashtags?, visual_identity?} → {designIdea}`.

## Requested by frontend (credits/billing/notifications/settings domain)

- `GET  v1/settings` / `PATCH v1/settings` — generic per-user settings (locale, active_provider,
  active_model) distinct from `PATCH profile`. Frontend currently reuses `PATCH profile` +
  `admin/settings` for provider connections; add a dedicated `v1/settings` resource if the
  product needs non-admin-writable preferences beyond what `profile` already covers.
- `POST v1/onboarding/setup` {full_name?} → {user, wallet} — idempotent welcome-credit grant +
  first-login bootstrap. Today `POST auth/register` creates the user but the wallet is only
  lazily created on first `CreditService::wallet()` call with balance 0; there is no "welcome
  credits" grant. Frontend now calls `POST v1/onboarding/setup` expecting it to top up the
  wallet once and return `{ user, wallet }` — please add this endpoint (idempotent, guarded by
  a `welcome_granted_at` column or similar) so new signups get a starting credit balance.
- Broadcast a `CreditsChanged` event on `private-users.{id}` whenever `CreditWallet.balance`
  changes (reservations, settlements, admin adjustments, plan grants) with payload
  `{ balance, lifetime_granted, lifetime_spent }`, so the frontend wallet hook can update
  live instead of polling. Today only `NotificationCreated`, `PostStatusChanged`,
  `ScheduledPostPublished` broadcast; there is no wallet event.
- Global (`user_id = null`) notifications broadcast on the public `announcements` channel per
  `NotificationCreated::broadcastOn()`, but the contract says every event is on
  `private-users.{id}`. Frontend now only subscribes to the private per-user channel and does
  not listen on `announcements`; either broadcast global notifications there too, or document
  that clients must additionally subscribe to the public `announcements` channel to receive
  admin broadcasts.

## Requested by frontend (admin + app-shell domain)

- `ProviderKey` needs an `arms_tier` column (`basic|pro|promax`, default `basic`) so the
  admin can keep one active provider per ARMS tier (as the old Firebase `active_ai` /
  `active_ai_pro` / `active_ai_promax` docs did). Until this lands, the frontend only
  manages the `basic` tier; `pro`/`promax` always read as "not configured".
- `PATCH v1/admin/settings/providers/{providerKey}` {is_active?, default_model?, api_key?,
  arms_tier?} — partial update without requiring `api_key` resubmission (only send it when
  rotating the secret). Response: the same shape as the `providers` entries in
  `GET v1/admin/settings`.
- `POST v1/admin/settings/providers/test` {provider, api_key, model, image_model?} — admin
  only, validates connectivity to the provider server-side (SSRF-safe, no persistence).
  Response: `{ ok, message, text_ok, image_ok }` (`image_ok` is `null` when no image model
  was supplied).
- `GET v1/admin/social-apps` → `[{ platform, client_id, client_secret_masked, has_secret,
  review_done, notes, updated_at }]` for every platform in
  `meta|linkedin|x|youtube|tiktok`. Admin only.
- `POST v1/admin/social-apps` {platform, client_id, client_secret?, review_done, notes} —
  upserts one platform's OAuth app config; an empty `client_secret` keeps the existing
  secret. Admin only.
- `GET v1/social/apps` → `[{ platform, client_id, ready }]` — public-safe (no secrets) list
  of configured platforms, used by the "connect account" UI to know which platforms are
  ready to link.
- `DELETE v1/admin/users/{user}` — hard-delete a customer account (auth credentials +
  profile). Response: `{ removed: number }`.
- `GET v1/admin/credit-logs?limit=` → `[{ id, user_id, user_email, amount, note, by, at }]`
  — history of admin credit adjustments (`POST /admin/users/{user}/credits`), newest first.
  Needed by the members panel's audit log.

## Requested by frontend (notifications gaps, follow-up)

- `DELETE v1/notifications/{notification}` — dismiss/delete a notification for the current
  user (global ones should just record a per-user dismissed state, similar to `NotificationState`
  used for read tracking). The bell UI lets users remove a notification from their list; there is
  no endpoint for that today.
- `POST v1/notifications/self` {title, body, link?} → NotificationResource — let a signed-in
  non-admin user create a notification for themselves (used for "your export finished" /
  "your post was scheduled" style toasts triggered client-side after a background action).
  Today only `POST v1/admin/notifications` exists and it requires the admin role.
- `DELETE v1/drafts/{key}` — clear a saved draft/UI-state key (used by `clearPersistentState`).
  Only GET/PUT are documented today.

## Requested by frontend (social publishing/scheduling domain)

- `GET  v1/social/connection/meta` → `{ connected: boolean, identity_name: string|null,
  page: {id,name,avatar}|null, instagram: {id,username,avatar}|null }` — derived status of the
  user's linked Meta identity (Facebook page + linked Instagram business account). Needed by the
  integrations panel and the publishing workspace to show which assets are ready to post to,
  without re-deriving this from raw `social-accounts` rows on the client.
- `POST v1/social/connection/meta/pages` `{ page_id }` → same shape as above — lets the user pick
  which Facebook Page (and its linked Instagram account) becomes the active publishing target
  after the Meta OAuth callback, when more than one Page was authorized.
- `GET  v1/social/connection/meta/pages` → `[{ id, name, avatar, instagram: {id,username,avatar}|null }]`
  — list of Facebook Pages available to the connected Meta identity, for the page picker dialog.
- `POST v1/social/connection/meta/disconnect` → `{ ok: true }` — disconnects both the Facebook and
  Instagram `social_accounts` rows tied to the user's Meta identity in one call.
- `POST v1/social/publish-now` `{ provider: facebook|instagram|linkedin|x, message, image_urls[] }`
  → `{ ok, platform_post_id?, page_id?, instagram_account_id?, published_at?, provider, error?,
  diagnostics?: {code,subcode,type} }` — immediate one-off publish using the user's already
  connected account for `provider`, without requiring a pre-existing `content_posts` row (unlike
  `POST v1/social/publish`, which needs `post_id` + `account_ids`). Used by the "publish now"
  button in the scheduling workspace for freeform/manual drafts.
- `PATCH  v1/social/published-posts/{localRecordId}` `{ title, body, hashtags }` → `{ ok, error? }`
  — edits the text of an already-published Facebook post (only supported when it has no image and
  a saved `platform_post_id`). `localRecordId` is the frontend's local scheduled-post record id.
- `DELETE v1/social/published-posts/{localRecordId}` → `{ ok, error? }` — deletes an already
  published Facebook post from the platform.
- `DELETE v1/schedule/{scheduledPost}` → `{ ok: true }` — hard-cancel-and-remove a scheduled post
  that has not published yet (distinct from `POST v1/schedule/{id}/cancel`, which only flips
  status to cancelled but keeps the row for history). The frontend's "delete" action on a
  scheduled (not-yet-due) post needs this.
- `GET    v1/social-apps/mine` → `[{ platform, client_id, has_secret, updated_at }]` — a signed-in
  user's own bring-your-own OAuth app credentials per platform (distinct from the admin-managed
  system-wide `admin/social-apps`). Secrets never returned.
- `POST   v1/social-apps/mine` `{ platform, client_id, client_secret? }` → `{ ok: true }` — upserts
  the current user's own app credentials for a platform; empty `client_secret` keeps the existing
  one. Needed by the per-user "استخدم تطبيقك الخاص" settings panel.
- `DELETE v1/social-apps/mine/{platform}` → `{ ok: true }` — clears the user's own app credentials
  for a platform, falling back to the system-wide app.

## POST v1/ai/reels (documented contract — not yet implemented)

Body:
```json
{
  "mode": "plan|single",
  "prompt": "string",
  "brief": "string",
  "count": 1,
  "platform": "string",
  "duration": "string",
  "language": "string",
  "dialect": "string",
  "tone": "string",
  "audience": "string",
  "goal": "string",
  "captionPrompt": "string",
  "avoidTitles": ["string"],
  "brandId": "uuid",
  "brandName": "string",
  "provider": "string",
  "model": "string"
}
```

Response:
```json
{
  "result": {
    "planName": "string",
    "strategy": "string",
    "reels": [
      { "title": "string", "hook": "string", "fullScript": "string", "caption": "string", "sources": [] }
    ]
  }
}
```
