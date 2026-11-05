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
        Schema::create('registrants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('wave_id')->constrained()->restrictOnDelete();
            $table->foreignId('ruleset_id')->nullable()->constrained()->nullOnDelete();
            $table->json('ruleset_snapshot')->nullable();
            $table->foreignId('registered_by_person_id')->constrained('people')->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->boolean('needs_identity_review')->default(false);
            $table->string('formal_photo_path')->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'organization_id', 'wave_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrants');
    }
};
