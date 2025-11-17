# Deploy Marketing Website (WordPress) to Railway

## Stap 1: MySQL Database Toevoegen

1. **Ga naar je Railway project dashboard**
2. Klik **"+ New"** → **"Database"** → **"Add MySQL"**
3. Wacht tot MySQL gedeployed is
4. Kopieer de connection details (komen automatisch in environment variables)

## Stap 2: Website Service Deployen

### Via Railway CLI (Aanbevolen):

```powershell
# Zorg dat je in de project root bent
cd C:\Users\Vulpe\Site\LSE\LSE-2\LSE

# Login bij Railway (als je dit nog niet hebt gedaan)
railway login

# Link naar je project
railway link

# Deploy
git add .
git commit -m "Add WordPress marketing site"
git push

# Railway detecteert railway.toml en deployt automatisch
```

### Via Railway Dashboard:

1. Je repo is al gelinkt (Jan1986-cloud/LSE)
2. Push naar main branch
3. Railway deploy automatisch

## Stap 3: Environment Variables Configureren

**In Railway Dashboard:**

1. Klik op **website-wp service**
2. Ga naar **"Variables"** tab
3. Voeg toe:

### Database Connectie (koppel aan MySQL service):
```
MYSQL_HOST = ${{MySQL.MYSQL_PRIVATE_URL}} (of gebruik Railway's reference)
MYSQL_DATABASE = railway
MYSQL_USER = root  
MYSQL_PASSWORD = ${{MySQL.MYSQL_ROOT_PASSWORD}}
```

> **TIP:** Railway heeft "Service Variables" - klik op "Add Reference" en selecteer MySQL service

### WordPress Admin:
```
WP_ADMIN_USER = admin
WP_ADMIN_PASSWORD = (genereer sterk wachtwoord)
WP_ADMIN_EMAIL = info@lightspeed-editor.eu
```

### WordPress Security Keys
Genereer op: https://api.wordpress.org/secret-key/1.1/salt/

```
WP_AUTH_KEY = (unique string)
WP_SECURE_AUTH_KEY = (unique string)
WP_LOGGED_IN_KEY = (unique string)
WP_NONCE_KEY = (unique string)
WP_AUTH_SALT = (unique string)
WP_SECURE_AUTH_SALT = (unique string)
WP_LOGGED_IN_SALT = (unique string)
WP_NONCE_SALT = (unique string)
```

### LSE Plugin Integratie:
```
LSE_M_API_URL = https://m-api-production.up.railway.app
LSE_API_KEY = (je eigen test API key - genereer deze in M-API)
```

### Optioneel:
```
WP_THEME = astra
WP_DEBUG = false
RAILWAY_PUBLIC_DOMAIN = lightspeed-editor.eu (komt automatisch na custom domain setup)
```

## Stap 4: Deploy Monitoren

```powershell
# Bekijk logs
railway logs -s website-wp

# Check status
railway status
```

De eerste deployment duurt ~3-5 minuten:
1. ✅ Docker image build
2. ✅ WordPress download
3. ✅ Database connectie check
4. ✅ WordPress installatie
5. ✅ Plugin setup

## Stap 5: WordPress Toegang

1. **Genereer public URL:**
   - In Railway: website-wp service → Settings → Networking
   - Click "Generate Domain"
   - Krijg iets als: `website-wp-production-xxxx.up.railway.app`

2. **Login:**
   - Ga naar: `https://website-wp-production-xxxx.up.railway.app/wp-admin`
   - Username: (je WP_ADMIN_USER)
   - Password: (je WP_ADMIN_PASSWORD)

## Stap 6: LSE Plugin Installeren

### Optie A: Handmatige Upload (Simpelst)

1. In WP admin: **Plugins → Add New → Upload Plugin**
2. Upload `client/wp-plugin` (zip het eerst)
3. Activeer plugin
4. **Configureer:** Settings → LightSpeed Editor
   - API Key wordt automatisch ingesteld (van LSE_API_KEY env var)
   - Of vul handmatig in

### Optie B: Via WP-CLI in Railway

```powershell
# SSH into Railway container
railway run bash -s website-wp

# In container:
wp plugin install /plugin-source --activate --allow-root
wp option update lse_api_key "your-api-key" --allow-root
```

## Stap 7: Custom Domain Setup (lightspeed-editor.eu)

### In Railway:
1. website-wp service → **Settings** → **Networking**
2. **Custom Domains** → **Add Domain**
3. Voer in: `lightspeed-editor.eu`
4. Railway geeft je CNAME value

### In je DNS (bijv. Cloudflare, TransIP):
```
Type: CNAME
Name: @ (of www voor www.lightspeed-editor.eu)
Value: (de Railway CNAME waarde)
TTL: Auto
```

### SSL Certificate:
- Railway genereert automatisch Let's Encrypt SSL
- Wacht 5-10 minuten na DNS update

## Stap 8: WordPress Site Bouwen

1. **Theme installeren:**
   - Appearance → Themes → Add New
   - Installeer modern theme (Astra, GeneratePress, Kadence)

2. **Pagina's maken:**
   - **Homepage**: Features, pricing, CTA
   - **Blog**: Voor content marketing
   - **Docs**: Plugin documentatie
   - **Legal**: Privacy Policy, Terms

3. **Test LSE Plugin:**
   - Maak nieuwe post
   - Gebruik LSE Blueprints
   - Test AI generatie
   - Verifieer analytics tracking

## Stap 9: Eerste Content Genereren

1. **Ga naar:** Posts → Add New
2. **Klik:** "LightSpeed Editor" button
3. **Selecteer:** Blueprint (maak eerst blueprints aan)
4. **Genereer:** AI content
5. **Publiceer!**

Nu gebruik je je **eigen product op je eigen site** 🚀

## Troubleshooting

### WordPress won't install
```powershell
# Check database connection
railway logs -s website-wp | Select-String "MySQL"

# Test MySQL directly
railway run mysql -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASSWORD -s MySQL
```

### Can't access wp-admin
- Check RAILWAY_PUBLIC_DOMAIN in environment variables
- Check WordPress URL: `railway run wp option get siteurl --allow-root`
- Reset if needed: `railway run wp option update siteurl "https://your-domain" --allow-root`

### Plugin errors
- Check M-API is reachable: `curl https://m-api-production.up.railway.app/health`
- Check API key is valid
- Check plugin logs in WP: wp-content/debug.log

### Domain not working
- DNS propagation: wacht 24-48 uur
- Check DNS: `nslookup lightspeed-editor.eu`
- Force SSL in WP: Settings → General → WordPress Address URL (https)

## Backup Strategy

### Database:
```powershell
# Export via Railway CLI
railway run mysqldump -u $MYSQL_USER -p$MYSQL_PASSWORD $MYSQL_DATABASE > backup.sql

# Import later:
railway run mysql -u $MYSQL_USER -p$MYSQL_PASSWORD $MYSQL_DATABASE < backup.sql
```

### Files:
- WordPress core: Reinstallable
- Uploads: Overweeg S3/Cloudflare R2 (WP Offload Media plugin)
- LSE Plugin: Version controlled in repo

## Kosten

Railway Hobby Plan:
- Website-WP service: $5/maand
- MySQL service: $5/maand  
- Bandwidth: Fair use included
- **Totaal: ~$10/maand**

Railway Pro Plan (indien nodig):
- More resources
- Priority support
- ~$20/maand

## Next Steps

- [ ] Deploy naar Railway
- [ ] Configureer environment variables
- [ ] Install WordPress
- [ ] Upload LSE plugin
- [ ] Setup custom domain
- [ ] Install theme
- [ ] Create homepage
- [ ] Write first blog post (met LSE!)
- [ ] Setup Google Analytics
- [ ] Test complete workflow

## Success Criteria

✅ WordPress draait op lightspeed-editor.eu
✅ LSE plugin geïnstalleerd en actief
✅ Content generatie werkt
✅ Analytics tracking werkt
✅ Site is snel (<2s load time)
✅ SSL certificaat actief
✅ Backup strategie werkend

Je hebt nu een **productie marketing site** die tegelijk je **product showcased**! 🎉
