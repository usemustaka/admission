<?php

use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Note;
use App\Models\Registrant;
use App\Models\RegistrantDocument;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake(RegistrantDocument::DISK);
    $this->admin = userWithRole(DefaultRole::Admin);
    $this->parent = userWithRole(DefaultRole::User);
    $this->registrant = admissionRegistrant(RegistrantStatus::Draft, ['registered_by_person_id' => $this->parent->person_id]);
});

test('an admin deletes an invalid registrant with a reason, which is kept as a private note on the child', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.registrants.destroy', $this->registrant), ['reason' => 'Data uji coba'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.registrants.index'));

    expect(Registrant::find($this->registrant->id))->toBeNull()
        ->and(User::find($this->parent->id))->not->toBeNull()
        ->and(Note::where('person_id', $this->registrant->person_id)->where('body', 'like', '%Data uji coba%')->exists())->toBeTrue();
});

test('a reason is required', function () {
    $this->actingAs($this->admin)->delete(route('admin.registrants.destroy', $this->registrant), [])->assertSessionHasErrors('reason');

    expect(Registrant::find($this->registrant->id))->not->toBeNull();
});

test('the parent login can be deleted too, only when the parent has no other registrant', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.registrants.destroy', $this->registrant), ['reason' => 'Ganda', 'delete_account' => true])
        ->assertSessionHasNoErrors();

    expect(User::find($this->parent->id))->toBeNull();

    $other = userWithRole(DefaultRole::User);
    $first = admissionRegistrant(RegistrantStatus::Draft, ['registered_by_person_id' => $other->person_id]);
    admissionRegistrant(RegistrantStatus::Draft, ['registered_by_person_id' => $other->person_id]);

    $this->delete(route('admin.registrants.destroy', $first), ['reason' => 'Ganda', 'delete_account' => true])->assertSessionHasNoErrors();

    expect(Registrant::find($first->id))->toBeNull()
        ->and(User::find($other->id))->not->toBeNull();
});

test('uploaded files are removed from storage', function () {
    $photo = 'photos/anak.jpg';
    Storage::disk('public')->put($photo, 'x');
    $this->registrant->update(['formal_photo_path' => $photo]);
    $document = RegistrantDocument::factory()->create(['registrant_id' => $this->registrant->id]);
    Storage::disk(RegistrantDocument::DISK)->put($document->path, 'x');

    $this->actingAs($this->admin)->delete(route('admin.registrants.destroy', $this->registrant), ['reason' => 'Uji'])->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($photo);
    Storage::disk(RegistrantDocument::DISK)->assertMissing($document->path);
});

test('a placed registrant and one with a payment are protected', function () {
    $placed = admissionRegistrant(RegistrantStatus::Placed);
    Student::factory()->create(['registrant_id' => $placed->id]);

    [$paid, $bill] = billedChild($this->parent);
    verifiedPayment($bill->items->first(), 50000);

    $this->actingAs($this->admin);
    $this->delete(route('admin.registrants.destroy', $placed), ['reason' => 'Uji'])->assertSessionHasErrors('registrant');
    $this->delete(route('admin.registrants.destroy', $paid), ['reason' => 'Uji'])->assertSessionHasErrors('registrant');

    expect(Registrant::find($placed->id))->not->toBeNull()->and(Registrant::find($paid->id))->not->toBeNull();
});

test('bulk delete removes the ones that qualify and skips the rest', function () {
    $second = admissionRegistrant(RegistrantStatus::Review);
    $placed = admissionRegistrant(RegistrantStatus::Placed);
    Student::factory()->create(['registrant_id' => $placed->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.registrants.bulk-delete'), ['ids' => [$this->registrant->id, $second->id, $placed->id], 'reason' => 'Bersih-bersih'])
        ->assertSessionHasNoErrors();

    expect(Registrant::find($this->registrant->id))->toBeNull()
        ->and(Registrant::find($second->id))->toBeNull()
        ->and(Registrant::find($placed->id))->not->toBeNull();
});

test('staff without the delete ability cannot delete', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator))
        ->delete(route('admin.registrants.destroy', $this->registrant), ['reason' => 'Uji'])
        ->assertForbidden();

    $this->post(route('admin.registrants.bulk-delete'), ['ids' => [$this->registrant->id], 'reason' => 'Uji'])->assertForbidden();

    expect(Registrant::find($this->registrant->id))->not->toBeNull();
});
