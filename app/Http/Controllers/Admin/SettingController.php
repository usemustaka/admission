<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Ability;
use App\Enums\SettingGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSettingsRequest;
use App\Models\Setting;
use App\Support\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SettingController extends Controller
{
    public function index(): RedirectResponse
    {
        Gate::authorize(Ability::SettingManage->value);

        return to_route('admin.settings.edit', SettingGroup::Identity->value);
    }

    public function edit(string $group): Response
    {
        Gate::authorize(Ability::SettingManage->value);

        $current = SettingGroup::from($group);
        Brand::flush();

        return Inertia::render('admin/settings/edit', [
            'group' => $current->value,
            'groups' => collect(SettingGroup::cases())->map(fn (SettingGroup $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
            ])->values(),
            'values' => Arr::undot(collect($current->rules())
                ->keys()
                ->mapWithKeys(fn (string $key): array => [$key => Brand::get($key)])
                ->all()),
            'files' => ['logo_url' => Brand::logoUrl(), 'favicon_url' => Brand::faviconUrl()],
            'defaults' => [
                'brand.name' => config('app.name'),
                'brand.accent_color' => Brand::DEFAULT_ACCENT,
                'brand.welcome_title' => Brand::DEFAULT_WELCOME_TITLE,
                'brand.welcome_description' => Brand::DEFAULT_WELCOME_DESCRIPTION,
            ],
        ]);
    }

    public function update(SaveSettingsRequest $request, string $group): RedirectResponse
    {
        $current = SettingGroup::from($group);
        $validated = $request->validated();
        $replaced = [];

        $storedFiles = $current === SettingGroup::Identity
            ? [
                'brand.logo_path' => $this->storeFile($request->file('logo'), $request->boolean('remove_logo')),
                'brand.favicon_path' => $this->storeFile($request->file('favicon'), $request->boolean('remove_favicon')),
            ]
            : [];

        try {
            DB::transaction(function () use ($current, $validated, $storedFiles, &$replaced): void {
                foreach (array_keys($current->rules()) as $key) {
                    $value = data_get($validated, $key);

                    Setting::put($key, is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value);
                }

                foreach ($storedFiles as $key => $stored) {
                    if ($stored === false) {
                        continue;
                    }

                    $old = Setting::get($key);

                    if (is_string($old)) {
                        $replaced[] = $old;
                    }

                    Setting::put($key, $stored);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete(array_filter($storedFiles, is_string(...)));

            throw $exception;
        }

        Storage::disk('public')->delete($replaced);
        Brand::flush();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pengaturan {$current->label()} sudah disimpan."]);

        return back();
    }

    /**
     * Store a new upload before anything is changed, so a failure leaves the old file in place.
     *
     * @return string|false|null The new path, null to clear the setting, false to leave it alone.
     */
    private function storeFile(?UploadedFile $file, bool $remove): string|false|null
    {
        if ($file === null) {
            return $remove ? null : false;
        }

        return $file->store('brand', 'public') ?: false;
    }
}
