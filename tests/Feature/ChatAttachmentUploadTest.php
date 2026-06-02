<?php

namespace Tests\Feature;

use App\Models\CounselingSession;
use App\Models\AiDiagnostic;
use App\Models\Notification;
use App\Models\PeerAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatAttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $counselor;
    private User $peerCounselor;
    private CounselingSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->student = User::factory()->create(['email' => 'student-attachment@test.com']);
        $this->counselor = User::factory()->create(['email' => 'counselor-attachment@test.com']);
        $this->peerCounselor = User::factory()->create(['email' => 'peer-attachment@test.com']);

        $this->assignRole($this->student, 'student');
        $this->assignRole($this->counselor, 'counselor');
        $this->assignRole($this->peerCounselor, 'peer_counselor');

        $this->session = CounselingSession::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'status' => 'active',
            'session_type' => 'chat',
        ]);
    }

    /** @test */
    public function participant_can_upload_attachment_and_message_history_includes_metadata(): void
    {
        $file = UploadedFile::fake()->create('support-note.png', 512, 'image/png');

        $uploadResponse = $this->actingAs($this->student)->post('/api/chat/upload-file', [
            'session_id' => $this->session->id,
            'file' => $file,
        ]);

        $uploadResponse
            ->assertStatus(201)
            ->assertJsonPath('message_type', 'file')
            ->assertJsonPath('has_file', true)
            ->assertJsonPath('attachment.file_name', 'support-note.png');

        $messageId = (int) $uploadResponse->json('id');
        $attachmentId = (int) $uploadResponse->json('attachment.id');
        $storedPath = (string) $uploadResponse->json('attachment.file_path');

        $this->assertGreaterThan(0, $messageId);
        $this->assertGreaterThan(0, $attachmentId);
        Storage::disk('local')->assertExists($storedPath);

        $this->assertDatabaseHas('messages', [
            'id' => $messageId,
            'session_id' => $this->session->id,
            'message_type' => 'file',
            'has_file' => true,
        ]);

        $this->assertDatabaseHas('chat_files', [
            'id' => $attachmentId,
            'message_id' => $messageId,
            'file_name' => 'support-note.png',
            'file_path' => $storedPath,
        ]);

        $messagesResponse = $this->actingAs($this->counselor)->getJson(
            '/api/chat/messages?session_id=' . $this->session->id
        );

        $messagesResponse
            ->assertStatus(200)
            ->assertJsonPath('0.id', $messageId)
            ->assertJsonPath('0.has_file', true)
            ->assertJsonPath('0.attachment.file_name', 'support-note.png');

        $attachmentUrl = (string) $messagesResponse->json('0.attachment.url');
        $downloadUrl = (string) $messagesResponse->json('0.attachment.download_url');

        $this->assertStringContainsString('/api/chat/files/' . $attachmentId . '/content', $attachmentUrl);
        $this->assertStringContainsString('/api/chat/files/' . $attachmentId . '/content', $downloadUrl);
    }

    /** @test */
    public function upload_rejects_disallowed_file_types(): void
    {
        $file = UploadedFile::fake()->create('payload.php', 10, 'application/x-httpd-php');

        $response = $this->actingAs($this->student)->post('/api/chat/upload-file', [
            'session_id' => $this->session->id,
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('chat_files', 0);
    }

    /** @test */
    public function deleting_an_attachment_message_removes_the_file_record_and_blob(): void
    {
        $file = UploadedFile::fake()->create('progress-report.pdf', 256, 'application/pdf');

        $uploadResponse = $this->actingAs($this->student)->post('/api/chat/upload-file', [
            'session_id' => $this->session->id,
            'file' => $file,
        ]);

        $uploadResponse->assertStatus(201);

        $messageId = (int) $uploadResponse->json('id');
        $storedPath = (string) $uploadResponse->json('attachment.file_path');

        $deleteResponse = $this->actingAs($this->student)->deleteJson(
            "/api/sessions/{$this->session->id}/messages/{$messageId}"
        );

        $deleteResponse
            ->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'id' => $messageId,
            ]);

        $this->assertDatabaseHas('messages', [
            'id' => $messageId,
            'content' => 'This message was deleted.',
            'message_type' => 'text',
            'has_file' => false,
        ]);
        $this->assertDatabaseMissing('chat_files', ['message_id' => $messageId]);
        Storage::disk('local')->assertMissing($storedPath);
    }

    /** @test */
    public function missing_attachment_blob_does_not_emit_broken_signed_urls(): void
    {
        $file = UploadedFile::fake()->create('missing-report.pdf', 256, 'application/pdf');

        $uploadResponse = $this->actingAs($this->student)->post('/api/chat/upload-file', [
            'session_id' => $this->session->id,
            'file' => $file,
        ]);

        $uploadResponse->assertStatus(201);
        $messageId = (int) $uploadResponse->json('id');
        $storedPath = (string) $uploadResponse->json('attachment.file_path');
        Storage::disk('local')->delete($storedPath);

        $messagesResponse = $this->actingAs($this->counselor)->getJson(
            '/api/chat/messages?session_id=' . $this->session->id
        );

        $messagesResponse
            ->assertStatus(200)
            ->assertJsonPath('0.id', $messageId)
            ->assertJsonPath('0.has_file', true)
            ->assertJsonPath('0.attachment.available', false)
            ->assertJsonPath('0.attachment.url', null)
            ->assertJsonPath('0.attachment.download_url', null);

        $downloadResponse = $this->actingAs($this->counselor)->getJson("/api/messages/{$messageId}/attachment");

        $downloadResponse
            ->assertStatus(404)
            ->assertJson(['message' => 'File not found']);
    }

    /** @test */
    public function assigned_peer_counselor_can_upload_voice_note_but_not_file_attachment(): void
    {
        $peerSession = CounselingSession::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'peer_counselor_id' => $this->peerCounselor->id,
            'assigned_role' => 'peer_counselor',
            'status' => 'active',
            'session_type' => 'chat',
        ]);

        $voice = UploadedFile::fake()->create('check-in.webm', 128, 'audio/webm');

        $voiceResponse = $this->actingAs($this->peerCounselor)->post('/api/chat/upload-file', [
            'session_id' => $peerSession->id,
            'message_type' => 'voice',
            'file' => $voice,
        ]);

        $voiceResponse
            ->assertStatus(201)
            ->assertJsonPath('message_type', 'voice')
            ->assertJsonPath('has_file', true);

        $this->assertDatabaseHas('messages', [
            'id' => (int) $voiceResponse->json('id'),
            'session_id' => $peerSession->id,
            'sender_id' => $this->peerCounselor->id,
            'recipient_id' => $this->student->id,
            'message_type' => 'voice',
            'has_file' => true,
        ]);
        $this->assertTrue($this->messageNotificationExistsFor($this->student->id, (int) $voiceResponse->json('id')));
        $this->assertFalse($this->messageNotificationExistsFor($this->counselor->id, (int) $voiceResponse->json('id')));

        $endpointVoice = UploadedFile::fake()->create('endpoint-voice.webm', 128, 'audio/webm');

        $endpointVoiceResponse = $this->actingAs($this->peerCounselor)->post("/api/sessions/{$peerSession->id}/voice-notes", [
            'audio' => $endpointVoice,
        ]);

        $endpointVoiceResponse
            ->assertStatus(201)
            ->assertJsonPath('message_type', 'voice')
            ->assertJsonPath('has_file', true);
        $this->assertTrue($this->messageNotificationExistsFor($this->student->id, (int) $endpointVoiceResponse->json('id')));
        $this->assertFalse($this->messageNotificationExistsFor($this->counselor->id, (int) $endpointVoiceResponse->json('id')));

        AiDiagnostic::create([
            'student_id' => $this->student->id,
            'session_id' => (string) $peerSession->id,
            'risk_level' => 'high',
        ]);

        $blockedVoice = UploadedFile::fake()->create('blocked-voice.webm', 128, 'audio/webm');

        $blockedVoiceResponse = $this->actingAs($this->peerCounselor)->post("/api/sessions/{$peerSession->id}/voice-notes", [
            'audio' => $blockedVoice,
        ]);

        $blockedVoiceResponse
            ->assertStatus(422)
            ->assertJsonPath('risk_level', 'high');

        $document = UploadedFile::fake()->create('notes.pdf', 64, 'application/pdf');

        $documentResponse = $this->actingAs($this->peerCounselor)->post('/api/chat/upload-file', [
            'session_id' => $peerSession->id,
            'message_type' => 'file',
            'file' => $document,
        ]);

        $documentResponse->assertStatus(422);
    }

    /** @test */
    public function peer_assignment_table_access_uses_same_voice_and_attachment_rules(): void
    {
        $this->session->update(['status' => 'completed']);
        $legacySession = CounselingSession::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'peer_counselor_id' => null,
            'assigned_role' => 'counselor',
            'status' => 'active',
            'session_type' => 'chat',
        ]);

        PeerAssignment::create([
            'session_id' => $legacySession->id,
            'peer_counselor_id' => $this->peerCounselor->id,
            'assigned_by' => $this->counselor->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $peerVoice = UploadedFile::fake()->create('legacy-peer.webm', 128, 'audio/webm');
        $peerVoiceResponse = $this->actingAs($this->peerCounselor)->post("/api/sessions/{$legacySession->id}/voice-notes", [
            'audio' => $peerVoice,
        ]);

        $peerVoiceResponse
            ->assertStatus(201)
            ->assertJsonPath('sender_id', $this->peerCounselor->id)
            ->assertJsonPath('recipient_id', $this->student->id)
            ->assertJsonPath('message_type', 'voice');

        $studentVoice = UploadedFile::fake()->create('legacy-student.webm', 128, 'audio/webm');
        $studentVoiceResponse = $this->actingAs($this->student)->post('/api/chat/upload-file', [
            'session_id' => $legacySession->id,
            'message_type' => 'voice',
            'file' => $studentVoice,
        ]);

        $studentVoiceResponse
            ->assertStatus(201)
            ->assertJsonPath('sender_id', $this->student->id)
            ->assertJsonPath('recipient_id', $this->peerCounselor->id)
            ->assertJsonPath('message_type', 'voice');

        $document = UploadedFile::fake()->create('legacy-notes.pdf', 64, 'application/pdf');
        $documentResponse = $this->actingAs($this->peerCounselor)->post('/api/chat/upload-file', [
            'session_id' => $legacySession->id,
            'message_type' => 'file',
            'file' => $document,
        ]);

        $documentResponse
            ->assertStatus(422)
            ->assertJsonPath('message', 'Peer counselors can send voice notes, but cannot upload file attachments in supervised chat.');
    }

    private function assignRole(User $user, string $role): void
    {
        $user->roles()->create([
            'role' => $role,
            'approved' => true,
        ]);
    }

    private function messageNotificationExistsFor(int $userId, int $messageId): bool
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->where('meta->chat_message_id', $messageId)
            ->exists();
    }
}
