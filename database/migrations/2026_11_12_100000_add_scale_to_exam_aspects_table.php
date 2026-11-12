<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_aspects', function (Blueprint $table) {
            $table->string('scale')->default('number')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('exam_aspects', function (Blueprint $table) {
            $table->dropColumn('scale');
        });
    }
};
