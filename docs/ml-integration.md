# ML Integration Overview

## Scope

The CMS now includes a lightweight, explainable ML layer designed for low-bandwidth environments. The implementation uses feature-based scoring over existing platform data instead of heavy remote inference, so the system can keep working when external AI services are unavailable.

## Capabilities

- Personalized student wellness insights through `StudentWellnessController`
- Privacy-safe context injection for AI wellness chat prompts
- Hybrid diagnostic calibration in `AIDiagnosticService`
- Counselor matching through `GET /api/ml/counselor-matches`
- Admin ML analytics through `AnalyticsController`

## Model Design

Primary service: `App\Services\MentalHealthMlService`

Signals combine:

- Recent diagnostics and AI diagnostics
- Mood logs
- Appointment continuity and cancellations
- Counseling session history
- AI chat topic/distress patterns
- Counselor availability, continuity, and prior completion history

The model is intentionally:

- Explainable: every score is derived from explicit features and thresholds
- Low-bandwidth: no heavy client payloads, short prompt context, local-first scoring
- Fault-tolerant: diagnostics and chat still work when external AI providers are offline

## Privacy And Ethics

- ML prompts use aggregated features only
- Names, emails, and direct identifiers are excluded from prompt context
- High-risk outputs are advisory and still require human review
- Fairness is monitored through the admin `ml_intelligence.validation` payload
- Auditability is preserved through explicit reasons and message metadata

## API Surface

### Student wellness summary

`GET /api/student-wellness/summary`

New field:

- `ml_insights`

### AI wellness chat

`POST /api/ai/wellness-chat`

New field:

- `ml_signals`

Additional behavior:

- Stores per-message ML metadata in `message_metadata`

### Counselor matching

`GET /api/ml/counselor-matches?mode=online&limit=6`

Response:

- ranked counselors
- fit reasons
- availability and reliability metrics

### ML health telemetry (Admin)

`GET /api/ml/health`

Operational monitoring payload includes:

- inference volume in last 24h
- provider mode/name distribution
- fallback rate
- average and p95 latency
- high-risk follow-up indicators

### Admin analytics

`GET /api/analytics/dashboard`

New field:

- `ml_intelligence`

## Release Checklist

1. Run backend tests: `php artisan test`
   - Include ML telemetry checks: `php artisan test --filter=MlHealthEndpointTest`
2. Run frontend build: `npm run build`
3. Review `ml_intelligence.validation` in admin analytics
4. Review `/api/ml/health` for fallback rate and latency budgets
5. Confirm counselor matching and AI chat flows in staging
6. Verify production environment variables for AI providers if external AI is required

## Deployment Note

This implementation is production-ready in code, but production deployment still requires environment-specific release access, configuration review, and final human sign-off.
