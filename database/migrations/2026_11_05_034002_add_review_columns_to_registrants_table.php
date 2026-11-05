<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('registrants', function (Blueprint $table) {
            $table->foreignId('exam_session_id')->nullable()->after('wave_id')->constrained()->restrictOnDelete();
            $table->json('exam_scores')->nullable()->after('formal_photo_path');
            $table->timestamp('submitted_at')->nullable()->after('exam_scores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_session_id');
            $table->dropColumn(['exam_scores', 'submitted_at']);
        });
    }
};
