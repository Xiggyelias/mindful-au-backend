# Mindful AU 2k-4k Production Runbook

This runbook is the concrete deployment profile for keeping the system responsive at roughly 2,000 to 4,000 active users.

## Scope

- Student-heavy traffic: login, dashboards, encrypted chat, AI wellness chat, appointments
- Counselor traffic: concurrent conversations, session notes, video authorization
- Admin traffic: analytics, logs, panic alerts, user management

## Starting Topology

Use this as a starting point, not a guarantee:

- 2 app nodes, each with 4 vCPU and 8 GB RAM
- 1 MySQL or MariaDB node with 8 vCPU and 16 GB RAM
- 1 Redis node with 2 vCPU and 4 GB RAM
- 1 frontend static host or CDN in front of the Vite build

If you stay on a single VM, use at least 8 vCPU and 16 GB RAM and expect less headroom during spikes.

## Required Runtime Profile

Use the production template in [backend/.env.production.example](/C:/Users/emmanuel/Desktop/mindful-au-main/backend/.env.production.example) as the baseline. The important scale values are:

```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_STORE=redis
REDIS_HOST=redis
REDIS_PORT=6379
SCALE_TARGET_USERS=4000
QUEUE_WORKER_PROCESSES=8
QUEUE_WORKER_SLEEP_SECONDS=1
QUEUE_WORKER_TRIES=3
QUEUE_WORKER_TIMEOUT_SECONDS=120
QUEUE_WORKER_MAX_TIME_SECONDS=3600
QUEUE_WORKER_MEMORY_MB=256
SESSIONS_LIGHTWEIGHT_CACHE_SECONDS=8
NOTIFICATIONS_CACHE_SECONDS=15
CHAT_LIST_CACHE_SECONDS=10
API_RATE_LIMIT_AUTH_PER_MINUTE=600
API_RATE_LIMIT_GUEST_PER_MINUTE=300
AUTH_LOGIN_RATE_LIMIT_PER_MINUTE=120
MESSAGES_READ_RATE_LIMIT_PER_MINUTE=240
MESSAGES_WRITE_RATE_LIMIT_PER_MINUTE=120
```

## Container Path

Use [frontend/docker-compose.yml](/C:/Users/emmanuel/Desktop/mindful-au-main/frontend/docker-compose.yml) for the multi-container path.

1. Copy the env template.
2. Set real production secrets.
3. Build and start the stack.
4. Run migrations once.
5. Verify health and AI readiness.

```bash
cp backend/.env.production.example backend/.env
node backend/scripts/validate-production-env.mjs
docker compose -f frontend/docker-compose.yml up -d --build
docker compose -f frontend/docker-compose.yml exec app php artisan migrate --force
docker compose -f frontend/docker-compose.yml exec app php artisan storage:link
docker compose -f frontend/docker-compose.yml exec app php artisan admin:create
```

Queue worker concurrency is now controlled by `QUEUE_WORKER_PROCESSES` through:

- [backend/docker/supervisor/supervisord.conf](/C:/Users/emmanuel/Desktop/mindful-au-main/backend/docker/supervisor/supervisord.conf)
- [backend/supervisord.conf](/C:/Users/emmanuel/Desktop/mindful-au-main/backend/supervisord.conf)

## VM Path

Use one of these API server templates:

- Nginx: [backend/deploy/nginx/mindful-au-api.conf](/C:/Users/emmanuel/Desktop/mindful-au-main/backend/deploy/nginx/mindful-au-api.conf)
- Apache: [backend/deploy/apache/mindful-au-api-vhost.conf](/C:/Users/emmanuel/Desktop/mindful-au-main/backend/deploy/apache/mindful-au-api-vhost.conf)

Use these worker units:

- Queue worker template: [backend/deploy/systemd/laravel-queue@.service](/C:/Users/emmanuel/Desktop/mindful-au-main/backend/deploy/systemd/laravel-queue@.service)
- Scheduler: [backend/deploy/systemd/laravel-scheduler.service](/C:/Users/emmanuel/Desktop/mindful-au-main/backend/deploy/systemd/laravel-scheduler.service)

Typical VM steps:

```bash
sudo cp backend/deploy/nginx/mindful-au-api.conf /etc/nginx/sites-available/mindful-au-api.conf
sudo ln -s /etc/nginx/sites-available/mindful-au-api.conf /etc/nginx/sites-enabled/mindful-au-api.conf
sudo nginx -t && sudo systemctl reload nginx

sudo cp backend/deploy/systemd/laravel-queue@.service /etc/systemd/system/
sudo cp backend/deploy/systemd/laravel-scheduler.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now laravel-scheduler.service
sudo systemctl enable --now laravel-queue@1.service laravel-queue@2.service laravel-queue@3.service laravel-queue@4.service laravel-queue@5.service laravel-queue@6.service laravel-queue@7.service laravel-queue@8.service
```

## Preflight Before Traffic

Run this after every deploy:

```bash
node backend/scripts/validate-production-env.mjs
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan admin:create
php artisan queue:restart
```

Verify:

- `GET /api/health`
- `GET /api/ready` as an admin
- Redis connectivity
- queue workers are online
- OpenRouter AI status is `ready`

## AI Provider Expectations

Current code supports multiple providers, but only providers with real credentials are live. For production, do not claim failover readiness unless the status endpoint confirms each provider.

Minimum check:

```bash
curl -H "Authorization: Bearer <admin-token>" https://api.example.com/api/ai/wellness-chat/status
```

Expect:

- `external_provider_ready=true`
- `active_provider=openrouter` or another configured provider

## Load-Test Sequence

The staged load script is [backend/scripts/load-test-chat-video.mjs](/C:/Users/emmanuel/Desktop/mindful-au-main/backend/scripts/load-test-chat-video.mjs). It now supports `LOAD_TEST_APPOINTMENT_SLOT_MINUTES` so counselor appointments do not all collide on the same time slot.

Stage the ramp instead of jumping straight to 4k:

```bash
LOAD_TEST_STUDENTS=500 LOAD_TEST_COUNSELORS=75 LOAD_TEST_DURATION_SECONDS=60 LOAD_TEST_POLL_INTERVAL_MS=8000 LOAD_TEST_PREP_BATCH_SIZE=25 LOAD_TEST_PREP_BATCH_DELAY_MS=1500 LOAD_TEST_CALL_BATCH_SIZE=150 LOAD_TEST_APPOINTMENT_SLOT_MINUTES=15 node backend/scripts/load-test-chat-video.mjs
LOAD_TEST_STUDENTS=1000 LOAD_TEST_COUNSELORS=150 LOAD_TEST_DURATION_SECONDS=90 LOAD_TEST_POLL_INTERVAL_MS=10000 LOAD_TEST_PREP_BATCH_SIZE=25 LOAD_TEST_PREP_BATCH_DELAY_MS=1200 LOAD_TEST_CALL_BATCH_SIZE=200 LOAD_TEST_APPOINTMENT_SLOT_MINUTES=15 node backend/scripts/load-test-chat-video.mjs
LOAD_TEST_STUDENTS=2000 LOAD_TEST_COUNSELORS=300 LOAD_TEST_DURATION_SECONDS=120 LOAD_TEST_POLL_INTERVAL_MS=12000 LOAD_TEST_PREP_BATCH_SIZE=30 LOAD_TEST_PREP_BATCH_DELAY_MS=900 LOAD_TEST_CALL_BATCH_SIZE=250 LOAD_TEST_APPOINTMENT_SLOT_MINUTES=15 node backend/scripts/load-test-chat-video.mjs
LOAD_TEST_STUDENTS=4000 LOAD_TEST_COUNSELORS=600 LOAD_TEST_DURATION_SECONDS=180 LOAD_TEST_POLL_INTERVAL_MS=12000 LOAD_TEST_PREP_BATCH_SIZE=40 LOAD_TEST_PREP_BATCH_DELAY_MS=750 LOAD_TEST_CALL_BATCH_SIZE=300 LOAD_TEST_APPOINTMENT_SLOT_MINUTES=15 node backend/scripts/load-test-chat-video.mjs
```

Add the smaller reusable baseline:

```bash
SCALE_TARGET_USERS=4000 node backend/scripts/validate-production-env.mjs
BENCH_RUNS=20 BENCH_CONCURRENCY=20 BENCH_ROUNDS=5 node backend/scripts/benchmark-load-speed.mjs
```

## Acceptance Targets

Treat these as minimum targets before calling the platform 2k-4k ready:

- `POST /login` p95 under 800 ms during staged ramps
- `GET /me` p95 under 300 ms
- `GET /sessions?lightweight=1` p95 under 400 ms
- encrypted message send p95 under 500 ms
- `GET /analytics/dashboard` p95 under 1500 ms with cache hits
- AI wellness median under 6 s and p95 under 12 s
- no repeated `429` on expected traffic patterns
- no queue backlog growth during steady state

## Rollback Triggers

Rollback or freeze traffic increases if any of these occur:

- file cache, file sessions, or sync queue reappear in readiness details
- queue workers restart repeatedly
- login or session-list p95 doubles between ramp stages
- AI requests fall back to local mode unexpectedly
- database CPU is saturated for more than a few minutes
- video authorization or encrypted message sends start returning 5xx
