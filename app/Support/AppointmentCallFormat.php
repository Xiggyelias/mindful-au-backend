<?php

namespace App\Support;

use App\Models\Appointment;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Single source of truth for the `notes` / `call_type` pair that decides whether an
 * appointment runs as an in-person session, an online audio call, or an online video call.
 *
 * Why this exists: switching an appointment to anonymous forces it to online-audio, and the
 * reverse transition has to put it *back*. That used to be written out by hand in each
 * controller, and only the anonymous direction was ever implemented — so an appointment that
 * had been anonymous once kept `notes = 'Online audio'` forever. The student saw "Identified
 * Mode" while every call it produced was still audio-only: identity revealed, format stuck.
 * Both directions now live here so they cannot drift apart again.
 */
class AppointmentCallFormat
{
    public const NOTES_ONLINE_VIDEO = 'Online';

    public const NOTES_ONLINE_AUDIO = 'Online audio';

    /** In-person appointments keep whatever room/location detail the student typed. */
    public static function isPhysical(?string $notes): bool
    {
        return Str::startsWith(Str::lower(trim((string) $notes)), 'physical');
    }

    public static function isOnlineAudio(?string $notes): bool
    {
        return Str::startsWith(Str::lower(trim((string) $notes)), 'online audio');
    }

    /**
     * The attribute updates that put an appointment into anonymous form: anonymous online
     * sessions are audio-only, so the format has to follow the flag. In-person appointments
     * keep their notes (there is no call to downgrade).
     */
    public static function anonymousFormatUpdates(Appointment $appointment): array
    {
        if (self::isPhysical($appointment->notes)) {
            return [];
        }

        $updates = ['notes' => self::NOTES_ONLINE_AUDIO];
        if (self::supportsCallTypeColumn()) {
            $updates['call_type'] = 'audio';
        }

        return $updates;
    }

    /**
     * The inverse: restore an online appointment to video once it is no longer anonymous.
     *
     * An appointment the student deliberately booked as audio-only should stay audio-only, so
     * only rows that were forced to audio *by anonymity* are restored. Anonymous rows are
     * always `Online audio`, which makes them indistinguishable from a deliberate audio
     * booking on their own — `$wasForcedByAnonymity` is the caller's assertion that this row
     * is being taken out of anonymous mode right now.
     */
    public static function identifiedFormatUpdates(
        Appointment $appointment,
        bool $wasForcedByAnonymity
    ): array {
        if (! $wasForcedByAnonymity) {
            return [];
        }

        if (self::isPhysical($appointment->notes)) {
            return [];
        }

        if (! self::isOnlineAudio($appointment->notes)) {
            return [];
        }

        $updates = ['notes' => self::NOTES_ONLINE_VIDEO];
        if (self::supportsCallTypeColumn()) {
            $updates['call_type'] = 'video';
        }

        return $updates;
    }

    public static function supportsCallTypeColumn(): bool
    {
        static $hasCallTypeColumn = null;
        if ($hasCallTypeColumn !== null) {
            return $hasCallTypeColumn;
        }

        try {
            $hasCallTypeColumn = Schema::hasColumn('appointments', 'call_type');
        } catch (\Throwable) {
            $hasCallTypeColumn = false;
        }

        return $hasCallTypeColumn;
    }
}
