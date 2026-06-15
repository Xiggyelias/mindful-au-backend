<?php

namespace App\Jobs;

use App\Models\CounselingSession;
use App\Models\Notification;
use App\Services\AIDiagnosticService;
use App\Support\SystemSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAIDiagnostic implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public int $backoff = 60;

    public $session;

    public $messages;

    public function __construct(CounselingSession $session, array $messages)
    {
        $this->session = $session;
        $this->messages = $messages;
    }

    public function handle(AIDiagnosticService $service): void
    {
        try {
            $diagnostic = $service->analyzeSession($this->session, $this->messages);

            // Create notification for counselor if high risk and alerts are enabled.
            if (
                SystemSettings::getBool('ai_risk_alerts', true)
                && ($diagnostic->risk_level === 'high' || $diagnostic->risk_level === 'critical')
            ) {
                if ($this->session->counselor_id) {
                    Notification::create([
                        'user_id' => $this->session->counselor_id,
                        'title' => 'High Risk Alert',
                        'message' => "AI analysis detected {$diagnostic->risk_level} risk indicators for a student session.",
                        'type' => 'warning',
                        'meta' => [
                            'path' => '/counselor/alerts',
                        ],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('AI Diagnostic processing failed.', [
                'exception' => $e::class,
                'session_id' => $this->session?->id ?? null,
            ]);

            throw $e;
        }
    }
}
