# M-API Service

## Role
Main customer-facing API aggregating authentication and billing workflows.

## Exposure
- **Public** via Railway routing.
- Private network dependency on `o-api` at `http://o-api.railway.internal:<port>`.

## Endpoints
| Path | Method | Description |
|------|--------|-------------|
| `/auth/register` | POST | Register a new user. |
| `/auth/login` | POST | Authenticate and issue an API key. |
| `/billing/status` | GET | Return billing and usage context for an authenticated user. |
| `/health` | GET | Database ping plus `o-api` connectivity check. |
| `/live` | GET | Liveness probe without dependencies. |

## Environment
- `DATABASE_URL` (**required**) – PostgreSQL DSN.
- `O_API_INTERNAL_PORT` (optional) – overrides the fallback `8080` port (see `TECHNICAL_DEBT.md` P0-001).

A startup log line records `env:DATABASE_URL detected/absent` for Phase 0 diagnostics.

## Local Development
```bash
composer install
php -S 0.0.0.0:8080 index.php
```

## Health Expectations
- `/health` returns `200` when the database ping and `o-api` connectivity succeed; otherwise `502` with details.
- `/live` returns `200` regardless of dependencies.
