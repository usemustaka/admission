<?php

use App\Enums\DocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A ruleset can now ask for its own files (a name and how many files each holds), and can switch the supporting
     * files (KIP, certificates, ...) on and off like any other. Until now the supporting files were always offered, so
     * every existing ruleset is given them as optional and keeps behaving as before.
     */
    public function up(): void
    {
        Schema::table('ruleset_documents', function (Blueprint $table) {
            $table->string('label')->nullable()->after('type');
            $table->unsignedTinyInteger('max_files')->default(1)->after('label');
        });

        $now = now();

        foreach (DB::table('rulesets')->pluck('id') as $rulesetId) {
            foreach (DocumentType::supporting() as $type) {
                DB::table('ruleset_documents')->insertOrIgnore(['ruleset_id' => $rulesetId, 'type' => $type->value, 'is_required' => false, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        DB::table('ruleset_documents')->whereIn('type', array_map(fn (DocumentType $type): string => $type->value, DocumentType::supporting()))->delete();

        Schema::table('ruleset_documents', function (Blueprint $table) {
            $table->dropColumn(['label', 'max_files']);
        });
    }
};
