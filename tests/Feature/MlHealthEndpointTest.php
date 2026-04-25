<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MlHealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_view_ml_health_snapshot(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $admin = $this->createPortalUser('admin', 'ml-health-admin@test.com', 'ML Health Admin');

        $response = $this->actingAs($admin)->getJson('/api/ml/health');

        $response->assertOk()
            ->assertJsonStructure([
                'model_version',
                'window' => ['from', 'to'],
                'inference' => [
                    'assistant_inferences_24h',
                    'provider_modes',
                    'provider_names',
                    'fallback_rate_percent',
                    'average_latency_ms',
                    'p95_latency_ms',
                ],
                'risk_monitoring' => [
                    'students_needing_follow_up',
                    'rising_risk_students',
                    'fairness_status',
                ],
                'readiness' => [
                    'low_bandwidth_mode',
                    'external_ai_optional',
                    'human_review_required',
                ],
            ]);
    }

    /** @test */
    public function non_admin_cannot_view_ml_health_snapshot(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $student = $this->createPortalUser('student', 'ml-health-student@test.com', 'ML Health Student');

        $this->actingAs($student)
            ->getJson('/api/ml/health')
            ->assertStatus(403);
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

