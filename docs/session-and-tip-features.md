# Session And Tip Features

## Summary

This release adds two user-facing platform capabilities:

1. Multi-device authentication with per-device session tracking
2. Daily Tip of the Day delivery with admin CRUD management

The implementation is designed to preserve existing CMS flows while improving security, engagement, and low-bandwidth behavior.

## Multi-device authentication

### Backend behavior

- Sanctum personal access tokens now store:
  - `device_id`
  - `device_name`
  - `ip_address`
  - `user_agent`
  - `last_activity_at`
- Login no longer revokes every existing token.
- A relogin from the same device replaces that device's previous token.
- Different devices can remain signed in at the same time.
- Refresh now rotates only the current device token.
- `logout` still removes only the current token.
- `logout other devices` removes every token except the current one.
- Device binding is enforced through request headers to reduce token reuse risk.

### Auth session endpoints

- `GET /api/auth/sessions`
- `DELETE /api/auth/sessions/{sessionId}`
- `POST /api/auth/sessions/logout-others`
- `POST /api/refresh`

### Frontend behavior

- The client generates and persists a stable browser device ID.
- Every authenticated request sends:
  - `X-Device-ID`
  - `X-Device-Name`
- Token expiry is stored locally and refreshed proactively before expiry.
- Active session management is available from the shared dashboard header.

## Tip of the Day

### Backend behavior

- Tips are stored in the `tips` table.
- Rotation is deterministic by day and audience.
- Active tips cycle before repeating.
- Students can receive mood-aware personalization when matching mood-tagged tips exist.
- Admins can create, edit, deactivate, and delete tips.

### Tip endpoints

- `GET /api/tips/today`
- `GET /api/tips`
- `POST /api/tips`
- `PUT /api/tips/{tip}`
- `DELETE /api/tips/{tip}`

### Seed data

- `DatabaseSeeder` now includes `TipSeeder`.
- The seed set provides starter tips for:
  - students
  - counselors
  - peer counselors
  - admins
  - general audience use

## Validation completed

- Backend feature coverage:
  - `MultiDeviceSessionTest`
  - `TipOfDayFeatureTest`
- Full backend suite:
  - `php artisan test`
- Frontend production build:
  - `npm run build`

## Deployment note

Production deployment was not executed from this environment. Final release should go through the organization's normal deployment pipeline or a credentialed production remote once available.
