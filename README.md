# Africa University Counseling System Backend

Laravel REST API for student, staff, peer counselor, and admin workflows, including secure chat attachments and appointment-gated call authorization.

Full cross-repo manual: `../CMS_MANUAL.md`

## Requirements

- PHP 8.1+
- Composer
- Database: SQLite (default), MySQL/MariaDB, or PostgreSQL

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

If you use the default local SQLite settings from `.env.example`, create `database/database.sqlite` before running migrations.

API default: `http://127.0.0.1:8000`

## Environment

Set in `.env`:

```env
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://127.0.0.1:5173

GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URL=http://127.0.0.1:8000/api/auth/google/callback
INSTITUTION_EMAIL_DOMAINS=africau.edu

AUTH_REQUIRE_GOOGLE_FOR_STUDENTS=true
AUTH_AUTO_PROVISION_STUDENTS=true
CHAT_UPLOAD_MAX_FILE_SIZE_KB=5120
CHAT_UPLOAD_DIRECTORY=uploads/chat_files
CHAT_UPLOAD_SIGNED_URL_MINUTES=1440
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
- Chat: encrypted messages, seen receipts, attachment upload/download, peer assignment flow
- Peer Support: assign peer counselor, escalate assigned cases, availability
- Appointments and video-call authorization windows
- Notifications and admin management APIs
- Daily wellness tip rotation, favorites, and in-app delivery tracking

## Wellness Tips

- Daily tip endpoint: `GET /api/wellness/tip`
- Saved tips: `GET /api/wellness/tips/favorites`
- Save a tip: `POST /api/wellness/tips/{tip}/favorite`
- Remove a saved tip: `DELETE /api/wellness/tips/{tip}/favorite`
- Admin aliases:
  - `POST /api/admin/add-tip`
  - `PUT /api/admin/update-tip/{tip}`
  - `DELETE /api/admin/delete-tip/{tip}`

Behavior:

- tip content is served from the existing `tips` library
- one tip is cached per user per day in `tip_deliveries`
- the first daily delivery also creates a notification entry
- favorites are stored in `tip_favorites`
- admin tip validation restricts content to short, safe, non-harmful guidance

If you are upgrading an existing environment, apply the schema and refresh the wellness library:

```bash
php artisan migrate
php artisan db:seed --class=TipSeeder
```

## Chat Attachments

- Upload endpoint: `POST /api/chat/upload-file`
- Session-scoped upload endpoint: `POST /api/sessions/{id}/attachments`
- Attachment metadata in message lists: `GET /api/chat/messages?session_id={id}`
- Download handoff: `GET /api/messages/{id}/attachment`
- Signed attachment content route: `GET /api/chat/files/{chatFile}/content`

Attachment rules:

- Default size limit: `5 MB` via `CHAT_UPLOAD_MAX_FILE_SIZE_KB`
- Storage path: `storage/app/uploads/chat_files/...`
- Filenames are normalized and stored with UUID-based names
- Allowed document/image types: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `docx`, `txt`
- Allowed voice/audio types: `mp3`, `wav`, `webm`, `ogg`, `m4a`, `aac`
- Executable uploads are rejected by extension and MIME validation
- Peer-delegated sessions remain text-only by design

Each uploaded file creates a `messages` row with `has_file = true` and a matching `chat_files` record linked by `message_id`.

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

Canonical schema snapshot: `database/schema.sql`

## Tests

```bash
php artisan test
```

Call/session-specific checks:

```bash
php artisan test tests/Feature/VideoCallCompletionTest.php
php artisan test tests/Feature/ChatAttachmentUploadTest.php
php artisan test tests/Feature/TipOfDayFeatureTest.php
```

## Production Bootstrap

For production, run under PHP-FPM and Nginx (or equivalent), then warm caches:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

## Docker / Dokploy Deployment

Deployment files are included for Dokploy-compatible container rollout:

- `Dockerfile` (multi-stage Laravel PHP-FPM image)
- `docker-compose.yml` (app, queue worker, scheduler, Redis, MySQL)
- `docker/php/*` (PHP-FPM tuning)
- `docker/supervisor/supervisord.conf` (queue workers)

If you also deploy the web client, use the sibling frontend repository for its `Dockerfile` and `deploy/nginx/default.conf`.

1. Copy env template:

```bash
cp .env.example .env
```

2. Generate app key:

```bash
php artisan key:generate --ansi
```

3. Start stack:

```bash
docker compose up -d --build
```

4. Run migrations once:

```bash
docker compose exec app php artisan migrate --force
```

Health probes:

- `GET /health` readiness (DB + cache + queue + disk)
- `GET /live` liveness

Backup key rotation note:

- Encrypted backups use Laravel `Crypt` with the active `APP_KEY`.
- Each backup now stores an encryption key fingerprint in backup metadata.
- Before rotating `APP_KEY`, decrypt/re-encrypt legacy backups or keep the prior key available for restore/verification.
- Run `php artisan system:backup:verify --notify` after key changes to confirm restore safety.

Validate production env before deploy:

```bash
node scripts/validate-production-env.mjs
```

## Call Reliability Note

The backend only authorizes and finalizes calls. Reliable browser-to-browser audio/video, especially on campus, mobile, carrier NAT, or office networks, still depends on TURN-capable ICE server configuration in the frontend environment.

## Load Speed Benchmark

Run the reusable benchmark script from this repository:

```bash
node scripts/benchmark-load-speed.mjs
```

Optional knobs:

```bash
# examples
BENCH_RUNS=20 BENCH_CONCURRENCY=15 BENCH_ROUNDS=3 node scripts/benchmark-load-speed.mjs
API_BASE_URL=http://127.0.0.1:8000/api FRONTEND_URL=http://127.0.0.1:5173/ node scripts/benchmark-load-speed.mjs
```
