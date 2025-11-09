-- Project Aurora Core Data Contracts
-- Schema targets Railway PostgreSQL for multi-service consumption

CREATE TABLE IF NOT EXISTS cms_users (
    id BIGSERIAL PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    display_name TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS cms_api_keys (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES cms_users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    hashed_key TEXT NOT NULL UNIQUE,
    last_four TEXT NOT NULL,
    expires_at TIMESTAMPTZ,
    last_used_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS cms_billing_plans (
    id BIGSERIAL PRIMARY KEY,
    plan_code TEXT NOT NULL UNIQUE,
    display_name TEXT NOT NULL,
    description TEXT,
    base_rate DECIMAL(12,6) NOT NULL,
    tier_thresholds JSONB NOT NULL,
    overage_multiplier DECIMAL(8,4) NOT NULL DEFAULT 1.00,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS cms_user_billing_plans (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES cms_users(id) ON DELETE CASCADE,
    billing_plan_id BIGINT NOT NULL REFERENCES cms_billing_plans(id) ON DELETE RESTRICT,
    assigned_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    active BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (user_id, active) WHERE active
);

CREATE TABLE IF NOT EXISTS cms_blueprints (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES cms_users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    description TEXT,
    category TEXT,
    workflow_definition JSONB NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    version INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS cms_site_context (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES cms_users(id) ON DELETE CASCADE,
    target_url TEXT NOT NULL,
    context_snapshot JSONB NOT NULL,
    tone_profile JSONB,
    audience_profile JSONB,
    last_scanned_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, target_url)
);

CREATE TABLE IF NOT EXISTS cms_content_suggestions (
    id BIGSERIAL PRIMARY KEY,
    blueprint_id BIGINT REFERENCES cms_blueprints(id) ON DELETE SET NULL,
    site_context_id BIGINT REFERENCES cms_site_context(id) ON DELETE SET NULL,
    suggestion_payload JSONB NOT NULL,
    priority INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'pending',
    suggested_for DATE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS cms_sources (
    id BIGSERIAL PRIMARY KEY,
    blueprint_id BIGINT REFERENCES cms_blueprints(id) ON DELETE SET NULL,
    source_url TEXT NOT NULL,
    title TEXT,
    author TEXT,
    html_snapshot TEXT NOT NULL,
    captured_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    checksum TEXT,
    UNIQUE (source_url, captured_at)
);

CREATE TABLE IF NOT EXISTS cms_token_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES cms_users(id) ON DELETE CASCADE,
    blueprint_id BIGINT REFERENCES cms_blueprints(id) ON DELETE SET NULL,
    orchestration_id TEXT NOT NULL,
    service_tag TEXT NOT NULL,
    tokens_used INTEGER NOT NULL,
    input_tokens INTEGER DEFAULT 0,
    output_tokens INTEGER DEFAULT 0,
    cost_amount DECIMAL(18,6) NOT NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'USD',
    metadata JSONB,
    logged_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS cms_billing_invoices (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES cms_users(id) ON DELETE CASCADE,
    billing_period_start DATE NOT NULL,
    billing_period_end DATE NOT NULL,
    subtotal DECIMAL(18,6) NOT NULL,
    discounts DECIMAL(18,6) NOT NULL DEFAULT 0,
    taxes DECIMAL(18,6) NOT NULL DEFAULT 0,
    total_due DECIMAL(18,6) NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    issued_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    paid_at TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS cms_orchestrations (
    id BIGSERIAL PRIMARY KEY,
    blueprint_id BIGINT REFERENCES cms_blueprints(id) ON DELETE SET NULL,
    site_context_id BIGINT REFERENCES cms_site_context(id) ON DELETE SET NULL,
    status TEXT NOT NULL,
    run_parameters JSONB,
    result_payload JSONB,
    started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS cms_analytics_log (
    id BIGSERIAL PRIMARY KEY,
    content_id TEXT NOT NULL,
    blueprint_id BIGINT REFERENCES cms_blueprints(id) ON DELETE SET NULL,
    user_id BIGINT REFERENCES cms_users(id) ON DELETE SET NULL,
    event_type TEXT NOT NULL,
    user_agent TEXT,
    request_ip INET,
    metadata JSONB,
    occurred_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS cms_agent_detections (
    id BIGSERIAL PRIMARY KEY,
    analytics_log_id BIGINT NOT NULL REFERENCES cms_analytics_log(id) ON DELETE CASCADE,
    agent_name TEXT NOT NULL,
    agent_family TEXT,
    confidence DECIMAL(5,2) NOT NULL,
    detection_reason TEXT,
    detected_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS cms_content_items (
    id BIGSERIAL PRIMARY KEY,
    orchestration_id BIGINT REFERENCES cms_orchestrations(id) ON DELETE SET NULL,
    blueprint_id BIGINT REFERENCES cms_blueprints(id) ON DELETE SET NULL,
    site_context_id BIGINT REFERENCES cms_site_context(id) ON DELETE SET NULL,
    external_reference TEXT,
    content_payload JSONB NOT NULL,
    published BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Supporting indexes for performance-sensitive queries
CREATE INDEX IF NOT EXISTS idx_api_keys_user_id ON cms_api_keys(user_id);
CREATE INDEX IF NOT EXISTS idx_blueprints_user_id ON cms_blueprints(user_id);
CREATE INDEX IF NOT EXISTS idx_site_context_user ON cms_site_context(user_id);
CREATE INDEX IF NOT EXISTS idx_token_logs_user_time ON cms_token_logs(user_id, logged_at DESC);
CREATE INDEX IF NOT EXISTS idx_token_logs_orchestration ON cms_token_logs(orchestration_id);
CREATE INDEX IF NOT EXISTS idx_analytics_event_type ON cms_analytics_log(event_type);
CREATE INDEX IF NOT EXISTS idx_agent_detections_agent_name ON cms_agent_detections(agent_name);
CREATE INDEX IF NOT EXISTS idx_content_suggestions_status ON cms_content_suggestions(status);
