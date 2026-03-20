<?php

namespace App\Http\Controllers;

use App\Models\StudentMoodLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentMoodController extends Controller
{
    private const ALLOWED_MOODS = ['great', 'okay', 'low', 'stressed', 'tired'];

    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('student')) {
            return response()->json(['message' => 'Only students can view mood check-ins'], 403);
        }

        $today = now()->toDateString();
        $log = StudentMoodLog::query()
            ->where('student_id', $user->id)
            ->where('logged_on', $today)
            ->first();

        return response()->json([
            'logged_on' => $today,
            'log' => $log,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('student')) {
            return response()->json(['message' => 'Only students can create mood check-ins'], 403);
        }

        $validated = $request->validate([
            'mood' => ['required', 'string', Rule::in(self::ALLOWED_MOODS)],
        ]);

        $today = now()->toDateString();
        $existing = StudentMoodLog::query()
            ->where('student_id', $user->id)
            ->where('logged_on', $today)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Mood already recorded today. Only the first selection is kept.',
                'already_recorded' => true,
                'log' => $existing,
            ]);
        }

        $log = StudentMoodLog::create([
            'student_id' => $user->id,
            'mood' => $validated['mood'],
            'logged_on' => $today,
        ]);

        return response()->json([
            'message' => 'Mood recorded.',
            'already_recorded' => false,
            'log' => $log,
        ], 201);
    }
}

