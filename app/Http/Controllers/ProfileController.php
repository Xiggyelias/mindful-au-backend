<?php

namespace App\Http\Controllers;

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
                \App\Models\Appointment::query()
                    ->where('student_id', $user->id)
                    ->whereIn('status', ['scheduled', 'confirmed', 'pending'])
                    ->where('scheduled_at', '>=', now()->subMinutes(30))
                    ->update(['is_anonymous' => $newMode]);
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
}

