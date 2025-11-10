#!/usr/bin/env bash
set -euo pipefail

# Delegate to the stock entrypoint to provision WordPress, but keep the process running for customised setup.
/usr/local/bin/docker-entrypoint.sh apache2-foreground &
APACHE_PID=$!

cleanup() {
  if kill -0 "$APACHE_PID" 2>/dev/null; then
    kill "$APACHE_PID"
    wait "$APACHE_PID"
  fi
}

trap cleanup EXIT INT TERM

# Wait for MySQL to be ready before running WP-CLI commands.
until mysqladmin ping -h"${WORDPRESS_DB_HOST%%:*}" --silent; do
  echo "Waiting for database..."
  sleep 2
done

WP_PATH="/var/www/html"
cd "$WP_PATH"

# Ensure WordPress is installed.
if ! wp core is-installed --allow-root; then
  wp core install --allow-root \
    --url="http://localhost:8080" \
    --title="The Aurora Digital - E2E Test Site" \
    --admin_user="teamlead" \
    --admin_password="password" \
    --admin_email="teamlead@example.com"
fi

wp option update blogdescription "Exploring the intersection of AI, Strategy, and Digital Content." --allow-root

# Activate plugin if available.
if wp plugin is-installed luminate-strategy-engine-connector --allow-root; then
  wp plugin activate luminate-strategy-engine-connector --allow-root
fi

# Remove default sample content if present.
wp post delete 1 --allow-root --force || true
wp post delete 2 --allow-root --force || true

ensure_post() {
  local title="$1"
  local content="$2"
  local slug
  slug=$(echo "$title" | tr '[:upper:]' '[:lower:]' | sed "s/[^a-z0-9\- ]//g" | tr ' ' '-')

  if ! wp post list --allow-root --post_type=post --name="$slug" --format=ids | grep -qE '^[0-9]+$'; then
    wp post create --allow-root \
      --post_title="$title" \
      --post_status=publish \
      --post_author=1 \
      --post_content="$content"
  fi
}

ensure_post "Project Aurora: The Future of Headless AI" "A forward-looking exploration of how Project Aurora is redefining AI-driven content production through modular microservices."
ensure_post "Top 5 AI Agent Trends We're Watching (F4)" "From Google-Extended to GPTBot, these are the AI agents reshaping discoverability metrics."
ensure_post "The Power of Dynamic Blueprints (F1)" "Blueprints empower teams to orchestrate bespoke workflows with reusable AI instructions."
ensure_post "Case Study: Why Context-Aware AI (F2) Matters" "Real-world outcomes when Site Context feeds high-signal prompts for content generation."
ensure_post "Monetizing LLMs: A Look at Token-Based Billing (F6)" "A breakdown of tiered token pricing and how usage-based billing keeps Project Aurora sustainable."
ensure_post "Adaptive Strategy with S-API" "Leveraging trend analysis and suggestion engines to stay ahead of market signals."

wait "$APACHE_PID"
