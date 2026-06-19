<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\RiskAlert;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemExecutionFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function core_role_based_workflows_execute_successfully(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-06-15 09:00:00');

        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $admin = $this->createPortalUser('admin', 'admin-flow@test.com', 'Admin Flow');
        $counselor = $this->createPortalUser('counselor', 'counselor-flow@test.com', 'Counselor Flow');
        $student = $this->createPortalUser('student', 'student-flow@test.com', 'Student Flow');
        $staffStudent = $this->createPortalUser('student', 'staff-student@test.com', 'Staff Student');

        // Student booking appointment
        $appointmentResponse = $this->actingAs($student)->postJson('/api/appointments', [
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->addDay()->setHour(10)->setMinute(0)->toIso8601String(),
            'duration_minutes' => 45,
            'notes' => 'Need support with stress.',
        ]);

        $appointmentResponse->assertStatus(201);
        $appointmentId = (int) $appointmentResponse->json('id');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $counselor->id,
            'title' => 'New appointment request',
        ]);

        // Anonymous user session
        $anonymousSessionResponse = $this->actingAs($student)->postJson('/api/sessions', [
            'counselor_id' => $counselor->id,
            'session_type' => 'chat',
            'is_anonymous' => true,
        ]);
        $anonymousSessionResponse
            ->assertStatus(201)
            ->assertJson([
                'is_anonymous' => true,
            ]);
        $this->assertNotEmpty($anonymousSessionResponse->json('anonymous_id'));

        // Staff booking (modeled as counselor-created student session)
        $staffBookingResponse = $this->actingAs($counselor)->postJson('/api/sessions/counselor', [
            'student_id' => $staffStudent->id,
            'session_type' => 'voice',
        ]);
        $staffBookingResponse->assertStatus(201);

        // Counselor accepting session via appointment confirmation
        $confirmResponse = $this->actingAs($counselor)->putJson("/api/appointments/{$appointmentId}", [
            'status' => 'confirmed',
        ]);
        $confirmResponse->assertStatus(200)->assertJson([
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'title' => 'Appointment Confirmed',
        ]);

        // Staff-created high-risk case alert
        $highRiskResponse = $this->actingAs($counselor)->postJson('/api/intake-submissions', [
            'submitter_type' => 'staff',
            'presenting_concerns' => ['panic attacks', 'academic decline'],
            'risk_answers' => [
                'immediate_danger' => true,
                'self_harm_thoughts' => true,
            ],
            'consent_acknowledged' => true,
            'summary' => 'Need urgent support.',
        ]);

        $highRiskResponse->assertStatus(201)->assertJson([
            'risk_level' => 'high',
        ]);

        $this->assertSame(1, RiskAlert::query()->count());

        $highRiskNotificationRecipients = Notification::query()
            ->where('title', 'High-Risk Intake Alert')
            ->pluck('user_id')
            ->all();

        $this->assertContains($admin->id, $highRiskNotificationRecipients);
        $this->assertContains($counselor->id, $highRiskNotificationRecipients);

        // Admin generating reports
        $reportResponse = $this->actingAs($admin)
            ->get('/api/analytics/export?report=overview&format=csv');
        $reportResponse->assertStatus(200);
        $this->assertStringContainsString(
            'text/csv',
            (string) $reportResponse->headers->get('content-type')
        );

        // Supervisor analytics view (admin scope in current RBAC model)
        $this->actingAs($admin)
            ->getJson('/api/analytics/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'overview',
                'sessions',
                'appointments',
            ]);

        // Referral creation
        $referralResponse = $this->actingAs($counselor)->postJson('/api/referrals', [
            'student_id' => $student->id,
            'direction' => 'internal',
            'target_service' => 'medical',
            'destination_details' => 'Campus clinic referral',
            'consent_granted' => true,
            'notes' => 'Follow-up required.',
        ]);
        $referralResponse->assertStatus(201)->assertJson([
            'direction' => 'internal',
            'status' => 'pending',
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
