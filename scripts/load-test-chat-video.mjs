import { performance } from 'node:perf_hooks';
import { randomBytes } from 'node:crypto';

const API_BASE_URL = process.env.API_BASE_URL ?? 'http://127.0.0.1:8000/api';
const LOAD_TEST_PASSWORD = process.env.LOAD_TEST_PASSWORD ?? 'password123';
const STUDENT_COUNT = Number.parseInt(process.env.LOAD_TEST_STUDENTS ?? '20', 10);
const COUNSELOR_COUNT = Number.parseInt(process.env.LOAD_TEST_COUNSELORS ?? '5', 10);
const TEST_DURATION_SECONDS = Number.parseInt(process.env.LOAD_TEST_DURATION_SECONDS ?? '45', 10);
const POLL_INTERVAL_MS = Number.parseInt(process.env.LOAD_TEST_POLL_INTERVAL_MS ?? '3000', 10);
const PREP_BATCH_SIZE = Math.max(
  1,
  Number.parseInt(process.env.LOAD_TEST_PREP_BATCH_SIZE ?? process.env.LOAD_TEST_RAMP_BATCH_SIZE ?? '50', 10)
);
const PREP_BATCH_DELAY_MS = Math.max(
  0,
  Number.parseInt(process.env.LOAD_TEST_PREP_BATCH_DELAY_MS ?? process.env.LOAD_TEST_RAMP_DELAY_MS ?? '250', 10)
);
const CALL_BATCH_SIZE = Math.max(
  1,
  Number.parseInt(process.env.LOAD_TEST_CALL_BATCH_SIZE ?? '200', 10)
);
const CHAT_POLL_LIMIT = Math.min(
  30,
  Math.max(1, Number.parseInt(process.env.LOAD_TEST_CHAT_POLL_LIMIT ?? '30', 10))
);
const CALL_AUTH_BURST_PER_PAIR = Number.parseInt(
  process.env.LOAD_TEST_CALL_BURST_PER_PAIR ?? '2',
  10
);

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const chunk = (items, size) => {
  const batches = [];
  for (let index = 0; index < items.length; index += size) {
    batches.push(items.slice(index, index + size));
  }
  return batches;
};

const mapInBatches = async (items, batchSize, mapper, delayMs = 0) => {
  const results = [];
  let globalIndex = 0;

  for (const batch of chunk(items, batchSize)) {
    const batchResults = await Promise.all(
      batch.map((item) => {
        const currentIndex = globalIndex;
        globalIndex += 1;
        return mapper(item, currentIndex);
      })
    );
    results.push(...batchResults);

    if (delayMs > 0) {
      await sleep(delayMs);
    }
  }

  return results;
};

const metrics = new Map();

const getMetric = (name) => {
  if (!metrics.has(name)) {
    metrics.set(name, {
      name,
      count: 0,
      success: 0,
      latencies: [],
      statusCounts: new Map(),
    });
  }
  return metrics.get(name);
};

const recordMetric = (name, status, latencyMs) => {
  const metric = getMetric(name);
  metric.count += 1;
  if (status >= 200 && status < 300) {
    metric.success += 1;
  }
  metric.latencies.push(latencyMs);
  metric.statusCounts.set(status, (metric.statusCounts.get(status) ?? 0) + 1);
};

const percentile = (values, p) => {
  if (!values.length) return 0;
  const sorted = [...values].sort((a, b) => a - b);
  const idx = Math.min(sorted.length - 1, Math.max(0, Math.ceil((p / 100) * sorted.length) - 1));
  return sorted[idx];
};

const makeEncryptedPayload = () => {
  const payload = randomBytes(48).toString('base64');
  return payload.length >= 40 ? payload : payload.padEnd(40, 'A');
};

const timedRequest = async (metricName, { method = 'GET', path, token, body }) => {
  const headers = { Accept: 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  if (body !== undefined) headers['Content-Type'] = 'application/json';

  const start = performance.now();
  let status = 0;

  try {
    const response = await fetch(`${API_BASE_URL}${path}`, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    status = response.status;

    let data = null;
    const text = await response.text();
    if (text) {
      try {
        data = JSON.parse(text);
      } catch {
        data = text;
      }
    }

    const latencyMs = performance.now() - start;
    recordMetric(metricName, status, latencyMs);
    return { ok: response.ok, status, data, latencyMs };
  } catch (error) {
    const latencyMs = performance.now() - start;
    recordMetric(metricName, 0, latencyMs);
    return { ok: false, status: 0, data: String(error), latencyMs };
  }
};

const login = async (email) => {
  const result = await timedRequest('auth_login', {
    method: 'POST',
    path: '/login',
    body: { email, password: LOAD_TEST_PASSWORD },
  });

  if (!result.ok || !result.data?.access_token) {
    const reason =
      result.data && typeof result.data === 'object'
        ? result.data.message ?? JSON.stringify(result.data)
        : String(result.data ?? 'unknown error');

    const hint =
      result.status === 403 && email.startsWith('load.student')
        ? ' | Hint: run against a backend with AUTH_REQUIRE_GOOGLE_FOR_STUDENTS=false for password-based load users.'
        : '';

    throw new Error(`Login failed for ${email} (status ${result.status}): ${reason}${hint}`);
  }

  return result.data.access_token;
};

const getUserId = async (token) => {
  const me = await timedRequest('auth_me', {
    path: '/me',
    token,
  });

  if (!me.ok || !me.data?.id) {
    throw new Error(`Failed to fetch /me (status ${me.status})`);
  }

  return Number(me.data.id);
};

const ensureSession = async (studentToken, counselorId) => {
  const sessionsResult = await timedRequest('sessions_list', {
    path: '/sessions',
    token: studentToken,
  });

  if (!sessionsResult.ok || !Array.isArray(sessionsResult.data)) {
    throw new Error(`Failed to list sessions (status ${sessionsResult.status})`);
  }

  const existing = sessionsResult.data.find(
    (session) => Number(session.counselor_id) === Number(counselorId) && session.status !== 'completed'
  );
  if (existing) return Number(existing.id);

  const createResult = await timedRequest('sessions_create', {
    method: 'POST',
    path: '/sessions',
    token: studentToken,
    body: { counselor_id: counselorId, session_type: 'chat' },
  });

  if (!createResult.ok || !createResult.data?.id) {
    throw new Error(`Failed to create session (status ${createResult.status})`);
  }

  return Number(createResult.data.id);
};

const createAppointment = async (studentToken, counselorId) => {
  const scheduledAt = new Date(Date.now() + 5 * 60 * 1000).toISOString();
  const result = await timedRequest('appointments_create', {
    method: 'POST',
    path: '/appointments',
    token: studentToken,
    body: {
      counselor_id: counselorId,
      scheduled_at: scheduledAt,
      duration_minutes: 60,
      notes: 'video load test',
    },
  });

  if (!result.ok || !result.data?.id) {
    throw new Error(`Failed to create appointment (status ${result.status})`);
  }

  return Number(result.data.id);
};

const pollMessagesUntilDeadline = async ({ token, sessionId, deadlineMs }) => {
  while (Date.now() < deadlineMs) {
    await timedRequest('chat_poll', {
      path: `/sessions/${sessionId}/messages?limit=${CHAT_POLL_LIMIT}`,
      token,
    });
    await sleep(POLL_INTERVAL_MS);
  }
};

const run = async () => {
  const students = Array.from({ length: STUDENT_COUNT }, (_, i) =>
    `load.student${String(i + 1).padStart(3, '0')}@example.com`
  );
  const counselors = Array.from({ length: COUNSELOR_COUNT }, (_, i) =>
    `load.counselor${String(i + 1).padStart(2, '0')}@example.com`
  );

  console.log(`API: ${API_BASE_URL}`);
  console.log(
    `Scenario: ${STUDENT_COUNT} students, ${COUNSELOR_COUNT} counselors, ${TEST_DURATION_SECONDS}s duration, ${POLL_INTERVAL_MS}ms polling`
  );
  console.log(
    `Preparation: batch=${PREP_BATCH_SIZE}, delay=${PREP_BATCH_DELAY_MS}ms | Call bursts: batch=${CALL_BATCH_SIZE}`
  );

  const counselorLoginResults = await mapInBatches(
    counselors,
    PREP_BATCH_SIZE,
    async (email) => [email, await login(email)],
    PREP_BATCH_DELAY_MS
  );
  const counselorTokens = new Map(counselorLoginResults);

  const counselorIdentityResults = await mapInBatches(
    counselors,
    PREP_BATCH_SIZE,
    async (email) => [email, await getUserId(counselorTokens.get(email))],
    PREP_BATCH_DELAY_MS
  );
  const counselorIds = new Map(counselorIdentityResults);

  const studentLoginResults = await mapInBatches(
    students,
    PREP_BATCH_SIZE,
    async (email) => [email, await login(email)],
    PREP_BATCH_DELAY_MS
  );
  const studentTokens = new Map(studentLoginResults);

  const pairs = await mapInBatches(
    students,
    PREP_BATCH_SIZE,
    async (studentEmail, i) => {
      const counselorEmail = counselors[i % counselors.length];
      const studentToken = studentTokens.get(studentEmail);
      const counselorToken = counselorTokens.get(counselorEmail);
      const counselorId = counselorIds.get(counselorEmail);

      const sessionId = await ensureSession(studentToken, counselorId);
      const appointmentId = await createAppointment(studentToken, counselorId);

      await timedRequest('messages_send', {
        method: 'POST',
        path: `/sessions/${sessionId}/messages`,
        token: studentToken,
        body: {
          content: makeEncryptedPayload(),
          is_encrypted: true,
          message_type: 'text',
        },
      });

      return {
        studentEmail,
        counselorEmail,
        studentToken,
        counselorToken,
        sessionId,
        appointmentId,
      };
    },
    PREP_BATCH_DELAY_MS
  );

  console.log(`Prepared ${pairs.length} student/counselor pairs.`);

  const deadlineMs = Date.now() + TEST_DURATION_SECONDS * 1000;

  const pollTasks = pairs.map((pair) =>
    pollMessagesUntilDeadline({
      token: pair.studentToken,
      sessionId: pair.sessionId,
      deadlineMs,
    })
  );

  const callTasks = [];
  for (const pair of pairs) {
    for (let i = 0; i < CALL_AUTH_BURST_PER_PAIR; i++) {
      callTasks.push(
        timedRequest('call_authorize_student', {
          method: 'POST',
          path: '/video-calls/authorize',
          token: pair.studentToken,
          body: { appointment_id: pair.appointmentId },
        })
      );
      callTasks.push(
        timedRequest('call_authorize_counselor', {
          method: 'POST',
          path: '/video-calls/authorize',
          token: pair.counselorToken,
          body: { appointment_id: pair.appointmentId },
        })
      );
    }
  }

  for (const batch of chunk(callTasks, CALL_BATCH_SIZE)) {
    await Promise.all(batch);
  }
  await Promise.all(pollTasks);

  console.log('\nResults:');
  for (const metric of metrics.values()) {
    const p50 = percentile(metric.latencies, 50);
    const p95 = percentile(metric.latencies, 95);
    const max = metric.latencies.length ? Math.max(...metric.latencies) : 0;
    const successRate = metric.count ? ((metric.success / metric.count) * 100).toFixed(2) : '0.00';
    const statusSummary = [...metric.statusCounts.entries()]
      .sort((a, b) => a[0] - b[0])
      .map(([status, count]) => `${status}:${count}`)
      .join(', ');

    console.log(
      `- ${metric.name}: count=${metric.count}, success=${successRate}%, p50=${p50.toFixed(
        1
      )}ms, p95=${p95.toFixed(1)}ms, max=${max.toFixed(1)}ms, statuses=[${statusSummary}]`
    );
  }

  const pollRpmPerUser = Math.ceil(60000 / POLL_INTERVAL_MS);
  const recommendedReadLimit = Math.max(120, pollRpmPerUser * 4);
  const recommendedWriteLimit = 60;
  const recommendedCallLimit = 20;
  const estimatedPollRps = Number((pairs.length / Math.max(1, POLL_INTERVAL_MS / 1000)).toFixed(2));

  console.log('\nRecommended per-user throttles (based on this scenario):');
  console.log(`- messages-read: ${recommendedReadLimit}/min`);
  console.log(`- messages-write: ${recommendedWriteLimit}/min`);
  console.log(`- video-calls authorize/end: ${recommendedCallLimit}/min`);
  console.log(`\nEstimated steady-state chat poll volume: ~${estimatedPollRps} requests/sec`);
};

run().catch((error) => {
  console.error('\nLoad test failed:', error);
  process.exitCode = 1;
});
