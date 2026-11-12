<?php

namespace App\Actions\Admission;

use App\Actions\Notes\WritePersonAuditNote;
use App\Enums\ExamScale;
use App\Enums\RegistrantStatus;
use App\Models\Registrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveExamScores
{
    public function __construct(private WritePersonAuditNote $writeAuditNote) {}

    /**
     * Save scores per exam aspect. `$scores` is keyed by the position of the aspect in the ruleset copy kept on
     * the registration; aspects that are not in it keep their score, and an empty value clears that aspect's
     * score (not entered yet). The registration is read again under a row lock, so the scores merged into are
     * the stored ones and the audit note names the values that were really replaced.
     *
     * Once the result was announced, a score changes only when `$confirmAfterAnnounce` says the staff member
     * knows. When `$by` is given, a private note on the child lists what changed. Returns what changed.
     *
     * @param  array<int|string, mixed>  $scores
     * @return list<array{aspect: string, from: int|null, to: int|null, from_label: string|null, to_label: string|null}>
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, array $scores, ?User $by = null, bool $confirmAfterAnnounce = false): array
    {
        return DB::transaction(function () use ($registrant, $scores, $by, $confirmAfterAnnounce): array {
            $fresh = Registrant::query()->lockForUpdate()->findOrFail($registrant->getKey());
            $registrant->setRawAttributes($fresh->getAttributes(), sync: true);

            $aspects = $registrant->ruleset_snapshot['exam_aspects'] ?? [];
            $stored = $registrant->exam_scores ?? [];
            $merged = [];
            $changes = [];

            foreach ($scores as $index => $value) {
                if (! is_int($index) && ! (is_string($index) && ctype_digit($index)) || ! isset($aspects[(int) $index])) {
                    throw ValidationException::withMessages(['scores' => 'Ada nilai untuk aspek ujian yang tidak ada di aturan pendaftaran ini.']);
                }
            }

            foreach ($aspects as $index => $aspect) {
                $before = $stored[$index] ?? null;
                $after = array_key_exists($index, $scores) ? $this->normalize($scores[$index], $index, $aspect['name'], ExamScale::tryFrom($aspect['scale'] ?? '') ?? ExamScale::Number) : $before;

                $merged[] = $after;

                if ($after !== $before) {
                    $scale = ExamScale::tryFrom($aspect['scale'] ?? '') ?? ExamScale::Number;
                    $changes[] = ['aspect' => $aspect['name'], 'from' => $before, 'to' => $after, 'from_label' => $scale->display($before), 'to_label' => $scale->display($after)];
                }
            }

            if ($changes === []) {
                return [];
            }

            if (in_array($registrant->status, [RegistrantStatus::Announced, RegistrantStatus::Placed], true) && ! $confirmAfterAnnounce) {
                throw ValidationException::withMessages(['confirm_after_announce' => 'Hasil pendaftar ini sudah diumumkan. Centang "Saya sadar nilai ini sudah diumumkan" kalau nilainya memang perlu diubah.']);
            }

            $registrant->update(['exam_scores' => $merged]);

            if ($by !== null) {
                $this->writeAuditNote->handle($registrant->person, $by, implode('. ', array_map($this->describe(...), $changes)).'.');
            }

            return $changes;
        });
    }

    /**
     * @throws ValidationException
     */
    private function normalize(mixed $value, int|string $index, string $aspect, ExamScale $scale): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && preg_match('/^\d{1,3}$/', trim($value)) === 1) {
            $value = (int) trim($value);
        }

        if (! is_int($value) || ! $scale->isValidScore($value)) {
            throw ValidationException::withMessages(["scores.{$index}" => $scale->isNumeric()
                ? "Nilai {$aspect} harus berupa angka bulat 0 sampai 100."
                : "Nilai {$aspect} harus salah satu dari: ".implode(', ', array_column($scale->levels(), 'label')).'.']);
        }

        return $value;
    }

    /**
     * @param  array{aspect: string, from: int|null, to: int|null, from_label: string|null, to_label: string|null}  $change
     */
    private function describe(array $change): string
    {
        return match (true) {
            $change['from'] === null => "Nilai {$change['aspect']} diisi {$change['to_label']}",
            $change['to'] === null => "Nilai {$change['aspect']} dikosongkan (sebelumnya {$change['from_label']})",
            default => "Nilai {$change['aspect']} diubah dari {$change['from_label']} ke {$change['to_label']}",
        };
    }
}
