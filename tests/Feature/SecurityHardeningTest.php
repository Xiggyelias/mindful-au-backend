<?php

namespace Tests\Feature;

use App\Models\CounselingSession;
use App\Models\Referral;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function role_escalation_and_idor_attempts_are_blocked(): void
    {
        $admin = $this->createPortalUser('admin', 'admin-sec@test.com', 'Admin Sec');
        $counselor = $this->createPortalUser('counselor', 'counselor-sec@test.com', 'Counselor Sec');
        $studentA = $this->createPortalUser('student', 'student-a-sec@test.com', 'Student A');
        $studentB = $this->createPortalUser('student', 'student-b-sec@test.com', 'Student B');

        $this->actingAs($studentA)->getJson('/api/users')->assertStatus(403);

        $referral = Referral::query()->create([
            'student_id' => $studentA->id,
            'referred_by' => $counselor->id,
            'direction' => 'internal',
            'target_service' => 'medical',
            'consent_granted' => true,
            'status' => 'pending',
            'referred_at' => now(),
        ]);

        $this->actingAs($studentB)
            ->getJson("/api/referrals/{$referral->id}")
            ->assertStatus(403);

        $this->actingAs($admin)
            ->getJson("/api/referrals/{$referral->id}")
            ->assertStatus(200);
    }

    /** @test */
    public function anonymous_identity_is_masked_from_counselor_until_reveal(): void
    {
        $counselor = $this->createPortalUser('counselor', 'counselor-anon@test.com', 'Counselor Anon');
        $student = $this->createPortalUser('student', 'student-anon@test.com', 'Student Anon');

        $createSessionResponse = $this->actingAs($student)->postJson('/api/sessions', [
            'counselor_id' => $counselor->id,
            'session_type' => 'chat',
            'is_anonymous' => true,
        ]);
        $createSessionResponse->assertStatus(201);
        $sessionId = (int) $createSessionResponse->json('id');

        $showResponse = $this->actingAs($counselor)->getJson("/api/sessions/{$sessionId}");
        $showResponse->assertStatus(200);
        $this->assertSame(0, (int) $showResponse->json('student_id'));
        $this->assertFalse((bool) $showResponse->json('identity_visible_to_viewer'));
        $this->assertSame($student->id, (int) $showResponse->json('chat_peer_student_id'));

        $listResponse = $this->actingAs($counselor)->getJson('/api/sessions/chat-list?limit=50&open_only=1');
        $listResponse->assertStatus(200);
        $list = $listResponse->json();
        $this->assertIsArray($list);
        $row = collect($list)->first(fn ($item) => (int) ($item['id'] ?? 0) === $sessionId);
        $this->assertNotNull($row);
        $this->assertSame(0, (int) ($row['student_id'] ?? -1));
        $this->assertSame($student->id, (int) ($row['chat_peer_student_id'] ?? 0));
    }

    /** @test */
    public function voice_note_upload_rejects_invalid_file_types(): void
    {
        $counselor = $this->createPortalUser('counselor', 'counselor-voice@test.com', 'Counselor Voice');
        $student = $this->createPortalUser('student', 'student-voice@test.com', 'Student Voice');

        $session = CounselingSession::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'status' => 'active',
            'session_type' => 'chat',
            'is_anonymous' => false,
        ]);

        $file = UploadedFile::fake()->create('payload.php', 20, 'application/x-php');

        $this->actingAs($student)
            ->postJson("/api/sessions/{$session->id}/voice-notes", [
                'audio' => $file,
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function token_rotation_prevents_session_fixation_after_relogin(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $user = $this->createPortalUser('counselor', 'counselor-login-sec@test.com', 'Counselor Login');
        putenv('AUTH_REQUIRE_GOOGLE_FOR_STUDENTS=false');

        $firstLogin = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'SecretPass123!',
        ]);
        $firstLogin->assertStatus(200);
        $token1 = (string) $firstLogin->json('access_token');
        $this->assertNotEmpty($token1);

        $secondLogin = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'SecretPass123!',
        ]);
        $secondLogin->assertStatus(200);
        $token2 = (string) $secondLogin->json('access_token');
        $this->assertNotEmpty($token2);
        $this->assertNotSame($token1, $token2);

        $this->withHeader('Authorization', "Bearer {$token1}")
            ->getJson('/api/me')
            ->assertStatus(401);

        $this->withHeader('Authorization', "Bearer {$token2}")
            ->getJson('/api/me')
            ->assertStatus(200);
    }

    /** @test */
    public function session_notes_are_encrypted_at_rest_and_decrypted_on_read(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $counselor = $this->createPortalUser('counselor', 'counselor-note-sec@test.com', 'Counselor Notes');
        $student = $this->createPortalUser('student', 'student-note-sec@test.com', 'Student Notes');

        $session = CounselingSession::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'status' => 'active',
            'session_type' => 'chat',
            'is_anonymous' => false,
        ]);

        $noteText = 'SOAP: Student reports severe anxiety before exams.';

        $this->actingAs($counselor)
            ->putJson("/api/sessions/{$session->id}/note", [
                'notes' => $noteText,
            ])
            ->assertStatus(200);

        $storedValue = DB::table('counseling_sessions')
            ->where('id', $session->id)
            ->value('notes');

        $this->assertIsString($storedValue);
        $this->assertStringStartsWith('enc::', (string) $storedValue);

        $this->actingAs($counselor)
            ->getJson("/api/sessions/{$session->id}")
            ->assertStatus(200)
            ->assertJsonPath('notes', $noteText);
    }

    /** @test */
    public function corrupted_encrypted_notes_are_not_exposed(): void
    {
        $counselor = $this->createPortalUser('counselor', 'counselor-corrupt-note@test.com', 'Counselor Corrupt');
        $student = $this->createPortalUser('student', 'student-corrupt-note@test.com', 'Student Corrupt');

        $session = CounselingSession::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'status' => 'active',
            'session_type' => 'chat',
            'is_anonymous' => false,
        ]);

        DB::table('counseling_sessions')
            ->where('id', $session->id)
            ->update([
                'notes' => 'enc::corrupted-ciphertext-payload',
            ]);

        $this->actingAs($counselor)
            ->getJson("/api/sessions/{$session->id}")
            ->assertStatus(200)
            ->assertJsonPath('notes', null);
    }

    /** @test */
    public function admin_can_request_counselor_scope_for_multi_role_account(): void
    {
        $adminCounselor = $this->createPortalUser('admin', 'admin-counselor-scope@test.com', 'Admin Counselor');
        $adminCounselor->roles()->create([
            'role' => 'counselor',
            'approved' => true,
        ]);

        $studentA = $this->createPortalUser('student', 'scope-student-a@test.com', 'Scope Student A');
        $studentB = $this->createPortalUser('student', 'scope-student-b@test.com', 'Scope Student B');
        $otherCounselor = $this->createPortalUser('counselor', 'scope-other-c@test.com', 'Scope Other C');

        $ownedSession = CounselingSession::query()->create([
            'student_id' => $studentA->id,
            'counselor_id' => $adminCounselor->id,
            'status' => 'active',
            'session_type' => 'chat',
            'is_anonymous' => false,
        ]);

        CounselingSession::query()->create([
            'student_id' => $studentB->id,
            'counselor_id' => $otherCounselor->id,
            'status' => 'active',
            'session_type' => 'chat',
            'is_anonymous' => false,
        ]);

        $scoped = $this->actingAs($adminCounselor)->getJson('/api/sessions?as_role=counselor&page=1&per_page=50');
        $scoped->assertStatus(200);
        $ids = collect($scoped->json('data'))->pluck('id')->all();
        $this->assertContains($ownedSession->id, $ids);
        $this->assertCount(1, $ids);

        $unscoped = $this->actingAs($adminCounselor)->getJson('/api/sessions?page=1&per_page=50');
        $unscoped->assertStatus(200);
        $this->assertGreaterThan(1, count($unscoped->json('data')));
    }

    /** @test */
    public function api_unhandled_exceptions_are_sanitized_even_with_debug_enabled(): void
    {
        $previousDebug = (bool) config('app.debug');
        $previousExpose = getenv('API_EXPOSE_ERROR_DETAILS');

        config()->set('app.debug', true);
        putenv('API_EXPOSE_ERROR_DETAILS=false');

        try {
            Route::middleware('api')->get('/api/test/security/unhandled-error', function () {
                throw new \RuntimeException('database credentials leaked');
            });

            $response = $this->getJson('/api/test/security/unhandled-error');

            $response->assertStatus(500)
                ->assertJsonStructure(['message', 'error_id'])
                ->assertJsonMissingPath('detail')
                ->assertJsonMissingPath('exception');

            $this->assertNotEmpty((string) $response->json('error_id'));
        } finally {
            config()->set('app.debug', $previousDebug);
            if ($previousExpose === false) {
                putenv('API_EXPOSE_ERROR_DETAILS');
            } else {
                putenv('API_EXPOSE_ERROR_DETAILS=' . $previousExpose);
            }
        }
    }

    /** @test */
    public function api_validation_responses_preserve_error_payload_structure(): void
    {
        Route::middleware('api')->post('/api/test/security/validation-error', function (\Illuminate\Http\Request $request) {
            $request->validate([
                'email' => 'required|email',
            ]);

            return response()->json(['ok' => true]);
        });

        $response = $this->postJson('/api/test/security/validation-error', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonStructure([
                'errors' => ['email'],
            ]);
    }

    /** @test */
    public function production_environment_never_exposes_api_error_details_even_if_toggles_are_enabled(): void
    {
        $previousEnv = (string) config('app.env');
        $previousDebug = (bool) config('app.debug');
        $previousExpose = getenv('API_EXPOSE_ERROR_DETAILS');

        config()->set('app.env', 'production');
        config()->set('app.debug', true);
        putenv('API_EXPOSE_ERROR_DETAILS=true');

        try {
            Route::middleware('api')->get('/api/test/security/prod-sanitized-error', function () {
                throw new \RuntimeException('sensitive stack detail');
            });

            $response = $this->getJson('/api/test/security/prod-sanitized-error');

            $response->assertStatus(500)
                ->assertJsonStructure(['message', 'error_id'])
                ->assertJsonMissingPath('detail')
                ->assertJsonMissingPath('exception');
        } finally {
            config()->set('app.env', $previousEnv);
            config()->set('app.debug', $previousDebug);
            if ($previousExpose === false) {
                putenv('API_EXPOSE_ERROR_DETAILS');
            } else {
                putenv('API_EXPOSE_ERROR_DETAILS=' . $previousExpose);
            }
        }
    }

    /** @test */
    public function api_responses_set_no_store_cache_headers_to_reduce_data_leakage(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        $cacheControl = (string) $response->headers->get('Cache-Control', '');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
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
