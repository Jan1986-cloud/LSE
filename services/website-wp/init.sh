#!/bin/bash
set -e

echo "=== LightSpeed Editor - WordPress Marketing Site ==="
echo "Starting initialization..."

# Wait for MySQL to be ready
echo "Waiting for MySQL connection..."
MAX_TRIES=30
COUNT=0

until mysql -h"${MYSQL_HOST%%:*}" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SELECT 1" &> /dev/null || [ $COUNT -eq $MAX_TRIES ]; do
    echo "MySQL not ready yet (attempt $COUNT/$MAX_TRIES)..."
    COUNT=$((COUNT+1))
    sleep 3
done

if [ $COUNT -eq $MAX_TRIES ]; then
    echo "ERROR: Could not connect to MySQL after $MAX_TRIES attempts"
    echo "MySQL Host: ${MYSQL_HOST}"
    echo "MySQL User: ${MYSQL_USER}"
    exit 1
fi

echo "✓ MySQL connection successful!"

# Download WordPress core if not present
if [ ! -f /var/www/html/wp-load.php ]; then
    echo "Downloading WordPress core..."
    wp core download --allow-root --force
fi

# Check if WordPress is installed
if ! wp core is-installed --allow-root 2>/dev/null; then
    echo "Installing WordPress..."
    
    SITE_URL="https://${RAILWAY_PUBLIC_DOMAIN:-localhost:8080}"
    
    wp core install \
        --url="$SITE_URL" \
        --title="LightSpeed Editor - AI Content Generator" \
        --admin_user="${WP_ADMIN_USER:-admin}" \
        --admin_password="${WP_ADMIN_PASSWORD:-changeme123!}" \
        --admin_email="${WP_ADMIN_EMAIL:-admin@lightspeed-editor.eu}" \
        --skip-email \
        --allow-root
    
    echo "✓ WordPress installed successfully!"
    
    # Set site URL and home URL
    wp option update siteurl "$SITE_URL" --allow-root
    wp option update home "$SITE_URL" --allow-root
    
    # Set permalink structure
    wp rewrite structure '/%postname%/' --allow-root
    wp rewrite flush --allow-root
    
    echo "✓ WordPress configured"
else
    echo "✓ WordPress already installed"
fi

# Install LSE API Key if provided
if [ ! -z "$LSE_API_KEY" ]; then
    echo "Configuring LSE API Key..."
    wp option update lse_api_key "$LSE_API_KEY" --allow-root
    wp option update lse_m_api_url "${LSE_M_API_URL:-https://m-api-production.up.railway.app}" --allow-root
    echo "✓ LSE configuration saved"
fi

# Install recommended theme if specified
if [ ! -z "$WP_THEME" ] && ! wp theme is-installed "$WP_THEME" --allow-root 2>/dev/null; then
    echo "Installing theme: $WP_THEME"
    wp theme install "$WP_THEME" --activate --allow-root
    echo "✓ Theme installed"
fi

# Install recommended plugins
echo "Checking recommended plugins..."

# Yoast SEO
if ! wp plugin is-installed wordpress-seo --allow-root; then
    echo "Installing Yoast SEO..."
    wp plugin install wordpress-seo --activate --allow-root
fi

# Security headers
if ! wp plugin is-installed security-headers --allow-root; then
    echo "Installing Security Headers..."
    wp plugin install security-headers --activate --allow-root || echo "Security Headers plugin not found, skipping"
fi

echo "✓ Plugins configured"

# Set correct permissions
chown -R www-data:www-data /var/www/html
find /var/www/html -type d -exec chmod 755 {} \;
find /var/www/html -type f -exec chmod 644 {} \;

echo "✓ Permissions set"

echo ""
echo "=== WordPress is ready! ==="
echo "URL: https://${RAILWAY_PUBLIC_DOMAIN:-localhost:8080}"
echo "Admin: https://${RAILWAY_PUBLIC_DOMAIN:-localhost:8080}/wp-admin"
echo "User: ${WP_ADMIN_USER:-admin}"
echo ""
echo "Starting Apache..."

# Start Apache in foreground
exec apache2-foreground
