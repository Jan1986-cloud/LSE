# S-API Service

## Role
Internal service placeholder for future orchestration and scheduling capabilities.

## Exposure
- **Internal-only** via Railway Private Networking (`http://s-api.railway.internal`).

## Endpoints
| Path | Method | Description |
|------|--------|-------------|
| `/health` | GET | Returns `{"status":"ok"}` within the private network. |
| `/live` | GET | Returns `{"status":"live"}` for container monitoring. |

## Environment
No runtime environment variables are required.

## Local Development
```bash
composer install
php -S 0.0.0.0:8080 index.php
```

## Health Expectations
- `/health` is only routable inside the private network.
- Public access should be blocked upstream.
