<?php

use App\Models\DiagnosticQuestionnaire;
use Database\Seeders\DiagnosticQuestionnaireSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Fresh installs that never ran DatabaseSeeder often have an empty diagnostic_questionnaires
     * table; students then see 404 from GET /api/diagnostics/questionnaire.
     */
    public function up(): void
    {
        if (DiagnosticQuestionnaire::query()->exists()) {
            return;
        }

        Artisan::call('db:seed', [
            '--class' => DiagnosticQuestionnaireSeeder::class,
            '--force' => true,
        ]);
    }

    public function down(): void
    {
        // Keep questionnaires — reference data should not be rolled back here.
    }
};
