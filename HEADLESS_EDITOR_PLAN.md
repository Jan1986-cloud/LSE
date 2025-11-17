# Headless Editor - Technical Plan

## Concept
Een moderne content editor als alternatief voor WordPress admin, waarbij content via WordPress REST API wordt opgeslagen.

## Architectuur

```
┌─────────────────┐
│  Klant Browser  │
│  (editor UI)    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│   Editor API    │ ← Nieuw te bouwen
│   (Railway)     │
└────────┬────────┘
         │
    ┌────┴────┐
    │         │
    ▼         ▼
┌────────┐  ┌──────────────┐
│ M-API  │  │  WordPress   │
│ (Auth) │  │  REST API    │
└────────┘  │ (klant site) │
            └──────────────┘
```

## Frontend Stack (Editor UI)

### Optie A: React + TipTap (Aanbevolen)
```javascript
- React 18 + Vite
- TipTap (WYSIWYG editor zoals Notion)
- Tailwind CSS
- React Query (API calls)
- Zustand (state management)
```

**Voordelen:**
- TipTap = moderne editor (blocks, markdown, extensible)
- Snelle development met Vite
- Makkelijk AI features toevoegen
- Goede preview functionaliteit

### Optie B: Vue + Quill
```javascript
- Vue 3 + Vite
- Quill editor
- Pinia (state)
```

## Backend: Editor API Service

### Functionaliteit
```php
services/editor-api/
├── index.php (Slim Framework)
├── src/
│   ├── WordPressProxy.php      // Proxy naar WP REST API
│   ├── EditorAuthMiddleware.php // Check M-API token
│   ├── AIAssistant.php          // Integratie met O-API
│   └── PreviewGenerator.php     // Live preview
```

### Endpoints
```
POST   /api/posts              // Create draft
GET    /api/posts/{id}         // Get post voor editing
PUT    /api/posts/{id}         // Update post
POST   /api/posts/{id}/publish // Publish naar WP
GET    /api/posts/{id}/preview // Preview URL
POST   /api/ai/suggest         // AI suggesties tijdens typen
POST   /api/ai/complete        // Auto-complete paragraph
GET    /api/media              // WordPress media library
POST   /api/media              // Upload media
```

## WordPress REST API Integratie

### Authentication
WordPress REST API ondersteunt:
1. **Application Passwords** (WordPress 5.6+)
   - Klant genereert app password in WP
   - Editor API gebruikt dit voor auth

2. **JWT Tokens** (via plugin)
   - WordPress JWT plugin installeren
   - Token-based auth

### Voorbeeld Code
```php
// In Editor API - proxy naar WordPress
class WordPressProxy {
    private string $siteUrl;
    private string $appPassword;
    
    public function createPost(array $data): array {
        $response = $this->request('POST', '/wp-json/wp/v2/posts', [
            'title' => $data['title'],
            'content' => $data['content'],
            'status' => 'draft',
            'categories' => $data['categories'] ?? []
        ]);
        
        return $response;
    }
    
    private function request(string $method, string $path, array $body = []): array {
        $ch = curl_init($this->siteUrl . $path);
        curl_setopt($ch, CURLOPT_USERPWD, "user:" . $this->appPassword);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $result = curl_exec($ch);
        return json_decode($result, true);
    }
}
```

## Railway Deployment

### Static Site (Marketing)
```yaml
# railway.toml toevoegen voor website service
[[services]]
name = "website"
dockerfile = "website/Dockerfile"

[services.website.deploy]
startCommand = "nginx -g 'daemon off;'"

[[services.website.domains]]
domain = "lightspeed-editor.eu"
```

```dockerfile
# website/Dockerfile
FROM nginx:alpine
COPY . /usr/share/nginx/html
COPY nginx.conf /etc/nginx/nginx.conf
```

### Editor API Service
```yaml
[[services]]
name = "editor-api"
source = "services/editor-api"

[services.editor-api.build]
builder = "nixpacks"

[[services.editor-api.domains]]
domain = "editor.lightspeed-editor.eu"
```

### Editor Frontend (React App)
```yaml
[[services]]
name = "editor-ui"
source = "client/editor-ui"

[services.editor-ui.build]
builder = "nixpacks"
buildCommand = "npm run build"
startCommand = "npm run preview"

[[services.editor-ui.domains]]
domain = "app.lightspeed-editor.eu"
```

## AI Features Integratie

Tijdens het typen kan de editor **real-time suggesties** geven:

```typescript
// In React editor component
const { data: suggestions } = useQuery({
  queryKey: ['ai-suggestions', currentText],
  queryFn: () => fetch('/api/ai/suggest', {
    method: 'POST',
    body: JSON.stringify({ 
      context: currentText,
      blueprint_id: activeBlueprint 
    })
  }),
  enabled: currentText.length > 50,
  refetchInterval: 5000
});
```

Dit roept O-API aan voor:
- Paragraph completions
- Headline suggesties  
- SEO optimalisaties
- Tone-of-voice checks

## Development Roadmap

### Phase 1: Marketing Site op Railway (1 dag)
- [x] Static HTML/CSS gemaakt
- [ ] Nginx Dockerfile
- [ ] Railway deployment config
- [ ] Custom domain (lightspeed-editor.eu)

### Phase 2: Editor API Foundation (3-5 dagen)
- [ ] Editor API service opzetten (Slim)
- [ ] WordPress REST API proxy
- [ ] Auth via M-API tokens
- [ ] CRUD endpoints voor posts
- [ ] Media upload proxy

### Phase 3: Editor Frontend MVP (5-7 dagen)
- [ ] React + Vite project setup
- [ ] TipTap editor integratie
- [ ] Basic post CRUD interface
- [ ] Preview functionaliteit
- [ ] Media library browser

### Phase 4: AI Features (3-5 dagen)
- [ ] AI suggesties tijdens typen
- [ ] Auto-complete functionaliteit
- [ ] Blueprint selector in editor
- [ ] Real-time SEO scoring

### Phase 5: Polish & Deploy (2-3 dagen)
- [ ] Error handling
- [ ] Loading states
- [ ] Railway deployment
- [ ] Custom domains
- [ ] SSL certificates

**Totaal: 14-21 werkdagen voor volledige headless editor**

## Klant Setup Flow

1. **Registreer op lightspeed-editor.eu** → krijgt API key
2. **WordPress plugin installeren** → vult API key in
3. **WordPress Application Password genereren**
4. **In editor dashboard:** WordPress site URL + App Password invoeren
5. **Klaar!** Kan nu kiezen:
   - Optie A: WordPress admin gebruiken (traditioneel)
   - Optie B: app.lightspeed-editor.eu gebruiken (modern)

## Kosten Overweging

Railway pricing per service:
- $5/maand per service (Hobby plan)
- 7 services = ~$35/maand

**Of:** Alles bundelen in 1 monolith service:
```
services/web-api/
├── public/
│   ├── marketing/     (static site)
│   └── app/          (editor UI build)
├── api/
│   ├── management/   (M-API)
│   ├── editor/       (Editor API)
│   └── proxy/        (internal routing)
```

Dit kan op **1 Railway service** = $5/maand

## Aanbeveling

**Start met Phase 1 (marketing site)** - dat is snel te deployen.

Voor de **headless editor**: Dit is een **medium-complex project**. De WordPress REST API maakt het mogelijk, maar je bouwt eigenlijk een mini-CMS frontend.

**Alternatieven overwegen:**
1. **Eerst alleen AI features in WordPress plugin** (sneller ROI)
2. **Later headless editor** als klanten erom vragen
3. **Of:** Editor API bouwen die **WordPress Gutenberg blocks** genereert (minder werk)

Wil je dat ik **start met Phase 1** (marketing site op Railway)?
Of wil je **eerst de editor API** proof-of-concept maken?
