<?php

use App\Enums\Ability;
use App\Enums\RegistrantStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\ExamDay;
use App\Models\ExamSession;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\RegistrantStatusChange;
use App\Models\Setting;
use App\Models\User;
use App\Support\Announcement;
use App\Support\Brand;
use App\Support\MessageTemplates;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

function templateManager(): User
{
    test()->seed(RolesAndPermissionsSeeder::class);

    return tap(User::factory()->create())->givePermissionTo([Ability::AdminAccess->value, Ability::AnnouncementManage->value]);
}

beforeEach(function () {
    $this->withoutVite();
    Brand::flush();

    $this->registrant = admissionRegistrant(RegistrantStatus::Validated);
    $this->contact = Person::factory()->create(['name' => 'Siti Aminah', 'phone' => '0812-3456-7890']);
    Parentship::query()->where('person_id', $this->registrant->person_id)->update(['is_primary_contact' => false]);
    Person::query()->whereKey(Parentship::query()->where('person_id', $this->registrant->person_id)->pluck('related_person_id'))->update(['phone' => null]);
    Parentship::factory()->primaryContact()->create(['person_id' => $this->registrant->person_id, 'related_person_id' => $this->contact->id]);
});

describe('defaults and rendering', function () {
    test('every kind has a default that only uses placeholders it knows', function () {
        foreach (MessageTemplates::kinds() as $kind) {
            expect(MessageTemplates::unknownTokens($kind, MessageTemplates::defaultTemplate($kind)))->toBe([], $kind)
                ->and(MessageTemplates::template($kind))->toBe(MessageTemplates::defaultTemplate($kind));
        }
    });

    test('the announcement follows the result: tuntas, belum tuntas and nilai belum ada', function () {
        $name = $this->registrant->person->name;

        expect(Announcement::textFor($this->registrant, 'announcement'))->toContain('belum ada nilai');

        $this->registrant->update(['exam_scores' => [80, 50]]);
        expect(Announcement::announcementKind($this->registrant))->toBe(MessageTemplates::ANNOUNCEMENT_FAILED)
            ->and(Announcement::textFor($this->registrant, 'announcement'))->toContain($name)->toContain('belum tuntas')->toContain('Bapak/Ibu Siti Aminah');

        $this->registrant->update(['exam_scores' => [80, 60]]);
        expect(Announcement::announcementKind($this->registrant))->toBe(MessageTemplates::ANNOUNCEMENT_PASSED)
            ->and(Announcement::textFor($this->registrant, 'announcement'))->toContain('Selamat')->toContain('tuntas');
    });

    test('each outcome uses its own saved template', function () {
        Setting::put('admission.announcement.passed', 'Lulus: {nama} {hasil}');
        Setting::put('admission.announcement.failed', 'Belum: {nama} {hasil}');
        Setting::put('admission.announcement.incomplete', 'Tunggu: {nama} {hasil}');
        $name = $this->registrant->person->name;

        expect(Announcement::textFor($this->registrant, 'announcement'))->toBe("Tunggu: {$name} belum ada nilai");

        $this->registrant->update(['exam_scores' => [80, 50]]);
        expect(Announcement::textFor($this->registrant, 'announcement'))->toBe("Belum: {$name} belum tuntas");

        $this->registrant->update(['exam_scores' => [80, 60]]);
        expect(Announcement::textFor($this->registrant, 'announcement'))->toBe("Lulus: {$name} tuntas");
    });

    test('the old single announcement template still applies to every outcome until a specific one is saved', function () {
        Setting::put(Announcement::SETTING_KEY, 'Lama: {nama} {hasil}');
        $name = $this->registrant->person->name;
        $this->registrant->update(['exam_scores' => [80, 60]]);

        expect(Announcement::text($this->registrant))->toBe("Lama: {$name} tuntas");

        Setting::put('admission.announcement.passed', 'Baru: {nama}');
        expect(Announcement::text($this->registrant))->toBe("Baru: {$name}");

        Setting::put('admission.announcement.passed', '   ');
        expect(Announcement::text($this->registrant))->toBe("Lama: {$name} tuntas");
    });

    test('a registration without exam aspects counts as lolos', function () {
        $this->registrant->update(['ruleset_snapshot' => [...$this->registrant->ruleset_snapshot, 'exam_aspects' => []]]);

        expect(Announcement::announcementKind($this->registrant))->toBe(MessageTemplates::ANNOUNCEMENT_PASSED)
            ->and(Announcement::textFor($this->registrant, 'announcement'))->toContain('dinyatakan lolos');
    });

    test('the revision reminder carries the latest reason of the committee', function () {
        RegistrantStatusChange::factory()->create(['registrant_id' => $this->registrant->id, 'to_status' => RegistrantStatus::Revision, 'note' => 'Foto akta buram']);
        RegistrantStatusChange::factory()->create(['registrant_id' => $this->registrant->id, 'to_status' => RegistrantStatus::Revision, 'note' => 'Alamat belum lengkap']);

        expect(Announcement::textFor($this->registrant, 'revision'))->toContain('Alamat belum lengkap.')->not->toContain('Foto akta buram');
    });

    test('the payment reminder carries what is still owed and the bank account', function () {
        Setting::put('payment.bank_name', 'BCA');
        Setting::put('payment.account_number', '1234567');
        Setting::put('payment.account_holder', 'Yayasan Mustaka');
        $bill = Bill::factory()->create(['registrant_id' => $this->registrant->id]);
        $paid = BillItem::factory()->create(['bill_id' => $bill->id, 'amount' => 100000, 'sort_order' => 0]);
        BillItem::factory()->create(['bill_id' => $bill->id, 'amount' => 300000, 'sort_order' => 1]);
        verifiedPayment($paid, 150000);

        $text = Announcement::textFor($this->registrant, 'payment');

        expect($text)->toContain('Rp300.000')->toContain('BCA 1234567 a.n. Yayasan Mustaka');
    });

    test('the exam reminder carries the chosen session, or says none was chosen', function () {
        expect(Announcement::textFor($this->registrant, 'exam'))->toContain('jadwal belum dipilih');

        $day = ExamDay::factory()->create(['ruleset_id' => $this->registrant->ruleset_id, 'date' => '2026-11-09']);
        $session = ExamSession::factory()->create(['exam_day_id' => $day->id, 'label' => 'Sesi 1', 'start_time' => '08:00:00', 'end_time' => '10:00:00']);
        $this->registrant->update(['exam_session_id' => $session->id]);

        expect(Announcement::textFor($this->registrant->refresh(), 'exam'))->toContain('Senin, 9 November 2026')->toContain('Sesi 1 · 08:00 s.d. 10:00');
    });

    test('values can be handed in instead of looked up', function () {
        Setting::put('billing.reminder_template', 'Sisa {sisa} untuk {nama}');

        expect(Announcement::textFor($this->registrant, 'payment-arrears', ['sisa' => 'Rp5.000']))->toBe("Sisa Rp5.000 untuk {$this->registrant->person->name}");
    });

    test('an unknown kind is refused', function () {
        expect(fn () => Announcement::textFor($this->registrant, 'tidak-ada'))->toThrow(InvalidArgumentException::class);
    });
});

describe('whatsapp links', function () {
    test('the link has the number in international form and the message URL-encoded', function () {
        Setting::put('admission.reminder.exam', "Halo {wali} & {nama}\nJam: {sesi}?");

        $url = Announcement::whatsappUrlFor($this->registrant, 'exam');
        $text = urldecode(explode('?text=', $url)[1]);

        expect($url)->toStartWith('https://wa.me/6281234567890?text=')
            ->and($url)->not->toContain(' ')->not->toContain("\n")->toContain('%26')->toContain('%0A')
            ->and($text)->toBe("Halo Bapak/Ibu Siti Aminah & {$this->registrant->person->name}\nJam: silakan pilih sesi di akun pendaftaran?")
            ->and($text)->toBe(Announcement::textFor($this->registrant, 'exam'));
    });

    test('it falls back to another parent, then to whoever registered the child, then to nothing', function () {
        $this->contact->update(['phone' => null]);
        $other = Person::factory()->create(['name' => 'Budi', 'phone' => '0813-0000-1111']);
        Parentship::factory()->create(['person_id' => $this->registrant->person_id, 'related_person_id' => $other->id]);

        expect(Announcement::whatsappUrlFor($this->registrant, 'payment-arrears'))->toStartWith('https://wa.me/6281300001111?text=')
            ->and(Announcement::textFor($this->registrant, 'payment-arrears'))->toContain('Bapak/Ibu Budi');

        $other->update(['phone' => null]);
        $this->registrant->registeredBy->update(['phone' => '0899-1111-2222']);
        expect(Announcement::whatsappUrlFor($this->registrant, 'payment-arrears'))->toStartWith('https://wa.me/6289911112222?text=');

        $this->registrant->registeredBy->update(['phone' => null]);
        expect(Announcement::whatsappUrlFor($this->registrant, 'payment-arrears'))->toBeNull();
    });
});

describe('the settings page', function () {
    test('lists every template with its tokens and sample values', function () {
        Carbon::setTestNow('2026-11-05 10:00:00');
        $this->actingAs(templateManager());

        $this->get(route('admin.admission-settings.edit'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('admin/admission-settings')
            ->has('templates', 7)
            ->where('templates.0.kind', 'announcement-passed')
            ->where('templates.0.template', MessageTemplates::defaultTemplate('announcement-passed'))
            ->where('templates.1.sample.hasil', 'belum tuntas')
            ->where('templates.2.sample.hasil', 'belum ada nilai')
            ->where('templates.6.kind', 'payment-arrears')
            ->has('templates.6.tokens', 6)
            ->where('limits.max', 2000));
    });

    test('saves each template under its own setting key', function () {
        $this->actingAs(templateManager());

        $this->put(route('admin.admission-settings.update'), ['templates' => [
            'announcement-passed' => 'Lulus {nama}',
            'announcement-failed' => 'Belum {nama}',
            'announcement-incomplete' => 'Tunggu {nama}',
            'revision' => 'Revisi {nama}: {alasan}',
            'payment' => 'Bayar {nama} {sisa} {rekening}',
            'exam' => 'Ujian {nama} {tanggal} {sesi}',
            'payment-arrears' => 'Tunggak {nama} {sisa}',
        ]])->assertSessionHasNoErrors();

        expect(Setting::get('admission.announcement.passed'))->toBe('Lulus {nama}')
            ->and(Setting::get('admission.announcement.failed'))->toBe('Belum {nama}')
            ->and(Setting::get('admission.announcement.incomplete'))->toBe('Tunggu {nama}')
            ->and(Setting::get('admission.reminder.revision'))->toBe('Revisi {nama}: {alasan}')
            ->and(Setting::get('admission.reminder.payment'))->toBe('Bayar {nama} {sisa} {rekening}')
            ->and(Setting::get('admission.reminder.exam'))->toBe('Ujian {nama} {tanggal} {sesi}')
            ->and(Setting::get('billing.reminder_template'))->toBe('Tunggak {nama} {sisa}');
    });

    test('refuses placeholders a template does not know and lists them all', function () {
        $this->actingAs(templateManager());

        $response = $this->put(route('admin.admission-settings.update'), ['templates' => [
            'announcement-passed' => 'Halo {nama} {sisa} {typo}',
            'exam' => 'Ujian {tanggal}',
        ]]);

        $response->assertSessionHasErrors('templates.announcement-passed');
        expect(session('errors')->first('templates.announcement-passed'))->toContain('{sisa}, {typo}')
            ->and(Setting::query()->where('key', 'admission.reminder.exam')->exists())->toBeFalse();
    });

    test('a blank template goes back to the default and a long one is refused', function () {
        $this->actingAs(templateManager());
        Setting::put('admission.reminder.exam', 'Lama {nama}');

        $this->put(route('admin.admission-settings.update'), ['templates' => ['exam' => '']])->assertSessionHasNoErrors();
        expect(MessageTemplates::template('exam'))->toBe(MessageTemplates::defaultTemplate('exam'));

        $this->put(route('admin.admission-settings.update'), ['templates' => ['exam' => str_repeat('a', 2001)]])->assertSessionHasErrors('templates.exam');
    });

    test('an unknown kind is refused', function () {
        $this->actingAs(templateManager());

        $this->put(route('admin.admission-settings.update'), ['templates' => ['rahasia' => 'x']])->assertSessionHasErrors('templates.rahasia');
    });

    test('needs the announcement ability', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(tap(User::factory()->create())->givePermissionTo([Ability::AdminAccess->value, Ability::RegistrantView->value, Ability::RegistrantManage->value, Ability::PaymentView->value]));

        $this->get(route('admin.admission-settings.edit'))->assertForbidden();
        $this->put(route('admin.admission-settings.update'), ['templates' => ['exam' => 'x']])->assertForbidden();
        expect(Setting::query()->where('key', 'admission.reminder.exam')->exists())->toBeFalse();
    });
});
