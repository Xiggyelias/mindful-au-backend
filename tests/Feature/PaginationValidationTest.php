<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AiDiagnostic;
use App\Models\AiReport;
use App\Models\Appointment;
use App\Models\DataAccessLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaginationValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function paginated_endpoints_return_consistent_metadata_and_filters(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $admin = $this->createPortalUser('admin', 'admin-pagination@test.com', 'Admin Pagination');
        $counselor = $this->createPortalUser('counselor', 'counselor-pagination@test.com', 'Counselor Pagination');
        $student = $this->createPortalUser('student', 'student-pagination@test.com', 'Student Pagination');

        for ($i = 0; $i < 30; $i++) {
            $this->createPortalUser(
                'student',
                "student-{$i}-pagination@test.com",
                "Student {$i}"
            );
        }

        for ($i = 0; $i < 20; $i++) {
            Appointment::query()->create([
                'student_id' => $student->id,
                'counselor_id' => $counselor->id,
                'scheduled_at' => now()->addDays($i + 1)->setHour(9),
                'duration_minutes' => 30,
                'status' => $i % 2 === 0 ? 'scheduled' : 'confirmed',
            ]);
        }

        for ($i = 0; $i < 22; $i++) {
            ActivityLog::query()->create([
                'user_id' => $admin->id,
                'action' => 'pagination.test',
                'description' => "Log {$i}",
                'type' => 'system',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'phpunit',
            ]);
        }

        for ($i = 0; $i < 14; $i++) {
            DataAccessLog::query()->create([
                'user_id' => $admin->id,
                'method' => 'GET',
                'path' => '/api/test',
                'status_code' => 200,
                'resource_type' => 'test',
                'resource_id' => (string) $i,
                'ip_address' => '127.0.0.1',
                'metadata' => ['idx' => $i],
            ]);
        }

        for ($i = 0; $i < 12; $i++) {
            AiReport::query()->create([
                'name' => "Report {$i}",
                'type' => 'weekly_heatmap',
                'status' => 'ready',
                'summary' => 'summary',
                'data' => ['idx' => $i],
                'generated_at' => now()->subDays($i),
            ]);
        }

        for ($i = 0; $i < 15; $i++) {
            AiDiagnostic::query()->create([
                'student_id' => $student->id,
                'session_id' => null,
                'stress_level' => 30 + $i,
                'anxiety_level' => 35 + $i,
                'depression_level' => 20 + $i,
                'mood' => 'concerned',
                'risk_level' => $i % 3 === 0 ? 'high' : 'low',
                'insights' => "Insight {$i}",
                'recommendations' => 'Follow-up counseling',
            ]);
        }

        $usersResponse = $this->actingAs($admin)->getJson('/api/users?page=2&per_page=10');
        $usersResponse->assertStatus(200)->assertJsonPath('meta.page', 2)->assertJsonPath('meta.per_page', 10);
        $this->assertCount(10, $usersResponse->json('data'));

        $appointmentsResponse = $this->actingAs($admin)
            ->getJson('/api/appointments?status=scheduled&page=2&per_page=5');
        $appointmentsResponse->assertStatus(200)
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.filters.status', 'scheduled');
        $appointmentLink = (string) (
            $appointmentsResponse->json('links.prev')
            ?: $appointmentsResponse->json('links.first')
            ?: $appointmentsResponse->json('links.last')
        );
        $this->assertStringContainsString(
            'status=scheduled',
            $appointmentLink
        );
        $this->assertStringContainsString(
            'per_page=5',
            $appointmentLink
        );

        $activityLogsResponse = $this->actingAs($admin)
            ->getJson('/api/activity-logs?type=system&page=2&per_page=5');
        $activityLogsResponse->assertStatus(200)
            ->assertJsonPath('meta.filters.type', 'system');
        $activityLink = (string) (
            $activityLogsResponse->json('links.next')
            ?: $activityLogsResponse->json('links.prev')
            ?: $activityLogsResponse->json('links.first')
            ?: $activityLogsResponse->json('links.last')
        );
        $this->assertStringContainsString(
            'type=system',
            $activityLink
        );

        $dataAccessResponse = $this->actingAs($admin)
            ->getJson('/api/data-access-logs?method=GET&page=2&per_page=5');
        $dataAccessResponse->assertStatus(200)
            ->assertJsonPath('meta.filters.method', 'GET');

        $reportsResponse = $this->actingAs($admin)->getJson('/api/ai-reports?page=2&per_page=5');
        $reportsResponse->assertStatus(200)
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 5);

        $diagnosticsResponse = $this->actingAs($admin)
            ->getJson("/api/ai-diagnostics?student_id={$student->id}&page=2&per_page=5");
        $diagnosticsResponse->assertStatus(200)
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.filters.student_id', (string) $student->id);

        $emptyResponse = $this->actingAs($admin)
            ->getJson('/api/ai-reports?page=99&per_page=10');
        $emptyResponse->assertStatus(200);
        $this->assertIsArray($emptyResponse->json('data'));
        $this->assertCount(0, $emptyResponse->json('data'));
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
