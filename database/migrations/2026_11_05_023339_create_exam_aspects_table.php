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
        Schema::create('exam_aspects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruleset_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('passing_score');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_aspects');
    }
};
