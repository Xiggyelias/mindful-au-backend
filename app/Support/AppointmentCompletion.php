<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\CounselingSession;

/**
 * Whether a counseling session finishing should also finish its appointment.
 *
 * Shared by the hang-up endpoint and the `appointments:complete-past` sweep so the two cannot
 * reach different conclusions about the same pair of rows. Sessions are matched to
 * appointments by participants plus a `#id` reference in the session notes, because the
 * schema has no foreign key between them.
 */
class AppointmentCompletion
{
    public static function shouldComplete(
        Appointment $appointment,
        ?CounselingSession $session,
        bool $allowInferenceWithoutAppointmentNote = false
    ): bool {
        if (! $session) {
            return false;
        }

        if (in_array((string) $appointment->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        if ((string) $session->status !== 'completed') {
            return false;
        }

        if (! self::matchesParticipants($appointment, $session)) {
            return false;
        }

        if (! $session->started_at && ! $session->ended_at) {
            return false;
        }

        $sessionNotes = trim((string) ($session->notes ?? ''));

        if (self::notesReferenceAppointment($appointment, $sessionNotes)) {
            return true;
        }

        if (! $allowInferenceWithoutAppointmentNote) {
            return false;
        }

        if ($sessionNotes !== '' && self::notesReferToAnotherAppointmentId($appointment, $sessionNotes)) {
            return false;
        }

        return true;
    }

    public static function matchesParticipants(
        Appointment $appointment,
        CounselingSession $session
    ): bool {
        return (int) $session->student_id === (int) $appointment->student_id
            && (int) $session->counselor_id === (int) $appointment->counselor_id
            && (string) $session->session_type === 'video'
            && (bool) $session->is_anonymous === (bool) $appointment->is_anonymous;
    }

    public static function notesReferenceAppointment(Appointment $appointment, string $sessionNotes): bool
    {
        if ($sessionNotes === '') {
            return false;
        }

        $idPattern = preg_quote((string) (int) $appointment->id, '/');

        return (bool) preg_match('/video\s+appointment\s*#\s*'.$idPattern.'(?!\d)/iu', $sessionNotes)
            || (bool) preg_match('/appointment\s*#\s*'.$idPattern.'(?!\d)/iu', $sessionNotes);
    }

    public static function notesReferToAnotherAppointmentId(
        Appointment $appointment,
        string $sessionNotes
    ): bool {
        $myId = (int) $appointment->id;
        $pattern = '/(?:video\s+)?appointment\s*#\s*(\d+)(?!\d)/iu';

        if (! preg_match_all($pattern, $sessionNotes, $matches)) {
            return false;
        }

        foreach ($matches[1] as $digits) {
            if ((int) $digits !== $myId) {
                return true;
            }
        }

        return false;
    }
}
