This is a comprehensive Mission Brief for the Team Lead responsible for migrating and evolving the AI Content Generation platform.

---

**MISSION BRIEF: Project Aurora - Headless AI Content SaaS Implementation**

**To:** Team Lead, Project Aurora
**From:** [Your Name/Company Leadership]
**Date:** November 5, 2025
**Subject:** Implementation Strategy, Milestones, and Standards for the new Microservices Architecture

---

## Phase 0 Completion Summary ✅

**Status:** COMPLETED (November 10, 2025)

### Railway Private Networking - The Correct Approach

Phase 0 has been successfully completed with all services communicating correctly. During implementation, we learned the proper way to handle microservice communication on Railway:

**Key Learnings:**

1. **Railway Private Networking DNS:** Services communicate internally via `<service-name>.railway.internal`
2. **Default Internal Port:** Railway services expose port 8080 by default for internal communication
3. **No Fallback Logic Needed:** Railway's infrastructure is reliable - no need for port scanning, fallback arrays, or complex retry mechanisms

**The Correct Implementation Pattern:**

```php
// Simple, direct connection - no fallbacks needed
$url = 'http://o-api.railway.internal:8080/health';
$handle = curl_init($url);
curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
curl_setopt($handle, CURLOPT_TIMEOUT_MS, 1000);
$response = curl_exec($handle);
$httpStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
curl_close($handle);
```

**What We Removed (139 lines of unnecessary code):**
- ❌ Port scanning across multiple fallback ports [8080, 3000, 8000, 5000]
- ❌ Complex environment variable resolution logic
- ❌ Port alias resolution functions
- ❌ Multi-candidate port building logic
- ❌ Nested helper functions for simple HTTP calls

**Result:** Clean, maintainable code that follows Railway best practices. Each microservice-to-microservice call is now a single, straightforward HTTP request.

---

## Phase 1 Progress Update 🚀

**Status:** IN FLIGHT (November 10, 2025)

**Highlights:**

1. Authentication core delivered – protected endpoints now validated through a centralized `AuthGuard` with explicit 401/403 handling and dedicated PHPUnit coverage.
2. API key management enables creation, listing, and revocation with SHA-256 hashing and audit metadata (last four, last used, revocation timestamp).
3. BillingService refactored with deterministic tiered pricing logic; unit tests confirm staffel discounts across low, medium, and high token volumes.
4. Default "Starter" billing plan seeded during migrations to guarantee monetization continuity immediately after user registration.

**Next:** Harden production DB migrations and extend integration tests once O-API token logging (Phase 2) is available.

## Phase 2 Progress Update 🧭

**Status:** IN FLIGHT (November 10, 2025)

**Highlights:**

1. O-API toolchain refactored with dependency-injected helpers: `WritingTool` (LLM execution) and `ResearchTool` (HTML provenance capture) now reside in `services/o-api/tools/`.
2. Centralized `TokenUsageAggregator` logs prompt/completion totals, workflow IDs, and metadata into `cms_token_logs`, enabling M-API billing precision.
3. Provenance requirements met via HTML snapshot storage (checksum + timestamp) for every researched source, conforming to `cms_sources` contract.
4. PHPUnit suite established for O-API with in-memory PDO stubs; coverage verifies token logging, JSON repair heuristics, and provenance persistence without external calls.

**Next:** Integrate the new O-API tools into the orchestration workflows and validate against live Railway PostgreSQL before closing the Phase 2 gate.

---

## Phase 3 Addendum 🚧

**Status:** KICKOFF (November 10, 2025)

**Blueprint Foundation (M-API):**

- REST endpoints for `/blueprints` and `/blueprints/{id}` now ship with full CRUD support behind AuthGuard enforcement. Token-authenticated operators can list, create, update (with version bump logic), and delete blueprints through a deterministic JSON contract.
- `BlueprintService` encapsulates persistence and validation: PSR-12 PHP, PDO-backed workflow storage, and automatic version increments when structural fields (`name`, `category`, `description`, `workflowDefinition`) change. Status-only edits leave versions untouched for stable release management.
- PHPUnit 10.5 coverage uses an in-memory SQLite harness to assert creation, ordering (newest-first with deterministic fallback), version bump semantics, and guardrails against invalid status mutations. Test suite passes in CI (`composer test`).

**Next:** Wire M-API blueprint APIs into the O-API orchestrator contract (`/orchestrate/run-blueprint`), introduce workflow execution fixtures, and begin validating end-to-end context injection (F2) alongside blueprint-driven tool sequencing (F1).

---

## Phase 4 Field Report ⚙️

**Status:** IN FLIGHT (November 10, 2025)

**C-API Delivery Enhancements:**
- `/content/{id}` endpoint online with deterministic lookup by external reference or numeric ID, returning orchestration payloads plus metadata; response headers now advertise `X-Response-Time` to enforce the <150 ms SLO.
- Delivery requests trigger asynchronous analytics beacons to A-API (fire-and-forget cURL with 200 ms cap) capturing latency, blueprint, and site context for each view without blocking the client.
- PHPUnit coverage (`composer test`) validates repository hydration logic and telemetry dispatch hooks via injectable reporters.

**A-API Analytics Ingestion:**
- `/analytics/ingest` POST endpoint operational with schema validation, ISO-8601 coercion, and batched persistence into `cms_analytics_log`.
- IP anonymization implemented via HMAC hashing before storage; raw IP is dropped to satisfy privacy requirements while preserving deduplication capability.
- PHPUnit coverage verifies persistence path and validation guardrails (missing events, anonymized metadata).

**Next:** Wire A-API detections back into C-API adaptive delivery (`/analytics/agent-detections`), introduce queue-backed async ingestion under higher load, and add smoke tests hitting Railway Deploy targets to measure end-to-end latency.

---

## Phase 5 Situation Report 🛰️

**Status:** IN FLIGHT (November 10, 2025)

**S-API Strategic Services:**
- Trend pipeline operational: `/strategy/suggestions` POST endpoint now orchestrates `TrendAnalysisTool`, `SuggestionEngine`, and `SuggestionService` to evaluate trend signals against site context and active blueprints, persisting prioritized suggestions into `cms_content_suggestions`.
- AI agent telemetry loop established: `/strategy/agent-detections` POST endpoint leverages `AiAgentDetector` and `AgentDetectionService` to classify analytics events, storing detections in `cms_agent_detections` with guidance for downstream optimization.
- PHPUnit coverage (`composer test`) executes five new cases validating SQLite-backed persistence, opportunity filtering, and user-agent fingerprint handling, satisfying the Phase 5 unit-test quality gate for suggestion generation and agent detection heuristics.

**Next:**
- Integrate S-API suggestion output with scheduling/cron orchestration and expose generated payloads to C-API consumers for adaptive content surfacing.
- Extend detection feedback loop by propagating `cms_agent_detections` insights into A-API analytics dashboards and Mission Control reporting.
- Document operational runbooks for Railway scheduled jobs once cron wiring is complete, then advance Phase 5 to a full ✅ gate review.

---

## Phase 6 Integration Update 🧩

**Status:** IN FLIGHT (November 10, 2025)

**WordPress Operator Plugin (Client Connector):**
- Delivered `client/wp-plugin` with the **Luminate Strategy Engine Connector** plugin, registering admin experiences for blueprints, strategy insights, analytics, billing, and service connectivity configuration.
- Blueprint builder UI consumes M-API (`GET/POST /blueprints`) via bearer token auth, enabling creation with workflow JSON payloads directly from WordPress.
- Strategy console accepts trend signal fixtures and calls S-API (`POST /strategy/suggestions`) to surface prioritized opportunities, while Analytics tooling posts to `/strategy/agent-detections` for AI agent fingerprinting feedback.
- Billing page integrates with `GET /billing/status`, giving operators a real-time view into invoices and aggregated token usage without leaving WordPress.

**Next:**
- Harden UX flows with inline validation, blueprint editing/deletion hooks, and contextual documentation linking back to Mission Control.
- Wire remaining service touchpoints (A-API dashboards, C-API content previews) plus enqueue custom styles/scripts for production polish.
- Package plugin for distribution (zip pipeline + versioning) and run acceptance against a staging WordPress site before marking Phase 6 complete.

---

### 1. Mission Objective

Our objective is to transition the existing monolithic AI Content Generator into a scalable, cloud-native, headless SaaS platform hosted on Railway.app. We are decoupling the powerful AI orchestration logic from the WordPress-specific infrastructure to create a highly maintainable and commercially scalable product.

This is not merely a lift-and-shift. We are evolving the platform from a tool into an intelligent content ecosystem designed for the age of AI discoverability.

### 2. Strategic Imperatives (The Core Value Propositions)

The new architecture must support the following six strategic imperatives that define our unique market position:

*   **F1: Dynamic Workflow Configuration (Blueprints):** Users must be able to configure custom AI workflows (e.g., "Quick News Fact" vs. "Deep Dive Research") using our available tools.
*   **F2: Automated Context Gathering:** The system must automatically analyze a target website to understand its context, tone, and audience, informing all content generation.
*   **F3: Proactive Content Strategy:** A dedicated service must analyze market trends and suggest high-impact content opportunities proactively.
*   **F4: Deep Analytics & AI Agent Detection:** We must analyze traffic to understand how AI agents (like GoogleBot, Perplexity) consume the content, creating a feedback loop for optimization (including anonymized global data).
*   **F5: Rigorous Source Management (Provenance):** All facts must be linked to verifiable sources, including timestamped HTML snapshots of the source at the time of research.
*   **F6: Token-Based Billing Model:** All LLM usage must be meticulously tracked per user and workflow, enabling a transparent, usage-based billing system with staffel discounts.

### 3. Architectural Blueprint

We will utilize a 5-microservice architecture deployed on Railway.app, using Railway PostgreSQL as the central data store. Inter-service communication must utilize Railway Private Networking.

1.  **M-API (Management):** Admin hub. Handles Blueprints (F1), Site Context (F2), Billing (F6), and Authentication.
2.  **O-API (Orchestration):** Execution engine. Runs the Blueprints, executes all AI Tools, manages Source Provenance (F5), and logs Token Usage (F6). (Internal Only)
3.  **C-API (Content Delivery):** Public endpoint. Delivers finalized JSON content rapidly. Reports usage data to A-API.
4.  **S-API (Strategy):** Proactive intelligence. Runs scheduled jobs for market research and content suggestions (F3). (Internal Only)
5.  **A-API (Analytics):** Data analysis. Ingests traffic data, detects AI agents, and analyzes performance (F4). (Internal Only)

### 4. Code Management and Standards

Strict adherence to code quality and organizational standards is mandatory to maintain this complex ecosystem.

#### 4.1 Repository Structure

We will use a Monorepo structure as defined in the project plan. The Team Lead is responsible for enforcing the separation of concerns. All code must be placed in the correct service directory (`services/o-api`, `services/m-api`, etc.). Shared contracts are strictly maintained in the `shared/contracts` directory.

#### 4.2 Coding Standards (`codestyle.md`)

A `codestyle.md` file must be created and maintained at the root of the repository, defining the exact standards and linting configurations.
*   **PHP:** Strict adherence to PSR-12. Utilize PHPStan (or similar static analysis) for quality assurance.
*   **JavaScript/TypeScript:** Adherence to ESLint (e.g., Airbnb style) and Prettier.
*   **Enforcement:** Quality standards enforced through Railway deployment and code review processes.

#### 4.3 Documentation (`agent.md` & `README.md`)

*   **Service Documentation:** Each service within the `services/` directory must contain a `README.md` detailing its role, endpoints, dependencies, and local development setup.
*   **AI Logic (`agent.md`):** The O-API and S-API must include an `agent.md` file. This document will detail the AI logic, the interaction between tools, the prompt strategies used within each tool, and the mechanism for the Quality Check feedback loop. This is essential for maintaining the AI components.

### 5. Phased Implementation, Milestones, and Quality Gates

This project is divided into six phases. Each phase has specific deliverables, testing requirements, and strict **"Go/No-Go" Quality Gates**.

**CRITICAL PROTOCOL:** If a Quality Gate fails, the phase is considered incomplete. The Team Lead must initiate an immediate review, identify the root cause, and direct the team to rework the necessary components until the gate criteria are fully met. **We do not proceed to the next phase until the current one is demonstrably stable and correct.**

#### Phase 0: Infrastructure and Data Contracts

*   **Deliverables:** 5 services deployed on Railway, PostgreSQL provisioned, Private Networking configured, finalized JSON schemas for Articles and Blueprints (F1).
*   **Testing Focus:** Connectivity, deployment stability, schema validation.
*   **Quality Gate (Go/No-Go):**
    *   [✅] All 5 services successfully connect to the DB.
    *   [✅] M-API can successfully ping O-API internally. O/S/A-APIs are confirmed inaccessible from the public internet.
    *   [✅] DB migrations run without error, including support for large TEXT/BLOB data (F5).
    *   [✅] **Code Quality:** Unnecessary complexity removed - clean implementation using Railway best practices (139 lines of fallback logic eliminated).

**Phase 0 Status:** ✅ **COMPLETED** (November 10, 2025)

#### Phase 1: M-API Core - Auth and Billing Structure (F6)

*   **Deliverables:** User authentication, API key management, Billing service logic (pricing/staffel discounts).
*   **Testing Focus:** Security (Auth tests 401/403/200), Unit tests for billing calculations.
*   **Quality Gate (Go/No-Go):**
    *   [✅] All protected endpoints correctly enforce authentication.
    *   [✅] Billing calculation unit tests pass with 100% accuracy against predefined scenarios (including staffel discounts).
    *   ***Failure Mandate:*** If billing calculations are inaccurate, the `BillingService` must be refactored immediately. We cannot proceed with flawed monetization logic.

#### Phase 2: O-API - Tools, Provenance (F5), and Token Logging (F6)

*   **Deliverables:** Migrated AI Tools, implemented `TokenUsageAggregator`, enhanced `ResearchTool` with HTML snapshotting.
*   **Testing Focus:** Unit tests with MOCKED external APIs (LLMs, Search). Data integrity tests.
*   **Quality Gate (Go/No-Go):**
    *   [ ] Execution of any tool results in a precise entry in `cms_token_logs` (F6).
    *   [ ] `ResearchTool` successfully captures and stores an HTML snapshot in `cms_sources` (F5).
    *   [ ] `JsonValidatorTool` correctly validates (and repairs) output against the Article Schema.
    *   ***Failure Mandate:*** This is a critical phase. Failure to accurately log tokens or capture sources requires an immediate halt and rework of the O-API core utilities.

#### Phase 3: Dynamic Orchestration (F1) and Context (F2)

*   **Deliverables:** M-API Blueprint CRUD, O-API `OrchestratorAgent` refactored for dynamic execution, `ContextGatheringTool` implemented.
*   **Testing Focus:** Integration tests between M-API and O-API. Workflow execution path verification.
*   **Quality Gate (Go/No-Go):**
    *   [ ] The Orchestrator can successfully execute two distinct Blueprints (e.g., "Simple" and "Complex") with different tool sequences and instructions.
    *   [ ] `ContextGatheringTool` successfully populates `cms_site_context` from a test URL.
    *   [ ] The gathered context is demonstrably used in the prompts during workflow execution.

#### Phase 4: C-API (Delivery) and A-API (Analytics Ingestion) (F4)

*   **Deliverables:** C-API content delivery endpoint, A-API data ingestion endpoint, Asynchronous reporting mechanism.
*   **Testing Focus:** Performance (Latency), Data integrity, Asynchronous reliability.
*   **Quality Gate (Go/No-Go):**
    *   [ ] C-API response latency (TTFB) is under 150ms.
    *   [ ] Data requested from C-API is reported to A-API without significantly impacting C-API latency.
    *   [ ] Data anonymization protocols for global analysis are correctly implemented in A-API.
    *   ***Failure Mandate:*** If C-API latency exceeds the threshold, stop and optimize (e.g., DB indexing, caching) before proceeding.

#### Phase 5: S-API (Strategy) (F3) and Advanced Analysis (F4)

*   **Deliverables:** Railway Cron Jobs configured, `TrendAnalysisTool`, `SuggestionEngine`, `AiAgentDetector`.
*   **Testing Focus:** Unit tests for suggestion relevance (using mocked trend data). Accuracy tests for AI agent detection.
*   **Quality Gate (Go/No-Go):**
    *   [ ] `SuggestionEngine` generates relevant content proposals based on input fixtures (Trends + Context + Blueprints).
    *   [ ] `AiAgentDetector` correctly identifies common AI bot User-Agents (e.g., Google-Extended, ChatGPT-User) from test data.

#### Phase 6: WP Plugin (Client Connector)

*   **Deliverables:** Fully functional WordPress plugin integrating all backend services (F1-F6). UIs for Blueprint Builder, Context Config, Strategy Dashboard, Analytics, and Billing.
*   **Testing Focus:** End-to-End (E2E) functional testing. User Experience (UX) validation.
*   **Quality Gate (Go/No-Go):**
    *   [ ] E2E Test Success: A user can configure a site (F2), define a blueprint (F1), execute a workflow, verify the source snapshots (F5), view the rendered content (C-API/WP), and see the correct token usage deducted in the billing UI (F6).

### 6. Success Criteria

The mission is successful when we have a stable, deployed SaaS platform where all strategic imperatives (F1-F6) are functional, tested, and performing efficiently, capable of serving content to multiple client websites simultaneously.

Team Lead, you are responsible for the technical execution and quality of this platform. Maintain clear communication, enforce standards rigorously, and do not compromise on the quality gates.
