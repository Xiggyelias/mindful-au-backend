<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'id_number' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|email:rfc|max:255',
            'avatar_url' => 'sometimes|nullable|url|max:255',
            'anonymous_mode' => 'sometimes|boolean',
            'peer_available' => 'sometimes|boolean',
        ]);

        $profileData = collect($validated)
            ->except(['email'])
            ->toArray();

        if (
            array_key_exists('peer_available', $profileData)
            && !$user->hasRole('peer_counselor')
            && !$user->hasRole('admin')
        ) {
            unset($profileData['peer_available']);
        }

        if (
            array_key_exists('anonymous_mode', $profileData)
            && !$user->hasRole('student')
            && !$user->hasRole('admin')
        ) {
            unset($profileData['anonymous_mode']);
        }

        if (!empty($profileData)) {
            $oldMode = (bool) $profile->anonymous_mode;
            $profile->update($profileData);
            $newMode = (bool) $profile->anonymous_mode;

            if ($oldMode !== $newMode && $user->hasRole('student')) {
                $this->syncStudentAnonymityFromProfile($user, $newMode);
            }
        }

        if (array_key_exists('email', $validated)) {
            $normalizedEmail = Str::lower(trim((string) $validated['email']));

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

        if (!$profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        return response()->json($profile->load('user'));
    }

    /**
     * Keep profile anonymous_mode, upcoming appointments, and open sessions aligned
     * so counselors see the correct name when a student switches before a video call.
     */
    private function syncStudentAnonymityFromProfile(User $user, bool $anonymousMode): void
    {
        $graceStart = now()->subMinutes(15);

        $appointments = Appointment::query()
            ->where('student_id', $user->id)
            ->whereIn('status', ['scheduled', 'confirmed', 'pending'])
            ->where('scheduled_at', '>=', $graceStart)
            ->get();

        foreach ($appointments as $appointment) {
            $notes = strtolower(trim((string) ($appointment->notes ?? '')));
            $isPhysical = str_starts_with($notes, 'physical');

            if ($anonymousMode) {
                $updates = [
                    'is_anonymous' => true,
                ];
                if ($appointment->anonymous_id === null || trim((string) $appointment->anonymous_id) === '') {
                    $updates['anonymous_id'] = $this->generateAnonymousIdForAppointment();
                }
                if (! $isPhysical) {
                    $updates['notes'] = 'Online audio';
                    if ($this->appointmentSupportsCallTypeColumn()) {
                        $updates['call_type'] = 'audio';
                    }
                }
                $appointment->update($updates);
            } else {
                $appointment->update([
                    'is_anonymous' => false,
                    'anonymous_id' => null,
                ]);
            }
        }

        $sessions = CounselingSession::query()
            ->where('student_id', $user->id)
            ->whereIn('status', ['pending', 'active'])
            ->get();

        foreach ($sessions as $session) {
            if ($anonymousMode) {
                $session->is_anonymous = true;
                if ($session->anonymous_id === null || trim((string) $session->anonymous_id) === '') {
                    $session->anonymous_id = CounselingSession::generateUniqueAnonymousId();
                }
            } else {
                $session->is_anonymous = false;
                $session->anonymous_id = null;
                $session->identity_revealed_at = now();
                $session->identity_revealed_by = $user->id;
            }
            $session->save();
        }
    }

    private function generateAnonymousIdForAppointment(): string
    {
        do {
            $candidate = 'User_' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Appointment::query()->where('anonymous_id', $candidate)->exists());

        return $candidate;
    }

    private function appointmentSupportsCallTypeColumn(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasColumn('appointments', 'call_type');
    }
}

