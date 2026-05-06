<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Support\PaginationPayload;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'status' => 'nullable|in:pending,scheduled,confirmed,completed,cancelled',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1|max:100000',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $query = Appointment::query()
            ->with(['student.profile', 'counselor.profile']);

        if ($user->hasRole('student')) {
            $query->where('student_id', $user->id);
        } elseif ($user->hasRole('counselor')) {
            $query->where('counselor_id', $user->id);
        } elseif (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['from'])) {
            $query->where('scheduled_at', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            $query->where('scheduled_at', '<=', $validated['to']);
        }

        $query->orderByDesc('scheduled_at')->orderByDesc('id');

        $limit = array_key_exists('limit', $validated)
            ? max(1, min(500, (int) $validated['limit']))
            : null;
        $usePagination = array_key_exists('page', $validated) || array_key_exists('per_page', $validated);
        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($validated['per_page'] ?? ($limit ?? 50))));

        if ($usePagination) {
            $paginator = $query
                ->paginate($perPage, ['*'], 'page', $page)
                ->appends($request->query());
            $paginator->getCollection()->transform(function (Appointment $appointment) use ($user) {
                $this->applyAnonymousAppointmentProjection($appointment, $user);
                return $appointment;
            });

            return response()->json(
                PaginationPayload::fromPaginator($paginator, $request, ['status', 'from', 'to'])
            );
        }

        if ($limit !== null) {
            $query->limit($limit);
        }
        $appointments = $query->get();
        $appointments->transform(function (Appointment $appointment) use ($user) {
            $this->applyAnonymousAppointmentProjection($appointment, $user);
            return $appointment;
        });

        return response()->json($appointments);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('student')) {
            return response()->json(['message' => 'Only students can create appointments'], 403);
        }

        $validated = $request->validate([
            'counselor_id' => 'required|exists:users,id',
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'sometimes|integer|min:15|max:120',
            'notes' => 'sometimes|nullable|string|max:2000',
            'is_anonymous' => 'sometimes|boolean',
            'call_type' => 'sometimes|in:audio,video',
        ]);

        if (!$this->isApprovedCounselor((int) $validated['counselor_id'])) {
            return response()->json(['message' => 'Selected counselor is not available'], 422);
        }

        if ((int) $validated['counselor_id'] === (int) $request->user()->id) {
            return response()->json(['message' => 'You cannot book an appointment with your own account'], 422);
        }

        $durationMinutes = (int) ($validated['duration_minutes'] ?? 60);
        $proposedStart = Carbon::parse($validated['scheduled_at']);
        $proposedEnd = (clone $proposedStart)->addMinutes($durationMinutes);
        $studentId = (int) $request->user()->id;
        $counselorId = (int) $validated['counselor_id'];
        $isAnonymous = array_key_exists('is_anonymous', $validated)
            ? (bool) $validated['is_anonymous']
            : (bool) ($request->user()->profile?->anonymous_mode ?? false);

        $notesRaw = trim((string) ($validated['notes'] ?? ''));
        $isPhysical = str_starts_with(strtolower($notesRaw), 'physical');

        if ($isAnonymous && !$isPhysical) {
            $finalNotes = 'Online audio';
            $callType = 'audio';
        } elseif ($isPhysical) {
            $finalNotes = $notesRaw !== '' ? $notesRaw : 'Physical';
            $callType = 'video';
        } else {
            $callType = $validated['call_type'] ?? $this->inferCallTypeFromNotes($notesRaw);
            if (!in_array($callType, ['audio', 'video'], true)) {
                $callType = 'video';
            }
            $finalNotes = $callType === 'audio' ? 'Online audio' : 'Online';
        }

        $appointment = $this->withBookingLocks(
            $studentId,
            $counselorId,
            function () use (
                $validated,
                $durationMinutes,
                $proposedStart,
                $proposedEnd,
                $studentId,
                $counselorId,
                $isAnonymous,
                $finalNotes,
                $callType
            ) {
                return DB::transaction(function () use (
                    $validated,
                    $durationMinutes,
                    $proposedStart,
                    $proposedEnd,
                    $studentId,
                    $counselorId,
                    $isAnonymous,
                    $finalNotes,
                    $callType
                ) {
                    // Keep row locks to ensure overlap checks are consistent for rows already present.
                    $candidateAppointments = Appointment::query()
                        ->whereIn('status', ['scheduled', 'confirmed'])
                        ->where(function ($query) use ($studentId, $counselorId) {
                            $query->where('student_id', $studentId)
                                ->orWhere('counselor_id', $counselorId);
                        });
                    $this->applyOverlapConstraint($candidateAppointments, $proposedStart, $proposedEnd);
                    $candidateAppointments = $candidateAppointments
                        ->lockForUpdate()
                        ->get(['id', 'student_id', 'counselor_id', 'scheduled_at', 'duration_minutes']);

                    foreach ($candidateAppointments as $candidate) {
                        $candidateStart = Carbon::parse($candidate->scheduled_at);
                        $candidateEnd = (clone $candidateStart)->addMinutes((int) $candidate->duration_minutes);

                        $overlaps = $candidateStart->lt($proposedEnd) && $candidateEnd->gt($proposedStart);
                        if (!$overlaps) {
                            continue;
                        }

                        $isCounselorConflict = (int) $candidate->counselor_id === $counselorId;
                        if ($isCounselorConflict) {
                            throw ValidationException::withMessages([
                                'scheduled_at' => ['Selected counselor is unavailable for that time slot.'],
                            ]);
                        }

                        $isStudentConflict = (int) $candidate->student_id === $studentId;
                        if ($isStudentConflict) {
                            throw ValidationException::withMessages([
                                'scheduled_at' => ['You already have an overlapping appointment.'],
                            ]);
                        }
                    }

                    return Appointment::query()->create([
                        'student_id' => $studentId,
                        'counselor_id' => $counselorId,
                        'is_anonymous' => $isAnonymous,
                        'anonymous_id' => $isAnonymous ? $this->generateAnonymousId() : null,
                        'scheduled_at' => $proposedStart,
                        'duration_minutes' => $durationMinutes,
                        'notes' => $finalNotes,
                        'call_type' => $callType,
                        'status' => 'scheduled',
                    ]);
                });
            }
        );

        $appointment->load(['student.profile', 'counselor.profile']);
        $this->notifyCounselorOnAppointmentCreated($appointment);
        $this->flushDashboardCaches();
        $this->applyAnonymousAppointmentProjection($appointment, $request->user());

        return response()->json($appointment, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        $user = $request->user();

        if (!$user->hasRole('admin') && (int) $appointment->counselor_id !== (int) $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:scheduled,confirmed,completed,cancelled',
            'scheduled_at' => 'sometimes|date|after:now',
            'notes' => 'sometimes|string|max:2000',
        ]);

        if (isset($validated['notes']) && $appointment->is_anonymous) {
            $trim = strtolower(trim($validated['notes']));
            if (!str_starts_with($trim, 'physical') && $trim === 'online') {
                throw ValidationException::withMessages([
                    'notes' => ['Anonymous online appointments are audio-only.'],
                ]);
            }
        }

        $payload = $validated;
        if ($appointment->is_anonymous && isset($validated['notes'])) {
            $trim = strtolower(trim($validated['notes']));
            if (!str_starts_with($trim, 'physical')) {
                $payload['call_type'] = 'audio';
            }
        }

        $previousStatus = $appointment->status;
        $previousScheduledAt = $appointment->scheduled_at?->toISOString();

        $appointment->update($payload);
        $appointment->refresh()->load(['student.profile', 'counselor.profile']);

        if (isset($validated['status']) && $validated['status'] !== $previousStatus) {
            $this->notifyStudentOnAppointmentStatusChanged($appointment, $validated['status']);
        }

        if (isset($validated['scheduled_at'])) {
            $nextScheduledAt = Carbon::parse($validated['scheduled_at'])->toISOString();
            if ($nextScheduledAt !== $previousScheduledAt) {
                $this->notifyStudentOnAppointmentRescheduled($appointment);
            }
        }

        $this->flushDashboardCaches();
        $this->applyAnonymousAppointmentProjection($appointment, $user);

        return response()->json($appointment);
    }

    /**
     * Counselor: cancel many appointments in one action (pending / scheduled / confirmed only).
     * scope=all: every cancellable row for this counselor.
     * scope=remaining: only those with scheduled_at in the future.
     */
    public function bulkCancel(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('counselor')) {
            return response()->json(['message' => 'Only counselors can bulk-cancel appointments.'], 403);
        }

        $validated = $request->validate([
            'scope' => 'required|in:all,remaining',
            'reason' => 'sometimes|nullable|string|max:1000',
        ]);

        $counselorId = (int) $user->id;
        $reason = isset($validated['reason']) ? trim((string) $validated['reason']) : '';
        $reasonStored = $reason !== '' ? $reason : null;
        $now = now();

        $result = DB::transaction(function () use ($validated, $counselorId, $now, $reasonStored, $user, $request) {
            $query = Appointment::query()
                ->where('counselor_id', $counselorId)
                ->whereIn('status', ['pending', 'scheduled', 'confirmed']);

            if ($validated['scope'] === 'remaining') {
                $query->where('scheduled_at', '>', $now);
            }

            $ids = $query->lockForUpdate()->pluck('id');

            if ($ids->isEmpty()) {
                return ['cancelled_count' => 0, 'appointment_ids' => []];
            }

            Appointment::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $reasonStored,
                    'cancelled_at' => $now,
                ]);

            $affected = Appointment::query()
                ->whereIn('id', $ids)
                ->with(['student', 'counselor.profile'])
                ->get();

            foreach ($affected as $appointment) {
                $this->notifyStudentOnCounselorCancelledAppointment($appointment, $reasonStored);
            }

            ActivityLog::query()->create([
                'user_id' => $user->id,
                'action' => 'appointments_bulk_cancel',
                'description' => sprintf(
                    'Counselor bulk-cancelled %d appointment(s) (scope: %s).',
                    $ids->count(),
                    $validated['scope']
                ),
                'type' => 'audit',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'scope' => $validated['scope'],
                    'cancelled_count' => $ids->count(),
                    'appointment_ids' => $ids->take(200)->values()->all(),
                    'reason' => $reasonStored,
                ],
            ]);

            return [
                'cancelled_count' => $ids->count(),
                'appointment_ids' => $ids->values()->all(),
            ];
        });

        $this->flushDashboardCaches();

        $count = (int) ($result['cancelled_count'] ?? 0);

        return response()->json([
            'message' => $count === 0
                ? 'No matching appointments to cancel.'
                : 'Sessions successfully cancelled.',
            'cancelled_count' => $count,
            'appointment_ids' => $result['appointment_ids'] ?? [],
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        $user = $request->user();
        $isAdmin = $user->hasRole('admin');
        $isStudentOwner = (int) $appointment->student_id === (int) $user->id;

        if (!$isAdmin && !$isStudentOwner) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $appointment->load(['student.profile', 'counselor.profile']);

        if (!$isAdmin && $isStudentOwner) {
            $validated = $request->validate([
                'reason' => 'required|string|min:5|max:1000',
            ]);

            if (in_array($appointment->status, ['completed', 'cancelled'], true)) {
                return response()->json([
                    'message' => 'This appointment can no longer be cancelled.',
                ], 422);
            }

            $reason = trim($validated['reason']);

            $appointment->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
            ]);

            $appointment->refresh()->load(['student.profile', 'counselor.profile']);
            $this->notifyCounselorOnAppointmentCancelled($appointment, (int) $user->id, $reason);
            $this->flushDashboardCaches();

            return response()->json([
                'message' => 'Appointment cancelled successfully.',
                'appointment' => $appointment,
            ]);
        }

        $this->notifyCounselorOnAppointmentCancelled($appointment, (int) $user->id);
        $appointment->delete();
        $this->flushDashboardCaches();

        return response()->json(['message' => 'Appointment deleted successfully']);
    }

    private function isApprovedCounselor(int $userId): bool
    {
        return User::query()
            ->where('id', $userId)
            ->whereHas('roles', function ($query) {
                $query->where('role', 'counselor')->where('approved', true);
            })
            ->exists();
    }

    private function withBookingLocks(int $studentId, int $counselorId, callable $callback): mixed
    {
        $ttlSeconds = max(5, (int) env('APPOINTMENT_BOOKING_LOCK_TTL_SECONDS', 15));
        $waitSeconds = max(1, min(10, (int) env('APPOINTMENT_BOOKING_LOCK_WAIT_SECONDS', 3)));

        $lockNames = [
            "appointments:participant:{$studentId}",
            "appointments:participant:{$counselorId}",
            "appointments:counselor:{$counselorId}",
        ];
        $lockNames = array_values(array_unique($lockNames));
        sort($lockNames);

        $locks = [];
        try {
            foreach ($lockNames as $lockName) {
                $lock = Cache::lock($lockName, $ttlSeconds);
                $lock->block($waitSeconds);
                $locks[] = $lock;
            }

            return $callback();
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['Another booking is being processed. Please retry in a moment.'],
            ]);
        } finally {
            foreach (array_reverse($locks) as $lock) {
                try {
                    $lock->release();
                } catch (\Throwable) {
                    // Ignore lock release issues; lock TTL guarantees eventual release.
                }
            }
        }
    }

    private function applyOverlapConstraint(Builder $query, Carbon $proposedStart, Carbon $proposedEnd): void
    {
        $query->where('scheduled_at', '<', $proposedEnd);

        $driver = DB::connection()->getDriverName();
        $startValue = $proposedStart->toDateTimeString();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $query->whereRaw(
                'DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?',
                [$startValue]
            );
            return;
        }

        if ($driver === 'pgsql') {
            $query->whereRaw(
                "(scheduled_at + make_interval(mins => duration_minutes)) > ?::timestamp",
                [$startValue]
            );
            return;
        }

        if ($driver === 'sqlite') {
            $query->whereRaw(
                "datetime(scheduled_at, '+' || duration_minutes || ' minutes') > datetime(?)",
                [$startValue]
            );
            return;
        }

        // Portable fallback when interval arithmetic support differs.
        $query->where('scheduled_at', '>=', (clone $proposedStart)->subDay());
    }

    private function notifyStudentOnCounselorCancelledAppointment(Appointment $appointment, ?string $reason): void
    {
        if (!$appointment->student_id) {
            return;
        }

        $message = 'Your session with the counselor has been cancelled.';

        if ($reason !== null && trim($reason) !== '') {
            $message .= ' Reason: ' . trim($reason);
        }

        Notification::create([
            'user_id' => $appointment->student_id,
            'title' => 'Session cancelled',
            'message' => $message,
            'type' => 'warning',
        ]);
    }

    private function notifyCounselorOnAppointmentCreated(Appointment $appointment): void
    {
        if (!$appointment->counselor_id) {
            return;
        }

        $studentName = $this->resolveAppointmentStudentLabel($appointment);

        Notification::create([
            'user_id' => $appointment->counselor_id,
            'title' => 'New appointment request',
            'message' => sprintf(
                '%s requested an appointment for %s.',
                $studentName,
                $this->formatAppointmentTime($appointment->scheduled_at)
            ),
            'type' => 'info',
        ]);
    }

    private function notifyStudentOnAppointmentStatusChanged(Appointment $appointment, string $newStatus): void
    {
        if (!$appointment->student_id) {
            return;
        }

        $counselorName = optional($appointment->counselor?->profile)->full_name
            ?: ($appointment->counselor?->email ? Str::before($appointment->counselor?->email, '@') : 'your counselor');

        $statusLabel = Str::headline($newStatus);
        $type = $newStatus === 'confirmed' || $newStatus === 'completed' ? 'success' : ($newStatus === 'cancelled' ? 'warning' : 'info');

        Notification::create([
            'user_id' => $appointment->student_id,
            'title' => "Appointment {$statusLabel}",
            'message' => sprintf(
                'Your appointment with %s is now %s.',
                $counselorName,
                strtolower($statusLabel)
            ),
            'type' => $type,
        ]);
    }

    private function notifyStudentOnAppointmentRescheduled(Appointment $appointment): void
    {
        if (!$appointment->student_id) {
            return;
        }

        Notification::create([
            'user_id' => $appointment->student_id,
            'title' => 'Appointment rescheduled',
            'message' => sprintf(
                'Your appointment has been moved to %s.',
                $this->formatAppointmentTime($appointment->scheduled_at)
            ),
            'type' => 'info',
        ]);
    }

    private function notifyCounselorOnAppointmentCancelled(
        Appointment $appointment,
        int $actorId,
        ?string $reason = null
    ): void
    {
        if (!$appointment->counselor_id || $appointment->counselor_id === $actorId) {
            return;
        }

        $studentName = $this->resolveAppointmentStudentLabel($appointment);
        $message = sprintf(
            '%s cancelled an appointment scheduled for %s.',
            $studentName,
            $this->formatAppointmentTime($appointment->scheduled_at)
        );

        if ($reason !== null && trim($reason) !== '') {
            $message .= sprintf(' Reason: %s', trim($reason));
        }

        Notification::create([
            'user_id' => $appointment->counselor_id,
            'title' => 'Appointment cancelled',
            'message' => $message,
            'type' => 'warning',
        ]);
    }

    private function formatAppointmentTime(mixed $scheduledAt): string
    {
        if (!$scheduledAt) {
            return 'a future date';
        }

        try {
            return Carbon::parse($scheduledAt)->format('M j, Y g:i A');
        } catch (\Throwable) {
            return 'a future date';
        }
    }

    private function flushDashboardCaches(): void
    {
        Cache::forget('analytics:admin:overview:v1');
        Cache::forget('analytics:dashboard:v2');
    }

    private function applyAnonymousAppointmentProjection(Appointment $appointment, User $viewer): void
    {
        if (!$appointment->is_anonymous) {
            $appointment->setAttribute('identity_visible_to_viewer', true);
            return;
        }

        $isStudentViewer = (int) $appointment->student_id === (int) $viewer->id;
        $isAdminViewer = $viewer->hasRole('admin');
        if ($isStudentViewer || $isAdminViewer) {
            $appointment->setAttribute('identity_visible_to_viewer', true);
            return;
        }

        $appointment->setAttribute('student_id', 0);
        $appointment->setAttribute('identity_visible_to_viewer', false);
        $appointment->setAttribute('identity_masked', true);
        // Never expose internal anonymous correlators (e.g. User_####) to counselors/peers over the API.
        $appointment->setAttribute('anonymous_id', null);

        if ($appointment->relationLoaded('student') && $appointment->student) {
            $appointment->student->setAttribute('id', 0);
            $appointment->student->email = null;
            $appointment->student->setAttribute('masked_for_viewer', true);
            if ($appointment->student->relationLoaded('profile') && $appointment->student->profile) {
                $appointment->student->profile->full_name = $this->resolveAppointmentStudentAlias($appointment);
                $appointment->student->profile->id_number = null;
                $appointment->student->profile->avatar_url = null;
            }
        }
    }

    private function resolveAppointmentStudentAlias(Appointment $_appointment): string
    {
        return 'Anonymous User';
    }

    private function inferCallTypeFromNotes(string $notes): string
    {
        $n = strtolower(trim($notes));
        if (str_starts_with($n, 'online audio')) {
            return 'audio';
        }

        return 'video';
    }

    private function resolveAppointmentStudentLabel(Appointment $appointment): string
    {
        if ($appointment->is_anonymous) {
            return $this->resolveAppointmentStudentAlias($appointment);
        }

        return optional($appointment->student?->profile)->full_name
            ?: ($appointment->student?->email ? Str::before($appointment->student?->email, '@') : 'A student');
    }

    private function generateAnonymousId(): string
    {
        do {
            $candidate = 'User_' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Appointment::query()->where('anonymous_id', $candidate)->exists());

        return $candidate;
    }
}








