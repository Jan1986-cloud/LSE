# Project Aurora - Technical Debt Log
This file tracks known architectural compromises made for the sake of velocity.
## Phase 0: Infrastructure
* **Issue:** `P0-001: Hardcoded Internal Port`
* **Service:** `m-api`
* **File:** `services/m-api/index.php`
* **Description:** The internal port for `o-api` (`8080`) is hardcoded in the `m-api` ping test.
* **Reason:** The dynamic variable injection (`${{o-api.PORT}}`) was failing to populate in the runtime environment, causing a fatal error. We established that Railway consistently uses `8080` internally.
* **Risk:** Low. If Railway changes its internal port allocation, this ping test will fail.
