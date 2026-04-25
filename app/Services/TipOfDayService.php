<?php

namespace App\Services;

use App\Models\StudentMoodLog;
use App\Models\Tip;
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
        $eligibleTips = Tip::query()
            ->where('is_active', true)
            ->whereIn('audience', ['all', $audience])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

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
        if (!$selectedTip instanceof Tip) {
            return null;
        }

        return [
            'id' => $selectedTip->id,
            'title' => $selectedTip->title,
            'content' => $selectedTip->content,
            'category' => $selectedTip->category,
            'audience' => $selectedTip->audience,
            'mood_tags' => $selectedTip->mood_tags ?? [],
            'personalized' => $personalizedTips->isNotEmpty(),
            'mood' => $personalizedTips->isNotEmpty() ? $latestMood : null,
            'served_for_date' => now()->toDateString(),
        ];
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
}
