# O-API Service

## Role
Operational tooling API providing research, validation, and token usage aggregation utilities for internal consumers.

## Exposure
- **Internal-only** via Railway Private Networking at `http://o-api.railway.internal`.
- Not exposed to the public internet.

## Endpoints
| Path | Method | Description |
|------|--------|-------------|
| `/` | POST | Execute the default research work-flow. |
| `/health` | GET | Returns `{"status":"ok"}` for readiness. |
| `/live` | GET | Returns `{"status":"live"}` for basic liveness checks. |

## Environment
No runtime environment variables are required.

## Local Development
```bash
composer install
php -S 0.0.0.0:8080 index.php
```

## Health Expectations
- `/health` accessible only inside the private network.
- `/live` is suitable for basic container monitoring.

## Networking Notes
- Accessibly by `m-api` and other internal services through `http://o-api.railway.internal:<port>`.
- Documented in the Phase 0 private networking policy.
