<?php

namespace App\Actions\Admission;

use App\Actions\Notes\WritePersonAuditNote;
use App\Enums\RegistrantStatus;
use App\Models\PaymentAllocation;
use App\Models\Registrant;
use App\Models\RegistrantDocument;
use App\Models\User;
use App\Support\ParentAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeleteRegistrant
{
    public function __construct(private WritePersonAuditNote $writeAuditNote) {}

    /**
     * Remove a registration the committee found invalid (a test, a duplicate, a made-up one) so the list only holds
     * real applicants. Its uploaded files, bills and history go with it. A registration that is already a student, or
     * that has any payment recorded against its bills, stays: money and placements are never erased from here.
     *
     * With `$deleteAccount` the parent's login is removed too, but only when that parent has no other registration
     * and is not staff. The child's and parent's person records stay, so older notes keep their subject; the reason
     * is left as a private note on the child.
     *
     * @return array{name: string, account_deleted: bool}
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, User $by, string $reason, bool $deleteAccount = false): array
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Tulis alasan menghapus pendaftar.']);
        }

        $photo = null;
        $documentPaths = [];

        $result = DB::transaction(function () use ($registrant, $by, $reason, $deleteAccount, &$photo, &$documentPaths): array {
            $fresh = Registrant::query()->lockForUpdate()->with(['person', 'organization', 'documents'])->find($registrant->id);

            if ($fresh === null) {
                throw ValidationException::withMessages(['registrant' => 'Pendaftar ini sudah dihapus. Muat ulang halamannya.']);
            }

            if ($fresh->status === RegistrantStatus::Placed || $fresh->student()->exists()) {
                throw ValidationException::withMessages(['registrant' => 'Sudah ditempatkan sebagai siswa. Batalkan penempatannya dulu.']);
            }

            $hasPayments = PaymentAllocation::query()
                ->whereIn('bill_item_id', DB::table('bill_items')
                    ->join('bills', 'bills.id', '=', 'bill_items.bill_id')
                    ->where('bills.registrant_id', $fresh->id)
                    ->select('bill_items.id'))
                ->exists();

            if ($hasPayments) {
                throw ValidationException::withMessages(['registrant' => 'Sudah ada pembayaran yang tercatat, jadi tidak bisa dihapus. Batalkan pembayarannya dulu bila memang keliru.']);
            }

            $parentAccount = $deleteAccount ? ParentAccount::for($fresh) : null;
            $deletableAccount = $parentAccount !== null
                && ! Registrant::query()->where('registered_by_person_id', $fresh->registered_by_person_id)->whereKeyNot($fresh->id)->exists();

            $name = $fresh->person->name;
            $summary = "Pendaftaran di {$fresh->organization->name} (status {$fresh->status->label()}) dihapus oleh {$by->name}. Alasan: {$reason}"
                .($deletableAccount ? ' Akun login orang tua ikut dihapus.' : '');

            $photo = $fresh->formal_photo_path;
            $documentPaths = $fresh->documents->pluck('path')->all();

            $this->writeAuditNote->handle($fresh->person, $by, $summary);

            $fresh->delete();

            if ($deletableAccount) {
                $parentAccount->delete();
            }

            return ['name' => $name, 'account_deleted' => $deletableAccount];
        });

        if ($photo !== null) {
            Storage::disk('public')->delete($photo);
        }

        Storage::disk(RegistrantDocument::DISK)->delete($documentPaths);

        return $result;
    }
}
