# Chat

- `GET /api/v1/ai/chat/threads` — thread list, paged (frontend uses infinite scroll).
- `GET /api/v1/ai/chat/threads/{id}/messages?per_page=30&cursor=<message id>` — newest page first; `cursor` = oldest id you already have; response `meta.has_more`, `meta.next_cursor`. Max 100 per page. Only the owner can read (404 otherwise).
- Ordering uses `(thread_id, created_at, id)` index, so paging is stable even with equal timestamps.

## Context sent to the AI (`App\Actions\Ai\ChatContextBuilder`)
- Only the newest N messages (N from config/plan) are sent, not the whole thread.
- Older messages are folded into `chat_threads.summary` (rolling summary, `summary_until_message_id` tracks progress).
- This keeps token cost flat no matter how long the thread grows.

Fixed in this release: the thread relation's default ascending order was overriding the paging/context order, which returned the oldest messages instead of the newest. Covered by `tests/Feature/Chat/*`.
