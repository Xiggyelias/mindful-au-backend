<?php

namespace App\Jobs;

use App\Models\CounselingSession;
use App\Support\SystemSettings;
use App\Services\AIDiagnosticService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAIDiagnostic implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
                    \App\Models\Notification::create([
                        'user_id' => $this->session->counselor_id,
                        'title' => 'High Risk Alert',
                        'message' => "AI analysis detected {$diagnostic->risk_level} risk indicators for a student session.",
                        'type' => 'warning',
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AI Diagnostic processing failed: ' . $e->getMessage());
        }
    }
}
