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

const errors = [];
const warnings = [];

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

if (get('CACHE_STORE') !== '' && get('CACHE_STORE') !== 'redis') {
  warnings.push(`CACHE_STORE is "${get('CACHE_STORE')}". Redis is recommended for horizontal scaling.`);
}

if (get('SESSION_DRIVER') !== '' && get('SESSION_DRIVER') !== 'redis') {
  warnings.push(`SESSION_DRIVER is "${get('SESSION_DRIVER')}". Redis is recommended for stateless scaling.`);
}

console.log(`Validated env file: ${envPath}`);

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
