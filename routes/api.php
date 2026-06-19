<?php

use App\Http\Controllers\AcademicRiskWebhookController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AIDiagnosticController;
use App\Http\Controllers\AIReportController;
use App\Http\Controllers\AIWellnessChatController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthSessionController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ChatAttachmentController;
use App\Http\Controllers\CounselorIncomingCallController;
use App\Http\Controllers\CounselorSessionReminderController;
use App\Http\Controllers\CounselorSlotController;
use App\Http\Controllers\CounselorTwoFactorController;
use App\Http\Controllers\CounselorWellnessController;
use App\Http\Controllers\DataAccessLogController;
use App\Http\Controllers\DiagnosticController;
use App\Http\Controllers\EmergencyRequestController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InstitutionAccountController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MlInsightsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\OpenRouterChatController;
use App\Http\Controllers\PanicLogController;
use App\Http\Controllers\PeerSupportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StudentIncomingCallController;
use App\Http\Controllers\StudentMoodController;
use App\Http\Controllers\StudentWellnessController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\TipController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VideoCallController;
use App\Http\Controllers\VoiceNotesController;
use Illuminate\Support\Facades\Route;

// Authentication routes
Route::get('/health', [HealthController::class, 'health']);
Route::get('/ready', [HealthController::class, 'ready']);

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
Route::get('/chat/files/{chatFile}/content', [ChatAttachmentController::class, 'show'])
    ->name('chat-files.content')
    ->middleware('signed');

// OAuth routes
Route::get('/auth/google', [OAuthController::class, 'redirectToGoogle'])->middleware('web');
Route::get('/auth/google/callback', [OAuthController::class, 'handleGoogleCallback'])->middleware('web');
Route::post('/auth/google/exchange-ticket', [OAuthController::class, 'exchangeLoginTicket'])->middleware('throttle:oauth-ticket-exchange');
Route::post('/integrations/academic-risk/webhook', [AcademicRiskWebhookController::class, 'ingest'])
    ->middleware(['verify.integration.signature', 'throttle:120,1']);

Route::get('/push/vapid-public-key', [PushSubscriptionController::class, 'vapidPublicKey']);

// Protected routes (Sanctum)
Route::middleware(['auth:sanctum', 'track.device_session', 'session.timeout', 'audit.admin', 'audit.access', 'counselor.2fa'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/me/presence', [AuthController::class, 'presence'])->middleware('throttle:presence');
    Route::get('/auth/sessions', [AuthSessionController::class, 'index']);
    Route::delete('/auth/sessions/{sessionId}', [AuthSessionController::class, 'destroy']);
    Route::post('/auth/sessions/logout-others', [AuthSessionController::class, 'logoutOtherDevices']);
    Route::get('/auth/2fa/status', [CounselorTwoFactorController::class, 'status']);
    Route::post('/auth/2fa/setup', [CounselorTwoFactorController::class, 'setup'])->middleware('throttle:auth-login');
    Route::post('/auth/2fa/verify', [CounselorTwoFactorController::class, 'verify'])->middleware('throttle:auth-login');
    Route::get('/tips/today', [TipController::class, 'today']);
    Route::get('/wellness/tip', [TipController::class, 'wellnessTip']);
    Route::get('/wellness/tips/favorites', [TipController::class, 'favorites']);
    Route::post('/wellness/tips/{tip}/favorite', [TipController::class, 'favorite']);
    Route::delete('/wellness/tips/{tip}/favorite', [TipController::class, 'unfavorite']);

    // Projects
    Route::apiResource('projects', ProjectController::class);

    // Sessions
    Route::get('/sessions/chat-list', [SessionController::class, 'chatList'])->middleware('throttle:messages-read');
    Route::patch('/sessions/{id}/chat-anonymity', [SessionController::class, 'updateChatAnonymity'])->middleware('throttle:messages-write');
    Route::apiResource('sessions', SessionController::class);
    Route::get('/sessions/{id}/messages', [MessageController::class, 'index'])->middleware('throttle:messages-read');
    Route::post('/sessions/{id}/messages/read', [MessageController::class, 'markInboundRead'])->middleware('throttle:messages-read');
    Route::get('/chat/messages', [MessageController::class, 'indexBySession'])->middleware('throttle:messages-read');
    Route::get('/chat/incoming-digest', [MessageController::class, 'incomingDigest'])->middleware('throttle:messages-read');
    Route::post('/sessions/{id}/messages', [MessageController::class, 'store'])->middleware('throttle:messages-write');
    Route::post('/sessions/{id}/crisis-signal', [MessageController::class, 'reportCrisisSignal'])->middleware('throttle:messages-write');
    Route::delete('/sessions/{id}/messages/{messageId}', [MessageController::class, 'destroy'])->middleware('throttle:messages-write');
    Route::post('/sessions/{id}/typing', [MessageController::class, 'setTyping'])->middleware('throttle:messages-write');
    Route::get('/sessions/{id}/typing', [MessageController::class, 'typingStatus'])->middleware('throttle:messages-read');

    // Chat Attachments
    Route::post('/chat/upload-file', [ChatAttachmentController::class, 'uploadForChat'])->middleware('throttle:messages-write');
    Route::post('/sessions/{id}/attachments', [ChatAttachmentController::class, 'upload'])->middleware('throttle:messages-write');
    Route::get('/messages/{id}/attachment', [ChatAttachmentController::class, 'download'])->middleware('throttle:messages-read');
    Route::post('/sessions/counselor', [SessionController::class, 'storeAsCounselor']);
    Route::put('/sessions/{id}/note', [SessionController::class, 'upsertNote'])->middleware('throttle:messages-write');
    Route::delete('/sessions/{id}/note', [SessionController::class, 'deleteNote'])->middleware('throttle:messages-write');
    Route::post('/sessions/{id}/touch', [SessionController::class, 'touch'])->middleware('throttle:session-touch');
    Route::post('/sessions/{id}/assign-peer', [SessionController::class, 'assignPeerCounselor'])->middleware('throttle:messages-write');
    Route::post('/sessions/{id}/unassign-peer', [SessionController::class, 'unassignPeerCounselor'])->middleware('throttle:messages-write');
    Route::post('/sessions/{id}/escalate', [SessionController::class, 'escalateToCounselor'])->middleware('throttle:messages-write');
    Route::post('/sessions/{id}/panic-escalate', [SessionController::class, 'panicEscalation'])->middleware('throttle:messages-write');
    Route::post('/sessions/{id}/flag-urgent', [SessionController::class, 'flagUrgentConcern'])->middleware('throttle:messages-write');
    Route::post('/sessions/{id}/reveal-identity', [SessionController::class, 'revealIdentity'])->middleware('throttle:messages-write');

    // Appointments
    Route::get('/counselor-slots', [CounselorSlotController::class, 'index'])->middleware('throttle:messages-read');
    Route::post('/counselor-slots/generate', [CounselorSlotController::class, 'generate'])->middleware('throttle:messages-write');
    Route::get('/counselor-schedules', [CounselorSlotController::class, 'schedules'])->middleware('throttle:messages-read');
    Route::put('/counselor-schedules', [CounselorSlotController::class, 'updateSchedules'])->middleware('throttle:messages-write');
    Route::post('/appointments/bulk-cancel', [AppointmentController::class, 'bulkCancel'])->middleware('throttle:messages-write');
    Route::post('/appointments/{id}/reveal-identity', [AppointmentController::class, 'revealIdentity'])->middleware('throttle:messages-write');
    Route::apiResource('appointments', AppointmentController::class);
    Route::get('/emergency-requests', [EmergencyRequestController::class, 'index'])->middleware('throttle:messages-read');
    Route::post('/emergency-requests', [EmergencyRequestController::class, 'store'])->middleware('throttle:messages-write');
    Route::patch('/emergency-requests/{id}', [EmergencyRequestController::class, 'update'])->middleware('throttle:messages-write');

    // Intake & Triage
    Route::get('/intake-submissions', [IntakeController::class, 'index']);
    Route::post('/intake-submissions', [IntakeController::class, 'store']);
    Route::get('/intake-submissions/{id}', [IntakeController::class, 'show']);
    Route::patch('/risk-alerts/{id}/acknowledge', [IntakeController::class, 'acknowledgeAlert'])->middleware('throttle:messages-write');

    // Referrals
    Route::get('/referrals', [ReferralController::class, 'index']);
    Route::post('/referrals', [ReferralController::class, 'store']);
    Route::get('/referrals/{id}', [ReferralController::class, 'show']);
    Route::patch('/referrals/{id}', [ReferralController::class, 'update']);
    Route::post('/referrals/{id}/events', [ReferralController::class, 'addEvent']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications', [NotificationController::class, 'store'])->middleware('admin');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/notifications/preferences', [NotificationController::class, 'preferences'])->middleware('throttle:messages-write');

    Route::post('/push/subscribe', [PushSubscriptionController::class, 'subscribe'])->middleware('throttle:messages-write');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe'])->middleware('throttle:messages-write');
    Route::patch('/push/preferences', [PushSubscriptionController::class, 'preferences'])->middleware('throttle:messages-write');

    // Analytics (Admin only)
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview'])->middleware('admin');
    Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard'])->middleware('admin');
    Route::get('/analytics/export', [ReportExportController::class, 'export'])->middleware('admin');

    // Voice Notes
    Route::post('/sessions/{id}/voice-notes', [VoiceNotesController::class, 'upload'])->middleware('throttle:messages-write');
    Route::get('/messages/{id}/voice-note', [VoiceNotesController::class, 'download'])->middleware('throttle:messages-read');
    Route::get('/messages/{id}/voice-note/stream', [VoiceNotesController::class, 'stream'])->middleware('throttle:messages-read');

    // Video Calls
    Route::post('/video-calls/authorize', [VideoCallController::class, 'authorizeCall'])->middleware('throttle:messages-write');
    Route::post('/video-calls/cancel', [VideoCallController::class, 'cancelCall'])->middleware('throttle:messages-write');
    Route::post('/video-calls/end', [VideoCallController::class, 'end'])->middleware('throttle:messages-write');
    Route::get('/counselor/incoming-calls', [CounselorIncomingCallController::class, 'index'])->middleware(['counselor', 'throttle:messages-read']);
    Route::patch('/counselor/incoming-calls/{counselingCall}', [CounselorIncomingCallController::class, 'update'])->middleware(['counselor', 'throttle:messages-write']);
    Route::get('/student/incoming-calls', [StudentIncomingCallController::class, 'index'])->middleware(['student', 'throttle:messages-read']);
    Route::patch('/student/incoming-calls/{counselingCall}', [StudentIncomingCallController::class, 'update'])->middleware(['student', 'throttle:messages-write']);
    Route::get('/counselor/session-reminders', [CounselorSessionReminderController::class, 'index'])->middleware(['counselor', 'throttle:messages-read']);

    // Users
    Route::get('/users', [UserController::class, 'index'])->middleware('admin');
    Route::get('/users/counselors', [UserController::class, 'counselors']);
    Route::get('/users/peer-counselors', [UserController::class, 'peerCounselors']);
    Route::get('/users/students', [UserController::class, 'students']);
    Route::post('/users/counselors/{id}/approve', [UserController::class, 'approveCounselor'])->middleware('admin');
    Route::post('/users/counselors/approve-bulk', [UserController::class, 'approveCounselorsBulk'])->middleware('admin');
    Route::post('/users/counselors/{id}/reject', [UserController::class, 'rejectCounselor'])->middleware('admin');
    Route::delete('/users/counselors/{id}', [UserController::class, 'destroyCounselor'])->middleware('admin');

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
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    // AI Wellness Chat
    Route::get('/ai/wellness-chat/history', [AIWellnessChatController::class, 'history'])->middleware('throttle:ai-read');
    Route::post('/ai/wellness-chat', [AIWellnessChatController::class, 'chat'])->middleware('throttle:ai-chat');

    // AI Diagnostics
    Route::get('/ai-diagnostics', [AIDiagnosticController::class, 'index']);
    Route::get('/ai-diagnostics/summary', [AIDiagnosticController::class, 'summary']);
    Route::get('/ai-diagnostics/latest', [AIDiagnosticController::class, 'latest']);
    Route::get('/ai-diagnostics/{id}', [AIDiagnosticController::class, 'show']);
    Route::post('/sessions/{id}/analyze', [AIDiagnosticController::class, 'analyzeSession']);
    Route::post('/appointments/{id}/analyze', [AIDiagnosticController::class, 'analyzeAppointment']);

    // Diagnostic Assessment
    Route::get('/diagnostics/questionnaire', [DiagnosticController::class, 'getQuestionnaire']);
    Route::post('/diagnostics/analyze', [DiagnosticController::class, 'analyze'])->middleware('throttle:diagnostics-submit');
    Route::get('/diagnostics/history', [DiagnosticController::class, 'getHistory']);
    Route::get('/diagnostics/latest', [DiagnosticController::class, 'getLatest']);
    Route::get('/diagnostics/trends', [DiagnosticController::class, 'getTrends']);
    Route::get('/diagnostics/counselor-dashboard', [DiagnosticController::class, 'getCounselorDashboard'])->middleware('counselor');
    Route::post('/diagnostics/assign', [DiagnosticController::class, 'assignNewAssessment'])->middleware('counselor');
    Route::get('/student-wellness/summary', [StudentWellnessController::class, 'summary']);
    Route::get('/ml/counselor-matches', [MlInsightsController::class, 'counselorMatches']);
    Route::get('/ml/health', [MlInsightsController::class, 'health']);
    Route::get('/student-mood/today', [StudentMoodController::class, 'today']);
    Route::post('/student-mood', [StudentMoodController::class, 'store']);

    // Counselor Wellness
    Route::get('/counselor-wellness', [CounselorWellnessController::class, 'index']);
    Route::get('/counselor-wellness/summary', [CounselorWellnessController::class, 'summary']);
    Route::post('/counselor-wellness', [CounselorWellnessController::class, 'store']);
    Route::post('/counselor-wellness/health-check', [CounselorWellnessController::class, 'runHealthCheck']);

    // Panic Logs
    Route::get('/panic-logs', [PanicLogController::class, 'index']);
    Route::post('/panic-logs', [PanicLogController::class, 'store']);
    Route::put('/panic-logs/{id}', [PanicLogController::class, 'update']);

    // Settings (Admin only)
    Route::get('/settings', [SystemSettingController::class, 'index'])->middleware('admin');
    Route::put('/settings', [SystemSettingController::class, 'update'])->middleware('admin');
    Route::post('/settings/clear-cache', [SystemSettingController::class, 'clearCache'])->middleware('admin');
    Route::get('/tips', [TipController::class, 'index'])->middleware('admin');
    Route::post('/tips', [TipController::class, 'store'])->middleware('admin');
    Route::put('/tips/{tip}', [TipController::class, 'update'])->middleware('admin');
    Route::delete('/tips/{tip}', [TipController::class, 'destroy'])->middleware('admin');
    Route::post('/admin/add-tip', [TipController::class, 'store'])->middleware('admin');
    Route::put('/admin/update-tip/{tip}', [TipController::class, 'update'])->middleware('admin');
    Route::delete('/admin/delete-tip/{tip}', [TipController::class, 'destroy'])->middleware('admin');
    Route::get('/backups', [BackupController::class, 'index'])->middleware('admin');
    Route::post('/backups/verify', [BackupController::class, 'verify'])->middleware('admin');
    Route::post('/backups/drill', [BackupController::class, 'drill'])->middleware('admin');

    // Activity Logs (Admin only)
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->middleware('admin');
    Route::get('/activity-logs/stream', [ActivityLogController::class, 'stream'])->middleware('admin');
    Route::get('/activity-logs/stats', [ActivityLogController::class, 'stats'])->middleware('admin');
    Route::get('/data-access-logs', [DataAccessLogController::class, 'index'])->middleware('admin');

    // AI Reports (Admin only)
    Route::get('/ai-reports', [AIReportController::class, 'index'])->middleware('admin');
    Route::get('/ai-reports/{id}', [AIReportController::class, 'show'])->middleware('admin');
    Route::post('/ai-reports/generate', [AIReportController::class, 'generate'])->middleware('admin');
    Route::delete('/ai-reports/{id}', [AIReportController::class, 'destroy'])->middleware('admin');

    // Academic Risk Integrations (Admin only - read endpoints)
    Route::get('/integrations/academic-risk/events', [AcademicRiskWebhookController::class, 'events'])->middleware('admin');
    Route::get('/integrations/academic-risk/runs', [AcademicRiskWebhookController::class, 'runs'])->middleware('admin');

    // OpenRouter AI Chat (authenticated users)
    Route::post('/openrouter/chat', [OpenRouterChatController::class, 'sendMessage'])->middleware('throttle:ai-chat');
    Route::post('/openrouter/stream', [OpenRouterChatController::class, 'streamMessage'])->middleware('throttle:ai-chat');
    Route::post('/openrouter/simple-chat', [OpenRouterChatController::class, 'simpleChat'])->middleware('throttle:ai-chat');
    Route::get('/openrouter/models', [OpenRouterChatController::class, 'getModels'])->middleware('throttle:ai-read');
    Route::get('/openrouter/conversations', [OpenRouterChatController::class, 'getConversations'])->middleware('throttle:ai-read');
    Route::post('/openrouter/conversations', [OpenRouterChatController::class, 'createConversation'])->middleware('throttle:ai-chat');
    Route::get('/openrouter/conversations/{conversationId}', [OpenRouterChatController::class, 'getConversationMessages'])
        ->whereNumber('conversationId')
        ->middleware('throttle:ai-read');
    Route::delete('/openrouter/conversations/{conversationId}', [OpenRouterChatController::class, 'deleteConversation'])
        ->whereNumber('conversationId')
        ->middleware('throttle:ai-chat');
});
