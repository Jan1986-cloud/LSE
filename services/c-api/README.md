# C-API Service

## Role
Public-facing JSON content delivery gateway. Serves orchestrated payloads with strict latency goals and forwards delivery telemetry to A-API for analytics.

## Exposure
- **Public** via Railway routing.

## Endpoints
| Path | Method | Description |
|------|--------|-------------|
| `/health` | GET | Database-aware readiness check with response timing header. |
| `/live` | GET | Lightweight liveness probe. |
| `/content/{id}` | GET | Delivers content by external reference or numeric ID. Includes payload metadata and emits analytics to A-API. |

## Environment
| Variable | Required | Description |
|----------|----------|-------------|
| `DATABASE_URL` | ✅ | PostgreSQL connection string (Railway format). |

## Local Development
```bash
composer install
composer test
php -S 0.0.0.0:8080 index.php
```

## Health Expectations
- `/health` returns `200` when the database is reachable and response time header stays within the <150 ms SLO under nominal load.
- `/content/{id}` returns `404` for unknown references without leaking existence metadata.
- Analytics dispatch is fire-and-forget; failures do not block delivery but are logged internally.
