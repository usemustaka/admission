<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    /**
     * The front page, with a brochure to download for every active lembaga that has one.
     */
    public function __invoke(): Response
    {
        return Inertia::render('welcome', [
            'brochures' => Organization::query()
                ->active()
                ->whereNotNull('brochure_path')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Organization $organization): array => ['id' => $organization->id, 'name' => $organization->name])
                ->values(),
        ]);
    }
}
