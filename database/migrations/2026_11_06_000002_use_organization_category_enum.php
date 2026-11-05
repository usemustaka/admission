<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The category of an organization used to be free text. It is now one of a fixed set (see OrganizationCategory),
 * so the text people typed is mapped onto it. What cannot be told, such as a category nobody recognises, becomes
 * empty and can be chosen again on the Lembaga page.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const MAP = [
        'sekolah' => ['sekolah', 'school', 'tk', 'paud', 'sd', 'smp', 'sma', 'smk', 'sltp', 'slta', 'smu', 'sdit', 'smpit', 'smait', 'sekolah dasar', 'sekolah menengah'],
        'madrasah' => ['madrasah', 'mi', 'mts', 'ma', 'man', 'mtsn', 'min', 'ra', 'diniyah', 'madin'],
        'pesantren' => ['pesantren', 'pondok', 'ponpes', 'boarding', 'santri'],
        'bootcamp' => ['bootcamp', 'kursus', 'pelatihan', 'course', 'training'],
    ];

    public function up(): void
    {
        foreach (DB::table('organizations')->whereNotNull('category')->get(['id', 'category']) as $organization) {
            DB::table('organizations')->where('id', $organization->id)->update(['category' => $this->mapped((string) $organization->category)]);
        }
    }

    /**
     * What a category was before cannot be known, so going back leaves the values as they are.
     */
    public function down(): void {}

    private function mapped(string $category): ?string
    {
        $category = mb_strtolower(trim($category));

        foreach (self::MAP as $mapped => $names) {
            if (in_array($category, $names, true)) {
                return $mapped;
            }
        }

        return null;
    }
};
