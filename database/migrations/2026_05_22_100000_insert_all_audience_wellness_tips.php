<?php

use App\Models\Tip;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $tips = [
            // ── Core Self-Care ────────────────────────────────────────────────────
            [
                'title'     => 'Check In with Yourself',
                'content'   => 'Before supporting others, take a moment to notice how you are feeling right now.',
                'category'  => 'Self-Care',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['burnout', 'selfcare'],
                'priority'  => 3,
                'is_active' => true,
            ],
            [
                'title'     => "You Can't Pour from Empty",
                'content'   => 'Rest and recovery are not optional — your wellbeing enables your impact.',
                'category'  => 'Self-Care',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['burnout', 'energy', 'selfcare'],
                'priority'  => 3,
                'is_active' => true,
            ],
            [
                'title'     => 'Your Feelings Are Valid',
                'content'   => 'It is normal to feel affected by difficult stories. Acknowledging your emotions keeps you resilient.',
                'category'  => 'Self-Care',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['awareness', 'selfcare', 'burnout'],
                'priority'  => 3,
                'is_active' => true,
            ],
            [
                'title'     => 'Rest Is Productive',
                'content'   => 'Taking time to rest is not laziness — it is how you sustain long-term impact for the students you serve.',
                'category'  => 'Self-Care',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['burnout', 'energy', 'selfcare'],
                'priority'  => 2,
                'is_active' => true,
            ],
            [
                'title'     => 'Nourish Before You Give',
                'content'   => 'Eat a proper meal today. Physical nourishment directly affects your emotional availability.',
                'category'  => 'Self-Care',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['energy', 'health', 'selfcare'],
                'priority'  => 2,
                'is_active' => true,
            ],

            // ── Boundaries ────────────────────────────────────────────────────────
            [
                'title'     => 'Boundaries Are Strength',
                'content'   => 'Saying "I need to step back" is an act of care — for both you and the student.',
                'category'  => 'Boundaries',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['burnout', 'boundaries'],
                'priority'  => 3,
                'is_active' => true,
            ],
            [
                'title'     => 'Off-Hours Are Sacred',
                'content'   => 'You are allowed to be unavailable outside your support hours. Protect your personal time.',
                'category'  => 'Boundaries',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['boundaries', 'burnout', 'stress'],
                'priority'  => 3,
                'is_active' => true,
            ],
            [
                'title'     => 'It Is Okay to Say No',
                'content'   => '"No" is a complete sentence. Declining a request you cannot handle protects both you and the student.',
                'category'  => 'Boundaries',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['boundaries', 'confidence', 'burnout'],
                'priority'  => 2,
                'is_active' => true,
            ],

            // ── Emotional Processing ──────────────────────────────────────────────
            [
                'title'     => 'Debrief Quietly',
                'content'   => 'After a difficult conversation, write two sentences about how you handled it — then let it go.',
                'category'  => 'Emotional Processing',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['stress', 'relief'],
                'priority'  => 2,
                'is_active' => true,
            ],
            [
                'title'     => 'Name the Feeling',
                'content'   => 'After a heavy session, label what you are feeling — "frustrated," "drained," "sad." Naming it reduces its grip on you.',
                'category'  => 'Emotional Processing',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['stress', 'awareness', 'relief'],
                'priority'  => 2,
                'is_active' => true,
            ],
            [
                'title'     => 'Release What Is Not Yours',
                'content'   => 'You can hold space for someone\'s pain without absorbing it. Remind yourself: their story is not your burden to carry home.',
                'category'  => 'Emotional Processing',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['burnout', 'stress', 'relief'],
                'priority'  => 3,
                'is_active' => true,
            ],
            [
                'title'     => 'Journal for Clarity',
                'content'   => 'Spend five minutes writing freely after a challenging week. You do not need to reread it — the act of writing is enough.',
                'category'  => 'Emotional Processing',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['stress', 'relief', 'awareness'],
                'priority'  => 2,
                'is_active' => true,
            ],

            // ── Professional Skills ───────────────────────────────────────────────
            [
                'title'     => 'Escalation Is Wisdom',
                'content'   => 'Recognizing when to escalate a case shows good judgment, not failure.',
                'category'  => 'Professional Skills',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['confidence', 'ethics'],
                'priority'  => 2,
                'is_active' => true,
            ],
            [
                'title'     => 'Silence Has Power',
                'content'   => 'Allow comfortable silences during sessions. Not every pause needs to be filled — sometimes silence is what a student needs most.',
                'category'  => 'Professional Skills',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['focus', 'confidence'],
                'priority'  => 2,
                'is_active' => true,
            ],
            [
                'title'     => 'Reflect, Don\'t Fix',
                'content'   => 'Your role is to reflect and guide, not to solve. Asking "What do you think you need?" is often more powerful than giving advice.',
                'category'  => 'Professional Skills',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['confidence', 'focus'],
                'priority'  => 2,
                'is_active' => true,
            ],
            [
                'title'     => 'Confidentiality Is Trust',
                'content'   => 'Every session you keep confidential builds a culture of safety. Students talk when they know they are safe.',
                'category'  => 'Professional Skills',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['ethics', 'confidence'],
                'priority'  => 3,
                'is_active' => true,
            ],

            // ── Support & Connection ──────────────────────────────────────────────
            [
                'title'     => 'You Are Not Alone',
                'content'   => 'Reach out to a supervisor or fellow peer counselor when a case feels heavy — that\'s what the team is for.',
                'category'  => 'Support',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['stress', 'support', 'burnout'],
                'priority'  => 3,
                'is_active' => true,
            ],
            [
                'title'     => 'Connect with Your Peers',
                'content'   => 'Check in with a fellow peer counselor this week. Shared experience builds collective resilience.',
                'category'  => 'Support',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['loneliness', 'support', 'connection'],
                'priority'  => 2,
                'is_active' => true,
            ],
            [
                'title'     => 'Ask for Supervision Early',
                'content'   => 'Do not wait for a crisis to seek guidance. Regular check-ins with your supervisor keep you sharp and supported.',
                'category'  => 'Support',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['support', 'burnout', 'stress'],
                'priority'  => 3,
                'is_active' => true,
            ],

            // ── Mindfulness ───────────────────────────────────────────────────────
            [
                'title'     => 'Active Listening Reset',
                'content'   => 'Between sessions, take three slow breaths to clear your mind and be fully present for the next conversation.',
                'category'  => 'Mindfulness',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['focus', 'calm', 'stress'],
                'priority'  => 2,
                'is_active' => true,
            ],
            [
                'title'     => 'Ground Yourself First',
                'content'   => 'Before a session, press your feet firmly into the floor and take one deep breath. Groundedness is contagious.',
                'category'  => 'Mindfulness',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['calm', 'focus', 'stress'],
                'priority'  => 2,
                'is_active' => true,
            ],
            [
                'title'     => 'One Thing at a Time',
                'content'   => 'When you feel overwhelmed by multiple cases, focus only on what is in front of you right now. The rest can wait.',
                'category'  => 'Mindfulness',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['anxiety', 'focus', 'calm'],
                'priority'  => 2,
                'is_active' => true,
            ],
            [
                'title'     => 'Body Scan at Lunch',
                'content'   => 'During your break, close your eyes for two minutes and scan from head to toe. Release any tension you notice.',
                'category'  => 'Mindfulness',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['stress', 'calm', 'energy'],
                'priority'  => 1,
                'is_active' => true,
            ],

            // ── Motivation & Perspective ──────────────────────────────────────────
            [
                'title'     => 'Celebrate Your Impact',
                'content'   => 'Think of one person you supported this week. Your presence matters more than you know.',
                'category'  => 'Motivation',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['motivation', 'positivity'],
                'priority'  => 2,
                'is_active' => true,
            ],
            [
                'title'     => 'Small Steps Count',
                'content'   => 'You do not need to solve everything in one session. Guiding someone to take one small step forward is a win.',
                'category'  => 'Motivation',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['motivation', 'confidence'],
                'priority'  => 2,
                'is_active' => true,
            ],
            [
                'title'     => 'You Chose This for a Reason',
                'content'   => 'On hard days, remember why you became a peer counselor. That original intention is still alive in you.',
                'category'  => 'Motivation',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['motivation', 'burnout', 'positivity'],
                'priority'  => 3,
                'is_active' => true,
            ],
            [
                'title'     => 'Progress Over Perfection',
                'content'   => 'You will not always say the perfect thing — and that is okay. Showing up consistently is more important than being flawless.',
                'category'  => 'Motivation',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['confidence', 'motivation'],
                'priority'  => 2,
                'is_active' => true,
            ],
            [
                'title'     => 'Your Story Is an Asset',
                'content'   => 'The experiences that shaped you — including the hard ones — make you more relatable and effective as a peer supporter.',
                'category'  => 'Motivation',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['confidence', 'motivation', 'positivity'],
                'priority'  => 2,
                'is_active' => true,
            ],

            // ── Growth & Awareness ────────────────────────────────────────────────
            [
                'title'     => 'Reflect on One Session',
                'content'   => 'Pick one recent session and ask: What went well? What would I do differently? Growth lives in honest reflection.',
                'category'  => 'Growth',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['awareness', 'confidence'],
                'priority'  => 2,
                'is_active' => true,
            ],
            [
                'title'     => 'Learn Something New',
                'content'   => 'Read one article or watch a short video on a mental health topic this week. Continuous learning sharpens your support.',
                'category'  => 'Growth',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['motivation', 'focus'],
                'priority'  => 1,
                'is_active' => true,
            ],
            [
                'title'     => 'Notice Your Assumptions',
                'content'   => 'Before a session, check for any assumptions you hold about the student. A curious mind listens better than a presuming one.',
                'category'  => 'Growth',
                'audience'  => 'peer_counselor',
                'mood_tags' => ['awareness', 'focus', 'ethics'],
                'priority'  => 2,
                'is_active' => true,
            ],
        ];

        foreach ($tips as $tip) {
            Tip::query()->updateOrCreate(
                [
                    'title'    => $tip['title'],
                    'audience' => $tip['audience'],
                ],
                [
                    'content'   => $tip['content'],
                    'category'  => $tip['category'],
                    'mood_tags' => $tip['mood_tags'],
                    'priority'  => $tip['priority'],
                    'is_active' => $tip['is_active'],
                ]
            );
        }
    }

    public function down(): void
    {
        Tip::query()
            ->where('audience', 'peer_counselor')
            ->delete();
    }
};
