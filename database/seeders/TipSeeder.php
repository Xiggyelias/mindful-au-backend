<?php

namespace Database\Seeders;

use App\Models\Tip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TipSeeder extends Seeder
{
    public function run(): void
    {
        $wellnessCategories = [
            'Stress Management' => [
                'Take 5 slow, deep breaths and focus only on your breathing.',
                'Break big tasks into smaller steps to reduce overwhelm.',
                'Step away for a short walk when things feel too intense.',
                "Write down what's stressing you. It helps clear your mind.",
                'Give yourself permission to rest without guilt.',
                'Listen to calming music for a few minutes.',
                'Stretch your body to release physical tension.',
                "Focus on what you can control, not what you can't.",
                'Take a break from screens for at least 10 minutes.',
                'Remind yourself: This moment will pass.',
            ],
            'Anxiety Relief' => [
                'Ground yourself by naming 5 things you can see and 4 you can touch.',
                'Slow your breathing: in for 4 seconds, out for 6.',
                'Avoid overthinking by focusing on the present moment.',
                'Talk to someone you trust about how you feel.',
                "Limit caffeine if you're feeling anxious.",
                'Challenge negative thoughts and ask, Is this really true?',
                'Keep your hands busy with drawing, writing, or a stress ball.',
                'Create a calm space around you.',
                'Practice mindfulness for a few minutes.',
                'Remind yourself: I am safe right now.',
            ],
            'Motivation & Productivity' => [
                'Start small. Progress is still progress.',
                'Set one clear goal for today and focus on it.',
                'Reward yourself after completing tasks.',
                "Don't wait for motivation. Take action first.",
                'Remove distractions when working.',
                'Visualize your success before starting.',
                'Stay consistent, even if progress feels slow.',
                'Celebrate small wins because they matter.',
                'Keep a simple to-do list.',
                'Focus on effort, not perfection.',
            ],
            'Study & Focus' => [
                'Study in short sessions of 25 to 30 minutes with breaks.',
                'Keep your study space clean and organized.',
                "Teach what you've learned to someone else.",
                'Turn off notifications while studying.',
                'Stay hydrated to maintain focus.',
                'Review notes regularly instead of cramming.',
                'Use active recall instead of just rereading.',
                'Study at the same time daily to build habit.',
                'Take short breaks to refresh your mind.',
                'Prioritize difficult subjects first.',
            ],
            'Emotional Wellbeing' => [
                "It's okay to not feel okay sometimes.",
                'Be kind to yourself because your thoughts matter.',
                'Express your feelings instead of holding them in.',
                'Spend time doing something you enjoy.',
                'Connect with someone who makes you feel safe.',
                "Practice gratitude and list 3 things you're thankful for.",
                "Let go of things you can't change.",
                "Give yourself credit for how far you've come.",
                'Rest when your body asks for it.',
                'Speak to yourself like you would a friend.',
            ],
            'Daily Habits & Self-Care' => [
                'Drink a glass of water first thing in the morning.',
                'Get some sunlight to boost your mood.',
                "Move your body, even if it's just a short walk.",
                'Eat regularly and nourish your body.',
                'Keep a consistent sleep schedule.',
                'Take a moment to stretch during the day.',
                'Limit social media if it affects your mood.',
                'Clean a small space because it can clear your mind too.',
                'Take deep breaths before starting your day.',
                'End your day with something relaxing.',
            ],
            'Confidence & Growth' => [
                'Believe in your ability to improve.',
                'Mistakes are part of learning.',
                "You don't need to compare your journey to others.",
                'Speak up because your voice matters.',
                'Try something new, even if it feels uncomfortable.',
                'Focus on your strengths.',
                'Growth takes time, so be patient with yourself.',
                "Replace I can't with I'll try.",
                'Be proud of your effort, not just results.',
                'Trust the process.',
            ],
            'Social & Relationships' => [
                'Listen fully when someone is speaking.',
                'Respect your own boundaries.',
                "It's okay to say no.",
                'Spend time with people who uplift you.',
                'Apologize when necessary because it shows strength.',
                'Communicate clearly and honestly.',
                'Avoid toxic conversations when possible.',
                "Support others, but don't forget yourself.",
                'Ask for help when you need it.',
                'Kindness goes a long way.',
            ],
        ];

        foreach ($wellnessCategories as $category => $tips) {
            foreach ($tips as $index => $content) {
                Tip::query()->updateOrCreate(
                    [
                        'title' => $this->buildTitle($category, $content, $index),
                        'audience' => 'all',
                    ],
                    [
                        'content' => $content,
                        'category' => $category,
                        'mood_tags' => $this->moodTagsForCategory($category),
                        'priority' => 8,
                        'is_active' => true,
                    ]
                );
            }
        }

        $roleSpecificTips = [
            [
                'title' => 'Protect a short recovery block',
                'content' => 'Schedule a short gap between demanding tasks so your attention can reset instead of stacking fatigue.',
                'category' => 'Counselor Wellbeing',
                'audience' => 'counselor',
                'mood_tags' => ['stressed', 'tired'],
                'priority' => 10,
            ],
            [
                'title' => 'Use compassionate boundaries',
                'content' => 'You can be warm and still keep clear boundaries. Boundaries protect consistency and safety.',
                'category' => 'Counselor Wellbeing',
                'audience' => 'counselor',
                'mood_tags' => ['okay', 'stressed'],
                'priority' => 10,
            ],
            [
                'title' => 'Prepare one supportive question',
                'content' => 'Before a session, choose one open question that invites the other person to describe what matters most today.',
                'category' => 'Counselor Practice',
                'audience' => 'counselor',
                'mood_tags' => [],
                'priority' => 9,
            ],
            [
                'title' => 'Escalate early, not late',
                'content' => 'If a conversation feels beyond your role, escalate promptly. Early escalation protects both helper and student.',
                'category' => 'Peer Support',
                'audience' => 'peer_counselor',
                'mood_tags' => [],
                'priority' => 10,
            ],
            [
                'title' => 'Respond after a pause',
                'content' => 'When a difficult message arrives, take one breath before replying. Calm pacing improves clarity.',
                'category' => 'Peer Support',
                'audience' => 'peer_counselor',
                'mood_tags' => ['stressed'],
                'priority' => 9,
            ],
            [
                'title' => 'Check workload balance',
                'content' => 'A quick review of open queues can reveal overload early and prevent avoidable delays for students.',
                'category' => 'Operations',
                'audience' => 'admin',
                'mood_tags' => [],
                'priority' => 9,
            ],
            [
                'title' => 'Plan tomorrow before closing today',
                'content' => 'Write down the first task for tomorrow before you log off. It reduces startup friction the next day.',
                'category' => 'Operations',
                'audience' => 'admin',
                'mood_tags' => ['tired'],
                'priority' => 8,
            ],
        ];

        foreach ($roleSpecificTips as $tip) {
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
                    'is_active' => true,
                ]
            );
        }
    }

    private function buildTitle(string $category, string $content, int $index): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9 ]+/', '', $content) ?? $content;
        $title = Str::of($normalized)
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(6)
            ->implode(' ');

        $prefix = match ($category) {
            'Stress Management' => 'Reset',
            'Anxiety Relief' => 'Calm',
            'Motivation & Productivity' => 'Momentum',
            'Study & Focus' => 'Focus',
            'Emotional Wellbeing' => 'Care',
            'Daily Habits & Self-Care' => 'Habit',
            'Confidence & Growth' => 'Growth',
            'Social & Relationships' => 'Connection',
            default => 'Wellness',
        };

        return sprintf('%s %02d: %s', $prefix, $index + 1, Str::limit((string) $title, 48, ''));
    }

    /**
     * @return array<int, string>
     */
    private function moodTagsForCategory(string $category): array
    {
        return match ($category) {
            'Stress Management' => ['stressed', 'tired'],
            'Anxiety Relief' => ['stressed', 'low'],
            'Motivation & Productivity' => ['okay', 'low'],
            'Study & Focus' => ['okay', 'tired'],
            'Emotional Wellbeing' => ['low', 'okay'],
            'Daily Habits & Self-Care' => ['tired', 'stressed'],
            'Confidence & Growth' => ['low', 'okay'],
            'Social & Relationships' => ['okay', 'stressed'],
            default => [],
        };
    }
}
