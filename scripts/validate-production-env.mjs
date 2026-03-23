import fs from 'node:fs';
import path from 'node:path';

const defaultEnvPath = path.resolve(process.cwd(), 'backend/.env');
const envPath = process.env.ENV_FILE ? path.resolve(process.env.ENV_FILE) : defaultEnvPath;

const parseEnvFile = (raw) => {
  const result = new Map();

  for (const line of raw.split(/\r?\n/)) {
    const trimmed = line.trim();
    if (trimmed === '' || trimmed.startsWith('#')) {
      continue;
    }

    const delimiter = trimmed.indexOf('=');
    if (delimiter <= 0) {
      continue;
    }

    const key = trimmed.slice(0, delimiter).trim();
    let value = trimmed.slice(delimiter + 1).trim();

    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }

    result.set(key, value);
  }

  return result;
};

if (!fs.existsSync(envPath)) {
  console.error(`Environment file not found: ${envPath}`);
  process.exit(1);
}

const env = parseEnvFile(fs.readFileSync(envPath, 'utf8'));
const get = (key) => String(env.get(key) ?? '').trim();
const getInt = (key, fallback) => {
  const raw = get(key);
  if (raw === '') {
    return fallback;
  }

  const parsed = Number.parseInt(raw, 10);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const errors = [];
const warnings = [];
const scaleTargetUsers = Math.max(0, getInt('SCALE_TARGET_USERS', 0));
const validateHighScaleProfile = scaleTargetUsers >= 2000;

const requireNonEmpty = (key) => {
  if (get(key) === '') {
    errors.push(`Missing required value: ${key}`);
  }
};

for (const key of [
  'APP_KEY',
  'APP_URL',
  'FRONTEND_URL',
  'DB_CONNECTION',
  'DB_DATABASE',
  'DB_USERNAME',
  'DB_PASSWORD',
  'CORS_ALLOWED_ORIGINS',
  'GOOGLE_CLIENT_ID',
  'GOOGLE_CLIENT_SECRET',
  'GOOGLE_REDIRECT_URL',
]) {
  requireNonEmpty(key);
}

const appKey = get('APP_KEY');
if (appKey !== '' && appKey.includes('REPLACE_WITH_GENERATED_KEY')) {
  errors.push('APP_KEY is still a placeholder. Generate a real key with php artisan key:generate.');
}

const appDebug = get('APP_DEBUG').toLowerCase();
if (appDebug !== 'false' && appDebug !== '0') {
  errors.push('APP_DEBUG must be false in production.');
}

const appEnv = get('APP_ENV').toLowerCase();
if (appEnv !== 'production') {
  warnings.push(`APP_ENV is "${get('APP_ENV') || '(empty)'}". Expected "production".`);
}

const corsOrigins = get('CORS_ALLOWED_ORIGINS');
if (corsOrigins.includes('*')) {
  errors.push('CORS_ALLOWED_ORIGINS must not use wildcard (*) in production.');
}

for (const key of ['APP_URL', 'FRONTEND_URL']) {
  const value = get(key);
  if (value !== '' && !/^https:\/\//i.test(value)) {
    warnings.push(`${key} does not use HTTPS: ${value}`);
  }
}

if (get('SECURITY_FORCE_HSTS').toLowerCase() !== 'true') {
  warnings.push('SECURITY_FORCE_HSTS is not true.');
}

if (get('CACHE_STORE') === 'array') {
  warnings.push('CACHE_STORE is set to array (non-persistent). Use file/redis/database in production.');
}

if (get('QUEUE_CONNECTION') === 'sync') {
  warnings.push('QUEUE_CONNECTION is sync. Use a worker-backed queue in production.');
}

if (get('FORCE_HTTPS').toLowerCase() !== 'true') {
  warnings.push('FORCE_HTTPS is not true.');
}

if (get('SESSION_SECURE_COOKIE').toLowerCase() !== 'true') {
  warnings.push('SESSION_SECURE_COOKIE should be true in production.');
}

if (get('API_EXPOSE_ERROR_DETAILS').toLowerCase() === 'true') {
  warnings.push('API_EXPOSE_ERROR_DETAILS is true. Keep it false outside local debugging.');
}

if (get('HEALTH_EXPOSE_DETAILS').toLowerCase() === 'true') {
  warnings.push('HEALTH_EXPOSE_DETAILS is true. This exposes detailed readiness diagnostics to unauthenticated requests unless you restrict access elsewhere.');
}

if (get('CACHE_STORE') !== '' && get('CACHE_STORE') !== 'redis') {
  warnings.push(`CACHE_STORE is "${get('CACHE_STORE')}". Redis is recommended for horizontal scaling.`);
}

if (get('SESSION_DRIVER') !== '' && get('SESSION_DRIVER') !== 'redis') {
  warnings.push(`SESSION_DRIVER is "${get('SESSION_DRIVER')}". Redis is recommended for stateless scaling.`);
}

if (validateHighScaleProfile) {
  const cacheStore = get('CACHE_STORE') || 'file';
  const queueConnection = get('QUEUE_CONNECTION') || get('QUEUE_DRIVER') || 'sync';
  const sessionDriver = get('SESSION_DRIVER') || 'file';
  const redisHost = get('REDIS_HOST');
  const presenceTouchIntervalSeconds = getInt('PRESENCE_TOUCH_INTERVAL_SECONDS', 60);
  const notificationsCacheSeconds = getInt('NOTIFICATIONS_CACHE_SECONDS', 10);
  const chatListCacheSeconds = getInt('CHAT_LIST_CACHE_SECONDS', 8);

  if (cacheStore !== 'redis') {
    errors.push(
      `SCALE_TARGET_USERS=${scaleTargetUsers} requires CACHE_STORE=redis. Current value: "${cacheStore}".`
    );
  }

  if (queueConnection !== 'redis') {
    errors.push(
      `SCALE_TARGET_USERS=${scaleTargetUsers} requires QUEUE_CONNECTION=redis. Current value: "${queueConnection}".`
    );
  }

  if (sessionDriver !== 'redis') {
    errors.push(
      `SCALE_TARGET_USERS=${scaleTargetUsers} requires SESSION_DRIVER=redis. Current value: "${sessionDriver}".`
    );
  }

  if (redisHost === '') {
    errors.push(`SCALE_TARGET_USERS=${scaleTargetUsers} requires REDIS_HOST to be configured.`);
  }

  if (presenceTouchIntervalSeconds < 45) {
    warnings.push(
      `PRESENCE_TOUCH_INTERVAL_SECONDS is ${presenceTouchIntervalSeconds}. Use 45-60 seconds to reduce write amplification at scale.`
    );
  }

  if (notificationsCacheSeconds < 5) {
    warnings.push(
      `NOTIFICATIONS_CACHE_SECONDS is ${notificationsCacheSeconds}. Use at least 5 seconds to reduce repeated notification queries at scale.`
    );
  }

  if (chatListCacheSeconds < 5) {
    warnings.push(
      `CHAT_LIST_CACHE_SECONDS is ${chatListCacheSeconds}. Use at least 5 seconds to reduce repeated chat list queries at scale.`
    );
  }
}

console.log(`Validated env file: ${envPath}`);
if (scaleTargetUsers > 0) {
  console.log(`Scale target users: ${scaleTargetUsers}`);
}

if (warnings.length > 0) {
  console.log('\nWarnings:');
  for (const warning of warnings) {
    console.log(`- ${warning}`);
  }
}

if (errors.length > 0) {
  console.error('\nErrors:');
  for (const error of errors) {
    console.error(`- ${error}`);
  }
  process.exit(1);
}

console.log('\nEnvironment validation passed.');
