<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\StudentMoodLog;
use App\Models\Tip;
use App\Models\TipDelivery;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TipOfDayService
{
    /**
     * @return array<string, mixed>|null
     */
    public function resolveForUser(User $user): ?array
    {
        $audience = $this->resolveAudience($user);

        $today = now()->toDateString();
        $existingDelivery = TipDelivery::query()
            ->with('tip')
            ->where('user_id', $user->id)
            ->whereDate('delivered_on', $today)
            ->first();

        if ($existingDelivery instanceof TipDelivery && $existingDelivery->tip instanceof Tip) {
            $this->ensureNotification($existingDelivery, $user);

            return $this->buildPayload($existingDelivery->tip, $existingDelivery, $user);
        }

        // Tier 1: audience-specific or universal tips
        $eligibleTips = Tip::query()
            ->where('is_active', true)
            ->whereIn('audience', ['all', $audience])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        if ($eligibleTips->isEmpty()) {
            // Tier 2: only 'all' audience
            $eligibleTips = Tip::query()
                ->where('is_active', true)
                ->where('audience', 'all')
                ->get();
        }

        if ($eligibleTips->isEmpty()) {
            // Tier 3: any active tip regardless of audience — never leave the card empty
            $eligibleTips = Tip::query()
                ->where('is_active', true)
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get();
        }

        if ($eligibleTips->isEmpty()) {
            return null;
        }

        $latestMood = $this->resolveMood($user, $audience);
        $personalizedTips = $latestMood !== null
            ? $eligibleTips->filter(function (Tip $tip) use ($latestMood) {
                $moodTags = collect($tip->mood_tags ?? [])
                    ->map(fn ($item) => strtolower(trim((string) $item)))
                    ->filter()
                    ->values();

                return $moodTags->contains($latestMood);
            })
            : collect();

        $pool = $personalizedTips->isNotEmpty() ? $personalizedTips->values() : $eligibleTips->values();
        $selectedTip = $this->rotateFromPool($user, $pool, $latestMood, $personalizedTips->isNotEmpty());
        if (! $selectedTip instanceof Tip) {
            return null;
        }

        $delivery = TipDelivery::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'delivered_on' => $today,
            ],
            [
                'tip_id' => $selectedTip->id,
                'audience' => $audience,
                'mood' => $personalizedTips->isNotEmpty() ? $latestMood : null,
                'personalized' => $personalizedTips->isNotEmpty(),
            ]
        );

        $delivery->setRelation('tip', $selectedTip);
        $this->ensureNotification($delivery, $user);

        return $this->buildPayload($selectedTip, $delivery, $user);
    }

    private function resolveAudience(User $user): string
    {
        if ($user->hasRole('admin')) {
            return 'admin';
        }
        if ($user->hasRole('counselor')) {
            return 'counselor';
        }
        if ($user->hasRole('peer_counselor')) {
            return 'peer_counselor';
        }

        return 'student';
    }

    private function resolveMood(User $user, string $audience): ?string
    {
        if ($audience !== 'student') {
            return null;
        }

        $latestMood = StudentMoodLog::query()
            ->where('student_id', $user->id)
            ->orderByDesc('logged_on')
            ->orderByDesc('created_at')
            ->value('mood');

        $normalized = strtolower(trim((string) $latestMood));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  Collection<int, Tip>  $pool
     */
    private function rotateFromPool(User $user, Collection $pool, ?string $mood, bool $personalized): ?Tip
    {
        if ($pool->isEmpty()) {
            return null;
        }

        $dayIndex = Carbon::create(2026, 1, 1, 0, 0, 0, config('app.timezone'))->diffInDays(now()->startOfDay());
        $seed = abs(crc32(implode('|', [
            (string) $user->id,
            $this->resolveAudience($user),
            $personalized ? (string) $mood : 'general',
        ])));

        $index = ($dayIndex + $seed) % $pool->count();

        return $pool->values()->get($index);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Tip $tip, TipDelivery $delivery, User $user): array
    {
        return [
            'id' => $tip->id,
            'title' => $tip->title,
            'content' => $tip->content,
            'category' => $tip->category,
            'audience' => $tip->audience,
            'mood_tags' => $tip->mood_tags ?? [],
            'priority' => $tip->priority,
            'is_active' => $tip->is_active,
            'personalized' => (bool) $delivery->personalized,
            'mood' => $delivery->mood,
            'served_for_date' => $delivery->delivered_on instanceof Carbon
                ? $delivery->delivered_on->toDateString()
                : (is_string($delivery->delivered_on) ? substr($delivery->delivered_on, 0, 10) : null),
            'delivered_at' => $delivery->created_at?->toISOString(),
            'is_favorite' => $user->tipFavorites()->where('tip_id', $tip->id)->exists(),
        ];
    }

    private function ensureNotification(TipDelivery $delivery, User $user): void
    {
        if ($delivery->notification_id) {
            return;
        }

        $tip = $delivery->relationLoaded('tip')
            ? $delivery->tip
            : $delivery->tip()->first();

        if (! $tip instanceof Tip) {
            return;
        }

        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'title' => 'Daily Wellness Tip',
            'message' => trim($tip->title.'. '.$tip->content),
            'type' => 'info',
            'read' => false,
        ]);

        $delivery->forceFill([
            'notification_id' => $notification->id,
        ])->save();
    }
}
