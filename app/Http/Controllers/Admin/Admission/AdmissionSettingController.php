<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\MessageTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionSettingController extends Controller
{
    public function edit(): Response
    {
        Gate::authorize(Ability::AnnouncementManage->value);

        return Inertia::render('admin/admission-settings', [
            'templates' => collect(MessageTemplates::kinds())->map(fn (string $kind): array => [
                'kind' => $kind,
                'label' => MessageTemplates::label($kind),
                'description' => MessageTemplates::description($kind),
                'template' => MessageTemplates::template($kind),
                'default' => MessageTemplates::defaultTemplate($kind),
                'tokens' => collect(MessageTemplates::tokens($kind))->map(fn (string $description, string $token): array => ['token' => $token, 'description' => $description])->values(),
                'sample' => MessageTemplates::sampleValues($kind),
            ])->values(),
            'limits' => ['max' => MessageTemplates::MAX_LENGTH, 'comfortable' => MessageTemplates::COMFORTABLE_LENGTH],
        ]);
    }

    /**
     * Save the templates that were sent. A blank text goes back to the default. Placeholders a kind does not know
     * are refused, all of them listed, so a typo never reaches a parent as literal braces.
     */
    public function update(Request $request): RedirectResponse
    {
        Gate::authorize(Ability::AnnouncementManage->value);

        $validated = $request->validate([
            'templates' => ['required', 'array'],
            'templates.*' => ['nullable', 'string', 'max:'.MessageTemplates::MAX_LENGTH],
        ], [], ['templates' => 'Templat', 'templates.*' => 'Templat']);

        $errors = [];

        foreach ($validated['templates'] as $kind => $text) {
            if (! MessageTemplates::isKind((string) $kind)) {
                $errors["templates.{$kind}"] = 'Jenis templat ini tidak dikenal.';

                continue;
            }

            $unknown = MessageTemplates::unknownTokens($kind, (string) $text);

            if ($unknown !== []) {
                $errors["templates.{$kind}"] = 'Kata '.implode(', ', $unknown).' tidak dikenal di templat ini. Pakai tombol kata di bawah kolom isian, atau hapus kata itu.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($validated['templates'] as $kind => $text) {
            Setting::put(MessageTemplates::settingKey($kind), trim((string) $text));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Templat pesan sudah disimpan.']);

        return back();
    }
}
