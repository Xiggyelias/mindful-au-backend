<?php

/**
 * Pre-counselling intake questionnaire (11 sections).
 * Stored under diagnostic_questionnaires.questions alongside top-level meta.
 */

$freqOpts = fn () => [
    ['value' => 'never', 'label' => 'Never'],
    ['value' => 'rarely', 'label' => 'Rarely'],
    ['value' => 'sometimes', 'label' => 'Sometimes'],
    ['value' => 'often', 'label' => 'Often'],
    ['value' => 'always', 'label' => 'Always'],
];

$f = $freqOpts;

$questions = [];

// ----- Section 1: Personal context -----
$questions[] = [
    'id' => 'ctx_year_level',
    'section' => '1',
    'section_title' => 'Personal context & study load',
    'category' => 'context',
    'type' => 'single_choice',
    'required' => true,
    'question' => 'What best describes where you are in your studies?',
    'description' => 'Choose one',
    'options' => [
        ['value' => 'first_year', 'label' => 'First year', 'severity' => 10],
        ['value' => 'undergrad_mid', 'label' => 'Undergraduate — mid-years', 'severity' => 10],
        ['value' => 'undergrad_final', 'label' => 'Undergraduate — final year', 'severity' => 15],
        ['value' => 'postgrad', 'label' => 'Postgraduate coursework', 'severity' => 12],
        ['value' => 'research', 'label' => 'Research degree (e.g. PhD, MPhil)', 'severity' => 18],
        ['value' => 'other', 'label' => 'Other / prefer not to say', 'severity' => 10],
    ],
];

$questions[] = [
    'id' => 'ctx_study_mode',
    'section' => '1',
    'section_title' => 'Personal context & study load',
    'category' => 'context',
    'type' => 'single_choice',
    'required' => true,
    'question' => 'How do you primarily study?',
    'description' => 'Choose one',
    'options' => [
        ['value' => 'on_campus_full', 'label' => 'On campus full-time', 'severity' => 10],
        ['value' => 'online_full', 'label' => 'Online full-time', 'severity' => 12],
        ['value' => 'hybrid', 'label' => 'Hybrid', 'severity' => 12],
        ['value' => 'part_time', 'label' => 'Part-time', 'severity' => 15],
    ],
];

$questions[] = [
    'id' => 'ctx_weekly_hours',
    'section' => '1',
    'section_title' => 'Personal context & study load',
    'category' => 'context',
    'type' => 'single_choice',
    'required' => false,
    'question' => 'Roughly how many hours per week do you dedicate to coursework (excluding paid work)?',
    'description' => 'Skip if unsure',
    'options' => [
        ['value' => 'under_15', 'label' => 'Under 15 hours', 'severity' => 10],
        ['value' => '15_25', 'label' => '15–25 hours', 'severity' => 14],
        ['value' => '25_40', 'label' => '25–40 hours', 'severity' => 22],
        ['value' => 'over_40', 'label' => 'More than 40 hours', 'severity' => 32],
        ['value' => 'skip', 'label' => 'Prefer not to answer', 'severity' => 10],
    ],
];

$questions[] = [
    'id' => 'ctx_other_roles',
    'section' => '1',
    'section_title' => 'Personal context & study load',
    'category' => 'context',
    'type' => 'multi_select',
    'required' => false,
    'question' => 'What other major roles or responsibilities do you juggle?',
    'description' => 'Select all that apply. Optional.',
    'options' => [
        ['value' => 'paid_work', 'label' => 'Paid work'],
        ['value' => 'caregiving', 'label' => 'Caregiving (children, elders, partner)'],
        ['value' => 'commute_long', 'label' => 'Long commute'],
        ['value' => 'health_condition', 'label' => 'Ongoing physical health concern'],
        ['value' => 'financial_pressure', 'label' => 'Financial pressure'],
        ['value' => 'none_above', 'label' => 'None of these'],
    ],
    'scoring' => ['max_selection_contribution' => 100],
];

// ----- Section 2: Emotional frequency -----
$emotionalPairs = [
    ['emo_anxious', 'How often have you felt nervous, anxious, or on edge?'],
    ['emo_low_mood', 'How often have you felt down, depressed, or hopeless?'],
    ['emo_irritable', 'How often have you felt irritable or quick to frustration?'],
    ['emo_overwhelmed_emotion', 'How often have you felt emotionally overwhelmed?'],
    ['emo_lonely', 'How often have you felt lonely—even around others?'],
    ['emo_numb_detach', 'How often have you felt numb or disconnected from your feelings?'],
];
foreach ($emotionalPairs as [$eid, $eq]) {
    $questions[] = [
        'id' => $eid,
        'section' => '2',
        'section_title' => 'Emotional patterns (past few weeks)',
        'category' => 'emotional_distress',
        'type' => 'frequency_5',
        'required' => true,
        'question' => $eq,
        'description' => 'Think about the last 2–3 weeks',
        'options' => $f(),
        'scoring' => ['polarity' => 'negative'],
    ];
}

// ----- Section 3: Cognition -----
$cogItems = [
    ['cog_worry_spiral', 'How often do worries spiral or feel hard to stop?', 'frequency_5'],
    ['cog_concentrate', 'How often have you had trouble concentrating on study or daily tasks?', 'frequency_5'],
    ['cog_self_critical', 'How often have you been highly self-critical or harsh toward yourself?', 'frequency_5'],
    ['cog_hopeless_future', 'How often have you thought things might not get better?', 'frequency_5'],
];
foreach ($cogItems as [$cid, $cq, $ctype]) {
    $questions[] = [
        'id' => $cid,
        'section' => '3',
        'section_title' => 'Thought patterns',
        'category' => 'cognitive_patterns',
        'type' => $ctype,
        'required' => true,
        'question' => $cq,
        'description' => '',
        'options' => $f(),
        'scoring' => ['polarity' => 'negative'],
    ];
}

$questions[] = [
    'id' => 'cog_control_over_thoughts',
    'section' => '3',
    'section_title' => 'Thought patterns',
    'category' => 'cognitive_patterns',
    'type' => 'scale_1_10',
    'required' => true,
    'question' => 'When worries show up, how much control do you feel you have over them?',
    'description' => '1 = no control · 10 = a lot of control',
    'options' => null,
    'scoring' => ['polarity' => 'positive'],
];

// ----- Section 4: Behaviour -----
foreach (
    [
        ['beh_avoid_tasks', 'How often do you avoid study tasks or responsibilities you’d normally tackle?'],
        ['beh_avoid_social', 'How often do you withdraw from friends, family, or classmates?'],
        ['beh_escalate_conflict', 'How often do you react in ways you later regret (e.g. snapping, shutting down)?'],
        ['beh_change_eating_sleep', 'How often have noticeable changes affected your eating or sleep?'],
    ] as [$bid, $bq]
) {
    $questions[] = [
        'id' => $bid,
        'section' => '4',
        'section_title' => 'Behaviours & avoidance',
        'category' => 'behavioural_patterns',
        'type' => 'frequency_5',
        'required' => true,
        'question' => $bq,
        'description' => '',
        'options' => $f(),
        'scoring' => ['polarity' => 'negative'],
    ];
}

// ----- Section 5: Functional impact -----
foreach (
    [
        ['fn_sleep_quality', 'How often has poor sleep affected your day?'],
        ['fn_energy', 'How often have you felt low on energy to get through the day?'],
        ['fn_academic_impact', 'How often has your mental health affected study performance?'],
        ['fn_relationships_impact', 'How often has your mental health affected relationships?'],
    ] as [$fid, $fq]
) {
    $questions[] = [
        'id' => $fid,
        'section' => '5',
        'section_title' => 'Impact on daily life',
        'category' => 'functional_impact',
        'type' => 'frequency_5',
        'required' => true,
        'question' => $fq,
        'description' => '',
        'options' => $f(),
        'scoring' => ['polarity' => 'negative'],
    ];
}

// ----- Section 6: Stress load -----
foreach (
    [
        ['str_academic', 'Academic pressure or deadlines'],
        ['str_financial', 'Financial stress'],
        ['str_family', 'Family or relationship stress'],
        ['str_health', 'Physical health or disability-related stress'],
        ['str_future', 'Uncertainty about the future / career'],
    ] as [$sid, $label]
) {
    $questions[] = [
        'id' => $sid,
        'section' => '6',
        'section_title' => 'Sources of stress',
        'category' => 'stress_load',
        'type' => 'scale_1_10',
        'required' => true,
        'question' => 'How intense is this source of stress for you right now?',
        'description' => "Focus on: {$label}",
        'options' => null,
        'scoring' => ['polarity' => 'negative', 'stress_label' => $label],
    ];
}

// ----- Section 7: Coping -----
$questions[] = [
    'id' => 'cop_strategies',
    'section' => '7',
    'section_title' => 'Coping & what helps',
    'category' => 'coping',
    'type' => 'multi_select',
    'required' => false,
    'question' => 'What do you tend to do when stress builds up?',
    'description' => 'Select all that apply',
    'options' => [
        ['value' => 'talk_someone', 'label' => 'Talk to someone', 'weight' => 15],
        ['value' => 'exercise', 'label' => 'Exercise or movement', 'weight' => 10],
        ['value' => 'screen_time', 'label' => 'More screen time / distraction', 'weight' => 35],
        ['value' => 'substances', 'label' => 'Alcohol or other substances', 'weight' => 55],
        ['value' => 'isolate', 'label' => 'Isolate', 'weight' => 45],
        ['value' => 'work_more', 'label' => 'Push harder / overwork', 'weight' => 40],
        ['value' => 'mindfulness', 'label' => 'Mindfulness, breathing, faith or spiritual practice', 'weight' => 5],
        ['value' => 'other_cope', 'label' => 'Something else', 'weight' => 25],
    ],
];

$questions[] = [
    'id' => 'cop_what_helped_note',
    'section' => '7',
    'section_title' => 'Coping & what helps',
    'category' => 'coping',
    'type' => 'textarea',
    'required' => false,
    'question' => 'What has helped you get through hard patches before?',
    'description' => 'Optional — a few words is fine',
    'options' => null,
    'scoring' => ['polarity' => 'none'],
];

$questions[] = [
    'id' => 'cop_professional_help',
    'section' => '7',
    'section_title' => 'Coping & what helps',
    'category' => 'coping',
    'type' => 'single_choice',
    'required' => true,
    'question' => 'Have counselling or mental health services helped you in the past?',
    'description' => '',
    'options' => [
        ['value' => 'yes_helpful', 'label' => 'Yes, and it was helpful', 'severity' => 15],
        ['value' => 'yes_mixed', 'label' => 'Yes, mixed experience', 'severity' => 35],
        ['value' => 'no_never', 'label' => 'No, I have not tried', 'severity' => 40],
        ['value' => 'no_not_helpful', 'label' => 'I tried and it did not help much', 'severity' => 45],
    ],
];

// ----- Section 8: Supports -----
foreach (
    [
        ['sup_someone_trust', 'How often do you feel you have someone you can talk to honestly?'],
        ['sup_understood', 'How often do you feel understood by people around you?'],
        ['sup_know_where_help', 'How often do you feel you know where to get help on campus or online?'],
    ] as [$uid, $uq]
) {
    $questions[] = [
        'id' => $uid,
        'section' => '8',
        'section_title' => 'Support & connection',
        'category' => 'social_support',
        'type' => 'frequency_5',
        'required' => true,
        'question' => $uq,
        'description' => '',
        'options' => $f(),
        'scoring' => ['polarity' => 'positive'],
    ];
}

// ----- Section 9: Self-view -----
$questions[] = [
    'id' => 'self_strength_note',
    'section' => '9',
    'section_title' => 'Strengths & self-view',
    'category' => 'self_resources',
    'type' => 'textarea',
    'required' => false,
    'question' => 'Name one strength or value that matters to you (even if it feels small).',
    'description' => 'Optional',
    'options' => null,
    'scoring' => ['polarity' => 'none'],
];

$questions[] = [
    'id' => 'self_compassion',
    'section' => '9',
    'section_title' => 'Strengths & self-view',
    'category' => 'self_resources',
    'type' => 'scale_1_10',
    'required' => true,
    'question' => 'How kindly do you treat yourself when you struggle?',
    'description' => '1 = very harsh · 10 = very kind',
    'options' => null,
    'scoring' => ['polarity' => 'positive'],
];

$questions[] = [
    'id' => 'self_agency',
    'section' => '9',
    'section_title' => 'Strengths & self-view',
    'category' => 'self_resources',
    'type' => 'scale_1_10',
    'required' => true,
    'question' => 'How much influence do you feel you have over improving your situation?',
    'description' => '1 = none · 10 = a great deal',
    'options' => null,
    'scoring' => ['polarity' => 'positive'],
];

// ----- Section 10: Safety (brief) -----
foreach (
    [
        ['risk_feel_safe', 'Do you feel physically and emotionally safe where you live and study?'],
        ['risk_thoughts_harm', 'Are you having thoughts of hurting yourself or that you would be better off dead?'],
        ['risk_want_urgent', 'Do you want a counsellor to reach out with urgent support?'],
    ] as [$rid, $rq]
) {
    $questions[] = [
        'id' => $rid,
        'section' => '10',
        'section_title' => 'Brief safety check',
        'category' => 'safety',
        'type' => 'yes_no',
        'required' => true,
        'question' => $rq,
        'description' => 'Your honest answers help us support you',
        'options' => null,
        'scoring' => ['polarity' => 'risk_screen'],
    ];
}

// ----- Section 11: Session goals -----
$questions[] = [
    'id' => 'sess_main_focus',
    'section' => '11',
    'section_title' => 'Goals for counselling',
    'category' => 'session_goals',
    'type' => 'textarea',
    'required' => true,
    'question' => 'What would you most like help with in counselling?',
    'description' => 'A short paragraph is enough',
    'options' => null,
    'scoring' => ['polarity' => 'none'],
];

$questions[] = [
    'id' => 'sess_hoped_outcome',
    'section' => '11',
    'section_title' => 'Goals for counselling',
    'category' => 'session_goals',
    'type' => 'textarea',
    'required' => false,
    'question' => 'What would a good outcome look like for you in 3 months?',
    'description' => 'Optional',
    'options' => null,
    'scoring' => ['polarity' => 'none'],
];

$questions[] = [
    'id' => 'sess_urgency',
    'section' => '11',
    'section_title' => 'Goals for counselling',
    'category' => 'session_goals',
    'type' => 'single_choice',
    'required' => true,
    'question' => 'How soon would you like to be seen?',
    'description' => '',
    'options' => [
        ['value' => 'routine', 'label' => 'Routine — within a few weeks', 'severity' => 15],
        ['value' => 'soon', 'label' => 'Soon — within a week', 'severity' => 40],
        ['value' => 'urgent', 'label' => 'As soon as possible', 'severity' => 70],
        ['value' => 'unsure', 'label' => 'Not sure', 'severity' => 25],
    ],
];

$questions[] = [
    'id' => 'sess_other',
    'section' => '11',
    'section_title' => 'Goals for counselling',
    'category' => 'session_goals',
    'type' => 'textarea',
    'required' => false,
    'question' => 'Anything else you want your counsellor to know before the first session?',
    'description' => 'Optional',
    'options' => null,
    'scoring' => ['polarity' => 'none'],
];

return [
    'meta' => [
        'scoring_model' => 'pre_counselling_v1',
        'schema_version' => 1,
        'estimated_minutes' => 15,
    ],
    'questions' => $questions,
];
