<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\VoiceNotesController;
use App\Http\Controllers\VideoCallController;
use App\Http\Controllers\OpenRouterChatController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\AcademicRiskWebhookController;
use App\Http\Controllers\CounselorTwoFactorController;
use App\Http\Controllers\DataAccessLogController;
use App\Http\Controllers\InstitutionAccountController;
use App\Http\Controllers\PeerSupportController;

// Authentication routes
Route::get('/health', [HealthController::class, 'health']);
Route::get('/ready', [HealthController::class, 'ready']);

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');

// OAuth routes
Route::get('/auth/google', [OAuthController::class, 'redirectToGoogle'])->middleware('web');
Route::get('/auth/google/callback', [OAuthController::class, 'handleGoogleCallback'])->middleware('web');
Route::post('/auth/google/exchange-ticket', [OAuthController::class, 'exchangeLoginTicket'])->middleware('throttle:oauth-ticket-exchange');
Route::post('/integrations/academic-risk/webhook', [AcademicRiskWebhookController::class, 'ingest'])
    ->middleware(['verify.integration.signature', 'throttle:120,1']);

// Protected routes (Sanctum)
Route::middleware(['auth:sanctum', 'session.timeout', 'audit.admin', 'audit.access', 'counselor.2fa'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/me/presence', [AuthController::class, 'presence'])->middleware('throttle:presence');
    Route::get('/auth/2fa/status', [CounselorTwoFactorController::class, 'status']);
    Route::post('/auth/2fa/setup', [CounselorTwoFactorController::class, 'setup'])->middleware('throttle:auth-login');
    Route::post('/auth/2fa/verify', [CounselorTwoFactorController::class, 'verify'])->middleware('throttle:auth-login');

    // Sessions
    Route::get('/sessions/chat-list', [SessionController::class, 'chatList'])->middleware('throttle:messages-read');
    Route::apiResource('sessions', SessionController::class);
    Route::get('/sessions/{id}/messages', [MessageController::class, 'index'])->middleware('throttle:messages-read');
    Route::post('/sessions/{id}/messages', [MessageController::class, 'store'])->middleware('throttle:messages-write');
    Route::delete('/sessions/{id}/messages/{messageId}', [MessageController::class, 'destroy'])->middleware('throttle:messages-write');
    Route::post('/sessions/{id}/typing', [MessageController::class, 'setTyping'])->middleware('throttle:messages-write');
    Route::get('/sessions/{id}/typing', [MessageController::class, 'typingStatus'])->middleware('throttle:messages-read');
    Route::post('/sessions/counselor', [SessionController::class, 'storeAsCounselor']);
    Route::put('/sessions/{id}/note', [SessionController::class, 'upsertNote'])->middleware('throttle:60,1');
    Route::delete('/sessions/{id}/note', [SessionController::class, 'deleteNote'])->middleware('throttle:60,1');
    Route::post('/sessions/{id}/assign-peer', [SessionController::class, 'assignPeerCounselor'])->middleware('throttle:30,1');
    Route::post('/sessions/{id}/unassign-peer', [SessionController::class, 'unassignPeerCounselor'])->middleware('throttle:30,1');
    Route::post('/sessions/{id}/escalate', [SessionController::class, 'escalateToCounselor'])->middleware('throttle:30,1');
    Route::post('/sessions/{id}/panic-escalate', [SessionController::class, 'panicEscalation'])->middleware('throttle:20,1');
    Route::post('/sessions/{id}/flag-urgent', [SessionController::class, 'flagUrgentConcern'])->middleware('throttle:30,1');

    // Appointments
    Route::apiResource('appointments', AppointmentController::class);

    // Intake & Triage
    Route::get('/intake-submissions', [IntakeController::class, 'index']);
    Route::post('/intake-submissions', [IntakeController::class, 'store']);
    Route::get('/intake-submissions/{id}', [IntakeController::class, 'show']);
    Route::patch('/risk-alerts/{id}/acknowledge', [IntakeController::class, 'acknowledgeAlert'])->middleware('throttle:60,1');

    // Referrals
    Route::get('/referrals', [ReferralController::class, 'index']);
    Route::post('/referrals', [ReferralController::class, 'store']);
    Route::get('/referrals/{id}', [ReferralController::class, 'show']);
    Route::patch('/referrals/{id}', [ReferralController::class, 'update']);
    Route::post('/referrals/{id}/events', [ReferralController::class, 'addEvent']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Analytics (Admin only)
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview'])->middleware('admin');
    Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard'])->middleware('admin');
    Route::get('/analytics/export', [ReportExportController::class, 'export'])->middleware('admin');

    // Voice Notes
    Route::post('/sessions/{id}/voice-notes', [VoiceNotesController::class, 'upload']);
    Route::get('/messages/{id}/voice-note', [VoiceNotesController::class, 'download']);

    // Video Calls
    Route::post('/video-calls/authorize', [VideoCallController::class, 'authorizeCall'])->middleware('throttle:20,1');
    Route::post('/video-calls/end', [VideoCallController::class, 'end'])->middleware('throttle:20,1');

    // Users
    Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->middleware('admin');
    Route::get('/users/counselors', [\App\Http\Controllers\UserController::class, 'counselors']);
    Route::get('/users/peer-counselors', [\App\Http\Controllers\UserController::class, 'peerCounselors']);
    Route::get('/users/students', [\App\Http\Controllers\UserController::class, 'students']);
    Route::post('/users/counselors/{id}/approve', [\App\Http\Controllers\UserController::class, 'approveCounselor'])->middleware('admin');
    Route::post('/users/counselors/approve-bulk', [\App\Http\Controllers\UserController::class, 'approveCounselorsBulk'])->middleware('admin');
    Route::post('/users/counselors/{id}/reject', [\App\Http\Controllers\UserController::class, 'rejectCounselor'])->middleware('admin');
    Route::delete('/users/counselors/{id}', [\App\Http\Controllers\UserController::class, 'destroyCounselor'])->middleware('admin');

    // Peer counselor dashboard
    Route::get('/peer/dashboard', [PeerSupportController::class, 'dashboard'])->middleware('role:peer_counselor');
    Route::get('/peer/escalations', [PeerSupportController::class, 'escalations'])->middleware('role:peer_counselor');
    Route::patch('/peer/availability', [PeerSupportController::class, 'setAvailability'])->middleware('role:peer_counselor');

    // Institutional account role records (Admin only)
    Route::get('/institution-accounts', [InstitutionAccountController::class, 'index'])->middleware('admin');
    Route::post('/institution-accounts', [InstitutionAccountController::class, 'store'])->middleware('admin');
    Route::put('/institution-accounts/{id}', [InstitutionAccountController::class, 'update'])->middleware('admin');
    Route::delete('/institution-accounts/{id}', [InstitutionAccountController::class, 'destroy'])->middleware('admin');

    // Profile
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'show']);
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update']);

    // AI Wellness Chat
    Route::get('/ai/wellness-chat/history', [\App\Http\Controllers\AIWellnessChatController::class, 'history'])->middleware('throttle:ai-read');
    Route::post('/ai/wellness-chat', [\App\Http\Controllers\AIWellnessChatController::class, 'chat'])->middleware('throttle:ai-chat');

    // AI Diagnostics
    Route::get('/ai-diagnostics', [\App\Http\Controllers\AIDiagnosticController::class, 'index']);
    Route::get('/ai-diagnostics/summary', [\App\Http\Controllers\AIDiagnosticController::class, 'summary']);
    Route::get('/ai-diagnostics/latest', [\App\Http\Controllers\AIDiagnosticController::class, 'latest']);
    Route::get('/ai-diagnostics/{id}', [\App\Http\Controllers\AIDiagnosticController::class, 'show']);
    Route::post('/sessions/{id}/analyze', [\App\Http\Controllers\AIDiagnosticController::class, 'analyzeSession']);
    
    // Diagnostic Assessment
    Route::get('/diagnostics/questionnaire', [\App\Http\Controllers\DiagnosticController::class, 'getQuestionnaire']);
    Route::post('/diagnostics/analyze', [\App\Http\Controllers\DiagnosticController::class, 'analyze'])->middleware('throttle:diagnostics-submit');
    Route::get('/diagnostics/history', [\App\Http\Controllers\DiagnosticController::class, 'getHistory']);
    Route::get('/diagnostics/latest', [\App\Http\Controllers\DiagnosticController::class, 'getLatest']);
    Route::get('/diagnostics/trends', [\App\Http\Controllers\DiagnosticController::class, 'getTrends']);
    Route::get('/diagnostics/counselor-dashboard', [\App\Http\Controllers\DiagnosticController::class, 'getCounselorDashboard'])->middleware('counselor');
    Route::get('/student-wellness/summary', [\App\Http\Controllers\StudentWellnessController::class, 'summary']);
    Route::get('/ml/counselor-matches', [\App\Http\Controllers\MlInsightsController::class, 'counselorMatches']);
    Route::get('/student-mood/today', [\App\Http\Controllers\StudentMoodController::class, 'today']);
    Route::post('/student-mood', [\App\Http\Controllers\StudentMoodController::class, 'store']);

    // Counselor Wellness
    Route::get('/counselor-wellness', [\App\Http\Controllers\CounselorWellnessController::class, 'index']);
    Route::get('/counselor-wellness/summary', [\App\Http\Controllers\CounselorWellnessController::class, 'summary']);
    Route::post('/counselor-wellness', [\App\Http\Controllers\CounselorWellnessController::class, 'store']);
    Route::post('/counselor-wellness/health-check', [\App\Http\Controllers\CounselorWellnessController::class, 'runHealthCheck']);

    // Panic Logs
    Route::get('/panic-logs', [\App\Http\Controllers\PanicLogController::class, 'index']);
    Route::post('/panic-logs', [\App\Http\Controllers\PanicLogController::class, 'store']);
    Route::put('/panic-logs/{id}', [\App\Http\Controllers\PanicLogController::class, 'update']);

    // Settings (Admin only)
    Route::get('/settings', [\App\Http\Controllers\SystemSettingController::class, 'index'])->middleware('admin');
    Route::put('/settings', [\App\Http\Controllers\SystemSettingController::class, 'update'])->middleware('admin');
    Route::post('/settings/clear-cache', [\App\Http\Controllers\SystemSettingController::class, 'clearCache'])->middleware('admin');
    Route::get('/backups', [BackupController::class, 'index'])->middleware('admin');
    Route::post('/backups/verify', [BackupController::class, 'verify'])->middleware('admin');
    Route::post('/backups/drill', [BackupController::class, 'drill'])->middleware('admin');

    // Activity Logs (Admin only)
    Route::get('/activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index'])->middleware('admin');
    Route::get('/activity-logs/stats', [\App\Http\Controllers\ActivityLogController::class, 'stats'])->middleware('admin');
    Route::get('/data-access-logs', [DataAccessLogController::class, 'index'])->middleware('admin');
    Route::get('/integrations/academic-risk/events', [AcademicRiskWebhookController::class, 'events'])->middleware('admin');
    Route::get('/integrations/academic-risk/runs', [AcademicRiskWebhookController::class, 'runs'])->middleware('admin');

    // AI Reports (Admin only)
    Route::get('/ai-reports', [\App\Http\Controllers\AIReportController::class, 'index'])->middleware('admin');
    Route::get('/ai-reports/{id}', [\App\Http\Controllers\AIReportController::class, 'show'])->middleware('admin');
    Route::post('/ai-reports/generate', [\App\Http\Controllers\AIReportController::class, 'generate'])->middleware('admin');
    Route::delete('/ai-reports/{id}', [\App\Http\Controllers\AIReportController::class, 'destroy'])->middleware('admin');

    // OpenRouter Chat
    Route::post('/openrouter/chat', [OpenRouterChatController::class, 'sendMessage'])->middleware('throttle:ai-chat');
    Route::post('/openrouter/stream', [OpenRouterChatController::class, 'streamMessage'])->middleware('throttle:ai-chat');
    Route::get('/openrouter/models', [OpenRouterChatController::class, 'getModels'])->middleware('throttle:ai-read');
    Route::post('/openrouter/simple-chat', [OpenRouterChatController::class, 'simpleChat'])->middleware('throttle:ai-chat');
    Route::get('/openrouter/conversations', [OpenRouterChatController::class, 'getConversations'])->middleware('throttle:ai-read');
    Route::post('/openrouter/conversations', [OpenRouterChatController::class, 'createConversation'])->middleware('throttle:ai-chat');
    Route::get('/openrouter/conversations/{id}', [OpenRouterChatController::class, 'getConversationMessages'])->middleware('throttle:ai-read');
    Route::delete('/openrouter/conversations/{id}', [OpenRouterChatController::class, 'deleteConversation'])->middleware('throttle:ai-chat');
});
