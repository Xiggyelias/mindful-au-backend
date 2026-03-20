<?php

namespace Tests\Feature;

use App\Models\RiskAlert;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IntakeReferralEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function students_cannot_access_intake_endpoints(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $studentA = $this->createPortalUser('student', 'student-intake-a@test.com', 'Student Intake A');
        $counselor = $this->createPortalUser('counselor', 'counselor-intake@test.com', 'Counselor Intake');

        $this->actingAs($studentA)->getJson('/api/intake-submissions')->assertStatus(403);

        $this->actingAs($studentA)->postJson('/api/intake-submissions', [
            'is_anonymous' => true,
            'presenting_concerns' => ['stress', 'sleep issues'],
            'risk_answers' => [
                'sleep_disruption' => true,
            ],
            'consent_acknowledged' => true,
            'summary' => 'Need support with stress management.',
        ])->assertStatus(403);

        $createResponse = $this->actingAs($counselor)->postJson('/api/intake-submissions', [
            'submitter_type' => 'staff',
            'is_anonymous' => false,
            'presenting_concerns' => ['stress', 'sleep issues'],
            'risk_answers' => [
                'sleep_disruption' => true,
                'academic_decline' => true,
            ],
            'consent_acknowledged' => true,
            'summary' => 'Need support with stress management.',
        ]);

        $createResponse
            ->assertStatus(201);

        $intakeId = (int) $createResponse->json('id');

        $this->actingAs($studentA)
            ->getJson("/api/intake-submissions/{$intakeId}")
            ->assertStatus(403);

        $this->actingAs($counselor)->getJson("/api/intake-submissions/{$intakeId}")->assertStatus(200);
    }

    /** @test */
    public function high_risk_intake_alert_can_be_acknowledged_and_resolved(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $admin = $this->createPortalUser('admin', 'admin-intake-alert@test.com', 'Admin Intake Alert');
        $counselor = $this->createPortalUser('counselor', 'counselor-intake-alert@test.com', 'Counselor Intake Alert');
        $staff = $this->createPortalUser('counselor', 'staff-intake-alert@test.com', 'Staff Intake Alert');

        $createResponse = $this->actingAs($staff)->postJson('/api/intake-submissions', [
            'submitter_type' => 'staff',
            'presenting_concerns' => ['panic attacks', 'self harm concerns'],
            'risk_answers' => [
                'immediate_danger' => true,
                'self_harm_thoughts' => true,
                'panic_attacks' => true,
            ],
            'consent_acknowledged' => true,
            'summary' => 'Urgent support required.',
        ]);

        $createResponse
            ->assertStatus(201)
            ->assertJsonPath('risk_level', 'high');

        $intakeId = (int) $createResponse->json('id');
        $alert = RiskAlert::query()->first();
        $this->assertNotNull($alert);

        $this->actingAs($counselor)
            ->patchJson("/api/risk-alerts/{$alert->id}/acknowledge", [
                'status' => 'acknowledged',
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'acknowledged');

        $this->actingAs($admin)
            ->patchJson("/api/risk-alerts/{$alert->id}/acknowledge", [
                'status' => 'resolved',
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'resolved');

        $this->assertDatabaseHas('intake_submissions', [
            'id' => $intakeId,
            'status' => 'closed',
        ]);
    }

    /** @test */
    public function referral_endpoints_enforce_role_scope_and_consent_rules(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $admin = $this->createPortalUser('admin', 'admin-referral@test.com', 'Admin Referral');
        $counselorA = $this->createPortalUser('counselor', 'counselor-a-referral@test.com', 'Counselor Referral A');
        $counselorB = $this->createPortalUser('counselor', 'counselor-b-referral@test.com', 'Counselor Referral B');
        $student = $this->createPortalUser('student', 'student-referral@test.com', 'Student Referral');

        $this->actingAs($student)
            ->postJson('/api/referrals', [
                'student_id' => $student->id,
                'direction' => 'internal',
                'target_service' => 'medical',
                'consent_granted' => true,
            ])
            ->assertStatus(403);

        $this->actingAs($counselorA)
            ->postJson('/api/referrals', [
                'student_id' => $student->id,
                'direction' => 'external',
                'target_service' => 'psychiatry',
                'consent_granted' => false,
                'shared_fields' => ['summary' => 'not allowed without consent'],
            ])
            ->assertStatus(422);

        $createResponse = $this->actingAs($counselorA)
            ->postJson('/api/referrals', [
                'student_id' => $student->id,
                'direction' => 'internal',
                'target_service' => 'chaplaincy',
                'destination_details' => 'Student support center',
                'consent_granted' => true,
                'shared_fields' => ['summary' => 'student requested extra support'],
                'notes' => 'Initial referral',
            ]);

        $createResponse
            ->assertStatus(201)
            ->assertJsonPath('status', 'pending');

        $referralId = (int) $createResponse->json('id');

        $this->actingAs($counselorB)
            ->getJson("/api/referrals/{$referralId}")
            ->assertStatus(403);

        $this->actingAs($student)
            ->getJson("/api/referrals/{$referralId}")
            ->assertStatus(200);

        $this->actingAs($admin)
            ->getJson("/api/referrals/{$referralId}")
            ->assertStatus(200);

        $this->actingAs($counselorA)
            ->patchJson("/api/referrals/{$referralId}", [
                'status' => 'completed',
                'outcome_notes' => 'Referral completed successfully.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'completed');

        $this->actingAs($counselorA)
            ->postJson("/api/referrals/{$referralId}/events", [
                'event_type' => 'follow_up',
                'notes' => 'Student confirmed attendance.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('event_type', 'follow_up');

        $this->assertDatabaseHas('referrals', [
            'id' => $referralId,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('referral_events', [
            'referral_id' => $referralId,
            'event_type' => 'follow_up',
        ]);
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
