# Scaling iden

Stack stays Laravel + MySQL + Redis + TanStack/React. No microservices, Kafka, Kubernetes, sharding or extra databases.

## Queues (Redis)
| Queue | Work | Worker (docker/supervisord.conf) |
|---|---|---|
| ai-high, ai-default, ai-low | AI text/design jobs, ordered by plan lane | worker-ai |
| notifications, default | emails, push, light jobs | worker-default |

- Lane per plan comes from `plans.ai_queue` or `plans.queue_priority` (1 = high, 2 = default, 3 = low) via `PlanQueueResolver`. Admins set it per plan in the dashboard; no plan name is hardcoded. A new plan works without code changes.
- Set `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `REDIS_CLIENT=predis` (or phpredis).
- Scale by adding worker processes/containers for the busy lane only.

## AI job lifecycle
Request -> balance check (402 if short) -> charge once -> `ai_generation_jobs` row -> queue (payload = job id only) -> worker -> completed / failed (refund once).
`iden:reconcile-ai-jobs` runs every 5 min: re-dispatches rows never queued, fails + refunds rows stuck in processing.

## Retention and aggregates
- `iden:prune-operational-logs` (daily): deletes old AI usage/job logs per `config/ai.php` retention.
- `iden:aggregate-monthly-usage` (daily): fills `usage_monthly_aggregates` so dashboards never scan raw logs.

## Observability
- Every request gets `X-Request-Id` (AssignRequestId middleware), added to log context.
- `LOG_CHANNEL=json` writes structured JSON to stderr.
- `GET /api/health` checks DB, cache and queue.

## Growth stages
- Now / 10K users: 1 app container, 1 MySQL, 1 Redis, 1 AI worker + 1 default worker.
- 100K: several app containers behind a load balancer, MySQL read replica for reports, more AI workers, S3/R2 for media (`FILESYSTEM_DISK=s3`), CDN for the frontend.
- 1M+: managed MySQL with bigger instance + replicas, Redis cluster/managed, per-lane autoscaling of workers, archive old chat/logs to object storage.
