<?php

namespace App\Http\Controllers;

use App\Actions\Setup\CompleteSetup;
use App\Enums\OrganizationCategory;
use App\Http\Requests\SetupRequest;
use App\Models\Organization;
use App\Models\Year;
use App\Support\Brand;
use App\Support\SetupState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The page that sets up a fresh install: identity, the first super admin, organizations and the school year.
 * It exists only until the setup is done.
 */
class SetupController extends Controller
{
    public function show(): Response
    {
        abort_if(SetupState::isComplete(), 404);

        return Inertia::render('setup/index', [
            'requiresToken' => filled(config('setup.token')),
            'checks' => $this->checks(),
            'existing' => $this->existing(),
            'categories' => OrganizationCategory::options(),
            'defaults' => [
                'app_name' => (string) config('app.name'),
                'accent_color' => Brand::DEFAULT_ACCENT,
                'year' => $this->currentYear(),
            ],
        ]);
    }

    public function store(SetupRequest $request, CompleteSetup $completeSetup): RedirectResponse
    {
        $user = $completeSetup->handle($request->validated(), $request->file('logo'));

        Auth::login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Setup selesai. Selamat datang!']);

        return redirect()->route('dashboard');
    }

    /**
     * What the install needs from its environment, so a missing step is named before it causes an error.
     *
     * @return list<array{label: string, ok: bool, hint: string|null, soft?: bool}>
     */
    private function checks(): array
    {
        $connected = true;

        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $connected = false;
        }

        $migrated = $connected && Schema::hasTable('users') && Schema::hasTable('settings') && Schema::hasTable('roles');
        $linked = is_link(public_path('storage')) || is_dir(public_path('storage'));
        $mailer = (string) config('mail.default');

        return [
            ['label' => 'Database tersambung', 'ok' => $connected, 'hint' => $connected ? null : 'Periksa DB_* di file .env.'],
            ['label' => 'Migrasi sudah dijalankan', 'ok' => $migrated, 'hint' => $migrated ? null : 'Jalankan php artisan migrate.'],
            ['label' => 'Folder unggahan bisa dipakai', 'ok' => $linked && is_writable(storage_path('app/public')), 'hint' => $linked ? null : 'Jalankan php artisan storage:link supaya logo dan foto tampil.'],
            ['label' => 'Pengiriman email diatur', 'ok' => ! in_array($mailer, ['log', 'array'], true), 'hint' => in_array($mailer, ['log', 'array'], true) ? 'MAIL_MAILER masih "'.$mailer.'": email lupa kata sandi dan verifikasi hanya tercatat di log.' : null, 'soft' => true],
            ['label' => 'Mode debug mati', 'ok' => ! (app()->isProduction() && config('app.debug')), 'hint' => app()->isProduction() && config('app.debug') ? 'Set APP_DEBUG=false di produksi.' : null],
        ];
    }

    /**
     * @return array{organizations: list<string>, years: list<string>}
     */
    private function existing(): array
    {
        try {
            return [
                'organizations' => Organization::query()->orderBy('sort_order')->orderBy('name')->pluck('name')->all(),
                'years' => Year::query()->orderByDesc('starts_on')->pluck('name')->all(),
            ];
        } catch (Throwable) {
            return ['organizations' => [], 'years' => []];
        }
    }

    /**
     * The school year that is running now: it starts in July.
     *
     * @return array{name: string, starts_on: string, ends_on: string}
     */
    private function currentYear(): array
    {
        $start = now()->month >= 7 ? now()->year : now()->year - 1;

        return ['name' => $start.'/'.($start + 1), 'starts_on' => "{$start}-07-01", 'ends_on' => ($start + 1).'-06-30'];
    }
}
