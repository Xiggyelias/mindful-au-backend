<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\Message;
use App\Models\User;
use App\Support\AppointmentCallFormat;
use App\Support\AppointmentCallWindow;
use App\Support\SafeEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if (! $profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'id_number' => 'sometimes|nullable|string|max:255',
            'email' => SafeEmail::sometimes(),
            'avatar_url' => 'sometimes|nullable|url|max:255',
            'anonymous_mode' => 'sometimes|boolean',
            'peer_available' => 'sometimes|boolean',
        ]);

        $profileData = collect($validated)
            ->except(['email'])
            ->toArray();

        if (
            array_key_exists('peer_available', $profileData)
            && ! $user->hasRole('peer_counselor')
            && ! $user->hasRole('admin')
        ) {
            unset($profileData['peer_available']);
        }

        if (
            array_key_exists('anonymous_mode', $profileData)
            && ! $user->hasRole('student')
            && ! $user->hasRole('admin')
        ) {
            unset($profileData['anonymous_mode']);
        }

        if (! empty($profileData)) {
            $oldMode = (bool) $profile->anonymous_mode;
            $profile->update($profileData);
            $newMode = (bool) $profile->anonymous_mode;

            // Always sync when anonymous_mode is submitted or whenever mode is false,
            // ensuring any stuck sessions or appointments are decisively cleaned up.
            if ($user->hasRole('student') && (array_key_exists('anonymous_mode', $profileData) || ! $newMode || $oldMode !== $newMode)) {
                $this->syncStudentAppointmentAnonymityFromProfile($user, $newMode);
            }
        }

        if (array_key_exists('email', $validated)) {
            $normalizedEmail = SafeEmail::normalize($validated['email']);

            $emailInUse = User::query()
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->where('id', '!=', $user->id)
                ->exists();

            if ($emailInUse) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'email' => ['The email has already been taken.'],
                    ],
                ], 422);
            }

            if ($normalizedEmail !== Str::lower((string) $user->email)) {
                $user->email = $normalizedEmail;
                $user->save();
            }
        }

        return response()->json($profile->load('user'));
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if (! $profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        return response()->json($profile->load('user'));
    }

    /**
     * Keep ALL upcoming appointments AND open chat sessions aligned with the
     * student's profile default when they toggle anonymous_mode.
     *
     * WHY CHAT SESSIONS MUST ALSO BE SYNCED:
     * The chat session has its own `is_anonymous` flag. Messages are stamped with
     * `sent_as_anonymous = (bool) $session->is_anonymous` at send time.
     * If the student turns off anonymous mode on their profile but the chat session
     * still has `is_anonymous=true`, every subsequent message they send is STILL
     * stamped as anonymous and appears as "Anonymous User" to the counselor.
     * This created an infinite loop where new messages always showed anonymous
     * even after the student had toggled the setting off.
     *
     * WHAT WE DO:
     * - turning OFF anonymous → clear `is_anonymous` + `anonymous_id` on all open
     *   chat sessions (pending/active) so future messages are identified.
     * - turning ON anonymous → set `is_anonymous=true` + generate `anonymous_id`
     *   on all open chat sessions so future messages are anonymous.
     *
     * NOTE: Old messages keep their stored `sent_as_anonymous` snapshot. We do not
     * rewrite history — only new messages sent AFTER the toggle will reflect the
     * new mode. This mirrors WhatsApp's approach where message context is frozen
     * at send time but future messages use the current setting.
     */
    private function syncStudentAppointmentAnonymityFromProfile(User $user, bool $anonymousMode): void
    {
        if (! $anonymousMode) {
            // Turning off anonymous mode: clean up ALL appointments of this student so
            // none remain stuck as "Anonymous User" anywhere across the system.
            $appointments = Appointment::query()
                ->where('student_id', $user->id)
                ->get();

            foreach ($appointments as $appointment) {
                $appointment->update(array_merge(
                    [
                        'is_anonymous' => false,
                        'anonymous_id' => null,
                    ],
                    AppointmentCallFormat::identifiedFormatUpdates(
                        $appointment,
                        (bool) $appointment->is_anonymous
                    )
                ));
            }

            // Clean up ALL sessions for this student
            CounselingSession::query()
                ->where('student_id', $user->id)
                ->update([
                    'is_anonymous'         => false,
                    'anonymous_id'         => null,
                    'identity_revealed_at' => null,
                    'identity_revealed_by' => null,
                ]);

            // Unmask all messages sent by this student
            Message::query()
                ->where('sender_id', $user->id)
                ->where('sent_as_anonymous', true)
                ->update(['sent_as_anonymous' => false]);

            return;
        }

        // ── Turning ON anonymous mode ─────────────────────────────────────────
        $earliestPossibleStart = now()->subMinutes(
            AppointmentCallWindow::MAX_DURATION_MINUTES + AppointmentCallWindow::JOIN_LATE_GRACE_MINUTES
        );

        $appointments = Appointment::query()
            ->where('student_id', $user->id)
            ->whereIn('status', ['scheduled', 'confirmed', 'pending'])
            ->where('scheduled_at', '>=', $earliestPossibleStart)
            ->get();

        foreach ($appointments as $appointment) {
            $updates = ['is_anonymous' => true];
            if ($appointment->anonymous_id === null || trim((string) $appointment->anonymous_id) === '') {
                $updates['anonymous_id'] = $this->generateAnonymousIdForAppointment();
            }
            $appointment->update(array_merge(
                $updates,
                AppointmentCallFormat::anonymousFormatUpdates($appointment)
            ));
        }

        $chatSessions = CounselingSession::query()
            ->where('student_id', $user->id)
            ->where('session_type', 'chat')
            ->whereIn('status', ['pending', 'active'])
            ->get(['id', 'is_anonymous', 'anonymous_id']);

        foreach ($chatSessions as $chatSession) {
            $anonId = trim((string) ($chatSession->anonymous_id ?? '')) !== ''
                ? $chatSession->anonymous_id
                : CounselingSession::generateUniqueAnonymousId();

            CounselingSession::query()
                ->whereKey((int) $chatSession->id)
                ->update([
                    'is_anonymous'         => true,
                    'anonymous_id'         => $anonId,
                    'identity_revealed_at' => null,
                    'identity_revealed_by' => null,
                ]);
        }
    }

    private function generateAnonymousIdForAppointment(): string
    {
        do {
            $candidate = 'User_'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Appointment::query()->where('anonymous_id', $candidate)->exists());

        return $candidate;
    }
}
