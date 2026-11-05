<?php

use App\Enums\DefaultRole;
use App\Models\Parentship;
use App\Models\Person;
use App\Support\PersonDuplicates;
use App\Support\PhoneNumber;
use Inertia\Support\SessionKey;

/**
 * What the form sent back as candidates ("Kemungkinan sudah ada"), read from the Inertia flash.
 *
 * @return list<array<string, mixed>>
 */
function flashedDuplicates(): array
{
    return session(SessionKey::FLASH_DATA.'.duplicates', []);
}

beforeEach(function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $this->existing = Person::factory()->create([
        'name' => 'Ahmad Fauzi',
        'birth_date' => '2012-03-04',
        'phone' => '081234567890',
        'email' => 'Fauzi@Example.com',
        'family_card_number' => '3201234567890123',
        'nik' => '3201987654321098',
    ]);
});

describe('adding a person', function () {
    test('stops when the name and birth date match, ignoring case, spacing and punctuation', function () {
        $this->from(route('admin.people.create'))
            ->post(route('admin.people.store'), ['name' => ' ahmad  FAUZI. ', 'birth_date' => '2012-03-04'])
            ->assertRedirect(route('admin.people.create'))
            ->assertSessionHasErrors('duplicate');

        expect(Person::where('name', 'like', '%ahmad%')->count())->toBe(1)
            ->and(flashedDuplicates())->toHaveCount(1)
            ->and(flashedDuplicates()[0])->toMatchArray([
                'id' => $this->existing->id,
                'name' => 'Ahmad Fauzi',
                'birth_date' => '2012-03-04',
                'phone_masked' => '0812••••7890',
                'has_nik' => true,
                'has_family_card' => true,
                'reasons' => ['Nama dan tanggal lahir sama'],
            ]);
    });

    test('does not stop for the same name with another birth date, or the same birth date with another name', function () {
        $before = Person::count();

        $this->post(route('admin.people.store'), ['name' => 'Ahmad Fauzi', 'birth_date' => '2012-03-05'])->assertSessionHasNoErrors();
        $this->post(route('admin.people.store'), ['name' => 'Budi Santoso', 'birth_date' => '2012-03-04'])->assertSessionHasNoErrors();
        $this->post(route('admin.people.store'), ['name' => 'Ahmad Fauzi'])->assertSessionHasNoErrors();

        expect(Person::count())->toBe($before + 3);
    });

    test('stops on the same phone number however it is typed', function () {
        $this->post(route('admin.people.store'), ['name' => 'Orang Lain', 'phone' => '0812-3456-7890'])->assertSessionHasErrors('duplicate');
        expect(flashedDuplicates()[0]['reasons'])->toBe(['Nomor HP sama']);

        $this->post(route('admin.people.store'), ['name' => 'Orang Lain', 'phone' => '+62 812 3456 7890'])->assertSessionHasErrors('duplicate');
        $this->post(route('admin.people.store'), ['name' => 'Orang Lain', 'phone' => '0812 3456 7891'])->assertSessionHasNoErrors();
    });

    test('stops on the same email without regard to case', function () {
        $this->post(route('admin.people.store'), ['name' => 'Orang Lain', 'email' => 'fauzi@example.COM'])->assertSessionHasErrors('duplicate');

        expect(flashedDuplicates()[0]['reasons'])->toBe(['Email sama']);
    });

    test('stops on the same family card number', function () {
        $this->post(route('admin.people.store'), ['name' => 'Adik Fauzi', 'family_card_number' => '3201 2345 6789 0123'])->assertSessionHasErrors('duplicate');

        expect(flashedDuplicates()[0]['reasons'])->toBe(['Nomor KK sama']);
    });

    test('lists every reason a candidate matches and several candidates', function () {
        $other = Person::factory()->create(['name' => 'Siti', 'phone' => '081234567890']);

        $this->post(route('admin.people.store'), ['name' => 'Ahmad Fauzi', 'birth_date' => '2012-03-04', 'phone' => '081234567890'])->assertSessionHasErrors('duplicate');

        $candidates = collect(flashedDuplicates())->keyBy('id');

        expect($candidates)->toHaveCount(2)
            ->and($candidates[$this->existing->id]['reasons'])->toBe(['Nama dan tanggal lahir sama', 'Nomor HP sama'])
            ->and($candidates[$other->id]['reasons'])->toBe(['Nomor HP sama']);
    });

    test('goes on when the duplicate is confirmed, and saves only what the person typed', function () {
        $this->post(route('admin.people.store'), ['name' => 'Ahmad Fauzi', 'birth_date' => '2012-03-04', 'confirm_duplicate' => true])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect(Person::where('name', 'Ahmad Fauzi')->count())->toBe(2);
    });

    test('a person with nothing in common is added without a question', function () {
        $this->post(route('admin.people.store'), ['name' => 'Orang Baru', 'birth_date' => '2000-01-01', 'phone' => '087700001111', 'email' => 'baru@example.com'])->assertSessionHasNoErrors();

        expect(Person::where('name', 'Orang Baru')->exists())->toBeTrue();
    });

    test('phone and card numbers are stored as digits, with an optional leading plus', function () {
        $this->post(route('admin.people.store'), ['name' => 'Orang Satu', 'phone' => '0877-0000-1111', 'family_card_number' => '3301 1111 2222 3333'])->assertSessionHasNoErrors();
        $this->post(route('admin.people.store'), ['name' => 'Orang Dua', 'phone' => '+62 877-0000-2222'])->assertSessionHasNoErrors();
        $this->post(route('admin.people.store'), ['name' => 'Orang Tiga', 'phone' => '   '])->assertSessionHasNoErrors();

        expect(Person::where('name', 'Orang Satu')->first()->only(['phone', 'family_card_number']))->toBe(['phone' => '087700001111', 'family_card_number' => '3301111122223333'])
            ->and(Person::where('name', 'Orang Dua')->value('phone'))->toBe('+6287700002222')
            ->and(Person::where('name', 'Orang Tiga')->value('phone'))->toBeNull();

        $this->post(route('admin.people.store'), ['name' => 'Orang Empat', 'phone' => '12'])->assertSessionHasErrors('phone');
    });

    test('phone numbers are cleaned when a person is edited too', function () {
        $person = Person::factory()->create();

        $this->put(route('admin.people.update', $person), ['name' => $person->name, 'phone' => '0812-9999-0000'])->assertSessionHasNoErrors();

        expect($person->refresh()->phone)->toBe('081299990000');
    });
});

describe('a NIK that is already on record', function () {
    test('keeps what was typed, names the person on record, and changes nothing', function () {
        $before = Person::count();

        $this->from(route('admin.people.create'))
            ->post(route('admin.people.store'), ['name' => 'Nama Berbeda', 'nik' => '3201987654321098', 'phone' => '087700003333'])
            ->assertRedirect(route('admin.people.create'))
            ->assertSessionHasErrors(['nik' => 'NIK ini sudah tercatat atas nama Ahmad Fauzi, bukan Nama Berbeda. Cek lagi NIK dan namanya; data yang sudah ada tidak diubah.']);

        expect(session(SessionKey::FLASH_DATA.'.existing_person'))->toBe([
            'id' => $this->existing->id,
            'name' => 'Ahmad Fauzi',
            'typed_name' => 'Nama Berbeda',
            'name_differs' => true,
        ])->and($this->existing->refresh()->only(['name', 'phone']))->toBe(['name' => 'Ahmad Fauzi', 'phone' => '081234567890'])
            ->and(Person::count())->toBe($before);
    });

    test('does not say the name differs when it only differs in case or spacing', function () {
        $this->post(route('admin.people.store'), ['name' => 'AHMAD  fauzi', 'nik' => '3201987654321098'])
            ->assertSessionHasErrors(['nik' => 'NIK ini sudah tercatat atas nama Ahmad Fauzi. Buka datanya, tidak perlu menambah lagi.']);

        expect(session(SessionKey::FLASH_DATA.'.existing_person.name_differs'))->toBeFalse();
    });

    test('is not offered as a probable duplicate', function () {
        expect(PersonDuplicates::find(['name' => 'Ahmad Fauzi', 'birth_date' => '2012-03-04', 'nik' => '3201987654321098']))->toBeEmpty();
    });
});

describe('linking a parent', function () {
    beforeEach(function () {
        $this->child = Person::factory()->create();
        $this->twin = Person::factory()->create(['name' => 'Ahmad  Fauzi', 'birth_date' => '2012-03-04', 'nik' => null]);
        $this->link = fn (Person $parent, array $extra = []) => $this->post(route('admin.people.parentships.store', $this->child), [
            'related_person_id' => $parent->id, 'relation' => 'father', 'kind' => 'biological', ...$extra,
        ]);
    });

    test('asks first when the chosen person has a lookalike, and never offers the person themselves', function () {
        ($this->link)($this->existing)->assertSessionHasErrors('duplicate');

        expect(Parentship::count())->toBe(0)
            ->and(collect(flashedDuplicates())->pluck('id')->all())->toBe([$this->twin->id])
            ->and(flashedDuplicates()[0]['reasons'])->toBe(['Nama dan tanggal lahir sama']);
    });

    test('links the chosen person once the lookalike is confirmed', function () {
        ($this->link)($this->existing, ['confirm_duplicate' => true])->assertSessionHasNoErrors();

        expect(Parentship::where(['person_id' => $this->child->id, 'related_person_id' => $this->existing->id])->exists())->toBeTrue();
    });

    test('links the lookalike instead when that is the one picked', function () {
        ($this->link)($this->twin)->assertSessionHasErrors('duplicate');
        expect(collect(flashedDuplicates())->pluck('id')->all())->toBe([$this->existing->id]);
    });

    test('links without a question when nobody looks alike', function () {
        $loner = Person::factory()->create(['name' => 'Unik Sekali', 'birth_date' => '1980-01-01', 'phone' => '087711112222', 'email' => 'unik@example.com', 'family_card_number' => null]);

        ($this->link)($loner)->assertSessionHasNoErrors()->assertRedirect(route('admin.people.show', $this->child));

        expect(Parentship::count())->toBe(1);
    });
});

describe('finding people', function () {
    test('the search finds a person by phone digits in any form, NISN, family card number and NIK prefix', function () {
        Person::factory()->create(['name' => 'Pembanding', 'nisn' => '0012345678', 'phone' => '087700004444', 'family_card_number' => '3301000011112222']);

        $search = fn (string $term) => $this->getJson(route('admin.people.search', ['q' => $term]))->json('data.*.name');

        expect($search('0812-3456'))->toBe(['Ahmad Fauzi'])
            ->and($search('+62 812 3456 7890'))->toBe(['Ahmad Fauzi'])
            ->and($search('628123456'))->toBe(['Ahmad Fauzi'])
            ->and($search('0012345'))->toBe(['Pembanding'])
            ->and($search('3301000011'))->toBe(['Pembanding'])
            ->and($search('32019876'))->toBe(['Ahmad Fauzi']);
    });

    test('percent and underscore in the search only match themselves', function () {
        Person::factory()->create(['name' => 'Siti 100% Rajin']);

        $names = fn (string $term) => $this->getJson(route('admin.people.search', ['q' => $term]))->json('data.*.name');

        expect($names('%'))->toBe(['Siti 100% Rajin'])->and($names('_'))->toBe([]);
    });

    test('search results carry the birth date and a masked phone, and mark people already linked', function () {
        $child = Person::factory()->create();
        Parentship::factory()->create(['person_id' => $child->id, 'related_person_id' => $this->existing->id]);

        $this->getJson(route('admin.people.search', ['q' => 'fauzi', 'except' => $child->id]))
            ->assertJsonPath('data.0.birth_date', '2012-03-04')
            ->assertJsonPath('data.0.phone_masked', '0812••••7890')
            ->assertJsonPath('data.0.linked', true);
    });

    test('the list can be narrowed to people who share a phone number or a name and birth date', function () {
        Person::factory()->create(['name' => 'Kembar', 'birth_date' => '2010-01-01', 'phone' => null]);
        Person::factory()->create(['name' => 'kembar', 'birth_date' => '2010-01-01', 'phone' => null]);
        Person::factory()->create(['name' => 'Satu Telepon', 'phone' => '087788889999']);
        Person::factory()->create(['name' => 'Dua Telepon', 'phone' => '087788889999']);
        Person::factory()->create(['name' => 'Sendiri', 'birth_date' => '2010-01-01', 'phone' => '087700000001']);

        $this->get(route('admin.people.index', ['data' => 'duplikat']))->assertInertia(fn ($page) => $page
            ->has('people.data', 4)
            ->where('counts.data.duplikat', 4)
            ->where('filters.data', 'duplikat'));
    });
});

test('phone numbers are compared by their international form', function () {
    expect(PhoneNumber::same('0812-3456-7890', '+62 812 3456 7890'))->toBeTrue()
        ->and(PhoneNumber::same('081234567890', '081234567891'))->toBeFalse()
        ->and(PhoneNumber::same(null, '081234567890'))->toBeFalse()
        ->and(PhoneNumber::variants('0812-3456-7890'))->toBe(['0812345678'.'90', '62812345678'.'90', '81234567890']);
});
