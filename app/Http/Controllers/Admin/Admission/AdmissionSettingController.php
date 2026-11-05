<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionSettingController extends Controller
{
    public function edit(): Response
    {
        Gate::authorize(Ability::AnnouncementManage->value);

        return Inertia::render('admin/admission-settings', [
            'template' => Announcement::template(),
            'defaultTemplate' => Announcement::DEFAULT_TEMPLATE,
            'placeholders' => collect(Announcement::PLACEHOLDERS)->map(fn (string $description, string $token): array => ['token' => $token, 'description' => $description])->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize(Ability::AnnouncementManage->value);

        $validated = $request->validate(['template' => ['required', 'string', 'max:2000']], [], ['template' => 'Templat pengumuman']);

        Setting::put(Announcement::SETTING_KEY, $validated['template']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Templat pengumuman sudah disimpan.']);

        return back();
    }
}
