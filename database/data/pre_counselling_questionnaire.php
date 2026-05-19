<?php

/**
 * Concise Pre-counselling intake questionnaire (13 highly clinical, direct questions).
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

// ----- Section 2: Emotional frequency -----
$questions[] = [
    'id' => 'emo_low_mood',
    'section' => '2',
    'section_title' => 'Emotional patterns (past few weeks)',
    'category' => 'emotional_distress',
    'type' => 'frequency_5',
    'required' => true,
    'question' => 'How often have you felt down, depressed, hopeless, nervous, or on edge?',
    'description' => 'Think about the last 2–3 weeks',
    'options' => $f(),
    'scoring' => ['polarity' => 'negative'],
];

// ----- Section 3: Cognition -----
$questions[] = [
    'id' => 'cog_concentrate',
    'section' => '3',
    'section_title' => 'Thought patterns',
    'category' => 'cognitive_patterns',
    'type' => 'frequency_5',
    'required' => true,
    'question' => 'How often have you had trouble concentrating on study or daily tasks due to intrusive worries?',
    'description' => 'Think about the last 2-3 weeks',
    'options' => $f(),
    'scoring' => ['polarity' => 'negative'],
];

// ----- Section 4: Behaviour -----
$questions[] = [
    'id' => 'beh_avoid_tasks',
    'section' => '4',
    'section_title' => 'Behaviours & avoidance',
    'category' => 'behavioural_patterns',
    'type' => 'frequency_5',
    'required' => true,
    'question' => 'How often do you avoid study tasks, responsibilities, or social activities you’d normally tackle?',
    'description' => 'Think about the last 2-3 weeks',
    'options' => $f(),
    'scoring' => ['polarity' => 'negative'],
];

// ----- Section 5: Functional impact -----
$questions[] = [
    'id' => 'fn_academic_impact',
    'section' => '5',
    'section_title' => 'Impact on daily life',
    'category' => 'functional_impact',
    'type' => 'frequency_5',
    'required' => true,
    'question' => 'How often has your mental health actively affected your academic or work performance?',
    'description' => 'Think about the last 2-3 weeks',
    'options' => $f(),
    'scoring' => ['polarity' => 'negative'],
];

// ----- Section 6: Stress load -----
$questions[] = [
    'id' => 'str_academic',
    'section' => '6',
    'section_title' => 'Sources of stress',
    'category' => 'stress_load',
    'type' => 'scale_1_10',
    'required' => true,
    'question' => 'How intense is your academic pressure, deadlines, or general study-related stress right now?',
    'description' => '1 = very low stress · 10 = extremely high stress',
    'options' => null,
    'scoring' => ['polarity' => 'negative', 'stress_label' => 'Academic stress'],
];

// ----- Section 7: Coping -----
$questions[] = [
    'id' => 'cop_professional_help',
    'section' => '7',
    'section_title' => 'Coping & what helps',
    'category' => 'coping',
    'type' => 'single_choice',
    'required' => true,
    'question' => 'Have counselling or mental health support services helped you manage distress in the past?',
    'description' => 'Choose one',
    'options' => [
        ['value' => 'yes_helpful', 'label' => 'Yes, and it was helpful', 'severity' => 15],
        ['value' => 'yes_mixed', 'label' => 'Yes, mixed experience', 'severity' => 35],
        ['value' => 'no_never', 'label' => 'No, I have not tried', 'severity' => 40],
        ['value' => 'no_not_helpful', 'label' => 'I tried and it did not help much', 'severity' => 45],
    ],
];

// ----- Section 8: Supports -----
$questions[] = [
    'id' => 'sup_someone_trust',
    'section' => '8',
    'section_title' => 'Support & connection',
    'category' => 'social_support',
    'type' => 'frequency_5',
    'required' => true,
    'question' => 'How often do you feel you have a trusted person you can talk to honestly for emotional support?',
    'description' => 'Think about the last 2-3 weeks',
    'options' => $f(),
    'scoring' => ['polarity' => 'positive'],
];

// ----- Section 9: Self-view -----
$questions[] = [
    'id' => 'self_agency',
    'section' => '9',
    'section_title' => 'Strengths & self-view',
    'category' => 'self_resources',
    'type' => 'scale_1_10',
    'required' => true,
    'question' => 'How much influence or agency do you feel you have over improving your current mental wellness?',
    'description' => '1 = none at all · 10 = a great deal',
    'options' => null,
    'scoring' => ['polarity' => 'positive'],
];

// ----- Section 10: Safety (clinical safety check) -----
$questions[] = [
    'id' => 'risk_feel_safe',
    'section' => '10',
    'section_title' => 'Brief safety check',
    'category' => 'safety',
    'type' => 'yes_no',
    'required' => true,
    'question' => 'Do you feel physically and emotionally safe where you live and study?',
    'description' => 'Your honest answers help us support you',
    'options' => null,
    'scoring' => ['polarity' => 'risk_screen'],
];

$questions[] = [
    'id' => 'risk_thoughts_harm',
    'section' => '10',
    'section_title' => 'Brief safety check',
    'category' => 'safety',
    'type' => 'yes_no',
    'required' => true,
    'question' => 'Are you having thoughts of hurting yourself or that you would be better off dead?',
    'description' => 'Your honest answers help us support you',
    'options' => null,
    'scoring' => ['polarity' => 'risk_screen'],
];

$questions[] = [
    'id' => 'risk_want_urgent',
    'section' => '10',
    'section_title' => 'Brief safety check',
    'category' => 'safety',
    'type' => 'yes_no',
    'required' => true,
    'question' => 'Do you want a counsellor to reach out with urgent support?',
    'description' => 'Your honest answers help us support you',
    'options' => null,
    'scoring' => ['polarity' => 'risk_screen'],
];

// ----- Section 11: Session goals -----
$questions[] = [
    'id' => 'sess_main_focus',
    'section' => '11',
    'section_title' => 'Goals for counselling',
    'category' => 'session_goals',
    'type' => 'textarea',
    'required' => true,
    'question' => 'What would you most like support or guidance with in counselling?',
    'description' => 'A short sentence or paragraph is enough',
    'options' => null,
    'scoring' => ['polarity' => 'none'],
];

return [
    'meta' => [
        'scoring_model' => 'pre_counselling_v1',
        'schema_version' => 1,
        'estimated_minutes' => 3,
    ],
    'questions' => $questions,
];
