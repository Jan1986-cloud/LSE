This is a comprehensive Mission Brief for the Team Lead responsible for migrating and evolving the AI Content Generation platform.

---

**MISSION BRIEF: Project Aurora - Headless AI Content SaaS Implementation**

**To:** Team Lead, Project Aurora
**From:** [Your Name/Company Leadership]
**Date:** November 5, 2025
**Subject:** Implementation Strategy, Milestones, and Standards for the new Microservices Architecture

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
*   **PHP:** Strict adherence to PSR-12. Utilize PHPStan (or similar static analysis) in the CI pipeline.
*   **JavaScript/TypeScript:** Adherence to ESLint (e.g., Airbnb style) and Prettier.
*   **Enforcement:** Automated tooling must be configured in the CI/CD pipeline. Non-compliant code will not be merged.

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
    *   [ ] All 5 services successfully connect to the DB.
    *   [ ] M-API can successfully ping O-API internally. O/S/A-APIs are confirmed inaccessible from the public internet.
    *   [ ] DB migrations run without error, including support for large TEXT/BLOB data (F5).

#### Phase 1: M-API Core - Auth and Billing Structure (F6)

*   **Deliverables:** User authentication, API key management, Billing service logic (pricing/staffel discounts).
*   **Testing Focus:** Security (Auth tests 401/403/200), Unit tests for billing calculations.
*   **Quality Gate (Go/No-Go):**
    *   [ ] All protected endpoints correctly enforce authentication.
    *   [ ] Billing calculation unit tests pass with 100% accuracy against predefined scenarios (including staffel discounts).
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
