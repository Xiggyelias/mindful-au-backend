<?php

namespace Tests\Feature;

use App\Models\EmergencyRequest;
use App\Models\PanicLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertActionStabilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function counselor_can_resolve_panic_alert_without_internal_server_error(): void
    {
        $student = $this->createPortalUser('student', 'alert-student@test.com', 'Alert Student');
        $counselor = $this->createPortalUser('counselor', 'alert-counselor@test.com', 'Alert Counselor');

        $panicLog = PanicLog::query()->create([
            'student_id' => $student->id,
            'location' => '-18.8950970639333, 32.603541298504496',
            'resolved' => false,
        ]);

        $response = $this->actingAs($counselor)->putJson("/api/panic-logs/{$panicLog->id}", [
            'resolved' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('resolved', true);

        $this->assertDatabaseHas('panic_logs', [
            'id' => $panicLog->id,
            'resolved' => true,
            'resolved_by' => $counselor->id,
        ]);
    }

    #[Test]
    public function panic_alert_resolution_tolerates_missing_optional_resolution_columns(): void
    {
        $student = $this->createPortalUser('student', 'legacy-panic-student@test.com', 'Legacy Panic Student');
        $counselor = $this->createPortalUser('counselor', 'legacy-panic-counselor@test.com', 'Legacy Panic Counselor');

        Schema::dropIfExists('panic_logs');
        Schema::create('panic_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('location')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });

        $panicLog = PanicLog::query()->create([
            'student_id' => $student->id,
            'location' => 'Legacy mobile alert',
            'resolved' => false,
        ]);

        $response = $this->actingAs($counselor)->putJson("/api/panic-logs/{$panicLog->id}", [
            'resolved' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('resolved', true);

        $this->assertDatabaseHas('panic_logs', [
            'id' => $panicLog->id,
            'resolved' => true,
        ]);
    }

    #[Test]
    public function counselor_can_take_and_resolve_emergency_support_request(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:50:00'));

        try {
            $student = $this->createPortalUser('student', 'emergency-student@test.com', 'Emergency Student');
            $counselor = $this->createPortalUser('counselor', 'emergency-counselor@test.com', 'Emergency Counselor');

            $emergencyRequest = EmergencyRequest::query()->create([
                'student_id' => $student->id,
                'requested_at' => now()->subMinutes(5),
                'is_after_hours' => false,
                'priority' => 1,
                'status' => 'queued',
                'reason' => 'need to talk to someone',
            ]);

            $takeResponse = $this->actingAs($counselor)->patchJson("/api/emergency-requests/{$emergencyRequest->id}", [
                'status' => 'assigned',
            ]);

            $takeResponse
                ->assertOk()
                ->assertJsonPath('status', 'assigned')
                ->assertJsonPath('assigned_to', $counselor->id);

            $resolveResponse = $this->actingAs($counselor)->patchJson("/api/emergency-requests/{$emergencyRequest->id}", [
                'status' => 'resolved',
            ]);

            $resolveResponse
                ->assertOk()
                ->assertJsonPath('status', 'resolved')
                ->assertJsonPath('resolved_by', $counselor->id);
        } finally {
            Carbon::setTestNow();
        }
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
