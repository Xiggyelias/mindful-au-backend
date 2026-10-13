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

            if ($user->hasRole('student') && (array_key_exists('anonymous_mode', $profileData) || $oldMode !== $newMode)) {
                $this->syncStudentAppointmentAnonymityFromProfile($user, $newMode, $oldMode);
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
     * Keep upcoming and currently-open appointments aligned with the student's
     * profile default when they toggle anonymous_mode.
     */
    private function syncStudentAppointmentAnonymityFromProfile(
        User $user,
        bool $anonymousMode,
        bool $oldMode = false
    ): void {
        if (! $anonymousMode) {
            // Turning off anonymous mode: de-anonymize upcoming/active appointments and restore
            // video format for any row that was forced to audio by anonymity.
            // Emergency appointments are always identified and should never be altered here.
            $appointments = Appointment::query()
                ->where('student_id', $user->id)
                ->whereIn('status', ['scheduled', 'confirmed', 'pending'])
                ->where(function ($q) {
                    $q->whereNull('is_emergency')->orWhere('is_emergency', false);
                })
                ->get();

            foreach ($appointments as $appointment) {
                $wasForcedByAnonymity = (bool) $appointment->is_anonymous
                    || $oldMode
                    || $appointment->anonymous_id !== null;

                $appointment->update(array_merge(
                    [
                        'is_anonymous' => false,
                        'anonymous_id' => null,
                    ],
                    AppointmentCallFormat::identifiedFormatUpdates(
                        $appointment,
                        $wasForcedByAnonymity
                    )
                ));
            }

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
            ->where(function ($q) {
                $q->whereNull('is_emergency')->orWhere('is_emergency', false);
            })
            ->get();

        foreach ($appointments as $appointment) {
            if (! AppointmentCallWindow::isOpen($appointment)) {
                continue;
            }

            $updates = ['is_anonymous' => true];
            if ($appointment->anonymous_id === null || trim((string) $appointment->anonymous_id) === '') {
                $updates['anonymous_id'] = $this->generateAnonymousIdForAppointment();
            }
            $appointment->update(array_merge(
                $updates,
                AppointmentCallFormat::anonymousFormatUpdates($appointment)
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
