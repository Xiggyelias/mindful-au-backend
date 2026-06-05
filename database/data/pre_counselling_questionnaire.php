<?php

/**
 * Concise Pre-counselling intake questionnaire (7 highly clinical, direct questions).
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

// ----- Section 3: Functional impact -----
$questions[] = [
    'id' => 'fn_academic_impact',
    'section' => '3',
    'section_title' => 'Impact on daily life',
    'category' => 'functional_impact',
    'type' => 'frequency_5',
    'required' => true,
    'question' => 'How often has your mental health actively affected your academic or work performance?',
    'description' => 'Think about the last 2-3 weeks',
    'options' => $f(),
    'scoring' => ['polarity' => 'negative'],
];

// ----- Section 4: Stress load -----
$questions[] = [
    'id' => 'str_academic',
    'section' => '4',
    'section_title' => 'Sources of stress',
    'category' => 'stress_load',
    'type' => 'scale_1_10',
    'required' => true,
    'question' => 'How intense is your academic pressure, deadlines, or general study-related stress right now?',
    'description' => '1 = very low stress · 10 = extremely high stress',
    'options' => null,
    'scoring' => ['polarity' => 'negative', 'stress_label' => 'Academic stress'],
];
// ----- Section 5: Safety (clinical safety check) -----
$questions[] = [
    'id' => 'risk_thoughts_harm',
    'section' => '5',
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
    'section' => '5',
    'section_title' => 'Brief safety check',
    'category' => 'safety',
    'type' => 'yes_no',
    'required' => true,
    'question' => 'Do you want a counsellor to reach out with urgent support?',
    'description' => 'Your honest answers help us support you',
    'options' => null,
    'scoring' => ['polarity' => 'risk_screen'],
];

// ----- Section 6: Session goals -----
$questions[] = [
    'id' => 'sess_main_focus',
    'section' => '6',
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
        'estimated_minutes' => 2,
    ],
    'questions' => $questions,
];
