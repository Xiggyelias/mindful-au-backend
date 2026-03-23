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
- Detailed readiness diagnostics are hidden from public responses by default. Set `HEALTH_EXPOSE_DETAILS=true` only for trusted internal monitoring, or call `/api/ready` as an authenticated admin.

Backup key rotation note:

- Encrypted backups use Laravel `Crypt` with the active `APP_KEY`.
- Each backup now stores an encryption key fingerprint in backup metadata.
- Before rotating `APP_KEY`, decrypt/re-encrypt legacy backups or keep the prior key available for restore/verification.
- Run `php artisan system:backup:verify --notify` after key changes to confirm restore safety.

Validate production env before deploy (run from workspace root):

```bash
node backend/scripts/validate-production-env.mjs
```

Validate against a 2k-4k active-user scale target:

```bash
SCALE_TARGET_USERS=4000 node backend/scripts/validate-production-env.mjs
```

Full deployment runbook and VM templates:

- Runbook: [docs/PRODUCTION_2K_4K_RUNBOOK.md](/C:/Users/emmanuel/Desktop/mindful-au-main/backend/docs/PRODUCTION_2K_4K_RUNBOOK.md)
- Nginx API template: [deploy/nginx/mindful-au-api.conf](/C:/Users/emmanuel/Desktop/mindful-au-main/backend/deploy/nginx/mindful-au-api.conf)
- Apache API template: [deploy/apache/mindful-au-api-vhost.conf](/C:/Users/emmanuel/Desktop/mindful-au-main/backend/deploy/apache/mindful-au-api-vhost.conf)
- systemd queue worker: [deploy/systemd/laravel-queue@.service](/C:/Users/emmanuel/Desktop/mindful-au-main/backend/deploy/systemd/laravel-queue@.service)
- systemd scheduler: [deploy/systemd/laravel-scheduler.service](/C:/Users/emmanuel/Desktop/mindful-au-main/backend/deploy/systemd/laravel-scheduler.service)

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

## 2k-4k User Readiness Workflow

Use this profile when you want to validate a horizontally scalable deployment:

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
PRESENCE_TOUCH_INTERVAL_SECONDS=60
NOTIFICATIONS_CACHE_SECONDS=10
CHAT_LIST_CACHE_SECONDS=8
```

Apply the latest indexes before load testing:

```bash
php artisan migrate --force
```

Seed a large user pool:

```bash
LOAD_TEST_STUDENTS=2000 LOAD_TEST_COUNSELORS=100 php artisan db:seed --class=LoadTestUserSeeder
```

Run the chat/video load scenario with staged ramp-up:

```bash
LOAD_TEST_STUDENTS=2000 LOAD_TEST_COUNSELORS=100 LOAD_TEST_DURATION_SECONDS=120 LOAD_TEST_POLL_INTERVAL_MS=12000 LOAD_TEST_PREP_BATCH_SIZE=50 LOAD_TEST_PREP_BATCH_DELAY_MS=250 LOAD_TEST_CALL_BATCH_SIZE=200 LOAD_TEST_APPOINTMENT_SLOT_MINUTES=15 node backend/scripts/load-test-chat-video.mjs
```

For the upper bound:

```bash
LOAD_TEST_STUDENTS=4000 LOAD_TEST_COUNSELORS=200 LOAD_TEST_DURATION_SECONDS=180 LOAD_TEST_POLL_INTERVAL_MS=12000 LOAD_TEST_PREP_BATCH_SIZE=50 LOAD_TEST_PREP_BATCH_DELAY_MS=250 LOAD_TEST_CALL_BATCH_SIZE=250 LOAD_TEST_APPOINTMENT_SLOT_MINUTES=15 node backend/scripts/load-test-chat-video.mjs
```

Production note: do not treat the system as 2k-4k ready unless Redis-backed cache/session/queue are active and queue workers are running.
