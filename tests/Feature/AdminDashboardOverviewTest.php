<?php

namespace Tests\Feature;

use App\Models\AiDiagnostic;
use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\PanicLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminDashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_load_a_single_dashboard_snapshot(): void
    {
        $admin = $this->createPortalUser('admin', 'admin-overview@test.com', 'Admin Overview');
        $student = $this->createPortalUser('student', 'student-overview@test.com', 'Student Overview');
        $counselor = $this->createPortalUser(
            'counselor',
            'counselor-overview@test.com',
            'Counselor Overview',
            now()->subMinutes(2)
        );
        $peerCounselor = $this->createPortalUser(
            'peer_counselor',
            'peer-overview@test.com',
            'Peer Overview',
            now()->subHours(2)
        );

        $activeSession = CounselingSession::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'status' => 'active',
            'session_type' => 'chat',
            'started_at' => now()->subMinutes(20),
        ]);

        $todayAppointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->addHours(2),
            'duration_minutes' => 45,
            'status' => 'scheduled',
        ]);

        $futureAppointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 60,
            'status' => 'scheduled',
        ]);

        AiDiagnostic::query()->create([
            'student_id' => $student->id,
            'session_id' => $activeSession->id,
            'stress_level' => 82,
            'anxiety_level' => 74,
            'depression_level' => 61,
            'mood' => 'anxious',
            'risk_level' => 'high',
            'insights' => 'Escalate support',
            'recommendations' => 'Schedule counselor follow-up',
        ]);

        PanicLog::query()->create([
            'student_id' => $student->id,
            'location' => 'Dormitory',
            'resolved' => false,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/analytics/overview');

        $response
            ->assertOk()
            ->assertJsonPath('overview.total_students', 1)
            ->assertJsonPath('overview.total_counselors', 1)
            ->assertJsonPath('overview.active_sessions', 1)
            ->assertJsonPath('overview.pending_appointments', 2)
            ->assertJsonPath('appointments.appointments_today', 1)
            ->assertJsonPath('alerts.active_panic_logs', 1)
            ->assertJsonPath('alerts.high_or_critical_last_30_days', 1)
            ->assertJsonPath('alerts.open_total', 2)
            ->assertJsonPath('counselor_presence.summary.total', 2)
            ->assertJsonPath('counselor_presence.summary.available', 0)
            ->assertJsonPath('counselor_presence.items.0.id', $counselor->id)
            ->assertJsonPath('counselor_presence.items.0.status', 'In Session')
            ->assertJsonPath('pending_appointments.0.id', $todayAppointment->id)
            ->assertJsonPath('pending_appointments.0.student.profile.full_name', 'Student Overview')
            ->assertJsonPath('pending_appointments.1.id', $futureAppointment->id)
            ->assertJsonCount(2, 'pending_appointments');

        $this->assertSame($peerCounselor->id, (int) $response->json('counselor_presence.items.1.id'));
    }

    private function createPortalUser(
        string $role,
        string $email,
        string $fullName,
        ?Carbon $lastSeenAt = null
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
