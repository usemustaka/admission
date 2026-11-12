<?php

namespace App\Actions\Admission;

use App\Enums\DocumentType;
use App\Models\Ruleset;
use App\Support\DocumentKind;

class SyncRulesetDocuments
{
    /**
     * Set what the ruleset asks for. Every built-in type is `none` (off), `optional` or `required` (a supporting type left out stays as it is); custom files are
     * the ruleset's own (a name, how many files, optional or required) and are matched by their key, so renaming one
     * keeps the files already uploaded to it. A custom file left out of the list is removed. Only registrations made
     * after this follow the change, because each keeps its own copy of the ruleset.
     *
     * @param  array<string, string>  $choices  keyed by the value of a `DocumentType`
     * @param  list<array{key?: string|null, label: string, max_files: int, choice: string}>  $custom
     */
    public function handle(Ruleset $ruleset, array $choices, array $custom = []): void
    {
        foreach (DocumentType::configurable() as $type) {
            if ($type->isSupporting() && ! array_key_exists($type->value, $choices)) {
                continue;
            }

            $choice = $choices[$type->value] ?? 'none';

            if ($choice === 'none') {
                $ruleset->documents()->where('type', $type->value)->delete();

                continue;
            }

            $ruleset->documents()->updateOrCreate(['type' => $type->value], ['is_required' => $choice === 'required']);
        }

        $existing = $ruleset->documents()->where('type', 'like', DocumentKind::CUSTOM_PREFIX.'%')->pluck('type')->all();
        $kept = [];

        foreach ($custom as $row) {
            $key = in_array($row['key'] ?? null, $existing, true) ? $row['key'] : DocumentKind::newCustomKey();
            $kept[] = $key;

            $ruleset->documents()->updateOrCreate(['type' => $key], [
                'label' => trim($row['label']),
                'max_files' => $row['max_files'],
                'is_required' => $row['choice'] === 'required',
            ]);
        }

        $ruleset->documents()->where('type', 'like', DocumentKind::CUSTOM_PREFIX.'%')->whereNotIn('type', $kept)->delete();
    }
}
