# C-API Service

## Role
Customer-facing content service placeholder slated for Phase 1 feature work.

## Exposure
- **Public** via Railway routing.

## Endpoints
| Path | Method | Description |
|------|--------|-------------|
| `/health` | GET | Returns `{"status":"ok"}` for readiness. |
| `/live` | GET | Returns `{"status":"live"}` for liveness. |

## Environment
No runtime environment variables are required.

## Local Development
```bash
composer install
php -S 0.0.0.0:8080 index.php
```

## Health Expectations
- `/health` returns `200` when the service container is responsive.
- `/live` may be used by Kubernetes-style liveness probes.
