<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use App\Support\SystemSettings;

class SystemSettingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $settings = SystemSettings::all();

        return response()->json($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate([
            'settings' => 'required|array|min:1',
            'settings.panic_alerts' => 'sometimes|boolean',
            'settings.ai_risk_alerts' => 'sometimes|boolean',
            'settings.daily_reports' => 'sometimes|boolean',
            'settings.new_registrations' => 'sometimes|boolean',
            'settings.two_factor_auth' => 'sometimes|boolean',
            'settings.session_timeout' => 'sometimes|boolean',
            'settings.audit_logging' => 'sometimes|boolean',
            'settings.data_encryption' => 'sometimes|boolean',
            'settings.anonymous_mode_default' => 'sometimes|boolean',
            'settings.ai_auto_analysis' => 'sometimes|boolean',
            'settings.auto_backup' => 'sometimes|boolean',
            'settings.admin_email' => 'sometimes|nullable|email:rfc|max:255',
            'settings.support_email' => 'sometimes|nullable|email:rfc|max:255',
            'settings.crisis_hotline' => 'sometimes|nullable|string|max:255',
        ]);

        $incoming = $validated['settings'];

        // Reject unknown keys explicitly to keep settings consistent and predictable.
        $allowedKeys = array_flip(SystemSettings::keys());
        $unknownKeys = array_values(array_diff(array_keys($incoming), array_keys($allowedKeys)));
        if (!empty($unknownKeys)) {
            throw ValidationException::withMessages([
                'settings' => ['Unknown setting keys: ' . implode(', ', $unknownKeys)],
            ]);
        }

        $settings = SystemSettings::setMany($incoming);
        $this->logAdminSystemAction($request, 'settings.updated', 'Admin updated system settings', [
            'updated_keys' => array_keys($incoming),
        ]);

        return response()->json($settings);
    }

    public function clearCache(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        // Clear Laravel cache
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        SystemSettings::forgetCache();

        $this->logAdminSystemAction($request, 'settings.cache_cleared', 'Admin cleared application cache');

        return response()->json(['message' => 'Cache cleared successfully']);
    }

    private function logAdminSystemAction(Request $request, string $action, string $description, array $metadata = []): void
    {
        // Log only if audit logging is enabled, but always allow explicit enable action to be recorded.
        $auditEnabled = SystemSettings::getBool('audit_logging', true);
        $isEnablingAudit = in_array('audit_logging', $metadata['updated_keys'] ?? [], true);

        if (!$auditEnabled && !$isEnablingAudit) {
            return;
        }

        ActivityLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'description' => $description,
            'type' => 'system',
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
