<?php

namespace Tests\Feature;

use App\Mail\InAppNotificationMail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.frontend_url' => 'https://mindful.example',
            'notifications.email.enabled' => true,
            'notifications.email.privacy_safe' => true,
        ]);
    }

    #[Test]
    public function notification_creation_sends_an_email_when_enabled(): void
    {
        Mail::fake();

        $user = $this->createPortalUser('student', 'student-email@test.com');

        Notification::query()->create([
            'user_id' => $user->id,
            'title' => 'Appointment confirmed',
            'message' => 'Your appointment with a counselor is now confirmed.',
            'type' => 'success',
            'meta' => [
                'appointment_id' => 123,
            ],
        ]);

        Mail::assertSent(InAppNotificationMail::class, function (InAppNotificationMail $mail) {
            return $mail->hasTo('student-email@test.com')
                && $mail->subjectLine === 'Appointment confirmed'
                && $mail->bodyText === 'There is an appointment update in Mindful AU. Sign in to view the details.'
                && $mail->actionUrl === 'https://mindful.example/student/appointments';
        });
    }

    #[Test]
    public function chat_notification_email_uses_privacy_safe_content(): void
    {
        Mail::fake();

        $counselor = $this->createPortalUser('counselor', 'counselor-email@test.com');

        Notification::query()->create([
            'user_id' => $counselor->id,
            'title' => 'New message',
            'message' => 'Student Name: I feel overwhelmed and need support tonight.',
            'type' => 'info',
            'meta' => [
                'chat_session_id' => 42,
                'chat_message_id' => 99,
            ],
        ]);

        Mail::assertSent(InAppNotificationMail::class, function (InAppNotificationMail $mail) {
            return $mail->hasTo('counselor-email@test.com')
                && $mail->subjectLine === 'New Mindful AU message'
                && $mail->bodyText === 'You have a new secure message in Mindful AU. Sign in to view and respond.'
                && ! str_contains($mail->bodyText, 'overwhelmed')
                && $mail->actionUrl === 'https://mindful.example/counselor/messages?session=42';
        });
    }

    #[Test]
    public function user_can_disable_email_notification_delivery(): void
    {
        Mail::fake();

        $user = $this->createPortalUser('student', 'email-off@test.com', false);

        Notification::query()->create([
            'user_id' => $user->id,
            'title' => 'Appointment confirmed',
            'message' => 'Your appointment was confirmed.',
            'type' => 'success',
        ]);

        Mail::assertNothingSent();
    }

    #[Test]
    public function authenticated_user_can_update_email_notification_preference(): void
    {
        $user = $this->createPortalUser('student', 'preference@test.com');

        $this->actingAs($user)
            ->patchJson('/api/notifications/preferences', [
                'email_enabled' => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('email_notifications_enabled', false);

        $this->assertFalse((bool) $user->fresh()->email_notifications_enabled);
    }

    private function createPortalUser(
        string $role,
        string $email,
        bool $emailNotificationsEnabled = true,
    ): User {
        $user = User::factory()->create([
            'email' => $email,
            'email_notifications_enabled' => $emailNotificationsEnabled,
        ]);

        $user->roles()->create([
            'role' => $role,
            'approved' => true,
        ]);

        return $user;
    }
}
