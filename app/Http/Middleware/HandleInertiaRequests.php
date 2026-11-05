<?php

namespace App\Http\Middleware;

use App\Enums\Ability;
use App\Models\Registrant;
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
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user()?->loadMissing('person'),
                'abilities' => $this->abilities($request),
            ],
            'portal' => fn (): ?array => $request->user() !== null && $request->routeIs('portal*') ? [
                'children' => Registrant::query()
                    ->forParent($request->user())
                    ->with('person')
                    ->latest()
                    ->get()
                    ->map(fn (Registrant $registrant): array => ['id' => $registrant->id, 'name' => $registrant->person->name, 'photo_url' => $registrant->person->photo_url])
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
