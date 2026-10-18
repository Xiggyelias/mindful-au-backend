<?php

namespace App\Support;

use App\Models\Appointment;
use Illuminate\Support\Carbon;

/**
 * When an appointment's session is "on": the span during which its call can be opened,
 * re-opened, and re-joined.
 *
 * The window opens JOIN_EARLY_MINUTES before the scheduled time and stays open until
 * `scheduled_at + duration + JOIN_LATE_GRACE_MINUTES`. That whole span is one session, not one
 * call — a dropped or hung-up call inside it must leave the slot callable again, which is why
 * this is a shared authority rather than a rule each caller re-derives with its own copy of
 * the constants.
 */
class AppointmentCallWindow
{
    public const MIN_DURATION_MINUTES = 15;

    public const MAX_DURATION_MINUTES = 120;

    public const DEFAULT_DURATION_MINUTES = 60;

    public const JOIN_EARLY_MINUTES = 15;

    public const JOIN_LATE_GRACE_MINUTES = 15;

    public static function normalizeDurationMinutes(?int $durationMinutes): int
    {
        if (! $durationMinutes) {
            return self::DEFAULT_DURATION_MINUTES;
        }

        return max(
            self::MIN_DURATION_MINUTES,
            min(self::MAX_DURATION_MINUTES, (int) round($durationMinutes))
        );
    }

    public static function isEmergency(Appointment $appointment): bool
    {
        if ((bool) ($appointment->is_emergency ?? false)) {
            return true;
        }

        $notes = strtolower((string) ($appointment->notes ?? ''));

        return str_contains($notes, 'emergency') || str_contains($notes, 'crisis');
    }

    /** When the session stops being joinable. */
    public static function closesAt(Appointment $appointment): ?Carbon
    {
        if (in_array($appointment->status, ['completed', 'cancelled'], true)) {
            return null;
        }

        if (! $appointment->scheduled_at) {
            return null;
        }

        $duration = self::normalizeDurationMinutes($appointment->duration_minutes);
        $scheduledAt = Carbon::parse($appointment->scheduled_at);
        $closesAt = $scheduledAt->copy()->addMinutes($duration + self::JOIN_LATE_GRACE_MINUTES);
        $isEmergency = self::isEmergency($appointment);

        if ($isEmergency && $closesAt->lt(now())) {
            $baseTime = $appointment->created_at ? Carbon::parse($appointment->created_at) : now();

            return $baseTime->copy()->addMinutes($duration + self::JOIN_LATE_GRACE_MINUTES);
        }

        return $closesAt;
    }

    /**
     * Is the session still on?
     *
     * True for the entire joinable span, including before the scheduled time — a session that
     * has not started yet is certainly not over. Callers that specifically need "can a call
     * start right now" want describe()['can_start'] instead.
     */
    public static function isOpen(Appointment $appointment): bool
    {
        if (in_array($appointment->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        $closesAt = self::closesAt($appointment);

        return $closesAt !== null && now()->lessThan($closesAt);
    }

    /** The full window description used by the call-authorization API. */
    public static function describe(Appointment $appointment): array
    {
        if (in_array($appointment->status, ['completed', 'cancelled'], true)) {
            return [
                'scheduled_at' => $appointment->scheduled_at ? Carbon::parse($appointment->scheduled_at)->toIso8601String() : null,
                'opens_at' => null,
                'closes_at' => null,
                'duration_minutes' => self::normalizeDurationMinutes($appointment->duration_minutes),
                'is_open' => false,
                'can_start' => false,
                'starts_in_seconds' => null,
                'ends_in_seconds' => 0,
                'message' => 'This appointment has concluded.',
            ];
        }

        $scheduledAt = Carbon::parse($appointment->scheduled_at);
        $durationMinutes = self::normalizeDurationMinutes($appointment->duration_minutes);
        $now = now();
        $isEmergency = self::isEmergency($appointment);

        if ($isEmergency) {
            // Priority crisis calls can start immediately upon booking without waiting for the 15m early lock.
            $opensAt = $appointment->created_at ? Carbon::parse($appointment->created_at)->subMinutes(5) : $now->copy()->subMinutes(5);
        } else {
            $opensAt = $scheduledAt->copy()->subMinutes(self::JOIN_EARLY_MINUTES);
        }

        $closesAt = $scheduledAt->copy()->addMinutes($durationMinutes + self::JOIN_LATE_GRACE_MINUTES);
        if ($isEmergency && $closesAt->lt($now)) {
            $closesAt = $now->copy()->addMinutes($durationMinutes + self::JOIN_LATE_GRACE_MINUTES);
        }

        $secondsUntilClose = $now->diffInSeconds($closesAt, false);

        $base = [
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'opens_at' => $opensAt->toIso8601String(),
            'closes_at' => $closesAt->toIso8601String(),
            'duration_minutes' => $durationMinutes,
        ];

        if ($now->lt($opensAt)) {
            return $base + [
                'can_start' => false,
                'message' => sprintf(
                    'Call is locked until %d minutes before the scheduled time.',
                    self::JOIN_EARLY_MINUTES
                ),
                'starts_in_seconds' => $now->diffInSeconds($opensAt),
                'ends_in_seconds' => $now->diffInSeconds($closesAt),
            ];
        }

        if ($now->greaterThanOrEqualTo($closesAt) || $secondsUntilClose < 60) {
            return $base + [
                'can_start' => false,
                'message' => 'This call window has closed.',
                'starts_in_seconds' => 0,
                'ends_in_seconds' => 0,
            ];
        }

        return $base + [
            'can_start' => true,
            'message' => 'Call is ready.',
            'starts_in_seconds' => 0,
            'ends_in_seconds' => $secondsUntilClose,
        ];
    }
}
