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
        Schema::table('people', function (Blueprint $table) {
            $table->char('nisn', 10)->nullable()->after('nik');
            $table->char('family_card_number', 16)->nullable()->after('nisn');
            $table->string('religion')->nullable();
            $table->string('blood_type', 3)->nullable();
            $table->unsignedSmallInteger('child_order')->nullable();
            $table->unsignedSmallInteger('siblings_count')->nullable();
            $table->text('address')->nullable();
            $table->string('last_education')->nullable();
            $table->string('job')->nullable();
            $table->string('monthly_income')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['nisn', 'family_card_number', 'religion', 'blood_type', 'child_order', 'siblings_count', 'address', 'last_education', 'job', 'monthly_income']);
        });
    }
};
