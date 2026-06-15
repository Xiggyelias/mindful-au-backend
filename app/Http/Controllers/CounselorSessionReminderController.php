<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CounselorSessionReminderController extends Controller
{
    public function __construct(
        private readonly WebPushService $webPush,
    ) {}

    /**
     * Appointments starting within the next 10 minutes (exclusive of past starts),
     * counselor-scoped, reminder not yet sent.
     *
     * Matches: session_time > NOW() AND session_time <= NOW() + 10 minutes
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('counselor')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $now = now();

        $payload = DB::transaction(function () use ($user, $now) {
            $query = Appointment::query()
                ->where('counselor_id', $user->id)
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->where('reminder_sent', false)
                ->where('scheduled_at', '>', $now)
                ->where('scheduled_at', '<=', $now->copy()->addMinutes(10))
                ->orderBy('scheduled_at')
                ->lockForUpdate();

            $rows = $query->with(['student.profile'])->get();
            $ids = $rows->pluck('id')->all();

            if ($ids !== []) {
                Appointment::query()->whereIn('id', $ids)->update(['reminder_sent' => true]);
            }

            $data = $rows->map(function (Appointment $apt): array {
                $isAnonymous = (bool) $apt->is_anonymous;
                $student = $apt->student;
                $profile = $student?->profile;
                $fullName = trim((string) (optional($profile)->full_name ?? ''));

                $studentName = $isAnonymous
                    ? 'Anonymous User'
                    : (
                        $fullName !== ''
                        ? $fullName
                        : (
                            $student?->email
                            ? strstr((string) $student->email, '@', true) ?: (string) $student->email
                            : 'Student'
                        )
                    );

                $scheduled = $apt->scheduled_at instanceof Carbon
                    ? $apt->scheduled_at
                    : Carbon::parse((string) $apt->scheduled_at);

                return [
                    'appointment_id' => (int) $apt->id,
                    'student_name' => $studentName,
                    'is_anonymous' => $isAnonymous,
                    'scheduled_at' => $scheduled->toIso8601String(),
                    'duration_minutes' => (int) ($apt->duration_minutes ?? 60),
                    'status' => (string) $apt->status,
                ];
            })->values()->all();

            return $data;
        });

        foreach ($payload as $row) {
            $this->webPush->sendToUser(
                (int) $user->id,
                'Session starts in 10 minutes',
                sprintf(
                    'Your session with %s is coming up.',
                    (string) ($row['student_name'] ?? 'your student')
                ),
                '/counselor/video',
                [
                    'tag' => 'cms-reminder-'.(int) ($row['appointment_id'] ?? 0),
                    'urgency' => 'normal',
                ]
            );
        }

        return response()->json(['data' => $payload]);
    }
}
