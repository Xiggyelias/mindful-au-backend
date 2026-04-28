<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'has_file')) {
                $table->boolean('has_file')->default(false)->after('file_url');
            }
        });

        Schema::create('chat_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->unique()->constrained('messages')->cascadeOnDelete();
            $table->string('file_name');
            $table->text('file_path');
            $table->string('file_type', 191);
            $table->unsignedBigInteger('file_size');
            $table->timestamp('uploaded_at')->useCurrent();
        });

        DB::table('messages')
            ->where(function ($query): void {
                $query->whereNotNull('file_url')
                    ->orWhereIn('message_type', ['file', 'voice']);
            })
            ->update([
                'has_file' => true,
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_files');

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'has_file')) {
                $table->dropColumn('has_file');
            }
        });
    }
};
