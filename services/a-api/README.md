# A-API Service

## Role
Internal analytics and attribution placeholder slated for later mission phases.

## Exposure
- **Internal-only** via Railway Private Networking (`http://a-api.railway.internal`).

## Endpoints
| Path | Method | Description |
|------|--------|-------------|
| `/health` | GET | Returns `{"status":"ok"}` for readiness. |
| `/live` | GET | Basic liveness check. |

## Environment
No runtime environment variables are required.

## Local Development
```bash
composer install
php -S 0.0.0.0:8080 index.php
```

## Health Expectations
- `/health` only available inside private network.
- `/live` used for container liveness probes.
