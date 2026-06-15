<?php

use Database\Seeders\DiagnosticQuestionnaireSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Install the pre-counselling questionnaire (v2) for existing databases that only have the legacy v1 form.
     */
    public function up(): void
    {
        Artisan::call('db:seed', [
            '--class' => DiagnosticQuestionnaireSeeder::class,
            '--force' => true,
        ]);
    }

    public function down(): void
    {
        // Reference data: do not remove questionnaires on rollback.
    }
};
