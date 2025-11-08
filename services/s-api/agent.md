# S-API Agent Notes

- **Scope:** internal scheduling/orchestration helpers only; never expose publicly.
- **Operational checks:**
  - `/health` must return `200` from `*.railway.internal` clients.
  - `/live` ensures the PHP runtime is alive without dependencies.
- **Quality loop:** ensure CI (lint + PHPStan + migrations) passes prior to deployment.
- **Networking:** reachable only over Railway Private Networking; requests from public ingress should be rejected upstream.
