# Luminate Strategy Engine Connector (WordPress Plugin)

This plugin delivers the Phase 6 bridge between the Project Aurora microservices and a WordPress property. It exposes admin tooling so editors can:

- Configure API connectivity and operator credentials.
- Orchestrate Blueprint CRUD against M-API.
- Generate immediate trend-informed content suggestions via S-API.
- Classify analytics events for AI agent detection feedback loops.
- View token usage and billing summaries pulled from M-API.

## Installation

1. Copy the `client/wp-plugin` directory into your WordPress installation under `wp-content/plugins/luminate-strategy-engine`.
2. Ensure the PHP server can reach the Railway-hosted services via their public HTTPS endpoints.
3. Activate **Luminate Strategy Engine Connector** from the WordPress admin Plugins screen.

## Configuration

Open **Headless AI → Settings** after activation and provide the following values:

| Field | Purpose |
| --- | --- |
| M-API Base URL | Public base URL for the Management API (e.g., `https://m-api-production.up.railway.app`). |
| S-API Base URL | Public base URL for the Strategy API (e.g., `https://s-api-production.up.railway.app`). |
| A-API Base URL | Optional (reserved for future analytics dashboards). |
| C-API Base URL | Optional (used for preview links in future revisions). |
| Operator API Key | API key generated via `POST /auth/api-keys` (bearer token). |
| Site Context ID | Numeric identifier for the site context to feed into suggestion generation. |

Save the settings and verify connectivity by loading any subpage (Blueprints, Strategy, Analytics, Billing). Errors are surfaced as WordPress admin notices.

## Admin Workflows

### Blueprints

- Lists existing blueprints for the authenticated operator.
- Provides a creation form supporting name, status, description, and workflow JSON payload.
- Uses `GET /blueprints` and `POST /blueprints` behind the scenes.

### Strategy

- Accepts a JSON payload of trend signals and optional Blueprint IDs.
- Calls `POST /strategy/suggestions` and renders the returned prioritized actions.
- Useful for rapid experimentation before automating cron-driven generation.

### Analytics

- Accepts a JSON array of analytics events (log ID + user agent).
- Posts to `POST /strategy/agent-detections` to classify AI agent traffic.
- Displays detection metadata plus guidance strings for optimization.

### Billing

- Pulls billing status via `GET /billing/status` for the current operator.
- Presents invoice history and aggregated token usage if available.

## Development Notes

- The plugin relies on the WordPress HTTP API (`wp_remote_request`) and requires PHP 8.1+ to align with the backend services.
- All requests automatically append the configured bearer token when present.
- Responses are validated for JSON and surfaced as admin notices on failure.
- Future enhancements will wire A-API analytics dashboards and expose C-API delivery previews directly within WordPress.
