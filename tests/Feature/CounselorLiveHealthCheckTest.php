<?php

namespace Tests\Feature;

use App\Models\AiDiagnostic;
use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\CounselorWellnessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounselorLiveHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function live_health_check_does_not_persist_demo_scores_without_live_activity(): void
    {
        $counselor = $this->portalUser('counselor');

        $response = $this->actingAs($counselor)
            ->postJson('/api/counselor-wellness/health-check');

        $response
            ->assertOk()
            ->assertJsonPath('persisted', false)
            ->assertJsonPath('summary.source', 'live-insufficient-data')
            ->assertJsonPath('summary.has_live_activity', false)
            ->assertJsonPath('summary.scores.mood_score', null)
            ->assertJsonPath('summary.scores.stress_level', null)
            ->assertJsonPath('summary.scores.burnout_index', null);

        $this->assertSame(0, CounselorWellnessLog::query()->count());
    }

    /** @test */
    public function live_health_check_uses_saved_self_check_in_but_does_not_save_it_as_live_activity(): void
    {
        $counselor = $this->portalUser('counselor');

        CounselorWellnessLog::query()->create([
            'counselor_id' => $counselor->id,
            'mood_score' => 45,
            'stress_level' => 70,
            'burnout_index' => 65,
            'recommendations' => 'Self check-in recommendation',
            'check_in_answers' => [
                'emotional_drain' => 3,
                'disconnect_difficulty' => 3,
                'calm_control' => 1,
                'energy_level' => 2,
                'break_quality' => 1,
                'support_level' => 2,
                'sleep_quality' => 2,
                'burnout_worry' => 3,
            ],
            'check_in_version' => 'v1',
        ]);

        $summary = $this->actingAs($counselor)
            ->getJson('/api/counselor-wellness/summary');

        $summary
            ->assertOk()
            ->assertJsonPath('source', 'self-check-in-only')
            ->assertJsonPath('has_live_activity', false)
            ->assertJsonPath('scores.mood_score', 45)
            ->assertJsonPath('scores.stress_level', 70)
            ->assertJsonPath('scores.burnout_index', 65);

        $response = $this->actingAs($counselor)
            ->postJson('/api/counselor-wellness/health-check');

        $response
            ->assertOk()
            ->assertJsonPath('persisted', false)
            ->assertJsonPath('summary.source', 'self-check-in-only');

        $this->assertSame(1, CounselorWellnessLog::query()->count());
    }

    /** @test */
    public function live_health_check_persists_scores_when_real_activity_exists(): void
    {
        $student = $this->portalUser('student');
        $counselor = $this->portalUser('counselor');

        $session = CounselingSession::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'status' => 'completed',
            'session_type' => 'chat',
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHours(2),
        ]);

        for ($i = 0; $i < 3; $i++) {
            CounselingSession::query()->create([
                'student_id' => $student->id,
                'counselor_id' => $counselor->id,
                'status' => 'completed',
                'session_type' => 'chat',
                'started_at' => now()->subDays($i + 1)->setTime(9, 0),
                'ended_at' => now()->subDays($i + 1)->setTime(9, 50),
            ]);
        }

        Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 45,
            'status' => 'confirmed',
        ]);

        AiDiagnostic::query()->create([
            'student_id' => $student->id,
            'session_id' => $session->id,
            'stress_level' => 82,
            'anxiety_level' => 74,
            'depression_level' => 61,
            'mood' => 'anxious',
            'risk_level' => 'high',
            'insights' => 'Escalate support',
            'recommendations' => 'Schedule counselor follow-up',
        ]);

        $response = $this->actingAs($counselor)
            ->postJson('/api/counselor-wellness/health-check');

        $response
            ->assertCreated()
            ->assertJsonPath('persisted', true)
            ->assertJsonPath('summary.source', 'live-computed')
            ->assertJsonPath('summary.has_live_activity', true)
            ->assertJsonPath('check_in_version', 'auto-v3');

        $this->assertSame(1, CounselorWellnessLog::query()->where('check_in_version', 'auto-v3')->count());
        $this->assertNotSame(82, $response->json('mood_score'));
        $this->assertNotSame(18, $response->json('stress_level'));
        $this->assertNotSame(14, $response->json('burnout_index'));
        $this->assertGreaterThan(0, $response->json('summary.metrics.live_data_points'));
    }

    private function portalUser(string $role): User
    {
        $user = User::factory()->create();

        $user->roles()->create([
            'role' => $role,
            'approved' => true,
        ]);

        return $user;
    }
}
