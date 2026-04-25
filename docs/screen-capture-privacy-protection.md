# Screen Capture Privacy Protection

This CMS implements maximum practical privacy controls across platforms.
Absolute screenshot prevention is not guaranteed on every device/OS, so the design uses layered prevention, detection, and deterrence.

## Web (Implemented)

- Sensitive routes enable runtime protections:
  - `student chat/video`
  - `counselor messages/video`
  - `peer chats`
- Active controls:
  - Blocks context menu, copy, cut, and drag-start.
  - Detects common capture shortcuts (`PrintScreen`, `Ctrl+Shift+S`, macOS screenshot keys).
  - Shows a full-screen warning overlay when a capture-like action is detected.
  - Auto-obscures sensitive content when app loses focus or tab becomes inactive.
  - Persistent watermark overlay with user alias/ID + timestamp to discourage sharing.
  - Privacy-policy warning banner on protected views.

## Android (Implementation Guidance)

For native Android containers (WebView/Capacitor/React Native host), set secure window flags:

- `WindowManager.LayoutParams.FLAG_SECURE`

Expected effect:

- Blocks screenshots in most Android environments.
- Blocks screen recording in many standard paths.

Apply on all sensitive activity windows and fallback to blur/obscure overlays during lifecycle transitions.

## iOS (Implementation Guidance)

iOS does not provide full screenshot blocking for standard apps. Use detection + mitigation:

- Observe `UIScreen.capturedDidChangeNotification` for screen recording.
- Observe `UIApplication.userDidTakeScreenshotNotification` for screenshot events.
- On detection:
  - blur/hide sensitive content immediately,
  - log security event,
  - show warning notice.

## Detection and Response Policy

- Capture attempts (where detectable) trigger immediate content obfuscation and user warning.
- Anonymous mode remains the default identity-protection path.
- Identity reveal stays restricted to authorized emergency/approved flows with audit logs.

## Security Limitations

- Browser and OS-level controls can be bypassed by external devices/cameras.
- Therefore, this system treats screenshot protection as **defense in depth**:
  - prevention where available,
  - detection where possible,
  - deterrence + watermarking everywhere.

## Validation Checklist

- Web:
  - Sensitive screens show watermark and warning policy.
  - Copy/context/drag actions are blocked on protected routes.
  - Focus loss hides content.
  - Capture shortcut attempts trigger warning overlays.
- Android:
  - `FLAG_SECURE` verified in QA builds.
- iOS:
  - screenshot/recording notifications trigger blur + warning.

