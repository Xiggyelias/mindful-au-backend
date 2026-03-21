# Africa University Counseling System Backend

Laravel REST API for student, staff, peer counselor, and admin workflows.

## Requirements

- PHP 8.1+
- Composer
- Database: MySQL/MariaDB

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Create the local MySQL databases before running migrations:

```sql
CREATE DATABASE counseling_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE counseling_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

API default: `http://127.0.0.1:8000`

## Environment

Set in `backend/.env`:

```env
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://127.0.0.1:5173

GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URL=http://127.0.0.1:8000/api/auth/google/callback
INSTITUTION_EMAIL_DOMAINS=africau.edu

AUTH_REQUIRE_GOOGLE_FOR_STUDENTS=true
AUTH_AUTO_PROVISION_STUDENTS=true
ADMIN_BOOTSTRAP_EMAIL=admin@example.com
ADMIN_BOOTSTRAP_PASSWORD=password123
ADMIN_BOOTSTRAP_NAME="Mindful AU Admin"
ADMIN_BOOTSTRAP_ID_NUMBER=ADM001
```

Security header defaults are configurable via:

```env
SECURITY_X_FRAME_OPTIONS=SAMEORIGIN
SECURITY_REFERRER_POLICY=strict-origin-when-cross-origin
SECURITY_PERMISSIONS_POLICY=camera=(), microphone=(), geolocation=()
SECURITY_FORCE_HSTS=true
SECURITY_HSTS_MAX_AGE=31536000
SECURITY_HSTS_INCLUDE_SUBDOMAINS=true
SECURITY_HSTS_PRELOAD=false
```

## Core API Areas

- Auth: register/login/logout/me, Google OAuth (`/api/auth/google`, `/api/auth/google/callback`)
- Sessions: student and counselor session creation, listing, updates
- Chat: encrypted messages, seen receipts, attachments, peer assignment flow
- Peer Support: assign peer counselor, escalate assigned cases, availability
- Appointments and video-call authorization windows
- Notifications and admin management APIs

## Authentication

Sanctum bearer tokens:

```text
Authorization: Bearer {token}
```

Role resolution for OAuth:
1. `institution_accounts`
2. Existing approved `user_roles`
3. Optional student auto-provisioning by domain

## Health Endpoints

- Liveness: `GET /api/health`
- Readiness (DB + cache): `GET /api/ready`

## Schema

Canonical schema snapshot: `backend/database/schema.sql`

## Tests

```bash
php artisan test
```

The test suite is configured for MySQL and uses `counseling_db_test` by default.

## Production Bootstrap

For production, run under PHP-FPM and Nginx (or equivalent), then warm caches:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Create or update the admin account details from `backend/.env`:

```bash
php artisan admin:create
```

## Docker / Dokploy Deployment

Deployment files are included for Dokploy-compatible container rollout:

- `backend/Dockerfile` (multi-stage Laravel PHP-FPM image)
- `frontend/docker-compose.yml` (app, nginx, redis, queue worker, scheduler)
- `frontend/deploy/nginx/default.conf` (hardened reverse proxy config)
- `backend/docker/php/*` (PHP-FPM + php.ini tuning)
- `backend/docker/supervisor/supervisord.conf` (queue workers)

1. Copy env template:

```bash
cp backend/.env.production.example backend/.env
```

2. Generate app key:

```bash
php artisan key:generate --ansi
```

3. Start stack:

```bash
docker compose -f frontend/docker-compose.yml up -d --build
```

4. Run migrations once:

```bash
docker compose -f frontend/docker-compose.yml exec app php artisan migrate --force
```

Health probes:

- `GET /health` readiness (DB + cache + queue + disk)
- `GET /live` liveness

Backup key rotation note:

- Encrypted backups use Laravel `Crypt` with the active `APP_KEY`.
- Each backup now stores an encryption key fingerprint in backup metadata.
- Before rotating `APP_KEY`, decrypt/re-encrypt legacy backups or keep the prior key available for restore/verification.
- Run `php artisan system:backup:verify --notify` after key changes to confirm restore safety.

Validate production env before deploy (run from workspace root):

```bash
node backend/scripts/validate-production-env.mjs
```

## Load Speed Benchmark

Run the reusable benchmark script from the workspace root:

```bash
node backend/scripts/benchmark-load-speed.mjs
```

Optional knobs:

```bash
# examples
BENCH_RUNS=20 BENCH_CONCURRENCY=15 BENCH_ROUNDS=3 node backend/scripts/benchmark-load-speed.mjs
API_BASE_URL=http://127.0.0.1:8000/api FRONTEND_URL=http://127.0.0.1:5173/ node backend/scripts/benchmark-load-speed.mjs
```
