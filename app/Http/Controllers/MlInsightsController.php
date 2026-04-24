<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\User;
use App\Services\MentalHealthMlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MlInsightsController extends Controller
{
    public function __construct(
        private readonly MentalHealthMlService $mentalHealthMlService
    ) {
    }

    public function counselorMatches(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('student') && !$user->hasRole('counselor') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'student_id' => 'nullable|integer|exists:users,id',
            'mode' => 'nullable|in:online,physical',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $studentId = $user->hasRole('student')
            ? (int) $user->id
            : (int) ($validated['student_id'] ?? 0);

        if ($studentId <= 0) {
            return response()->json(['message' => 'student_id is required'], 422);
        }

        if ($user->hasRole('counselor')) {
            $assignedBySession = CounselingSession::query()
                ->where('counselor_id', $user->id)
                ->where('student_id', $studentId)
                ->exists();

            $assignedByAppointment = Appointment::query()
                ->where('counselor_id', $user->id)
                ->where('student_id', $studentId)
                ->exists();

            if (!$assignedBySession && !$assignedByAppointment) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $student = User::findOrFail($studentId);
        if (!$student->hasRole('student')) {
            return response()->json(['message' => 'Target user is not a student'], 422);
        }

        $mode = (string) ($validated['mode'] ?? 'online');
        $limit = (int) ($validated['limit'] ?? 6);

        return response()->json([
            'student_id' => $studentId,
            'mode' => $mode,
            'model_version' => MentalHealthMlService::MODEL_VERSION,
            'matches' => $this->mentalHealthMlService->rankCounselorsForStudent($student, [
                'mode' => $mode,
                'limit' => $limit,
            ]),
        ]);
    }
}
