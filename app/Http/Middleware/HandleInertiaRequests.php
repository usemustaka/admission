<?php

namespace App\Http\Middleware;

use App\Enums\Ability;
use App\Models\Registrant;
use App\Support\Brand;
use App\Support\EmailVerification;
use App\Support\RegistrantPresenter;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => Brand::name(),
            'brand' => fn (): array => Brand::shared(),
            'auth' => [
                'user' => $request->user()?->loadMissing('person'),
                'abilities' => $this->abilities($request),
                'email_unverified' => EmailVerification::isPending($request->user()),
            ],
            'portal' => fn (): ?array => $request->user() !== null && $request->routeIs('portal*') ? [
                'children' => Registrant::query()
                    ->forParent($request->user())
                    ->with('person')
                    ->latest()
                    ->get()
                    ->map(fn (Registrant $registrant): array => ['id' => $registrant->id, 'name' => RegistrantPresenter::childName($registrant), 'photo_url' => RegistrantPresenter::childPhotoUrl($registrant)])
                    ->values()
                    ->all(),
            ] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Permissions of the signed-in user, so the UI only shows what they may use.
     *
     * @return list<string>
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return array_values(array_map(
            fn (Ability $ability): string => $ability->value,
            array_filter(Ability::cases(), fn (Ability $ability): bool => $user->can($ability->value)),
        ));
    }
}
