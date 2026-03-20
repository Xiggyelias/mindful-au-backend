<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Safety hardening:
        // This migration originally dropped active application tables.
        // It is intentionally a no-op to prevent accidental production data loss.
    }

    /**
     * Reverse the migrations.
     * Note: We cannot restore dropped tables with their original data,
     * but we can recreate empty table structures if needed.
     */
    public function down(): void
    {
        // No-op: forward migration does not perform destructive changes.
    }
};
