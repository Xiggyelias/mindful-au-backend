<?php

namespace App\Support;

use App\Models\CounselingCall;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers call signaling events to a *specific recipient* over Supabase Realtime.
 *
 * Why this exists: the ring used to be signalled browser-to-browser — the caller's tab
 * broadcast to `incoming-call-wake-{calleeUserId}`. That silently fails whenever the
 * caller cannot know the callee's user id, which is exactly the counselor→student case
 * for anonymous appointments (the appointments API masks `student_id` to 0 for the
 * counselor). The callee then only discovered the call on its next 30s+ poll — usually
 * after the caller had already given up.
 *
 * The server always knows both participants, so it is the only place that can reliably
 * address the recipient. Every state transition (ringing / accepted / declined /
 * cancelled / missed) is pushed from here to the party that needs to react to it.
 *
 * Delivery is best-effort: HTTP polling of /student|counselor/incoming-calls remains the
 * fallback, so a failed or unconfigured broadcast degrades latency, never correctness.
 */
class CallSignalBroadcaster
{
    /** Broadcast event name — must match INCOMING_CALL_WAKE_BROADCAST on the frontend. */
    public const EVENT = 'incoming-call-wake';

    /** Call is ringing on the recipient's device. */
    public const STATE_RINGING = 'RINGING';

    /** Callee answered — both sides move to CONNECTED. */
    public const STATE_CONNECTED = 'CONNECTED';

    /** Declined, cancelled or missed — both sides tear down. */
    public const STATE_ENDED = 'ENDED';

    /** Channel the given user listens on — must match incomingCallWakeChannelName(). */
    public static function channelFor(int $userId): string
    {
        return "incoming-call-wake-{$userId}";
    }

    public function isConfigured(): bool
    {
        return $this->url() !== '' && $this->key() !== '';
    }

    /**
     * Push a call state change to one user. Returns whether the realtime hop was accepted;
     * callers must not treat false as a failure of the call itself.
     */
    public function send(int $recipientUserId, array $payload, string $reason): bool
    {
        if ($recipientUserId <= 0) {
            Log::warning('[CallSignal] No recipient to signal', ['reason' => $reason, 'payload' => $payload]);

            return false;
        }

        $channel = self::channelFor($recipientUserId);

        if (! $this->isConfigured()) {
            // Not an error in local/dev setups without Supabase — but it does mean the
            // recipient will only see the call on their next poll, so make it visible.
            Log::warning('[CallSignal] Supabase realtime is not configured; falling back to polling', [
                'reason' => $reason,
                'recipient_user_id' => $recipientUserId,
                'hint' => 'Set SUPABASE_URL and SUPABASE_ANON_KEY in the backend .env',
            ]);

            return false;
        }

        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders([
                    'apikey' => $this->key(),
                    'Authorization' => 'Bearer '.$this->key(),
                ])
                ->post(rtrim($this->url(), '/').'/realtime/v1/api/broadcast', [
                    'messages' => [[
                        'topic' => $channel,
                        'event' => self::EVENT,
                        'payload' => $payload,
                        'private' => false,
                    ]],
                ]);

            if (! $response->successful()) {
                Log::warning('[CallSignal] Broadcast rejected', [
                    'reason' => $reason,
                    'channel' => $channel,
                    'status' => $response->status(),
                    'body' => mb_substr((string) $response->body(), 0, 500),
                ]);

                return false;
            }

            Log::info('[CallSignal] Incoming call event sent', [
                'reason' => $reason,
                'channel' => $channel,
                'recipient_user_id' => $recipientUserId,
                'payload' => $payload,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[CallSignal] Broadcast failed', [
                'reason' => $reason,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** The wire payload for a call, shared by every transition. */
    public function payloadFor(CounselingCall $call, string $state): array
    {
        return [
            'call_id' => (int) $call->id,
            'appointment_id' => (int) $call->appointment_id,
            'call_type' => (string) $call->call_type,
            'caller_role' => (string) $call->caller_role,
            'status' => (string) $call->status,
            'state' => $state,
        ];
    }

    private function url(): string
    {
        return trim((string) config('services.supabase.url', ''));
    }

    private function key(): string
    {
        return trim((string) config('services.supabase.key', ''));
    }

    private function timeout(): int
    {
        return max(1, (int) config('services.supabase.timeout', 3));
    }
}
