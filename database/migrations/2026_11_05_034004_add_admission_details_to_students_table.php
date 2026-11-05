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
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('year_id')->nullable()->after('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('registrant_id')->nullable()->unique()->after('year_id')->constrained()->nullOnDelete();
            $table->string('nis')->nullable()->unique()->after('registrant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('year_id');
            $table->dropConstrainedForeignId('registrant_id');
            $table->dropColumn('nis');
        });
    }
};
