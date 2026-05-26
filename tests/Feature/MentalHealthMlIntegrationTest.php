<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Diagnostic;
use App\Models\StudentMoodLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MentalHealthMlIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function student_wellness_summary_exposes_ml_insights(): void
    {
        $this->disableTwoFactor();

        $student = $this->createPortalUser('student', 'ml-student@test.com', 'ML Student');

        Diagnostic::create([
            'student_id' => $student->id,
            'responses' => [],
            'total_score' => 64,
            'risk_level' => 'high',
            'category_scores' => ['stress' => 18],
            'ai_recommendations' => ['primary' => 'Keep regular counseling follow-up.'],
            'insights' => 'Recent stress remains elevated.',
            'is_anonymous' => false,
        ]);

        StudentMoodLog::create([
            'student_id' => $student->id,
            'mood' => 'low',
            'logged_on' => now()->toDateString(),
        ]);

        $response = $this->actingAs($student)->getJson('/api/student-wellness/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'ml_insights' => [
                    'model_version',
                    'risk_forecast' => ['score', 'level', 'confidence'],
                    'trend' => ['label', 'delta'],
                    'focus_area',
                    'dominant_topics',
                    'protective_factors',
                    'recommended_actions',
                ],
            ]);

        $this->assertNotEmpty($response->json('ml_insights.recommended_actions'));
    }

    /** @test */
    public function counselor_matches_endpoint_returns_ranked_matches_for_student(): void
    {
        $this->disableTwoFactor();

        $student = $this->createPortalUser('student', 'matching-student@test.com', 'Matching Student');
        $onlineCounselor = $this->createPortalUser('counselor', 'online-counselor@test.com', 'Online Counselor', now());
        $offlineCounselor = $this->createPortalUser('counselor', 'offline-counselor@test.com', 'Offline Counselor', now()->subHours(6));

        Appointment::create([
            'student_id' => $student->id,
            'counselor_id' => $onlineCounselor->id,
            'scheduled_at' => now()->subDays(1),
            'duration_minutes' => 60,
            'status' => 'completed',
            'notes' => 'Online',
        ]);

        $response = $this->actingAs($student)->getJson('/api/ml/counselor-matches?mode=online&limit=3');

        $response->assertOk()
            ->assertJsonPath('matches.0.id', $onlineCounselor->id);

        $this->assertGreaterThan(
            (int) $response->json('matches.1.score'),
            (int) $response->json('matches.0.score')
        );
    }

    /** @test */
    public function ai_chat_returns_ml_signals_and_stores_message_metadata(): void
    {
        $this->disableTwoFactor();

        config([
            'services.openrouter.api_key' => null,
            'services.gemini.api_key' => null,
        ]);

        $student = $this->createPortalUser('student', 'chat-ml-student@test.com', 'Chat ML Student');

        $response = $this->actingAs($student)->postJson('/api/ai/wellness-chat', [
            'message' => 'I feel anxious about exams and deadlines',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'ml_signals' => [
                    'model_version',
                    'focus_area',
                    'risk_forecast',
                    'trend',
                    'dominant_topics',
                    'recommended_actions',
                ],
            ]);

        $assistantMessageId = (int) $response->json('assistant_message_id');

        $this->assertDatabaseHas('message_metadata', [
            'message_id' => $assistantMessageId,
            'key' => 'ml_signal_snapshot',
            'type' => 'json',
        ]);
    }

    /** @test */
    public function admin_dashboard_includes_ml_intelligence_payload(): void
    {
        $this->disableTwoFactor();

        $admin = $this->createPortalUser('admin', 'ml-admin@test.com', 'ML Admin');

        $response = $this->actingAs($admin)->getJson('/api/analytics/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'ml_intelligence' => [
                    'model_version',
                    'students_needing_follow_up',
                    'rising_risk_students',
                    'chat_support_utilization_30d',
                    'proactive_follow_up_coverage',
                    'risk_forecast_distribution',
                    'top_actions',
                    'validation',
                    'ethics',
                ],
            ]);
    }

    /** @test */
    public function it_factors_academic_risk_events_into_ml_insights(): void
    {
        $this->disableTwoFactor();

        $student = $this->createPortalUser('student', 'academic-ml-student@test.com', 'Academic Student');

        // Create an AcademicRiskEvent for this student
        \App\Models\AcademicRiskEvent::create([
            'student_identifier' => 'academic-ml-student@test.com',
            'linked_user_id' => $student->id,
            'risk_type' => 'failed_courses',
            'risk_score' => 75.50,
            'status' => 'linked',
            'received_at' => now(),
            'processed_at' => now(),
            'payload' => [],
        ]);

        $response = $this->actingAs($student)->getJson('/api/student-wellness/summary');

        $response->assertOk();
        $mlInsights = $response->json('ml_insights');

        $this->assertEquals('Academic pressure stabilization', $mlInsights['focus_area']);
        $this->assertContains('Academic risk events flagged: failed_courses.', $mlInsights['risk_indicators']);
        $this->assertEquals(1, $mlInsights['feature_snapshot']['academic_risk_events_count']);
        $this->assertEquals(75.50, $mlInsights['feature_snapshot']['academic_risk_highest_score']);
        $this->assertContains('failed_courses', $mlInsights['feature_snapshot']['academic_risk_types']);
    }

    private function disableTwoFactor(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );
    }

    private function createPortalUser(
        string $role,
        string $email,
        string $fullName,
        $lastSeenAt = null
    ): User {
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('SecretPass123!'),
            'last_seen_at' => $lastSeenAt,
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
