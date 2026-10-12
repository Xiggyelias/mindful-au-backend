<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
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

            if ($oldMode !== $newMode && $user->hasRole('student')) {
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
     * Keep ALL upcoming appointments aligned with the student's profile default.
     *
     * Chat sessions keep their own per-thread anonymity flag. A profile toggle
     * should affect future chats and upcoming bookings only; changing every open
     * chat would rewrite the student's current support context.
     *
     * IMPORTANT: We deliberately do NOT limit this to the currently-open call window.
     * The old code used AppointmentCallWindow::isOpen() as a gate, which meant that
     * any future appointment (e.g. booked for next week) was never de-anonymized when
     * the student turned off anonymous mode on their profile — causing the "stuck on
     * anonymous mode" bug where all upcoming sessions kept showing as "Anonymous User"
     * even after the profile toggle was saved.
     *
     * We sync every upcoming active appointment so that the student's identity choice
     * is reflected immediately everywhere, regardless of how far in the future the
     * appointment is scheduled.
     */
    private function syncStudentAppointmentAnonymityFromProfile(User $user, bool $anonymousMode): void
    {
        // Include any appointment that could still be upcoming or currently joinable.
        // The lower bound avoids touching very old records but is still generous enough
        // to catch sessions that started recently and are still in their grace window.
        $earliestPossibleStart = now()->subMinutes(
            AppointmentCallWindow::MAX_DURATION_MINUTES + AppointmentCallWindow::JOIN_LATE_GRACE_MINUTES
        );

        $appointments = Appointment::query()
            ->where('student_id', $user->id)
            ->whereIn('status', ['scheduled', 'confirmed', 'pending'])
            ->where('scheduled_at', '>=', $earliestPossibleStart)
            ->get();

        foreach ($appointments as $appointment) {
            if ($anonymousMode) {
                $updates = ['is_anonymous' => true];
                if ($appointment->anonymous_id === null || trim((string) $appointment->anonymous_id) === '') {
                    $updates['anonymous_id'] = $this->generateAnonymousIdForAppointment();
                }
                $appointment->update(array_merge(
                    $updates,
                    AppointmentCallFormat::anonymousFormatUpdates($appointment)
                ));

                continue;
            }

            // Turning off anonymous mode: clear the flag on ALL upcoming appointments,
            // not just the ones whose call window is currently open. This ensures that
            // a student who toggles "identified mode" sees their real name everywhere
            // immediately, not only for sessions happening right now.
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
    }

    private function generateAnonymousIdForAppointment(): string
    {
        do {
            $candidate = 'User_'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Appointment::query()->where('anonymous_id', $candidate)->exists());

        return $candidate;
    }
}
