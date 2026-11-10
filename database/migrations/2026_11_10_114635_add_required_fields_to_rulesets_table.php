<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Existing rulesets start with the details most schools want, so what they ask for is
     * visible and editable on the ruleset page; registrations already made keep the copy they were created with.
     */
    public function up(): void
    {
        Schema::table('rulesets', function (Blueprint $table) {
            $table->json('required_fields')->nullable()->after('is_active');
        });

        $parent = ['name', 'nik', 'phone', 'job', 'last_education', 'monthly_income', 'address'];

        $fields = [
            'child.gender', 'child.birth_place', 'child.birth_date', 'child.religion', 'child.address', 'child.family_card_number',
            'child.nisn', 'child.child_order', 'child.siblings_count',
            'education.npsn', 'education.school_address',
            ...array_map(fn (string $field): string => "father.{$field}", $parent),
            ...array_map(fn (string $field): string => "mother.{$field}", $parent),
        ];

        DB::table('rulesets')->update(['required_fields' => json_encode($fields)]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rulesets', function (Blueprint $table) {
            $table->dropColumn('required_fields');
        });
    }
};
