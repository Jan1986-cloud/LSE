# S-API Agent Notes

- **Scope:** internal scheduling/orchestration helpers only; never expose publicly.
- **Operational checks:**
  - `/health` must return `200` from `*.railway.internal` clients.
  - `/live` ensures the PHP runtime is alive without dependencies.
- **Quality loop:** ensure PSR-12 compliance and static analysis standards before deployment.
- **Networking:** reachable only over Railway Private Networking; requests from public ingress should be rejected upstream.
