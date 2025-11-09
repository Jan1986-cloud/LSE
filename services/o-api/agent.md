# O-API Agent Guidelines

- **Purpose:** expose internal research and validation helpers to other Phase 0 services.
- **Consumers:** primarily `m-api` for telemetry and research orchestration.
- **Network scope:** Railway Private Networking only. Never expose publicly.
- **QC loop:**
  1. Verify `/health` responds `200` from inside the private network.
  2. Confirm `/migrate` is unreachable; only `/health`/`/live` remain visible.
  3. Ensure PSR-12 compliance and static analysis standards.
- **Testing:** Manual verification on Railway deployment with proper dependency management.
