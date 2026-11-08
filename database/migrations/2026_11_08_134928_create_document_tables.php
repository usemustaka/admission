<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ruleset_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruleset_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->boolean('is_required')->default(false);
            $table->timestamps();
            $table->unique(['ruleset_id', 'type']);
        });

        Schema::create('registrant_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registrant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedTinyInteger('slot')->default(1);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['registrant_id', 'type', 'slot']);
        });

        $now = now();
        $defaults = ['family-card' => true, 'birth-certificate' => true, 'report-card' => true, 'diploma' => false];

        foreach (DB::table('rulesets')->pluck('id') as $rulesetId) {
            foreach ($defaults as $type => $required) {
                DB::table('ruleset_documents')->insert(['ruleset_id' => $rulesetId, 'type' => $type, 'is_required' => $required, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrant_documents');
        Schema::dropIfExists('ruleset_documents');
    }
};
