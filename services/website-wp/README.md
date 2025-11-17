# Website WordPress Service - README

## Overview
This is the production WordPress installation for lightspeed-editor.eu marketing website.
It runs the LSE plugin to dogfood our own product.

## Railway Setup

### 1. Create MySQL Database Service
```bash
# In Railway dashboard:
# 1. Click "New" → "Database" → "Add MySQL"
# 2. Copy the connection details
```

### 2. Deploy WordPress Service
```bash
# Add this service to railway.toml
railway up
```

### 3. Configure Environment Variables

In Railway dashboard, set these variables for the website-wp service:

**Database (from MySQL service):**
- `MYSQL_HOST` = `mysql.railway.internal` (or external URL)
- `MYSQL_DATABASE` = `railway`
- `MYSQL_USER` = (from MySQL service)
- `MYSQL_PASSWORD` = (from MySQL service)

**WordPress Admin:**
- `WP_ADMIN_USER` = `admin`
- `WP_ADMIN_PASSWORD` = (generate strong password)
- `WP_ADMIN_EMAIL` = `info@lightspeed-editor.eu`

**WordPress Security (generate at https://api.wordpress.org/secret-key/1.1/salt/):**
- `WP_AUTH_KEY`
- `WP_SECURE_AUTH_KEY`
- `WP_LOGGED_IN_KEY`
- `WP_NONCE_KEY`
- `WP_AUTH_SALT`
- `WP_SECURE_AUTH_SALT`
- `WP_LOGGED_IN_SALT`
- `WP_NONCE_SALT`

**LSE Integration:**
- `LSE_M_API_URL` = `https://m-api-production.up.railway.app`
- `LSE_API_KEY` = (your test API key from M-API)

**Optional:**
- `WP_THEME` = `astra` (or any theme slug from wordpress.org)
- `WP_DEBUG` = `false`

### 4. Setup Custom Domain

In Railway service settings:
1. Click "Settings" → "Networking"
2. Click "Generate Domain" (gets you a .railway.app domain first)
3. Add custom domain: `lightspeed-editor.eu`
4. Update your DNS:
   - Type: `CNAME`
   - Name: `@` (or `www`)
   - Value: (the Railway domain provided)

## Plugin Installation

The LSE plugin needs to be installed. Two options:

### Option A: Manual Upload (Recommended for now)
1. After WordPress is running, go to `https://your-domain.railway.app/wp-admin`
2. Login with admin credentials
3. Go to Plugins → Add New → Upload Plugin
4. Upload zipped plugin from `client/wp-plugin/`
5. Activate and configure API key

### Option B: Volume Mount (Advanced)
Add to railway.toml:
```toml
[services.website-wp.volumes]
"/var/www/html/wp-content/plugins/lse-headless-ai" = "client/wp-plugin"
```

## Development Workflow

### Local Testing
```bash
# Use the existing local-wp-test Docker setup
cd local-wp-test
docker-compose up
```

### Deploy to Railway
```bash
# Commit changes
git add .
git commit -m "Update WordPress site"
git push

# Railway auto-deploys from main branch
```

## Content Strategy

Use WordPress to manage:
- **Homepage**: Hero section, features, pricing (use page builder)
- **Blog**: Tips, tutorials, AI content strategy articles
- **Documentation**: Plugin setup, API docs
- **Case Studies**: Customer success stories
- **Legal**: Privacy Policy, Terms of Service

Use LSE Plugin to:
- Generate blog post drafts
- Create SEO-optimized content
- Test AI features in production
- Showcase product capabilities

## Monitoring

### Health Check
WordPress has built-in health check at `/wp-admin/site-health.php`

Railway health check endpoint: `/wp-admin/install.php`

### Logs
```bash
railway logs -s website-wp
```

## Backup Strategy

**Database Backup (Recommended):**
1. Railway MySQL automatic backups (check plan)
2. Or use WP plugin: UpdraftPlus

**Files Backup:**
- WordPress core: Reinstallable
- Uploads: Store in S3/R2 (use WP plugin: WP Offload Media)
- Plugins: Version controlled (LSE) or reinstallable

## Cost Estimation

Railway Pricing:
- WordPress service: ~$5-10/month (Hobby plan)
- MySQL service: ~$5-10/month
- Bandwidth: Included (fair use)

**Total: ~$10-20/month** for production marketing site

## Next Steps

1. ✅ Create services/website-wp structure
2. ⏳ Add to railway.toml
3. ⏳ Deploy to Railway
4. ⏳ Configure environment variables
5. ⏳ Install WordPress
6. ⏳ Upload and activate LSE plugin
7. ⏳ Setup custom domain (lightspeed-editor.eu)
8. ⏳ Install theme and build pages
9. ⏳ Create initial content
10. ⏳ Test LSE plugin functionality

## Troubleshooting

### WordPress won't install
- Check MySQL connection (MYSQL_HOST, credentials)
- Check logs: `railway logs -s website-wp`

### Plugin not showing
- Check file permissions in container
- Manually upload via WP admin

### Domain not working
- DNS propagation takes 24-48 hours
- Check CNAME record is correct
- Verify SSL certificate in Railway

### LSE Plugin errors
- Check LSE_M_API_URL is correct
- Verify API key is valid
- Check M-API logs for requests
