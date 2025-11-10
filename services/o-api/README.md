# O-API Service

## Role
Operational orchestration helpers powering research provenance, JSON validation, and token usage accounting for internal services.

## Exposure
- **Internal-only** via Railway Private Networking at `http://o-api.railway.internal`.
- Never expose this service to the public internet.

## Endpoints
| Path | Method | Description |
|------|--------|-------------|
| `/health` | GET | Readiness indicator consumed by Railway. |
| `/internal/ping` | GET | Internal connectivity probe used by other services. |

## Core Components
- **ResearchTool** &ndash; captures HTML snapshots with checksums and timestamps into `cms_sources`, satisfying Phase 2 provenance requirements.
- **WritingTool** &ndash; wraps LLM completions, records aggregate usage through the `TokenUsageAggregator`, and returns normalized content payloads.
- **JsonValidatorTool** &ndash; validates and auto-repairs common JSON defects (e.g., trailing commas) before handing results back to orchestrators.
- **TokenUsageAggregator** &ndash; canonical entry point for logging prompt/response tokens, workflow IDs, and metadata into `cms_token_logs`.

## Testing
```bash
composer install
composer test
```

All external integrations (LLMs, HTTP fetches) are mocked in the PHPUnit suite to guarantee deterministic CI runs.

## Networking Notes
- Reachable only by internal peers (e.g., `m-api`, `s-api`) via Railway's private DNS: `http://o-api.railway.internal:8080`.
- Honour the Phase 0 private networking policy; no public ingress rules should target this service.
