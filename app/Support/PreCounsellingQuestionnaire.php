<?php

namespace App\Support;

/**
 * Loads the pre-counselling questionnaire definition (meta + questions).
 */
final class PreCounsellingQuestionnaire
{
    public static function payload(): array
    {
        $path = database_path('data/pre_counselling_questionnaire.php');

        if (! is_readable($path)) {
            return ['meta' => [], 'questions' => []];
        }

        /** @var array{meta?: array, questions?: array} $data */
        $data = require $path;

        return is_array($data) ? $data : ['meta' => [], 'questions' => []];
    }
}
