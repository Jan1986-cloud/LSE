# O-API Agent Guidelines

- **Purpose:** provide internal orchestration helpers for provenance capture, LLM execution, and JSON validation. Phase 2 tightens auditing (F5/F6) across every tool run.
- **Consumers:** `m-api` orchestrations during workflow execution, and future internal agents that require reliable source capture or billing telemetry.
- **Network Scope:** Railway Private Networking only (`o-api.railway.internal`). Reject any attempts to expose public listeners.
- **Tooling Overview:**
  - `ResearchTool` stores HTML snapshots, checksums, authorship, and capture timestamps into `cms_sources`.
  - `WritingTool` wraps every LLM call, mandating token logging via `TokenUsageAggregator` before returning content to callers.
  - `JsonValidatorTool` ensures downstream consumers always receive well-formed JSON, applying safe repairs where possible.
  - `TokenUsageAggregator` is the single writer for `cms_token_logs`; do not bypass it.
- **Quality Loop:**
  1. Unit tests must pass (`composer test`) with all external dependencies mocked.
  2. Verify `/health` and `/internal/ping` respond with HTTP 200 from inside the private network.
  3. Confirm token usage entries materialize per run with correct `service_tag`, `workflow_id`, and token counts.
  4. Ensure provenance captures persist snapshots and timestamps for every external source fetch.
- **Operational Notes:**
  - Always inject dependencies (PDO, fetchers, LLM clients) for testability; never instantiate network clients directly inside tests.
  - Static analysis follows the repository `codestyle.md` guidance (PSR-12, PHPStan-ready design).
  - Log metadata sparingly but sufficiently for billing reconciliation (model, message count, request identifiers).
