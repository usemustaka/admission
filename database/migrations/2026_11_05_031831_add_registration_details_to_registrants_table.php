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
            $table->string('submitted_name')->default('')->after('registered_by_person_id');
            $table->string('registered_as_relation')->default('guardian')->after('submitted_name');
            $table->string('registered_as_kind')->nullable()->after('registered_as_relation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrants', function (Blueprint $table) {
            $table->dropColumn(['submitted_name', 'registered_as_relation', 'registered_as_kind']);
        });
    }
};
