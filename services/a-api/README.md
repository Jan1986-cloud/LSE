# A-API Service

## Role
Internal analytics ingestion and agent-detection foundation. Accepts telemetry from delivery surfaces, applies anonymization, and persists events for downstream analytics.

## Exposure
- **Internal-only** via Railway Private Networking (`http://a-api.railway.internal`).

## Endpoints
| Path | Method | Description |
|------|--------|-------------|
| `/health` | GET | Database-aware readiness check with response latency header. |
| `/live` | GET | Basic liveness check. |
| `/analytics/ingest` | POST | Accepts batched analytics events (`TokenUsageAggregator` and C-API). Hashes IP data before persistence. |

## Environment
| Variable | Required | Description |
|----------|----------|-------------|
| `DATABASE_URL` | ✅ | PostgreSQL connection string. |
| `ANONYMIZATION_SALT` | ➖ | Optional HMAC salt for IP hashing (defaults to `aurora-default-salt`). |

## Local Development
```bash
composer install
composer test
php -S 0.0.0.0:8080 index.php
```

## Health Expectations
- `/analytics/ingest` returns `202` on success, `422` for schema violations.
- Events persisted with anonymized IP hashes; raw IP addresses are discarded prior to storage.
- `/health` surfaces degraded status if the database probe fails.
