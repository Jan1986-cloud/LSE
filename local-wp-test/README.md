# Local WordPress E2E Environment

Spin up a disposable WordPress instance to validate the **Luminate Strategy Engine Connector** plugin (Phase 6 E2E).

## Prerequisites

- Docker and Docker Compose installed locally.
- Repository checked out so the plugin exists at `../client/wp-plugin` relative to this folder.

## Usage

```powershell
cd local-wp-test
docker-compose up -d --build
```

After the containers start, WordPress is available at [http://localhost:8080](http://localhost:8080).

### Credentials

- **Username:** `teamlead`
- **Password:** `password`

The site boots as **"The Aurora Digital - E2E Test Site"** with creative sample posts and the `Luminate Strategy Engine Connector` plugin pre-installed and activated.

To stop and remove containers (but keep volumes):

```powershell
docker-compose down
```

To reset everything, including the persistent volumes:

```powershell
docker-compose down -v
```
