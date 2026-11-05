<?php

namespace App\Actions\Setup;

use App\Actions\Users\CreateStaffAccount;
use App\Enums\DefaultRole;
use App\Enums\OrganizationCategory;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wave;
use App\Models\Year;
use App\Support\Brand;
use App\Support\SetupState;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The first-run setup in one step: roles and permissions, the app's identity and contact details, the
 * organizations, the first school year and wave, and the super admin. Everything is written together, so a failure
 * leaves a fresh install fresh, and only one setup can run at a time.
 */
class CompleteSetup
{
    public function __construct(private CreateStaffAccount $createStaffAccount) {}

    /**
     * @param  array<string, mixed>  $data  the validated request
     */
    public function handle(array $data, ?UploadedFile $logo = null): User
    {
        return Cache::lock('app-setup', 60)->block(10, function () use ($data, $logo): User {
            abort_if(SetupState::isComplete(), 404);

            $logoPath = $logo?->store('brand', 'public');

            try {
                $user = DB::transaction(function () use ($data, $logoPath): User {
                    (new RolesAndPermissionsSeeder)->run();

                    $this->saveSettings($data, $logoPath);
                    $this->createOrganizations($data['organizations'] ?? []);
                    $this->createYear($data['year'] ?? []);
                    $this->createWave($data['wave'] ?? []);

                    $user = $this->createStaffAccount->handle($data['admin']['name'], $data['admin']['email'], DefaultRole::SuperAdmin->value, $data['admin']['password']);

                    SetupState::markComplete();

                    return $user;
                });
            } catch (Throwable $exception) {
                if ($logoPath !== null) {
                    Storage::disk('public')->delete($logoPath);
                }

                throw $exception;
            }

            Brand::flush();

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveSettings(array $data, ?string $logoPath): void
    {
        $values = [
            'brand.name' => $data['brand']['name'],
            'brand.accent_color' => $data['brand']['accent_color'] ?? null,
            'brand.logo_path' => $logoPath,
            'contact.phone' => $data['contact']['phone'] ?? null,
            'contact.whatsapp' => $data['contact']['whatsapp'] ?? null,
            'contact.email' => $data['contact']['email'] ?? null,
            'contact.address' => $data['contact']['address'] ?? null,
            'payment.bank_name' => $data['payment']['bank_name'] ?? null,
            'payment.account_number' => $data['payment']['account_number'] ?? null,
            'payment.account_holder' => $data['payment']['account_holder'] ?? null,
        ];

        foreach ($values as $key => $value) {
            if (is_string($value) && trim($value) !== '') {
                Setting::put($key, trim($value));
            }
        }
    }

    /**
     * @param  list<array{name: string, category?: string|null}>  $organizations
     */
    private function createOrganizations(array $organizations): void
    {
        $order = (int) Organization::query()->max('sort_order');

        foreach ($organizations as $row) {
            Organization::query()->create([
                'name' => trim($row['name']),
                'code' => $this->uniqueCode($row['name']),
                'category' => OrganizationCategory::tryFrom((string) ($row['category'] ?? '')),
                'sort_order' => ++$order,
            ]);
        }
    }

    /**
     * A short lowercase code from the name, made unique when two names would give the same one.
     */
    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'lembaga';
        $code = $base;

        for ($suffix = 2; Organization::query()->where('code', $code)->exists(); $suffix++) {
            $code = "{$base}-{$suffix}";
        }

        return $code;
    }

    /**
     * @param  array{name?: string|null, starts_on?: string|null, ends_on?: string|null, with_periods?: bool|null}  $year
     */
    private function createYear(array $year): void
    {
        if (blank($year['name'] ?? null)) {
            return;
        }

        $created = Year::query()->create([
            'name' => trim($year['name']),
            'starts_on' => $year['starts_on'],
            'ends_on' => $year['ends_on'],
            'is_active' => ! Year::query()->where('is_active', true)->exists(),
        ]);

        if (! ($year['with_periods'] ?? true)) {
            return;
        }

        $start = Carbon::parse($year['starts_on'])->startOfDay();
        $end = Carbon::parse($year['ends_on'])->startOfDay();
        $middle = $start->copy()->addDays((int) floor($start->diffInDays($end) / 2));

        $created->periods()->create(['name' => 'Ganjil', 'sequence' => 1, 'starts_on' => $start, 'ends_on' => $middle, 'is_active' => true]);
        $created->periods()->create(['name' => 'Genap', 'sequence' => 2, 'starts_on' => $middle->copy()->addDay(), 'ends_on' => $end, 'is_active' => false]);
    }

    /**
     * @param  array{name?: string|null, starts_on?: string|null, ends_on?: string|null}  $wave
     */
    private function createWave(array $wave): void
    {
        if (blank($wave['name'] ?? null)) {
            return;
        }

        Wave::query()->create([
            'name' => trim($wave['name']),
            'starts_on' => $wave['starts_on'],
            'ends_on' => $wave['ends_on'],
            'is_open' => true,
        ]);
    }
}
