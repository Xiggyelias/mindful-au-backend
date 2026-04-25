<?php

namespace Database\Seeders;

use App\Models\Tip;
use Illuminate\Database\Seeder;

class TipSeeder extends Seeder
{
    public function run(): void
    {
        $tips = [
            ['title' => 'Reset with one deep breath', 'content' => 'Pause for ten seconds, inhale slowly, and let your shoulders drop before starting the next task.', 'category' => 'Mental Health', 'audience' => 'all', 'priority' => 10],
            ['title' => 'Break large work into sprints', 'content' => 'Choose one small task you can finish in fifteen minutes. Momentum is often more helpful than pressure.', 'category' => 'Productivity', 'audience' => 'student', 'priority' => 9],
            ['title' => 'Hydrate before another meeting', 'content' => 'A short water break can reduce tension and help you stay present in the next conversation.', 'category' => 'Wellness', 'audience' => 'all', 'priority' => 7],
            ['title' => 'Name the emotion first', 'content' => 'When stress rises, try naming the feeling before reacting. Labeling can make it easier to regulate.', 'category' => 'Mental Health', 'audience' => 'student', 'priority' => 10, 'mood_tags' => ['stressed', 'low']],
            ['title' => 'Protect a short recovery block', 'content' => 'Schedule a short gap between demanding tasks so your attention can reset instead of stacking fatigue.', 'category' => 'Productivity', 'audience' => 'counselor', 'priority' => 8],
            ['title' => 'Prepare one supportive question', 'content' => 'Before a session, choose one open question that invites the other person to describe what matters most today.', 'category' => 'Counseling Practice', 'audience' => 'counselor', 'priority' => 9],
            ['title' => 'Notice the small win', 'content' => 'Progress is not always dramatic. A calmer hour, a completed class, or asking for help still counts.', 'category' => 'Motivation', 'audience' => 'student', 'priority' => 9, 'mood_tags' => ['okay', 'low']],
            ['title' => 'Keep your phone out of reach', 'content' => 'If you need focused study time, create physical distance from distractions for the first twenty minutes.', 'category' => 'Study Habits', 'audience' => 'student', 'priority' => 7],
            ['title' => 'Respond after a pause', 'content' => 'When a difficult message arrives, take one breath before replying. Calm pacing improves clarity.', 'category' => 'Communication', 'audience' => 'peer_counselor', 'priority' => 8],
            ['title' => 'Escalate early, not late', 'content' => 'If a conversation feels beyond your role, escalate promptly. Early escalation protects both helper and student.', 'category' => 'Peer Support', 'audience' => 'peer_counselor', 'priority' => 10],
            ['title' => 'Plan tomorrow before closing today', 'content' => 'Write down the first task for tomorrow before you log off. It reduces startup friction the next day.', 'category' => 'Productivity', 'audience' => 'admin', 'priority' => 8],
            ['title' => 'Check workload balance', 'content' => 'A quick review of open queues can reveal overload early and prevent avoidable delays for students.', 'category' => 'Operations', 'audience' => 'admin', 'priority' => 9],
            ['title' => 'Step away from the screen', 'content' => 'If your eyes or thoughts feel heavy, take a brief walk or look away from the screen for a minute.', 'category' => 'Wellness', 'audience' => 'all', 'priority' => 7, 'mood_tags' => ['tired', 'stressed']],
            ['title' => 'Use a grounding detail', 'content' => 'Look for five things you can see or hear around you. Grounding helps when thoughts feel too fast.', 'category' => 'Mental Health', 'audience' => 'student', 'priority' => 10, 'mood_tags' => ['stressed']],
            ['title' => 'Keep recovery visible', 'content' => 'Treat rest as part of performance. Small breaks protect long-term consistency better than overextension.', 'category' => 'Motivation', 'audience' => 'all', 'priority' => 8],
            ['title' => 'Review notes before follow-up', 'content' => 'A two-minute note review can improve continuity and make the next session feel more intentional.', 'category' => 'Counseling Practice', 'audience' => 'counselor', 'priority' => 8],
            ['title' => 'Ask for support early', 'content' => 'You do not need to wait for a crisis before reaching out. Early support usually makes the next step easier.', 'category' => 'Mental Health', 'audience' => 'student', 'priority' => 9],
            ['title' => 'Close one open loop', 'content' => 'Choose one unfinished item and complete it before starting another. Fewer open loops can lower stress.', 'category' => 'Productivity', 'audience' => 'all', 'priority' => 7],
            ['title' => 'Respect quiet time', 'content' => 'Short periods of silence can help someone think, regulate, and answer more honestly.', 'category' => 'Peer Support', 'audience' => 'peer_counselor', 'priority' => 8],
            ['title' => 'Lead with clarity in updates', 'content' => 'A short, direct status update with the next action usually saves more time than a long explanation.', 'category' => 'Operations', 'audience' => 'admin', 'priority' => 7],
            ['title' => 'Pair studying with a ritual', 'content' => 'Use the same simple startup routine each time you study. Consistent cues reduce resistance.', 'category' => 'Study Habits', 'audience' => 'student', 'priority' => 8],
            ['title' => 'Treat fatigue as data', 'content' => 'If you feel drained, adjust pace, workload, or support. Fatigue is often information, not failure.', 'category' => 'Wellness', 'audience' => 'all', 'priority' => 8, 'mood_tags' => ['tired', 'low']],
            ['title' => 'Document patterns, not just events', 'content' => 'When reviewing service quality, recurring patterns usually matter more than isolated incidents.', 'category' => 'Operations', 'audience' => 'admin', 'priority' => 8],
            ['title' => 'Use compassionate boundaries', 'content' => 'You can be warm and still keep clear boundaries. Boundaries protect consistency and safety.', 'category' => 'Counseling Practice', 'audience' => 'counselor', 'priority' => 9],
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
                    'mood_tags' => $tip['mood_tags'] ?? [],
                    'priority' => $tip['priority'] ?? 0,
                    'is_active' => true,
                ]
            );
        }
    }
}
