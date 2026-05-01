<?php

use App\Models\Tip;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tips = [
            // Stress Management
            [
                'title' => 'Breathe Deeply',
                'content' => 'Take 5 slow, deep breaths and focus only on your breathing.',
                'category' => 'Stress Management',
                'audience' => 'student',
                'mood_tags' => ['stress', 'calm'],
                'priority' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Break Tasks Down',
                'content' => 'Break big tasks into smaller steps to reduce overwhelm.',
                'category' => 'Stress Management',
                'audience' => 'student',
                'mood_tags' => ['stress', 'productivity'],
                'priority' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Take a Walk',
                'content' => 'Step away for a short walk when things feel too intense.',
                'category' => 'Stress Management',
                'audience' => 'student',
                'mood_tags' => ['stress', 'relief'],
                'priority' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Write It Down',
                'content' => "Write down what's stressing you—it helps clear your mind.",
                'category' => 'Stress Management',
                'audience' => 'student',
                'mood_tags' => ['stress', 'clarity'],
                'priority' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Rest Without Guilt',
                'content' => 'Give yourself permission to rest without guilt.',
                'category' => 'Stress Management',
                'audience' => 'student',
                'mood_tags' => ['stress', 'selfcare'],
                'priority' => 1,
                'is_active' => true,
            ],

            // Anxiety Relief
            [
                'title' => '5-4-3-2-1 Grounding',
                'content' => 'Ground yourself by naming 5 things you can see, 4 you can touch.',
                'category' => 'Anxiety Relief',
                'audience' => 'student',
                'mood_tags' => ['anxiety', 'grounding'],
                'priority' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Controlled Breathing',
                'content' => 'Slow your breathing: in for 4 seconds, out for 6.',
                'category' => 'Anxiety Relief',
                'audience' => 'student',
                'mood_tags' => ['anxiety', 'calm'],
                'priority' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Stay Present',
                'content' => 'Avoid overthinking by focusing on the present moment.',
                'category' => 'Anxiety Relief',
                'audience' => 'student',
                'mood_tags' => ['anxiety', 'mindfulness'],
                'priority' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Talk It Out',
                'content' => 'Talk to someone you trust about how you feel.',
                'category' => 'Anxiety Relief',
                'audience' => 'student',
                'mood_tags' => ['anxiety', 'support'],
                'priority' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Reduce Caffeine',
                'content' => "Limit caffeine if you're feeling anxious.",
                'category' => 'Anxiety Relief',
                'audience' => 'student',
                'mood_tags' => ['anxiety', 'health'],
                'priority' => 1,
                'is_active' => true,
            ],

            // Motivation & Productivity
            [
                'title' => 'Start Small',
                'content' => 'Start small—progress is still progress.',
                'category' => 'Motivation & Productivity',
                'audience' => 'student',
                'mood_tags' => ['motivation', 'progress'],
                'priority' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Set Daily Goal',
                'content' => 'Set one clear goal for today and focus on it.',
                'category' => 'Motivation & Productivity',
                'audience' => 'student',
                'mood_tags' => ['motivation', 'focus'],
                'priority' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Reward Yourself',
                'content' => 'Reward yourself after completing tasks.',
                'category' => 'Motivation & Productivity',
                'audience' => 'student',
                'mood_tags' => ['motivation', 'reward'],
                'priority' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Take Action First',
                'content' => "Don't wait for motivation—take action first.",
                'category' => 'Motivation & Productivity',
                'audience' => 'student',
                'mood_tags' => ['motivation', 'discipline'],
                'priority' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Remove Distractions',
                'content' => 'Remove distractions when working.',
                'category' => 'Motivation & Productivity',
                'audience' => 'student',
                'mood_tags' => ['productivity', 'focus'],
                'priority' => 2,
                'is_active' => true,
            ],
        ];

        foreach ($tips as $tip) {
            Tip::query()->updateOrCreate(
                [
                    'title' => $tip['title'],
                    'audience' => $tip['audience'],
                ],
                [
                    'content' => $tip['content'],
                    'category' => $tip['category'],
                    'mood_tags' => $tip['mood_tags'],
                    'priority' => $tip['priority'],
                    'is_active' => $tip['is_active'],
                ]
            );
        }
    }

    public function down(): void
    {
        // Remove the inserted tips
        $titles = [
            'Breathe Deeply', 'Break Tasks Down', 'Take a Walk', 'Write It Down', 'Rest Without Guilt',
            '5-4-3-2-1 Grounding', 'Controlled Breathing', 'Stay Present', 'Talk It Out', 'Reduce Caffeine',
            'Start Small', 'Set Daily Goal', 'Reward Yourself', 'Take Action First', 'Remove Distractions',
        ];

        Tip::query()
            ->whereIn('title', $titles)
            ->where('audience', 'student')
            ->delete();
    }
};
