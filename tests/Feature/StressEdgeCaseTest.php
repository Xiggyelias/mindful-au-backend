<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\IntakeSubmission;
use App\Models\RiskAlert;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StressEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function rapid_overlapping_booking_attempts_do_not_create_duplicates(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $counselor = $this->createPortalUser('counselor', 'counselor-stress@test.com', 'Counselor Stress');
        $student = $this->createPortalUser('student', 'student-stress@test.com', 'Student Stress');
        $slot = now()->addDays(2)->setHour(11)->setMinute(0)->toIso8601String();

        $first = $this->actingAs($student)->postJson('/api/appointments', [
            'counselor_id' => $counselor->id,
            'scheduled_at' => $slot,
            'duration_minutes' => 60,
        ]);
        $first->assertStatus(201);

        $second = $this->actingAs($student)->postJson('/api/appointments', [
            'counselor_id' => $counselor->id,
            'scheduled_at' => $slot,
            'duration_minutes' => 60,
        ]);
        $second->assertStatus(422);

        $this->assertSame(1, Appointment::query()->count());
    }

    /** @test */
    public function appointment_overlap_window_blocks_partial_overlap_but_allows_boundary_touch(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $counselor = $this->createPortalUser('counselor', 'counselor-window@test.com', 'Counselor Window');
        $studentA = $this->createPortalUser('student', 'student-window-a@test.com', 'Student Window A');
        $studentB = $this->createPortalUser('student', 'student-window-b@test.com', 'Student Window B');

        $baseSlot = now()->addDays(3)->setHour(10)->setMinute(0)->setSecond(0);

        $this->actingAs($studentA)->postJson('/api/appointments', [
            'counselor_id' => $counselor->id,
            'scheduled_at' => $baseSlot->toIso8601String(),
            'duration_minutes' => 60,
        ])->assertStatus(201);

        $this->actingAs($studentB)->postJson('/api/appointments', [
            'counselor_id' => $counselor->id,
            'scheduled_at' => $baseSlot->copy()->addMinutes(30)->toIso8601String(),
            'duration_minutes' => 30,
        ])->assertStatus(422);

        $this->actingAs($studentB)->postJson('/api/appointments', [
            'counselor_id' => $counselor->id,
            'scheduled_at' => $baseSlot->copy()->addMinutes(60)->toIso8601String(),
            'duration_minutes' => 30,
        ])->assertStatus(201);
    }

    /** @test */
    public function booking_is_rejected_when_concurrent_slot_lock_is_already_held(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $counselor = $this->createPortalUser('counselor', 'counselor-lock@test.com', 'Counselor Lock');
        $student = $this->createPortalUser('student', 'student-lock@test.com', 'Student Lock');
        $slot = now()->addDays(3)->setHour(13)->setMinute(0)->toIso8601String();

        $lock = Cache::lock("appointments:counselor:{$counselor->id}", 15);
        $this->assertTrue($lock->get());

        try {
            $response = $this->actingAs($student)->postJson('/api/appointments', [
                'counselor_id' => $counselor->id,
                'scheduled_at' => $slot,
                'duration_minutes' => 60,
            ]);

            $response
                ->assertStatus(422)
                ->assertJsonValidationErrors(['scheduled_at']);
        } finally {
            $lock->release();
        }
    }

    /** @test */
    public function high_risk_intake_flood_creates_alerts_without_data_corruption(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $admin = $this->createPortalUser('admin', 'admin-flood@test.com', 'Admin Flood');
        $counselor = $this->createPortalUser('counselor', 'counselor-flood@test.com', 'Counselor Flood');
        for ($i = 0; $i < 40; $i++) {
            $response = $this->actingAs($counselor)->postJson('/api/intake-submissions', [
                'submitter_type' => 'staff',
                'presenting_concerns' => ['panic'],
                'risk_answers' => [
                    'immediate_danger' => true,
                    'self_harm_thoughts' => true,
                ],
                'consent_acknowledged' => true,
                'summary' => "High risk payload {$i}",
            ]);
            $response->assertStatus(201);
        }

        $this->assertSame(40, IntakeSubmission::query()->count());
        $this->assertSame(40, RiskAlert::query()->count());

        $notifications = DB::table('notifications')
            ->where('title', 'High-Risk Intake Alert')
            ->whereIn('user_id', [$admin->id, $counselor->id])
            ->count();

        $this->assertGreaterThanOrEqual(80, $notifications);
    }

    /** @test */
    public function multiple_anonymous_sessions_generate_unique_anonymous_ids(): void
    {
        $counselor = $this->createPortalUser('counselor', 'counselor-anon-stress@test.com', 'Counselor Anon Stress');

        for ($i = 0; $i < 30; $i++) {
            $student = $this->createPortalUser(
                'student',
                "anon-stress-student-{$i}@test.com",
                "Anon Student {$i}"
            );

            $response = $this->actingAs($student)->postJson('/api/sessions', [
                'counselor_id' => $counselor->id,
                'session_type' => 'chat',
                'is_anonymous' => true,
            ]);
            $response->assertStatus(201)->assertJson(['is_anonymous' => true]);
        }

        $distinctAnonymousIds = DB::table('counseling_sessions')
            ->where('is_anonymous', true)
            ->whereNotNull('anonymous_id')
            ->distinct('anonymous_id')
            ->count('anonymous_id');

        $this->assertSame(30, $distinctAnonymousIds);
    }

    private function createPortalUser(string $role, string $email, string $fullName): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('SecretPass123!'),
        ]);

        $user->profile()->create([
            'full_name' => $fullName,
            'id_number' => null,
            'anonymous_mode' => false,
            'peer_available' => true,
        ]);

        $user->roles()->create([
            'role' => $role,
            'approved' => true,
        ]);

        return $user;
    }
}
