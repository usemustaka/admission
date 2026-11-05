<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Users\DeactivateUser;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'canDeactivateAccount' => $request->user()->can(Ability::AdminAccess->value),
        ]);
    }

    /**
     * Update the user's profile information. A new email address has to be verified again.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $emailChanged = $request->emailIsChanging();

        DB::transaction(function () use ($request, $user, $emailChanged): void {
            $user->person?->update([
                'name' => $request->validated('name'),
                ...($emailChanged ? ['email' => $request->validated('email')] : []),
            ]);

            $user->fill(['email' => $request->validated('email')]);

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();
        });

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $emailChanged
            ? 'Profil diperbarui. Kami kirim tautan verifikasi ke alamat email barumu.'
            : __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Deactivate the user's own account. Only staff may do this; parents ask the school. The account stays, so the
     * notes and records it wrote keep their author.
     */
    public function destroy(ProfileDeleteRequest $request, DeactivateUser $deactivateUser): RedirectResponse
    {
        $deactivateUser->handle($request->user());

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
