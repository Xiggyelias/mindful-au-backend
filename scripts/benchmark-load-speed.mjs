import { performance } from 'node:perf_hooks';

const API_BASE_URL = process.env.API_BASE_URL ?? 'http://127.0.0.1:8000/api';
const FRONTEND_URL = process.env.FRONTEND_URL ?? 'http://127.0.0.1:5173/';
const BENCH_EMAIL = process.env.BENCH_EMAIL ?? 'load.counselor01@example.com';
const BENCH_PASSWORD = process.env.BENCH_PASSWORD ?? 'password123';
const BENCH_RUNS = Math.max(5, Number.parseInt(process.env.BENCH_RUNS ?? '30', 10));
const BENCH_CONCURRENCY = Math.max(1, Number.parseInt(process.env.BENCH_CONCURRENCY ?? '20', 10));
const BENCH_ROUNDS = Math.max(1, Number.parseInt(process.env.BENCH_ROUNDS ?? '5', 10));

const percentile = (values, p) => {
  if (!values.length) return 0;
  const sorted = [...values].sort((a, b) => a - b);
  const index = Math.min(
    sorted.length - 1,
    Math.max(0, Math.ceil((p / 100) * sorted.length) - 1)
  );
  return sorted[index];
};

const summarize = (name, values, statuses, extra = {}) => {
  const average = values.reduce((sum, value) => sum + value, 0) / values.length;
  return {
    name,
    runs: values.length,
    avg_ms: Number(average.toFixed(1)),
    p50_ms: Number(percentile(values, 50).toFixed(1)),
    p95_ms: Number(percentile(values, 95).toFixed(1)),
    min_ms: Number(Math.min(...values).toFixed(1)),
    max_ms: Number(Math.max(...values).toFixed(1)),
    statuses: [...statuses.entries()]
      .sort((a, b) => a[0] - b[0])
      .map(([status, count]) => `${status}:${count}`)
      .join(', '),
    ...extra,
  };
};

const timedRequest = async (url, options = {}) => {
  const startedAt = performance.now();
  const response = await fetch(url, options);
  const elapsedMs = performance.now() - startedAt;
  return { response, elapsedMs };
};

const benchFrontend = async () => {
  const latencies = [];
  const statuses = new Map();

  for (let i = 0; i < BENCH_RUNS; i += 1) {
    const { response, elapsedMs } = await timedRequest(FRONTEND_URL, {
      headers: { 'Cache-Control': 'no-cache' },
    });
    latencies.push(elapsedMs);
    statuses.set(response.status, (statuses.get(response.status) || 0) + 1);
    await response.text();
  }

  return summarize('Frontend GET /', latencies, statuses, { url: FRONTEND_URL });
};

const login = async () => {
  const { response, elapsedMs } = await timedRequest(`${API_BASE_URL}/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({
      email: BENCH_EMAIL,
      password: BENCH_PASSWORD,
    }),
  });

  const data = await response.json();
  if (!response.ok || !data?.access_token) {
    throw new Error(`Login failed (${response.status}): ${JSON.stringify(data)}`);
  }

  return { token: data.access_token, loginMs: elapsedMs, status: response.status };
};

const benchApiSteadyState = async (token) => {
  const headers = {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
  };

  const meLatencies = [];
  const meStatuses = new Map();
  const sessionsLatencies = [];
  const sessionsStatuses = new Map();
  let cacheHits = 0;
  let cacheMisses = 0;

  for (let i = 0; i < BENCH_RUNS; i += 1) {
    const meResult = await timedRequest(`${API_BASE_URL}/me`, { headers });
    meLatencies.push(meResult.elapsedMs);
    meStatuses.set(
      meResult.response.status,
      (meStatuses.get(meResult.response.status) || 0) + 1
    );
    await meResult.response.text();

    const sessionsResult = await timedRequest(
      `${API_BASE_URL}/sessions?lightweight=1&page=1&per_page=20`,
      { headers }
    );
    sessionsLatencies.push(sessionsResult.elapsedMs);
    sessionsStatuses.set(
      sessionsResult.response.status,
      (sessionsStatuses.get(sessionsResult.response.status) || 0) + 1
    );
    const cacheHeader = String(
      sessionsResult.response.headers.get('x-sessions-cache') || ''
    ).toLowerCase();
    if (cacheHeader === 'hit') cacheHits += 1;
    if (cacheHeader === 'miss') cacheMisses += 1;
    await sessionsResult.response.text();
  }

  return [
    summarize('API GET /me (single token)', meLatencies, meStatuses),
    summarize(
      'API GET /sessions?lightweight=1 (single token)',
      sessionsLatencies,
      sessionsStatuses,
      {
        cache_hits: cacheHits,
        cache_misses: cacheMisses,
      }
    ),
  ];
};

const benchApiConcurrentSessions = async (token) => {
  const headers = {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
  };

  const latencies = [];
  const statuses = new Map();
  let cacheHits = 0;
  let cacheMisses = 0;

  const hitOnce = async () => {
    const { response, elapsedMs } = await timedRequest(
      `${API_BASE_URL}/sessions?lightweight=1&page=1&per_page=20`,
      { headers }
    );
    latencies.push(elapsedMs);
    statuses.set(response.status, (statuses.get(response.status) || 0) + 1);
    const cacheHeader = String(response.headers.get('x-sessions-cache') || '').toLowerCase();
    if (cacheHeader === 'hit') cacheHits += 1;
    if (cacheHeader === 'miss') cacheMisses += 1;
    await response.text();
  };

  const startedAt = performance.now();
  for (let i = 0; i < BENCH_ROUNDS; i += 1) {
    await Promise.all(Array.from({ length: BENCH_CONCURRENCY }, () => hitOnce()));
  }
  const totalElapsedMs = performance.now() - startedAt;
  const totalRequests = BENCH_CONCURRENCY * BENCH_ROUNDS;
  const throughputRps = totalRequests / (totalElapsedMs / 1000);

  return summarize(
    `API concurrent GET /sessions?lightweight=1 (${BENCH_CONCURRENCY}x${BENCH_ROUNDS})`,
    latencies,
    statuses,
    {
      total_requests: totalRequests,
      total_time_ms: Number(totalElapsedMs.toFixed(1)),
      throughput_rps: Number(throughputRps.toFixed(2)),
      cache_hits: cacheHits,
      cache_misses: cacheMisses,
    }
  );
};

const run = async () => {
  const frontendSummary = await benchFrontend();
  const { token, loginMs, status } = await login();
  const apiSteadyStateSummaries = await benchApiSteadyState(token);
  const concurrentSummary = await benchApiConcurrentSessions(token);

  const result = {
    api_base_url: API_BASE_URL,
    frontend_url: FRONTEND_URL,
    bench_runs: BENCH_RUNS,
    bench_concurrency: BENCH_CONCURRENCY,
    bench_rounds: BENCH_ROUNDS,
    login_probe: {
      endpoint: 'POST /login',
      status,
      ms: Number(loginMs.toFixed(1)),
    },
    summaries: [frontendSummary, ...apiSteadyStateSummaries, concurrentSummary],
  };

  console.log(JSON.stringify(result, null, 2));
};

run().catch((error) => {
  console.error('Benchmark failed:', error);
  process.exitCode = 1;
});
