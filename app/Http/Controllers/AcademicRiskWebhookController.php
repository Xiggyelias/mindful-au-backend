<?php

namespace App\Http\Controllers;

use App\Models\AcademicRiskEvent;
use App\Models\Notification;
use App\Models\SyncRun;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcademicRiskWebhookController extends Controller
{
    public function ingest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'events' => 'required|array|min:1|max:500',
            'events.*.event_id' => 'sometimes|nullable|string|max:120',
            'events.*.student_identifier' => 'required|string|max:120',
            'events.*.registration_number' => 'sometimes|nullable|string|max:120',
            'events.*.faculty' => 'sometimes|nullable|string|max:120',
            'events.*.year_of_study' => 'sometimes|nullable|string|max:40',
            'events.*.enrolment_status' => 'sometimes|nullable|string|max:60',
            'events.*.risk_type' => 'required|string|max:60',
            'events.*.risk_score' => 'sometimes|nullable|numeric|min:0|max:100',
            'events.*.metadata' => 'sometimes|nullable|array',
        ]);

        $events = $validated['events'];
        $run = SyncRun::query()->create([
            'source' => 'academic_risk_webhook',
            'run_type' => 'ingest',
            'status' => 'processing',
            'received_count' => count($events),
            'processed_count' => 0,
            'failed_count' => 0,
            'started_at' => now(),
            'metadata' => [
                'ip_address' => $request->ip(),
            ],
        ]);

        $processed = 0;
        $failed = 0;

        foreach ($events as $eventPayload) {
            try {
                DB::transaction(function () use ($eventPayload, $run, &$processed) {
                    $registration = $this->cleanIdentifier($eventPayload['registration_number'] ?? null);
                    $riskType = $this->normalizeRiskType((string) $eventPayload['risk_type']);
                    $eventId = trim((string) ($eventPayload['event_id'] ?? ''));
                    $baseData = [
                        'sync_run_id' => $run->id,
                        'student_identifier' => (string) $eventPayload['student_identifier'],
                        'registration_number' => $registration,
                        'faculty' => $eventPayload['faculty'] ?? null,
                        'year_of_study' => $eventPayload['year_of_study'] ?? null,
                        'enrolment_status' => $eventPayload['enrolment_status'] ?? null,
                        'risk_type' => $riskType,
                        'risk_score' => $eventPayload['risk_score'] ?? null,
                        'status' => 'new',
                        'received_at' => now(),
                        'processed_at' => now(),
                        'payload' => $eventPayload,
                    ];

                    if ($eventId !== '') {
                        $event = AcademicRiskEvent::query()->updateOrCreate(
                            ['external_event_id' => $eventId],
                            $baseData
                        );
                    } else {
                        $event = AcademicRiskEvent::query()->create(
                            array_merge($baseData, ['external_event_id' => null])
                        );
                    }

                    $linkedUser = $this->resolveLinkedStudent($registration, (string) $eventPayload['student_identifier']);
                    if ($linkedUser) {
                        $event->update([
                            'linked_user_id' => $linkedUser->id,
                            'status' => 'linked',
                        ]);
                        $this->notifyLinkedStudent($linkedUser, $riskType);
                        $event->update(['status' => 'notified']);
                    }

                    $processed++;
                });
            } catch (\Throwable) {
                $failed++;
            }
        }

        $run->update([
            'status' => $failed > 0 ? ($processed > 0 ? 'partial' : 'failed') : 'success',
            'processed_count' => $processed,
            'failed_count' => $failed,
            'finished_at' => now(),
        ]);

        return response()->json([
            'message' => 'Academic risk sync processed.',
            'run_id' => $run->id,
            'received' => count($events),
            'processed' => $processed,
            'failed' => $failed,
        ], 202);
    }

    public function events(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate([
            'status' => 'sometimes|string|max:20',
            'risk_type' => 'sometimes|string|max:60',
            'limit' => 'sometimes|integer|min:1|max:500',
        ]);

        $query = AcademicRiskEvent::query()
            ->with(['linkedUser.profile', 'syncRun'])
            ->orderByDesc('created_at');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['risk_type'])) {
            $query->where('risk_type', $this->normalizeRiskType((string) $validated['risk_type']));
        }

        $limit = (int) ($validated['limit'] ?? 100);

        return response()->json($query->limit($limit)->get());
    }

    public function runs(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min(200, $limit));

        return response()->json(
            SyncRun::query()
                ->where('source', 'academic_risk_webhook')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
        );
    }

    private function cleanIdentifier(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);

        return $clean !== '' ? $clean : null;
    }

    private function normalizeRiskType(string $riskType): string
    {
        $normalized = Str::of($riskType)->lower()->replace([' ', '-'], '_')->value();

        return match ($normalized) {
            'failed_courses', 'failed_units', 'course_failure' => 'failed_courses',
            'probation', 'academic_probation' => 'probation',
            'performance_drop', 'declining_performance' => 'performance_drop',
            'attendance_drop', 'attendance_risk' => 'attendance_drop',
            default => 'other',
        };
    }

    private function resolveLinkedStudent(?string $registrationNumber, string $studentIdentifier): ?User
    {
        if ($registrationNumber) {
            $userByReg = User::query()
                ->whereHas('profile', function ($query) use ($registrationNumber) {
                    $query->where('id_number', $registrationNumber);
                })
                ->first();
            if ($userByReg) {
                return $userByReg;
            }
        }

        if (Str::contains($studentIdentifier, '@')) {
            return User::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower($studentIdentifier)])
                ->first();
        }

        return null;
    }

    private function notifyLinkedStudent(User $student, string $riskType): void
    {
        Notification::query()->create([
            'user_id' => $student->id,
            'title' => 'Academic Wellness Support Available',
            'message' => 'A gentle support referral was generated based on academic wellbeing indicators. You can book a counseling session anytime.',
            'type' => 'info',
        ]);

        if (! SystemSettings::getBool('ai_risk_alerts', true)) {
            return;
        }

        $counselorIds = User::query()
            ->whereHas('roles', function ($query) {
                $query->whereIn('role', ['admin', 'counselor'])->where('approved', true);
            })
            ->pluck('id')
            ->unique()
            ->values();

        foreach ($counselorIds as $recipientId) {
            Notification::query()->create([
                'user_id' => (int) $recipientId,
                'title' => 'Academic Risk Trigger Received',
                'message' => "An academic risk event ({$riskType}) has been synced and linked for follow-up support.",
                'type' => 'warning',
            ]);
        }
    }
}
