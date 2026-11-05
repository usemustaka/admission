<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The exam schedule belongs to who sits the exam (an organization and a wave, either of which may be empty for
 * "all"), not to the ruleset that prices their registration. Until now every ruleset kept its own copy of the
 * same dates, so one real session showed up once per ruleset and each copy had its own quota.
 *
 * Existing days take the organization and wave of their ruleset. Days that end up with the same scope and date
 * are merged: sessions with the same start and end time become one (the larger quota, never below the seats
 * already taken), and the registrations that picked a merged session move to the surviving one.
 */
return new class extends Migration
{
    /**
     * SQLite rebuilds a table to drop a column, and with foreign keys on that would cascade the delete of the
     * old table into the sessions. Foreign keys can only be switched off outside a transaction, so the steps that
     * need an all-or-nothing run open their own.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('exam_days', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('wave_id')->nullable()->after('organization_id')->constrained()->restrictOnDelete();
        });

        DB::transaction(fn () => $this->mergeDays());

        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::table('exam_days', function (Blueprint $table): void {
                $table->dropForeign(['ruleset_id']);
                $table->dropUnique(['ruleset_id', 'date']);
                $table->dropColumn('ruleset_id');
            });
        });
    }

    /**
     * The ruleset of a day cannot be recovered once days are merged, so going back leaves it empty.
     */
    public function down(): void
    {
        Schema::table('exam_days', function (Blueprint $table): void {
            $table->foreignId('ruleset_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unique(['ruleset_id', 'date']);
        });

        Schema::table('exam_days', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('wave_id');
            $table->dropConstrainedForeignId('organization_id');
        });
    }

    private function mergeDays(): void
    {
        $days = DB::table('exam_days')
            ->join('rulesets', 'rulesets.id', '=', 'exam_days.ruleset_id')
            ->select('exam_days.id', 'exam_days.date', 'rulesets.organization_id', 'rulesets.wave_id')
            ->orderBy('exam_days.id')
            ->get();

        /** @var array<string, int> $survivors the day that keeps each scope and date */
        $survivors = [];

        foreach ($days as $day) {
            DB::table('exam_days')->where('id', $day->id)->update(['organization_id' => $day->organization_id, 'wave_id' => $day->wave_id]);

            $key = ($day->organization_id ?? '-').'|'.($day->wave_id ?? '-').'|'.substr((string) $day->date, 0, 10);

            if (! isset($survivors[$key])) {
                $survivors[$key] = $day->id;

                continue;
            }

            $this->mergeInto($survivors[$key], $day->id);
        }

        $this->liftQuotasToSeatsTaken();
    }

    private function mergeInto(int $survivorId, int $dayId): void
    {
        foreach (DB::table('exam_sessions')->where('exam_day_id', $dayId)->orderBy('id')->get() as $session) {
            $twin = DB::table('exam_sessions')
                ->where('exam_day_id', $survivorId)
                ->where('start_time', $session->start_time)
                ->where('end_time', $session->end_time)
                ->orderBy('id')
                ->first();

            if ($twin === null) {
                DB::table('exam_sessions')->where('id', $session->id)->update(['exam_day_id' => $survivorId]);

                continue;
            }

            DB::table('registrants')->where('exam_session_id', $session->id)->update(['exam_session_id' => $twin->id]);
            DB::table('exam_sessions')->where('id', $twin->id)->update(['quota' => max((int) $twin->quota, (int) $session->quota)]);
            DB::table('exam_sessions')->where('id', $session->id)->delete();
        }

        DB::table('exam_days')->where('id', $dayId)->delete();
    }

    private function liftQuotasToSeatsTaken(): void
    {
        $taken = DB::table('registrants')
            ->whereNotNull('exam_session_id')
            ->selectRaw('exam_session_id, count(*) as total')
            ->groupBy('exam_session_id')
            ->pluck('total', 'exam_session_id');

        foreach ($taken as $sessionId => $total) {
            DB::table('exam_sessions')->where('id', $sessionId)->where('quota', '<', $total)->update(['quota' => $total]);
        }
    }
};
