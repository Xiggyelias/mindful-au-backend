<?php

namespace Database\Seeders;

use App\Models\DiagnosticQuestionnaire;
use Illuminate\Database\Seeder;

class DiagnosticQuestionnaireSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            [
                'id' => 'q1',
                'category' => 'anxiety',
                'type' => 'scale',
                'question' => 'How often do you feel anxious or worried?',
                'description' => 'Rate your anxiety level from 1 (Never) to 5 (Always)',
                'options' => null,
            ],
            [
                'id' => 'q2',
                'category' => 'anxiety',
                'type' => 'scale',
                'question' => 'Do you experience panic attacks or sudden intense fear?',
                'description' => 'Rate from 1 (Never) to 5 (Very frequently)',
                'options' => null,
            ],
            [
                'id' => 'q3',
                'category' => 'depression',
                'type' => 'scale',
                'question' => 'How often do you feel sad or hopeless?',
                'description' => 'Rate from 1 (Never) to 5 (Always)',
                'options' => null,
            ],
            [
                'id' => 'q4',
                'category' => 'depression',
                'type' => 'scale',
                'question' => 'Do you lose interest in activities you normally enjoy?',
                'description' => 'Rate from 1 (Never) to 5 (Always)',
                'options' => null,
            ],
            [
                'id' => 'q5',
                'category' => 'stress',
                'type' => 'scale',
                'question' => 'How stressed do you feel about your academic workload?',
                'description' => 'Rate from 1 (Not stressed) to 5 (Extremely stressed)',
                'options' => null,
            ],
            [
                'id' => 'q6',
                'category' => 'stress',
                'type' => 'scale',
                'question' => 'Do you feel overwhelmed by your responsibilities?',
                'description' => 'Rate from 1 (Never) to 5 (Always)',
                'options' => null,
            ],
            [
                'id' => 'q7',
                'category' => 'sleep',
                'type' => 'scale',
                'question' => 'How would you rate your sleep quality?',
                'description' => 'Rate from 1 (Very poor) to 5 (Excellent)',
                'options' => null,
            ],
            [
                'id' => 'q8',
                'category' => 'sleep',
                'type' => 'scale',
                'question' => 'How many hours of sleep do you typically get per night?',
                'description' => 'Rate from 1 (Less than 4 hours) to 5 (8+ hours)',
                'options' => null,
            ],
            [
                'id' => 'q9',
                'category' => 'social',
                'type' => 'scale',
                'question' => 'How satisfied are you with your social connections?',
                'description' => 'Rate from 1 (Very unsatisfied) to 5 (Very satisfied)',
                'options' => null,
            ],
            [
                'id' => 'q10',
                'category' => 'social',
                'type' => 'scale',
                'question' => 'Do you feel lonely or isolated?',
                'description' => 'Rate from 1 (Never) to 5 (Always)',
                'options' => null,
            ],
            [
                'id' => 'q11',
                'category' => 'academic',
                'type' => 'scale',
                'question' => 'How confident are you in your academic abilities?',
                'description' => 'Rate from 1 (Not confident) to 5 (Very confident)',
                'options' => null,
            ],
            [
                'id' => 'q12',
                'category' => 'academic',
                'type' => 'scale',
                'question' => 'Are you struggling with any specific subjects or courses?',
                'description' => 'Rate from 1 (Not struggling) to 5 (Severely struggling)',
                'options' => null,
            ],
            [
                'id' => 'q13',
                'category' => 'substance',
                'type' => 'multiple_choice',
                'question' => 'How often do you consume alcohol?',
                'description' => 'Select the option that best describes your alcohol consumption',
                'options' => [
                    ['value' => 'never', 'label' => 'Never', 'score' => 1],
                    ['value' => 'rarely', 'label' => 'Rarely (less than once a month)', 'score' => 2],
                    ['value' => 'sometimes', 'label' => 'Sometimes (1-2 times a month)', 'score' => 3],
                    ['value' => 'often', 'label' => 'Often (1-2 times a week)', 'score' => 4],
                    ['value' => 'frequently', 'label' => 'Frequently (3+ times a week)', 'score' => 5],
                ],
            ],
            [
                'id' => 'q14',
                'category' => 'substance',
                'type' => 'yes_no',
                'question' => 'Have you ever used recreational drugs?',
                'description' => 'Answer yes or no',
                'options' => null,
            ],
            [
                'id' => 'q15',
                'category' => 'general',
                'type' => 'text',
                'question' => 'Is there anything else you would like to share about your mental health or well-being?',
                'description' => 'Please provide any additional information that might be helpful',
                'options' => null,
            ],
        ];

        DiagnosticQuestionnaire::create([
            'title' => 'Comprehensive Mental Health Assessment',
            'description' => 'A comprehensive questionnaire to assess your mental health, stress levels, and overall well-being. This assessment takes approximately 10-15 minutes to complete.',
            'questions' => ['questions' => $questions],
            'status' => 'active',
            'version' => 1,
        ]);
    }
}
