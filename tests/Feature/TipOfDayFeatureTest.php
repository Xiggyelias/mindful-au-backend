<?php

namespace Tests\Feature;

use App\Models\StudentMoodLog;
use App\Models\Tip;
use App\Models\TipDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TipOfDayFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tip::query()->delete();
    }

    #[Test]
    public function admin_can_create_update_and_delete_tips(): void
    {
        $admin = $this->createPortalUser('admin', 'tip-admin@test.com', 'Tip Admin');

        $create = $this->actingAs($admin)->postJson('/api/tips', [
            'title' => 'Daily reset',
            'content' => 'Pause, breathe, and choose the next small action.',
            'category' => 'Wellness',
            'audience' => 'all',
            'priority' => 12,
            'is_active' => true,
            'mood_tags' => ['stressed'],
        ]);

        $create->assertStatus(201);
        $tipId = (int) $create->json('tip.id');

        $this->actingAs($admin)
            ->putJson("/api/tips/{$tipId}", [
                'title' => 'Daily reset updated',
                'content' => 'Pause, breathe, hydrate, and choose the next small action.',
                'category' => 'Wellness',
                'audience' => 'student',
                'priority' => 18,
                'is_active' => true,
                'mood_tags' => ['tired', 'stressed'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('tip.title', 'Daily reset updated')
            ->assertJsonPath('tip.audience', 'student');

        $this->actingAs($admin)
            ->getJson('/api/tips')
            ->assertStatus(200)
            ->assertJsonCount(1, 'tips');

        $this->actingAs($admin)
            ->deleteJson("/api/tips/{$tipId}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('tips', ['id' => $tipId]);
    }

    #[Test]
    public function student_receives_personalized_tip_when_matching_mood_tip_exists(): void
    {
        $student = $this->createPortalUser('student', 'tip-student@test.com', 'Tip Student');

        Tip::query()->create([
            'title' => 'General support',
            'content' => 'Take one kind step for yourself before the next task.',
            'category' => 'Wellness',
            'audience' => 'student',
            'priority' => 5,
            'is_active' => true,
            'mood_tags' => [],
        ]);

        Tip::query()->create([
            'title' => 'Tired-day recovery',
            'content' => 'If you are tired, reduce the task to the smallest useful version and recover between steps.',
            'category' => 'Wellness',
            'audience' => 'student',
            'priority' => 10,
            'is_active' => true,
            'mood_tags' => ['tired'],
        ]);

        StudentMoodLog::query()->create([
            'student_id' => $student->id,
            'mood' => 'tired',
            'logged_on' => now()->toDateString(),
        ]);

        $response = $this->actingAs($student)->getJson('/api/wellness/tip');

        $response
            ->assertStatus(200)
            ->assertJsonPath('tip.title', 'Tired-day recovery')
            ->assertJsonPath('tip.personalized', true)
            ->assertJsonPath('tip.mood', 'tired')
            ->assertJsonPath('tip.is_favorite', false);

        $this->assertDatabaseHas('tip_deliveries', [
            'user_id' => $student->id,
            'tip_id' => $response->json('tip.id'),
            'personalized' => true,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'title' => 'Daily Wellness Tip',
        ]);
    }

    #[Test]
    public function tip_rotation_cycles_without_repeating_until_the_pool_is_exhausted(): void
    {
        $student = $this->createPortalUser('student', 'tip-rotation@test.com', 'Tip Rotation Student');

        foreach ([
            ['First tip', 'First daily tip content.'],
            ['Second tip', 'Second daily tip content.'],
            ['Third tip', 'Third daily tip content.'],
        ] as $index => [$title, $content]) {
            Tip::query()->create([
                'title' => $title,
                'content' => $content,
                'category' => 'Study',
                'audience' => 'student',
                'priority' => 10 - $index,
                'is_active' => true,
                'mood_tags' => [],
            ]);
        }

        $dates = [
            '2026-04-24 09:00:00',
            '2026-04-25 09:00:00',
            '2026-04-26 09:00:00',
            '2026-04-27 09:00:00',
        ];

        $tipIds = [];

        foreach ($dates as $date) {
            Carbon::setTestNow($date);
            $tipIds[] = (int) $this->actingAs($student)
                ->getJson('/api/tips/today')
                ->json('tip.id');
        }

        Carbon::setTestNow();

        $this->assertCount(3, array_unique(array_slice($tipIds, 0, 3)));
        $this->assertSame($tipIds[0], $tipIds[3]);
    }

    #[Test]
    public function notifications_endpoint_primes_the_daily_wellness_tip_notification_once_per_day(): void
    {
        $student = $this->createPortalUser('student', 'tip-notify@test.com', 'Tip Notify Student');

        Tip::query()->create([
            'title' => 'Wellness primer',
            'content' => 'Take one steady breath before your next task.',
            'category' => 'Stress Management',
            'audience' => 'all',
            'priority' => 9,
            'is_active' => true,
            'mood_tags' => [],
        ]);

        $this->actingAs($student)
            ->getJson('/api/notifications')
            ->assertStatus(200);

        $this->actingAs($student)
            ->getJson('/api/notifications')
            ->assertStatus(200);

        $this->assertSame(
            1,
            TipDelivery::query()
                ->where('user_id', $student->id)
                ->whereDate('delivered_on', now()->toDateString())
                ->count()
        );

        $this->assertSame(
            1,
            $student->notifications()->where('title', 'Daily Wellness Tip')->count()
        );
    }

    #[Test]
    public function user_can_save_and_remove_a_favorite_tip(): void
    {
        $student = $this->createPortalUser('student', 'tip-favorite@test.com', 'Tip Favorite Student');

        $tip = Tip::query()->create([
            'title' => 'Saveable tip',
            'content' => 'Start with the smallest helpful step you can take today.',
            'category' => 'Motivation & Productivity',
            'audience' => 'all',
            'priority' => 7,
            'is_active' => true,
            'mood_tags' => [],
        ]);

        $this->actingAs($student)
            ->postJson("/api/wellness/tips/{$tip->id}/favorite")
            ->assertStatus(200)
            ->assertJsonPath('tip.is_favorite', true);

        $this->assertDatabaseHas('tip_favorites', [
            'user_id' => $student->id,
            'tip_id' => $tip->id,
        ]);

        $this->actingAs($student)
            ->deleteJson("/api/wellness/tips/{$tip->id}/favorite")
            ->assertStatus(200)
            ->assertJsonPath('tip.is_favorite', false);

        $this->assertDatabaseMissing('tip_favorites', [
            'user_id' => $student->id,
            'tip_id' => $tip->id,
        ]);
    }

    #[Test]
    public function admin_tip_validation_rejects_harmful_or_overlong_tip_content(): void
    {
        $admin = $this->createPortalUser('admin', 'tip-guard@test.com', 'Tip Guard Admin');

        $this->actingAs($admin)
            ->postJson('/api/tips', [
                'title' => 'Unsafe tip',
                'content' => 'Please diagnose yourself. Skip your medication. Keep going no matter what.',
                'category' => 'Emotional Wellbeing',
                'audience' => 'all',
                'priority' => 10,
                'is_active' => true,
                'mood_tags' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('content');
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
